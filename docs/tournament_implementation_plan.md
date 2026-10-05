# 🏏 Tournament Scoring System — Final Implementation Plan

## All Questions Resolved

Based on your answers and the [rules.blade.php](file:///g:/Documents/HTG/main-website/resources/views/rules.blade.php):

| # | Question | Answer |
|---|---|---|
| Q1 | Match format | **5 overs × 4 balls** (group/QF/SF), **5 overs × 6 balls** (finals) |
| Q2 | Overs input | **Two fields** (overs + balls) |
| Q3 | Matchups (QF / SF / Final) | **Fully manual** — system never decides or auto-pairs teams for any round. Scorer explicitly selects both teams for every match |
| Q4 | Draws in knockouts | **Not allowed** (super over in finals, so always a winner) |
| Q5 | Edit results | **Yes**, scorer can edit. Group stage → recalculate standings |
| Q6 | Match lifecycle & scheduling | **Manual Lifecycle (Upcoming, Live, Finished)** — Scorer can pre-schedule upcoming matches or set them live without scores, or enter results directly when finished. Status transitions are bidirectional. |
| Q7 | Rain/abandoned | **Treated as a draw** (manual decision, no special system logic) |
| Q8 | Group format | **Manual** — just enter results, no predefined schedule |
| Q9 | Wickets for all-out | **11 wickets** (12-member squad, 11 bat) |
| Q10 | Scorer dashboard | Stage selector + contextual form + group/team views + qualify option + Create Match modal |
| Q11 | Multiple scorers | **Yes**, all with same access |
| Q12 | Scoreboard URL | **Part of existing site** |
| Q13 | Score fields schema | **Nullable** — clean separation between unplayed matches and 0 score |
| Q14 | Standings & NRR | **Count Finished matches only** — Upcoming & Live matches do not affect standings |
| Q15 | Live match display | **Teams + "Match in Progress" badge** (and optional batting first indicator) |
| Bonus | Admin scores? | **No** — only scorers enter scores and qualify teams |
| Bonus | Admin creates scorers? | **Yes** — separate option in admin panel, not promotion |

---

### 🔄 Design Evolution: Previous Design vs. New Match Lifecycle Design

| Area | Previous Design | New Design |
|---|---|---|
| **Match Creation** | Scorer enters everything at once (teams + scores + winner) | Scorer creates match record with just teams + status. Enters scores later when finishing. |
| **`matches` Table** | All score fields required, no status column | Add `status` ENUM('upcoming','live','finished'). Make score fields NULLABLE. |
| **Public Match Cards** | Only show finished matches | Show all matches with status badges (🕐 Upcoming, 🟢 Live, ✅ Finished) |
| **Public Bracket Tree** | Only shows finished QF/SF/Final nodes | Shows upcoming/live knockout matches as team names in bracket (no scores yet), finished matches with scores |
| **Group Standings** | Calculates from all matches | Calculates only from `status = 'finished'` matches |
| **Filtering** | None | Both Public and Scorer can filter by Upcoming, Live, Finished, or All |
| **Scorer Workflow** | One-step: enter everything | Two-step: schedule first → finish later (with option to enter directly) |


---

## Key Rules That Affect The System

From the rules page, here's what matters for our scoring system:

| Rule | Impact on System |
|---|---|
| **5 overs, 4 balls per over** (group/QF/SF) | Max overs = 5, max balls per over = 4. Balls field: 0-3 range |
| **5 overs, 6 balls per over** (Final match only) | Same max overs, but balls field: 0-5 range |
| **11 players bat** (from 12 squad) | All out = 11 wickets. Wickets field: max 11 |
| **Tie = 1 point each** (group stage) | Draw logic confirmed |
| **Super Over in finals** | Finals always have a winner — entered as the result of the super over |
| **Win = 2pts, Loss = 0pts, Draw = 1pt** | Points system confirmed |

### NRR Calculation for This Format

Standard NRR formula, adapted:

```
NRR = (Runs Scored ÷ Overs Faced) − (Runs Conceded ÷ Overs Bowled)
```

**Converting overs to decimal** (using values from `config/tournament.php`):
- Group/QF/SF: `4 balls per over` → each ball = 1/4 of an over
  - 3 overs, 2 balls = 3 + (2/4) = **3.5 overs**
- Final only: `6 balls per over` → each ball = 1/6 of an over  
  - 3 overs, 2 balls = 3 + (2/6) = **3.333 overs**

**All-out rule**: If a team is all out (`config('tournament.max_wickets')` = 11 wickets), use **full allotted overs** (`config('tournament.overs_per_match')` = 5.0) for the NRR calculation, regardless of actual overs bowled. This follows ICC convention and prevents teams from getting an unfair NRR advantage by bowling out opponents quickly.

> [!IMPORTANT]
> Since NRR is only calculated from **group stage** matches, the balls-per-over is always 4 for NRR purposes. Finals use 6 balls per over but those results don't affect NRR.

---

---

## Config-Driven Rules (`config/tournament.php`)

All tournament rules are centralized in a single config file. If any rule changes, you update **one file** — no hunting through code.

```php
// config/tournament.php
return [
    /*
    |--------------------------------------------------------------------------
    | Match Format
    |--------------------------------------------------------------------------
    */
    'overs_per_match'        => 5,
    'balls_per_over'         => [
        'G'  => 4, // Group stage (used for NRR)
        'QF' => 4, // Easily change to 6 anytime!
        'SF' => 4, // Easily change to 6 anytime!
        'F'  => 6, // Final match
    ],

    /*
    |--------------------------------------------------------------------------
    | Team / Wickets
    |--------------------------------------------------------------------------
    */
    'max_wickets'            => 11,    // All out (squad of 12, 11 bat)

    /*
    |--------------------------------------------------------------------------
    | Points System
    |--------------------------------------------------------------------------
    */
    'points_win'             => 2,
    'points_draw'            => 1,
    'points_loss'            => 0,

    /*
    |--------------------------------------------------------------------------
    | Tournament Structure
    |--------------------------------------------------------------------------
    */
    'teams_qualify_per_group' => 2,
];
```

**Every part of the system references this config:**
- Validation: `'team1_balls' => ['integer', 'min:0', 'max:' . (config("tournament.balls_per_over.{$stage}") - 1)]`
- NRR calculation: `$oversDecimal = $totalBalls / config('tournament.balls_per_over.G')`
- All-out check: `if ($wickets >= config('tournament.max_wickets'))`
- Points award: `$points = $isDraw ? config('tournament.points_draw') : config('tournament.points_win')`
- Winning margin: `$wicketsRemaining = config('tournament.max_wickets') - $wicketsFallen`

> [!TIP]
> If a rule changes (e.g., QF and SF switch from 4 to 6 balls, or points for a win becomes 3), just update this one file. No migration, no code refactor needed.

---

## The Bracket Wire-Crossing Solution

Since matchups are fully manual, here's the approach:

### How It Works

1. After group stage, scorer qualifies 8 teams (2 from each group)
2. Scorer manually enters QF match results as they happen
3. The bracket displays QF matches in **two halves**:

```
TOP HALF                          BOTTOM HALF
┌──────────┐                      ┌──────────┐
│  QF 1    │──┐                ┌──│  QF 3    │
└──────────┘  ├── SF 1 ──┐  ┌──┤  └──────────┘
┌──────────┐──┘          ├──┤  └──┌──────────┐
│  QF 2    │         FINAL   │  QF 4    │
└──────────┘             └──┘  └──────────┘
```

4. **Assigning Bracket Position (Both During & After Entry)**:
   - **At Entry**: The QF match entry form includes a slot selector (Positions 1-4).
   - **After Entry**: The scorer can adjust or swap positions anytime via a visual bracket position manager or through the match edit modal.
   - Position 1 & 2 → winners meet in SF1 (Top Half)
   - Position 3 & 4 → winners meet in SF2 (Bottom Half)

5. **Rearrangement Window**: The scorer can rearrange bracket positions anytime before the respective Semi-Final match starts. Once an SF match is entered, the two QF matches feeding into it become locked in position.

### Why This Works
- No fixed formula needed
- Scorer has full control
- Wires never cross because the positions enforce the bracket structure
- Simple UI: just 4 numbered slots for QF matches

---

## Database Schema (Final)

### New Table: `tournament_groups`

```
tournament_groups
├── id                  BIGINT PK AUTO
├── name                VARCHAR(50)      -- "Group A", "Group B", etc.
├── created_at          TIMESTAMP
├── updated_at          TIMESTAMP
```

### New Table: `group_teams` (pivot: team ↔ group + standings)

```
group_teams
├── id                  BIGINT PK AUTO
├── group_id            FK → tournament_groups (CASCADE)
├── team_id             FK → teams (CASCADE)
├── matches_played      INTEGER DEFAULT 0
├── wins                INTEGER DEFAULT 0
├── losses              INTEGER DEFAULT 0
├── draws               INTEGER DEFAULT 0
├── points              INTEGER DEFAULT 0
├── total_runs_scored   INTEGER DEFAULT 0  -- for NRR: cumulative
├── total_overs_faced   INTEGER DEFAULT 0  -- stored as total balls for precision
├── total_runs_conceded INTEGER DEFAULT 0  -- for NRR: cumulative
├── total_overs_bowled  INTEGER DEFAULT 0  -- stored as total balls for precision
├── nrr                 DECIMAL(8,4) DEFAULT 0.0000
├── qualified           BOOLEAN DEFAULT FALSE
├── created_at          TIMESTAMP
├── updated_at          TIMESTAMP
├── UNIQUE(group_id, team_id)
```

> [!NOTE]
> **Why store overs as total balls?** Because cricket math is weird. Storing `total_balls_faced` instead of `total_overs_faced` avoids decimal conversion errors. When displaying or calculating NRR, we convert: `overs_decimal = total_balls / balls_per_over` (where `balls_per_over = 4` for this tournament's group stage).

### New Table: `matches`

```
matches
├── id                  BIGINT PK AUTO
├── stage               ENUM('G', 'QF', 'SF', 'F')           -- Group, Quarter, Semi, Final
├── status              ENUM('upcoming', 'live', 'finished') DEFAULT 'upcoming'
├── group_id            FK → tournament_groups (NULLABLE, only for 'G' stage)
├── bracket_position    TINYINT NULLABLE (1-4 for QF, 1-2 for SF, 1 for F)
├── team1_id            FK → teams
├── team2_id            FK → teams
├── batting_first_id    FK → teams NULLABLE                  -- optional until known / set
├── team1_score         INTEGER NULLABLE                     -- NULL for upcoming/live
├── team1_overs         TINYINT NULLABLE                     -- complete overs (0-5)
├── team1_balls         TINYINT NULLABLE                     -- remaining balls (0-3 for group, 0-5 for finals)
├── team1_wickets       TINYINT NULLABLE                     -- (0-11)
├── team2_score         INTEGER NULLABLE
├── team2_overs         TINYINT NULLABLE
├── team2_balls         TINYINT NULLABLE
├── team2_wickets       TINYINT NULLABLE
├── is_draw             BOOLEAN DEFAULT FALSE
├── winner_id           FK → teams (NULLABLE)                -- NULL if draw or upcoming/live
├── entered_by          FK → users                           -- which scorer created/entered this
├── created_at          TIMESTAMP
├── updated_at          TIMESTAMP
```

> [!NOTE]
> **Why Nullable Score Fields Instead of Default 0? (Clarification #1 Decision)**
> In cricket, 0 runs, 0 overs, and 0 wickets represent valid historical score events (e.g., getting bowled out for 0, or opening delivery). Storing `NULL` for `team1_score`, `team2_score`, `overs`, `balls`, and `wickets` cleanly delineates unplayed or ongoing matches from actual completed match statistics. Only matches with `status = 'finished'` are processed by Standings and NRR calculation engines. Matches in `upcoming` or `live` status remain cleanly stored with `null` scores.


### Modified Table: `users`

```diff
 users
 ├── (all existing fields)
-├── is_admin            BOOLEAN DEFAULT FALSE
+├── role                ENUM('company', 'admin', 'scorer') DEFAULT 'company'
```

**Migration plan**: Replace `is_admin` boolean with `role` enum. Migrate existing data:
- `is_admin = true` → `role = 'admin'`
- `is_admin = false` AND `company_id IS NOT NULL` → `role = 'company'`
- `is_admin = false` AND `company_id IS NULL` → keep as `role = 'company'` (orphan accounts)

---

## Complete `is_admin` Codebase Audit & Safe Migration Plan

To ensure zero regressions and safe transition, we conducted a complete codebase audit. Every occurrence of `is_admin` will be adjusted:

| File | Current Usage | Safe Replacement |
|---|---|---|
| [database/..._create_users_table.php](file:///g:/Documents/HTG/main-website/database/migrations/0001_01_01_000001_create_users_table.php) | `$table->boolean('is_admin')->default(false);` | Handled via new migration: `add_role_to_users_table_and_drop_is_admin.php` |
| [app/Models/User.php](file:///g:/Documents/HTG/main-website/app/Models/User.php) | `'is_admin'` in `$fillable`, boolean cast | Replace with `'role'`, add helper methods (`isAdmin()`, `isScorer()`, `isCompany()`, `hasRole()`), plus a backward-compatible accessor `getIsAdminAttribute(): bool { return $this->isAdmin(); }` to prevent runtime crashes if any legacy call is executed |
| [app/Http/Middleware/EnsureUserIsAdmin.php](file:///g:/Documents/HTG/main-website/app/Http/Middleware/EnsureUserIsAdmin.php) | `if (!$user || !$user->is_admin)` | Update to `if (!$user || !$user->isAdmin())` |
| [app/Console/Commands/SetUserAdmin.php](file:///g:/Documents/HTG/main-website/app/Console/Commands/SetUserAdmin.php) | `$user->is_admin = $isAdmin;` | Update to set role: `$user->role = $isAdmin ? 'admin' : 'company';` (and support setting `scorer`) |
| [resources/views/livewire/company-registration-form.blade.php](file:///g:/Documents/HTG/main-website/resources/views/livewire/company-registration-form.blade.php) | `'is_admin' => false` | Change to `'role' => 'company'` |
| [resources/views/livewire/admin-registration-form.blade.php](file:///g:/Documents/HTG/main-website/resources/views/livewire/admin-registration-form.blade.php) | `'is_admin' => false` | Route and component removed entirely in Phase 1 (security vulnerability remediation) |
| [resources/views/livewire/admin/users-list.blade.php](file:///g:/Documents/HTG/main-website/resources/views/livewire/admin/users-list.blade.php) | Updating `is_admin`, filtering `where('is_admin', true)`, `@if($user->is_admin)` | Refactored to manage `role`: role badges (Admin, Scorer, Company), role filter dropdown, and action buttons to assign/change roles safely |
| [resources/views/livewire/admin/approve-team.blade.php](file:///g:/Documents/HTG/main-website/resources/views/livewire/admin/approve-team.blade.php) | `if (!$user || !$user->is_admin)` | Update to `if (!$user || !$user->isAdmin())` |
| [resources/views/registrations.blade.php](file:///g:/Documents/HTG/main-website/resources/views/registrations.blade.php) | `$isAdmin = $user->is_admin == true;` | Update to `$isAdmin = $user->isAdmin();` |
| [resources/views/components/Navbar.blade.php](file:///g:/Documents/HTG/main-website/resources/views/components/Navbar.blade.php) | `$isAdmin = $user->is_admin == true;` | Update to `$isAdmin = $user->isAdmin();`, plus add Scorer portal link for `$user->isScorer()` |
| [resources/views/components/HeroSection.blade.php](file:///g:/Documents/HTG/main-website/resources/views/components/HeroSection.blade.php) | `$isAdmin = $user->is_admin == true;` | Update to `$isAdmin = $user->isAdmin();`, adjust CTA for Scorer vs Admin vs Company |
| [resources/views/admin/dashboard.blade.php](file:///g:/Documents/HTG/main-website/resources/views/admin/dashboard.blade.php) | `User::where('is_admin', true)->count()` | Update to `User::where('role', 'admin')->count()`, add scorer count `User::where('role', 'scorer')->count()` |
| [tests/Feature/AdminDashboardTest.php](file:///g:/Documents/HTG/main-website/tests/Feature/AdminDashboardTest.php) | Tests `route('admin.register')` | Update to test removed route (asserts 404), tests role-based middleware |

---

## Role Permissions & Access Control Architecture

The platform recognizes 4 distinct roles and access levels:

| Role | Company Association | Permitted Areas | Blocked Areas |
|---|---|---|---|
| **Public / Guest** | None | Public Scoreboard, Standings, Bracket, Rules, Gallery, Awards | Any dashboard, match entry, admin panel |
| **Company** (`role = 'company'`) | Associated `company_id` | Company Dashboard, Team Registration, Team Management, Public Scoreboard | Admin panel, Scorer portal, match score entry |
| **Scorer** (`role = 'scorer'`) | None (`company_id` = null) | Scorer Dashboard, Match Entry & Edit, Group Qualification, Bracket Assignment | Admin panel, Company Dashboard (prevents null company crash) |
| **Admin** (`role = 'admin'`) | None | Admin Dashboard, Group Management, Team Approval, Scorer Account Creation, User Management, Data Export | Match score entry (kept isolated to Scorer) |

### Route Protection & Middleware Setup

1. **Authentication Middleware (`auth.company` / `auth.jwt`)**:
   - Validates the JWT cookie (`company_token`).
   - Resolves user and attaches `$request->attributes->set('user', $user)`.

2. **Role Middlewares**:
   - `'admin' => \App\Http\Middleware\EnsureUserIsAdmin::class` — checks `$user->isAdmin()`. If not admin, aborts with 403 or redirects.
   - `'scorer' => \App\Http\Middleware\EnsureUserIsScorer::class` — checks `$user->isScorer()`. If not scorer, aborts with 403 or redirects.
   - `'company' => \App\Http\Middleware\EnsureUserIsCompany::class` — checks `$user->isCompany()`. Crucial addition: prevents scorers/admins from visiting `/company/dashboard` and crashing due to a missing company relation.

3. **Login Redirection Logic**:
   - Upon successful OTP/JWT login, the login controller inspects `$user->role`:
     - `admin` → redirects to `route('admin.dashboard')`
     - `scorer` → redirects to `route('scorer.dashboard')`
     - `company` → redirects to `route('company.dashboard')`

---

## Qualification & Stage Eligibility Architecture (Manual vs. Automatic)

A critical distinction in the tournament workflow:

| Stage Transition | Manual "Qualify" Button Needed? | How Eligibility is Identified | Dropdown Pool Size |
|---|---|---|---|
| **Group Stage $\rightarrow$ QF** | **YES** (Scorer clicks "Qualify") | `group_teams.qualified = true` | Exactly 8 Teams |
| **Quarter-Finals $\rightarrow$ SF** | **NO** (Automatic upon match save) | `matches.stage = 'QF'` AND `winner_id` | Exactly 4 Teams |
| **Semi-Finals $\rightarrow$ Final** | **NO** (Automatic upon match save) | `matches.stage = 'SF'` AND `winner_id` | Exactly 2 Teams |

### 1. Group Stage Qualification (Manual Action Required)
- **Why**: The group stage is a round-robin league table. Advancement is based on cumulative standings (Points and NRR).
- **Workflow**: Scorer reviews the finalized group standings table and clicks **"Qualify Top 2 Teams"** (with manual adjustment capability for committee tiebreaker decisions). This sets `group_teams.qualified = true`.
- **Query for QF Dropdown**:
  ```php
  Team::whereHas('groupTeam', fn($q) => $q->where('qualified', true))->get();
  ```

### 2. Knockout Qualification (Automatic via Match Winner)
- **Why**: Single-elimination matches. The team that wins the match is by definition the advancing team.
- **Workflow**: The scorer enters the match result and picks the winner. **No separate qualification button is clicked.**
- **Query for SF Dropdown**:
  ```php
  Team::whereIn('id', Match::where('stage', 'QF')->whereNotNull('winner_id')->pluck('winner_id'))->get();
  ```
- **Query for Final Dropdown**:
  ```php
  Team::whereIn('id', Match::where('stage', 'SF')->whereNotNull('winner_id')->pluck('winner_id'))->get();
  ```

---

## Security Plan for Scorer Role

### Scorer Account Creation
- **Only admin** can create scorer accounts (via dedicated modal/form on admin panel).
- Scorer goes through the standard email verification flow (OTP).
- Scorer is saved with `role = 'scorer'` and `company_id = null`.
- Scorer logs in using the standard unified login interface with email + OTP.

### Match Lifecycle & Two-Tier Input Validation

Matches operate with a 3-state lifecycle: `upcoming` ⇄ `live` ⇄ `finished`.

#### 1. Scheduling / In-Progress Mode (`status = 'upcoming'` or `'live'`)
- Scorer selects Stage, Group (if Group Stage), Bracket Position (if Knockout), Team 1, and Team 2.
- `batting_first_id` is **optional**.
- Scores, wickets, overs, balls, and winner fields are **not required** (stored as `NULL`).
- Prevents duplicate pairings in the same stage.
- Immediate database record creation enables the frontend to display the match as "Upcoming" or "Live / In Progress" with zero delay.

#### 2. Completion Mode (`status = 'finished'`)
When a match concludes (or when a scorer directly enters results, skipping "Live"), full validation activates against rules in `config/tournament.php`:
- Scores ≥ 0 (Integer)
- Overs: `0` to `config('tournament.overs_per_match')` (5)
- Balls: `0` to `balls_per_over - 1` (0-3 for G/QF/SF, 0-5 for Final)
- Wickets: `0` to `config('tournament.max_wickets')` (0-11)
- Team IDs exist and belong to the correct group/stage
- **Scorer-Selected Outcome**: The scorer explicitly selects which team won (`winner_id`) or marks the match as tied (`is_draw = true`). The system **never** automatically decides who won.
- Draw only allowed in group stage (knockouts strictly require the scorer to select a winner via Super Over).
- Optional `batting_first_id` can be specified to accurately compute run vs. wicket victory margins.

#### 3. Free Bidirectional Status Transitions
- **Forward**: `upcoming` → `live` → `finished`, or `upcoming` → `finished` (skipping `live` directly).
- **Backward**: `finished` → `live` or `upcoming`, and `live` → `upcoming`.
- **Standings Safety**: Standings, points, and NRR strictly calculate from matches where `status = 'finished'`. Moving a match backward from `finished` automatically invokes the cascade recalculator, cleanly removing that match from group standings.

---

## Winning Margin Calculation & Confirmation Modal

When saving a match as `finished`, the system assists by calculating the margin based on the **scorer's selected winner** and `batting_first_id`:

```php
if ($match->is_draw) {
    $display = "Match Tied";
} elseif ($match->batting_first_id && $match->winner_id === $match->batting_first_id) {
    // Team batting first won → won by runs
    $margin = abs($match->team1_score - $match->team2_score);
    $display = "Won by {$margin} runs";
} elseif ($match->batting_first_id && $match->winner_id !== $match->batting_first_id) {
    // Team batting second won → won by wickets
    $secondInningsWickets = ($match->winner_id === $match->team2_id) ? $match->team2_wickets : $match->team1_wickets;
    $wicketsRemaining = config('tournament.max_wickets') - $secondInningsWickets;
    $display = "Won by {$wicketsRemaining} wickets";
} else {
    // Batting first wasn't specified: fall back to simple winner display
    $margin = abs($match->team1_score - $match->team2_score);
    $display = $margin > 0 ? "Won by {$margin} runs" : "Winner: {$match->winner->name}";
}
```

> [!NOTE]
> **Pre-Save Confirmation**: Before committing a `finished` match to the database, a confirmation modal presents all entered details—teams, innings scores, overs, wickets, the scorer's selected winner or tie, and the calculated margin—for final visual verification.



---

## Implementation Order & Testing Plan

Each phase includes **in-line automated test cases (PHPUnit)** written alongside the implementation to ensure safety, regression prevention, and role isolation:

### Phase 1: Security Fixes (existing issues) + Tests
1. Fix `SecurityHeaders.php` bug (dead code)
2. Remove unauthenticated `/admin/register` route & component
3. Hash JWT tokens in database
4. Hash verification codes
5. Add CSP header
6. Fix `.env.example` SESSION_ENCRYPT
7. **Automated Tests**:
   - `tests/Feature/SecurityHeadersTest.php`: verifies CSP and security headers
   - `tests/Feature/AdminRegistrationRouteTest.php`: asserts `/admin/register` returns 404
   - Update `JWTSecurityTest.php` & `VerificationCodeSecurityTest.php`: tests token and OTP hashing

### Phase 2: Role System Migration & Codebase-wide Adjustment + Tests
8. Create migration: `add_role_to_users_table_and_drop_is_admin.php`
9. Update `User` model: fillable, casts, `isAdmin()`, `isScorer()`, `isCompany()`, `hasRole()`, and `getIsAdminAttribute()`
10. Update all middleware (`EnsureUserIsAdmin`, add `EnsureUserIsCompany`)
11. Update all views & components referencing `is_admin` (`Navbar.blade.php`, `HeroSection.blade.php`, `registrations.blade.php`, `dashboard.blade.php`, `approve-team.blade.php`)
12. Update `users-list.blade.php` to display and manage roles
13. Update `SetUserAdmin` console command
14. Update login redirect handler to route users based on role
15. **Automated Tests**:
    - `tests/Unit/UserModelRoleTest.php`: tests `$user->isAdmin()`, `$user->isScorer()`, `$user->isCompany()`, backward compatibility accessor `$user->is_admin`
    - `tests/Feature/RoleBasedMiddlewareTest.php`: verifies company users cannot access `/admin/*`, admin users can access `/admin/*`, company routes reject non-companies

### Phase 3: Scorer Infrastructure + Tests
16. Create `EnsureUserIsScorer` middleware and register in `bootstrap/app.php`
17. Create admin UI component for Admin to create Scorer accounts (no company attached)
18. Add protected scorer route group (`/scorer/*`) guarded by `['auth.company', 'scorer']`
19. **Automated Tests**:
    - `tests/Feature/ScorerAccountCreationTest.php`: verifies admin can create scorer, non-admin cannot
    - `tests/Feature/ScorerRouteAccessTest.php`: verifies scorer can access `/scorer/*`, scorer blocked from `/admin/*` and `/company/*`, company blocked from `/scorer/*`

### Phase 4: Tournament Config & Groups + Tests
20. Create `config/tournament.php` with all rule values (overs: 5, balls: 4, final balls: 6, max wickets: 11, points: win=2/draw=1/loss=0)
21. Create `tournament_groups` migration + model
22. Create `group_teams` migration + model (with overs tracked as balls)
23. Admin UI: Create groups, assign teams to groups
24. **Automated Tests**:
    - `tests/Feature/TournamentGroupManagementTest.php`: tests creating groups, assigning teams, preventing duplicates, unique group constraints

### Phase 5: Match Results — Schema, NRR & Standings Service + Tests
25. Create `matches` migration + model (with `status` enum `['upcoming', 'live', 'finished']`, nullable score fields, nullable `batting_first_id`)
26. Build `NRRCalculationService`: handles 4-ball decimal conversion, ICC all-out 5.0 overs allotment rule, runs/balls faced vs conceded — strictly processes `where('status', 'finished')`
27. Build `TournamentStandingsService`: computes matches played, wins, losses, draws, points, cumulative balls/runs, and ranks by points then NRR — strictly filtering `where('status', 'finished')`
28. **Automated Tests**:
    - `tests/Unit/NRRCalculationServiceTest.php`: tests decimal conversion (e.g., 3.2 overs = 3.5), all-out full 5.0 overs rule, negative and positive NRR
    - `tests/Unit/TournamentStandingsServiceTest.php`: tests points accumulation, ties, win margin calculation, correct tiebreaker ranking; explicitly asserts that matches in `upcoming` or `live` status produce zero impact on team points, runs, balls, or NRR

### Phase 6: Scorer Dashboard — Match Creation & Lifecycle + Tests
29. Build Scorer Dashboard layout & Stage selector UI
30. Build "Create / Schedule Match" Modal:
    - Stage selector (Group, QF, SF, Final)
    - Status toggle: `[ Upcoming ]` `[ Live ]` `[ Finished ]`
    - Team 1 & Team 2 selectors (from group or qualified/advancing pool)
    - Optional "Batting First" selector
    - If `Finished` is selected: expands full score entry inputs (runs, overs 0-5, balls 0-3, wickets 0-11, scorer-selected outcome)
31. Scorer Matches List with inline status management & filtering:
    - Status filter tabs: `[ All | Upcoming | Live | Finished ]` for quick match filtering
    - Quick action buttons ("Mark Live", "Enter Results / Finish")
    - Ability to switch status forward or backward (Upcoming ⇄ Live ⇄ Finished)
    - Skip "Live" option: directly transition from Upcoming → Finished
32. Confirmation modal displaying all entered match details before saving a `finished` match
33. Scorer view of group standings table
34. "Qualify teams" interface with confirmation (2 per group) — scorer has manual authority (soft advisory note displayed if matches in the group remain unfinished, but non-blocking)
35. **Automated Tests**:
    - `tests/Feature/ScorerMatchLifecycleTest.php`: tests creating an upcoming match, transitioning to live, completing as finished, skipping directly from upcoming to finished, and filtering by status
    - `tests/Feature/ScorerGroupMatchEntryTest.php`: validates scores, overs (max 5), balls (0-3), wickets (0-11), draws allowed in group stage, standings auto-update upon submit

### Phase 7: Public Scoreboard — Match Cards, Standings & Filtering + Tests
36. Public match result cards supporting 3 visual states:
    - **Status Filter Tabs**: `[ All | Live | Upcoming | Finished ]` allowing spectators to filter matches by status
    - **Upcoming Card**: Match number/stage, Team 1 vs Team 2, clean "Upcoming" badge
    - **Live Card**: Team 1 vs Team 2, pulsing "LIVE • Match in Progress" badge, Batting First indicator
    - **Finished Card**: Innings runs, overs, wickets for both teams, bold winning margin badge
37. Group standings tables displaying Pos, Team, P, W, L, D, Pts, NRR (strictly reflecting finished matches)
38. Tournament bracket UI showing scheduled/in-progress matchups
39. **Automated Tests**:
    - `tests/Feature/PublicScoreboardTest.php`: verifies public guest sees upcoming, live, and finished match cards; ensures standings match finished games; verifies no edit controls leaked

### Phase 8: Knockout Stages — Scheduling & Bracket Slots + Tests
40. QF match creation: scorer manually selects Team 1 and Team 2 from qualified teams, assigns Bracket Position (slots 1-4), and sets status (`Upcoming`, `Live`, or `Finished`)
41. SF match creation: scorer manually selects Team 1 and Team 2 from QF winners, assigns slot (SF1 or SF2), sets status
42. Final match creation: scorer manually selects Team 1 and Team 2 from SF winners, sets status; validates balls 0-5 using `config('tournament.balls_per_over.F')` when finished
43. Knockout validation: disallow draws for finished knockouts, enforce super over winner selected by scorer
44. Slot rearrangement window: allows swapping QF slots 1-4 until respective SF match is created
45. **Automated Tests**:
    - `tests/Feature/KnockoutMatchEntryTest.php`: verifies scheduling QF/SF/Final as upcoming or live, verifies QF/SF finished reject draws, Final allows up to 5 balls per over, bracket position assignments feed correctly into SF1/SF2

### Phase 9: Public Scoreboard — Dynamic Knockout Bracket + Tests
46. QF/SF/Final match cards on public scoreboard (Upcoming, Live, Finished)
47. Bracket UI update with clean lines:
    - Upcoming nodes: Team names with "Upcoming" indicator
    - Live nodes: Team names with pulsing "LIVE" badge
    - Finished nodes: Scores, wickets, and highlighted advancing winner
48. **Automated Tests**:
    - `tests/Feature/PublicBracketViewTest.php`: verifies bracket accurately displays upcoming pairings, live matches, and finished winners

### Phase 10: Match Editing, Bidirectional Status Transitions & Cascade Recalculation + Tests
49. Scorer matches list with edit button
50. Edit modal with pre-filled match data and bidirectional status selector (Upcoming ⇄ Live ⇄ Finished)
51. Cascade recalculation service:
    - Editing a finished group match updates standings, runs, balls, points, and NRR
    - Moving a match backward from `finished` to `live` or `upcoming` cleanly removes its statistics from group standings and NRR
52. **Automated Tests**:
    - `tests/Feature/MatchEditCascadeTest.php`: verifies editing score or winner of a past group match immediately recalculates team stats and standings
    - `tests/Feature/MatchStatusRevertCascadeTest.php`: verifies reverting a finished match back to live or upcoming recalculates standings and removes its points/NRR

### Phase 11: UI Polish & Responsive Optimization
53. Responsive layout for mobile, tablet, desktop
54. Loading indicators & animations for Livewire components
55. Graceful error handling edge cases

### Phase 12: Full Automated Test Suite Run & Verification
56. Run complete test suite: `php artisan test`
57. Verify 100% pass rate across security, roles, scoring, calculations, bracket, and UI permissions

---

## Edge Cases to Handle

| Case | How to Handle |
|---|---|
| Match created as Upcoming or Live | Stored with `NULL` scores and `winner_id = NULL`. Frontend displays "Upcoming" or "Live / In Progress". Ignored by Standings & NRR |
| Scorer skips "Live" stage | **Fully allowed** — match can transition directly from `upcoming` → `finished` or be created as `finished` immediately |
| Match transitioned backward (`finished` → `live` or `upcoming`) | **Allowed** — cascade recalculator automatically subtracts runs, balls, and points from group standings and recalculates NRR |
| Team scores 0 runs / 0 wickets | Valid — score can be 0. Cleanly distinguished from unplayed match because fields are integer 0 vs `NULL` |
| Qualifying group before all matches are finished | **Allowed with soft warning** — Scorer retains manual discretion without hard blocks |
| All out (11 wickets) in 3.2 overs | For NRR: use 5.0 overs (full allotment), not 3.2 |
| Same team selected as both team1 and team2 | Validation error — block it |
| Qualifying more than 2 teams from a group | Validation error — max 2 per group |
| Editing a group match after QF has started | **Allow it** — scorer can edit, standings recalculate dynamically |
| Unqualifying a team after their knockout match exists | **Block it** — show error "this team has already played a knockout match" |
| All group matches are draws | Valid — teams ranked by NRR (which would all be 0). Allow scorer manual qualification override if needed |
| Identical points and identical NRR | System allows scorer manual qualification selection |

