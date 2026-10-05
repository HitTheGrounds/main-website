## Relevant Files

### Existing Files to Modify
- `app/Models/User.php` - Replace `is_admin` boolean with `role` enum, add `isAdmin()`, `isScorer()`, `isCompany()` helper methods and backward-compatible accessor
- `app/Models/Team.php` - Add `groupTeam()` relationship for tournament group association
- `app/Http/Middleware/EnsureUserIsAdmin.php` - Update from `$user->is_admin` to `$user->isAdmin()`
- `app/Http/Middleware/EnsureCompanyAuthenticated.php` - Verify JWT auth flow compatibility with new roles
- `app/Http/Middleware/SecurityHeaders.php` - Fix dead code bug (return before `X-Powered-By` removal)
- `app/Console/Commands/SetUserAdmin.php` - Update to support `role` field and scorer role assignment
- `app/Services/JWTService.php` - Hash JWT tokens before storing, compare via hash on validation
- `bootstrap/app.php` - Register new middleware aliases (`scorer`, `company`)
- `routes/web.php` - Remove `/admin/register`, add scorer route group, add company middleware to company routes
- `config/app.php` - Verify JWT config compatibility
- `resources/views/components/Navbar.blade.php` - Replace `$user->is_admin` with `$user->isAdmin()`, add scorer portal link
- `resources/views/components/HeroSection.blade.php` - Replace `$user->is_admin` with `$user->isAdmin()`, add scorer CTA
- `resources/views/registrations.blade.php` - Replace `$user->is_admin` with `$user->isAdmin()`
- `resources/views/admin/dashboard.blade.php` - Replace `User::where('is_admin', true)` with role-based query, add scorer count
- `resources/views/livewire/admin/users-list.blade.php` - Refactor to manage roles (admin, scorer, company) instead of `is_admin` toggle
- `resources/views/livewire/admin/approve-team.blade.php` - Replace `$user->is_admin` with `$user->isAdmin()`
- `resources/views/livewire/company-registration-form.blade.php` - Replace `'is_admin' => false` with `'role' => 'company'`
- `resources/views/livewire/admin-registration-form.blade.php` - Remove entirely (security vulnerability)
- `tests/Feature/AdminDashboardTest.php` - Update to assert `/admin/register` returns 404, add role-based tests

### New Files to Create
- `config/tournament.php` - Centralized tournament rules (overs, balls per over per stage, max wickets, points system)
- `database/migrations/xxxx_xx_xx_fix_security_headers_and_remove_admin_register.php` - Not a migration per se, but security fixes tracked here
- `database/migrations/xxxx_xx_xx_add_role_to_users_table.php` - Add `role` ENUM column, migrate `is_admin` data, drop `is_admin`
- `database/migrations/xxxx_xx_xx_create_tournament_groups_table.php` - Tournament groups table
- `database/migrations/xxxx_xx_xx_create_group_teams_table.php` - Group-team pivot with standings columns (balls stored as integers)
- `database/migrations/xxxx_xx_xx_create_matches_table.php` - Matches table with status lifecycle, nullable scores, bracket position
- `app/Models/TournamentGroup.php` - Model for tournament groups
- `app/Models/GroupTeam.php` - Pivot model for group-team standings (points, NRR, qualification)
- `app/Models/TournamentMatch.php` - Match model (named `TournamentMatch` to avoid PHP 8 `match` keyword conflict)
- `app/Services/NRRCalculationService.php` - Net Run Rate calculation with all-out rule, decimal overs conversion
- `app/Services/TournamentStandingsService.php` - Standings computation (points, W/L/D, NRR ranking) from finished matches only
- `app/Http/Middleware/EnsureUserIsScorer.php` - Scorer role middleware
- `app/Http/Middleware/EnsureUserIsCompany.php` - Company role middleware (prevents scorers/admins crashing on company routes)
- `resources/views/scorer/dashboard.blade.php` - Scorer dashboard layout with stage selector tabs
- `resources/views/livewire/scorer/match-create-modal.blade.php` - Create/schedule match modal with dynamic stage-based validation
- `resources/views/livewire/scorer/matches-list.blade.php` - Scorer match list with status filter tabs and quick actions
- `resources/views/livewire/scorer/group-standings.blade.php` - Scorer view of group standings with qualify button
- `resources/views/livewire/scorer/bracket-slot-manager.blade.php` - QF bracket position assignment and swap UI
- `resources/views/livewire/scorer/match-edit-modal.blade.php` - Edit match modal with bidirectional status and confirmation
- `resources/views/livewire/admin/create-scorer-modal.blade.php` - Admin UI for creating scorer accounts
- `resources/views/livewire/admin/group-manager.blade.php` - Admin UI for creating groups and assigning teams
- `resources/views/livewire/public/match-cards.blade.php` - Public match result cards with upcoming/live/finished states
- `resources/views/livewire/public/group-standings.blade.php` - Public group standings tables
- `resources/views/livewire/public/tournament-bracket.blade.php` - Public interactive tournament bracket tree
- `resources/views/scoreboard.blade.php` - Public scoreboard page layout

### Test Files
- `tests/Feature/SecurityHeadersTest.php` - Verify CSP header, X-Powered-By removed, all security headers present
- `tests/Feature/AdminRegistrationRouteTest.php` - Assert `/admin/register` returns 404
- `tests/Unit/UserModelRoleTest.php` - Test `isAdmin()`, `isScorer()`, `isCompany()`, backward-compat `is_admin` accessor
- `tests/Feature/RoleBasedMiddlewareTest.php` - Verify role isolation (company can't access admin/scorer, scorer can't access admin/company, etc.)
- `tests/Feature/ScorerAccountCreationTest.php` - Admin can create scorer, non-admin cannot
- `tests/Feature/ScorerRouteAccessTest.php` - Scorer can access `/scorer/*`, blocked from `/admin/*` and `/company/*`
- `tests/Feature/TournamentGroupManagementTest.php` - Create groups, assign teams, prevent duplicates
- `tests/Unit/NRRCalculationServiceTest.php` - Decimal overs conversion, all-out rule, positive/negative NRR
- `tests/Unit/TournamentStandingsServiceTest.php` - Points, ties, ranking, upcoming/live matches have zero impact
- `tests/Feature/ScorerMatchLifecycleTest.php` - Create upcoming → live → finished, skip live, bidirectional transitions
- `tests/Feature/ScorerGroupMatchEntryTest.php` - Validate scores, overs, balls, wickets, draws, standings auto-update
- `tests/Feature/PublicScoreboardTest.php` - Guest sees all match states, standings match finished games, no edit controls
- `tests/Feature/KnockoutMatchEntryTest.php` - QF/SF/Final scheduling, draw rejection, bracket positions, 6-ball final validation
- `tests/Feature/PublicBracketViewTest.php` - Bracket displays upcoming, live, finished nodes correctly
- `tests/Feature/MatchEditCascadeTest.php` - Editing score/winner recalculates standings and NRR
- `tests/Feature/MatchStatusRevertCascadeTest.php` - Reverting finished → live/upcoming removes stats from standings

### Notes

- Unit tests should be placed in `tests/Unit/` for service/model tests and `tests/Feature/` for HTTP/integration tests following Laravel conventions.
- Use `php artisan test` to run all tests, or `php artisan test --filter=TestClassName` to run specific tests.
- This project uses Laravel 11 with Livewire, Blade components, JWT-based custom auth (cookie: `company_token`), and DaisyUI/TailwindCSS.
- The `Match` PHP model is named `TournamentMatch` to avoid conflict with PHP 8's `match` reserved keyword.
- NRR overs columns in `group_teams` are stored as total balls (integers) to avoid decimal precision issues.

## Instructions for Completing Tasks

**IMPORTANT:** As you complete each task, you must check it off in this markdown file by changing `- [ ]` to `- [x]`. This helps track progress and ensures you don't skip any steps.

Example:
- `- [ ] 1.1 Read file` → `- [x] 1.1 Read file` (after completing)

Update the file after completing each sub-task, not just after completing an entire parent task.

## Tasks

- [x] 0.0 Create feature branch
  - [x] 0.1 Create and checkout a new branch for this feature: `git checkout -b feature/tournament-scoring-system`

- [x] 1.0 Security Hotfixes — Fix critical vulnerabilities in the existing codebase before any tournament work begins
  - [x] 1.1 Fix `SecurityHeaders.php` dead code bug: restructure the method so `$response->headers->remove('X-Powered-By')` executes before the return statement, and the method returns `$response` at the end
  - [x] 1.2 Add a Content-Security-Policy (CSP) header to `SecurityHeaders.php` appropriate for the application (allow self, inline styles for DaisyUI, trusted CDNs)
  - [x] 1.3 Do not remove the unprotected `/admin/register` route from `routes/web.php` (line 53-56) and do not delete or archive `resources/views/admin/register.blade.php` and `resources/views/livewire/admin-registration-form.blade.php`
  - [x] 1.4 Hash JWT tokens before storing them in the database: update `JWTService.php` to store `hash('sha256', $token)` and validate by hashing the incoming cookie token and comparing against the stored hash
  - [x] 1.5 Hash verification codes in the `verification_codes` table: update the creation and validation logic to use `Hash::make()` / `Hash::check()` for OTP codes
  - [x] 1.6 Review `.env.example` and ensure `SESSION_ENCRYPT=true` is documented
  - [x] 1.7 Write `tests/Feature/SecurityHeadersTest.php`: assert CSP header is present, `X-Powered-By` is absent, and all security headers (X-Frame-Options, HSTS, etc.) are set on responses
  - [x] 1.8 Write `tests/Feature/AdminRegistrationRouteTest.php`: assert that `GET /admin/register` returns 404
  - [x] 1.9 Update `tests/Feature/AdminDashboardTest.php`: remove `test_admin_register_page_can_be_rendered` test, ensure remaining tests pass
  - [x] 1.10 Run `php artisan test` and verify all tests pass before proceeding

- [ ] 2.0 Role System Migration — Replace `is_admin` boolean with `role` ENUM and update all references across the codebase
  - [ ] 2.1 Create migration `php artisan make:migration add_role_to_users_table_and_drop_is_admin`: add `role` ENUM column (`'company'`, `'admin'`, `'scorer'`) with default `'company'`, migrate existing data (`is_admin = true` → `role = 'admin'`, all others → `role = 'company'`), then drop the `is_admin` column
  - [ ] 2.2 Handle orphan accounts in the migration: users with `is_admin = false` AND `company_id IS NULL` should be flagged for manual review (log a warning) rather than silently assigned `role = 'company'`
  - [ ] 2.3 Update `app/Models/User.php`: replace `'is_admin'` in `$fillable` with `'role'`, remove `'is_admin' => 'boolean'` cast, add `'role'` as a string cast, add helper methods `isAdmin(): bool`, `isScorer(): bool`, `isCompany(): bool`, `hasRole(string $role): bool`, and a backward-compatible accessor `getIsAdminAttribute(): bool` that returns `$this->isAdmin()`
  - [ ] 2.4 Update `app/Http/Middleware/EnsureUserIsAdmin.php`: change `!$user->is_admin` to `!$user->isAdmin()`
  - [ ] 2.5 Create `app/Http/Middleware/EnsureUserIsCompany.php`: check `$user->isCompany()` and that `$user->company_id` is not null, abort 403 otherwise
  - [ ] 2.6 Register the new `company` middleware alias in `bootstrap/app.php`: add `'company' => \App\Http\Middleware\EnsureUserIsCompany::class`
  - [ ] 2.7 Add the `company` middleware to the company route group in `routes/web.php`: change `Route::middleware('auth.company')` (line 92) to `Route::middleware(['auth.company', 'company'])` for all `/company/*` routes
  - [ ] 2.8 Update `resources/views/components/Navbar.blade.php` (line 66): replace `$user->is_admin == true` with `$user->isAdmin()`, add an `@elseif($user->isScorer())` branch with a link to `route('scorer.dashboard')`
  - [ ] 2.9 Update `resources/views/components/HeroSection.blade.php` (line 49): replace `$user->is_admin == true` with `$user->isAdmin()`, add scorer-specific CTA
  - [ ] 2.10 Update `resources/views/registrations.blade.php` (line 162): replace `$user->is_admin == true` with `$user->isAdmin()`
  - [x] 2.11 Update `resources/views/admin/dashboard.blade.php` (line 52): replace `User::where('is_admin', true)->count()` with `User::where('role', 'admin')->count()`, add a scorer count display: `User::where('role', 'scorer')->count()`
  - [x] 2.12 Update `resources/views/livewire/admin/users-list.blade.php`: replace all `is_admin` references with role-based logic — show role badges (Admin/Scorer/Company), replace the admin toggle with a role selector dropdown, update the filter to filter by role instead of `is_admin`
  - [x] 2.13 Update `resources/views/livewire/admin/approve-team.blade.php` (line 13): replace `$user->is_admin` with `$user->isAdmin()`
  - [x] 2.14 Update `resources/views/livewire/company-registration-form.blade.php` (line 83): replace `'is_admin' => false` with `'role' => 'company'`
  - [x] 2.15 Update `app/Console/Commands/SetUserAdmin.php`: rename to `SetUserRole.php`, update signature to `user:set-role {email} {role}` where role is `admin`, `scorer`, or `company`, update the logic to set `$user->role` accordingly
  - [x] 2.16 Update login redirect logic: after successful JWT login, inspect `$user->role` and redirect to `admin.dashboard`, `scorer.dashboard`, or `company.dashboard` accordingly. Add a null `company_id` guard for company users
  - [x] 2.17 Write `tests/Unit/UserModelRoleTest.php`: test `isAdmin()`, `isScorer()`, `isCompany()`, `hasRole()`, and backward-compatible `$user->is_admin` accessor
  - [x] 2.18 Write `tests/Feature/RoleBasedMiddlewareTest.php`: verify company users cannot access `/admin/*`, admin users can access `/admin/*`, scorer users are blocked from `/admin/*` and `/company/*`, company users are blocked from `/scorer/*`
  - [x] 2.19 Run migration on test database: `php artisan migrate:fresh --seed` and verify no errors
  - [x] 2.20 Run `php artisan test` and verify all tests pass

- [x] 3.0 Scorer Infrastructure — Create scorer middleware, admin UI for scorer account creation, and protected scorer route group
  - [x] 3.1 Create `app/Http/Middleware/EnsureUserIsScorer.php`: check `$user->isScorer()`, abort 403 if not a scorer
  - [x] 3.2 Register the `scorer` middleware alias in `bootstrap/app.php`: add `'scorer' => \App\Http\Middleware\EnsureUserIsScorer::class`
  - [x] 3.3 Add a protected scorer route group in `routes/web.php`: `Route::middleware(['auth.company', 'scorer'])->group(...)` with `GET /scorer/dashboard` pointing to `scorer.dashboard` view
  - [x] 3.4 Create `resources/views/scorer/dashboard.blade.php`: basic layout extending the app layout with a placeholder "Scorer Dashboard" heading (full UI built in Task 6.0)
  - [x] 3.5 Build `resources/views/livewire/admin/create-scorer-modal.blade.php`: a Livewire component form in the admin panel with name and email fields, that creates a new user with `role = 'scorer'`, `company_id = null`, and triggers the standard email verification OTP flow
  - [x] 3.6 Add a "Create Scorer Account" button on the admin users list page (`users-list.blade.php`) that opens the create-scorer modal
  - [x] 3.7 Write `tests/Feature/ScorerAccountCreationTest.php`: verify admin can create a scorer account, non-admin (company/scorer) cannot, scorer is saved with `role = 'scorer'` and `company_id = null`
  - [x] 3.8 Write `tests/Feature/ScorerRouteAccessTest.php`: verify scorer can access `/scorer/dashboard`, scorer is blocked from `/admin/*` and `/company/*`, company and admin users are blocked from `/scorer/*`
  - [x] 3.9 Run `php artisan test` and verify all tests pass

- [x] 4.0 Tournament Config & Groups — Create `config/tournament.php`, `tournament_groups` table, `group_teams` pivot table, and admin group management UI
  - [x] 4.1 Create `config/tournament.php` with all rule values: `overs_per_match => 5`, `balls_per_over => ['G' => 4, 'QF' => 4, 'SF' => 4, 'F' => 6]`, `max_wickets => 11`, `points_win => 2`, `points_draw => 1`, `points_loss => 0`, `teams_qualify_per_group => 2`
  - [x] 4.2 Create migration `php artisan make:migration create_tournament_groups_table`: columns `id`, `name` (VARCHAR 50), `created_at`, `updated_at`
  - [x] 4.3 Create migration `php artisan make:migration create_group_teams_table`: columns `id`, `group_id` (FK → tournament_groups CASCADE), `team_id` (FK → teams CASCADE), `matches_played` (INT default 0), `wins` (INT default 0), `losses` (INT default 0), `draws` (INT default 0), `points` (INT default 0), `total_balls_faced` (INT default 0), `total_runs_scored` (INT default 0), `total_balls_bowled` (INT default 0), `total_runs_conceded` (INT default 0), `nrr` (DECIMAL 8,4 default 0.0000), `qualified` (BOOLEAN default false), `created_at`, `updated_at`, UNIQUE constraint on `(group_id, team_id)`
  - [x] 4.4 Create `app/Models/TournamentGroup.php`: fillable `name`, add `groupTeams()` HasMany relationship, add `teams()` BelongsToMany through pivot
  - [x] 4.5 Create `app/Models/GroupTeam.php`: fillable fields for all standings columns, add `group()` BelongsTo and `team()` BelongsTo relationships, cast `qualified` to boolean, cast `nrr` to decimal
  - [x] 4.6 Add `groupTeam()` HasOne relationship to `app/Models/Team.php`: `return $this->hasOne(GroupTeam::class)`
  - [x] 4.7 Build `resources/views/livewire/admin/group-manager.blade.php`: Livewire component for admin panel that allows creating groups (A, B, C, D), assigning approved teams to groups via dropdown, showing current group rosters, and preventing duplicate team assignments
  - [x] 4.8 Add a "Tournament Groups" navigation link and route in the admin section of `routes/web.php`: `GET /admin/groups` guarded by `['auth.company', 'admin']`
  - [x] 4.9 Write `tests/Feature/TournamentGroupManagementTest.php`: test creating groups, assigning teams, preventing duplicate assignments, unique group name constraint, only approved teams can be assigned
  - [x] 4.10 Run `php artisan test` and verify all tests pass

- [x] 5.0 Match Schema & Scoring Services — Create `matches` table with lifecycle status, build NRR calculation service, and tournament standings service
  - [x] 5.1 Create migration `php artisan make:migration create_matches_table`: columns `id`, `stage` ENUM('G','QF','SF','F'), `status` ENUM('upcoming','live','finished') default 'upcoming', `group_id` (FK → tournament_groups NULLABLE, only for 'G' stage), `bracket_position` (TINYINT NULLABLE), `team1_id` (FK → teams), `team2_id` (FK → teams), `batting_first_id` (FK → teams NULLABLE), `team1_score` (INT NULLABLE), `team1_overs` (TINYINT NULLABLE), `team1_balls` (TINYINT NULLABLE), `team1_wickets` (TINYINT NULLABLE), `team2_score` (INT NULLABLE), `team2_overs` (TINYINT NULLABLE), `team2_balls` (TINYINT NULLABLE), `team2_wickets` (TINYINT NULLABLE), `is_draw` (BOOLEAN default false), `winner_id` (FK → teams NULLABLE, ON DELETE SET NULL), `entered_by` (FK → users, ON DELETE SET NULL), `created_at`, `updated_at`
  - [x] 5.2 Add a CHECK constraint or application-level validation enforcing: when `is_draw = true` then `winner_id` must be NULL, and when `winner_id IS NOT NULL` then `is_draw` must be false
  - [x] 5.3 Create `app/Models/TournamentMatch.php`: set `$table = 'matches'`, fillable for all columns, add relationships `team1()`, `team2()`, `battingFirst()`, `winner()`, `enteredBy()`, `group()`, add scopes `scopeFinished()`, `scopeLive()`, `scopeUpcoming()`, `scopeForStage($stage)`
  - [x] 5.4 Create `app/Services/NRRCalculationService.php`: implement `calculateNRR(GroupTeam $groupTeam): float` — query all finished group matches for this team, compute total balls faced/bowled, convert to decimal overs using `config('tournament.balls_per_over.G')`, apply the all-out rule (if wickets >= `config('tournament.max_wickets')` use full `config('tournament.overs_per_match')` × `config('tournament.balls_per_over.G')` balls), return `(runs_scored / overs_faced) - (runs_conceded / overs_bowled)`, handle division by zero
  - [x] 5.5 Create `app/Services/TournamentStandingsService.php`: implement `recalculateForGroup(TournamentGroup $group): void` — iterate all teams in the group, query only `status = 'finished'` matches, compute matches_played, wins, losses, draws, points (using config values), cumulative balls/runs for NRR, call `NRRCalculationService`, update `group_teams` rows, handle the case where a match is reverted from finished (i.e., full recalculation from scratch)
  - [x] 5.6 Write `tests/Unit/NRRCalculationServiceTest.php`: test decimal overs conversion (3 overs 2 balls with 4 bpov = 3.5 overs), all-out rule (11 wickets → use 20 balls = 5.0 overs), positive and negative NRR, zero overs division protection
  - [x] 5.7 Write `tests/Unit/TournamentStandingsServiceTest.php`: test points accumulation (win=2, draw=1, loss=0), ties, correct ranking by points then NRR, explicitly assert that `upcoming` and `live` matches produce zero impact on standings
  - [x] 5.8 Run `php artisan test` and verify all tests pass

- [x] 6.0 Scorer Dashboard — Match Creation & Lifecycle — Build the scorer dashboard layout, create/schedule match modal, match list with status filtering, and group qualification interface
  - [x] 6.1 Build the scorer dashboard layout in `resources/views/scorer/dashboard.blade.php`: stage selector tabs (`Group Stage`, `Quarter-Finals`, `Semi-Finals`, `The Final`), summary stats panel showing current group standings, played matches, and pending stages
  - [x] 6.2 Build `resources/views/livewire/scorer/match-create-modal.blade.php` as a Livewire component: stage selector dropdown, group selector (shown only when stage = 'G'), Team 1 and Team 2 dropdowns (populated based on stage — group teams for 'G', qualified teams for 'QF', QF winners for 'SF', SF winners for 'F'), optional batting first selector, status toggle (Upcoming / Live / Finished), conditionally show score fields when status = 'Finished' (runs, overs 0-5, balls 0-N based on stage, wickets 0-11, outcome selector: Team 1 Won / Team 2 Won / Tied)
  - [x] 6.3 Implement validation in the match create Livewire component: prevent same team as both team1 and team2, validate overs max from config, validate balls max from `config('tournament.balls_per_over.{$stage}') - 1`, validate wickets max from config, reject draws in knockout stages, validate team IDs belong to correct group/stage pool, prevent duplicate pairings in the same stage
  - [x] 6.4 Implement the pre-save confirmation modal: when saving as `finished`, display all entered details (teams, innings scores, overs, wickets, selected winner/tie, calculated winning margin using the batting_first_id logic from the plan) and require explicit "Confirm & Save" before committing
  - [x] 6.5 On successful `finished` match save in group stage, trigger `TournamentStandingsService::recalculateForGroup()` for the match's group
  - [x] 6.6 Build `resources/views/livewire/scorer/matches-list.blade.php` as a Livewire component: status filter tabs (`All | Upcoming | Live | Finished`), list of matches with status badges, quick action buttons (Mark Live, Enter Results / Finish), status dropdown for bidirectional transitions (Upcoming ⇄ Live ⇄ Finished), edit button for each match
  - [x] 6.7 Implement bidirectional status transitions in the matches list: forward (upcoming → live → finished, or upcoming → finished skipping live), backward (finished → live or upcoming, live → upcoming), on backward transition from `finished` trigger cascade recalculator to remove the match's stats from standings
  - [x] 6.8 Build `resources/views/livewire/scorer/group-standings.blade.php`: display group standings table (Pos, Team, P, W, L, D, Pts, NRR) for each group, highlight top 2 teams, show a "Qualify Top 2 Teams" button per group with manual adjustment capability
  - [x] 6.9 Implement the qualification action: set `group_teams.qualified = true` for selected teams (max 2 per group), show a soft advisory warning if any matches in the group are still `upcoming` or `live` (non-blocking), prevent un-qualifying a team if they already have a knockout match entry, add confirmation dialog
  - [x] 6.10 Write `tests/Feature/ScorerMatchLifecycleTest.php`: test creating an upcoming match, transitioning to live, completing as finished, skipping directly from upcoming to finished, backward transitions, and status filter functionality
  - [x] 6.11 Write `tests/Feature/ScorerGroupMatchEntryTest.php`: test score validation (overs max 5, balls 0-3, wickets 0-11), draws allowed in group stage, group standings auto-update on finished match, duplicate pairing rejection, same-team rejection
  - [x] 6.12 Run `php artisan test` and verify all tests pass

- [x] 7.0 Public Scoreboard — Match Cards, Standings & Filtering — Build public-facing match result cards with status badges, group standings tables, and status filter tabs
  - [x] 7.1 Add public scoreboard route in `routes/web.php`: `GET /scoreboard` (no auth required) pointing to `scoreboard` view
  - [x] 7.2 Create `resources/views/scoreboard.blade.php`: public scoreboard page layout extending the public layout, with sections for match cards and group standings
  - [x] 7.3 Build `resources/views/livewire/public/match-cards.blade.php` as a Livewire component: status filter tabs (`All | 🟢 Live | 🕐 Upcoming | ✅ Finished`), render match cards with 3 visual states — Upcoming card (stage badge, Team 1 vs Team 2, "Upcoming" badge), Live card (Team 1 vs Team 2, pulsing "LIVE • Match in Progress" badge, batting first indicator), Finished card (innings runs, overs, wickets for both teams, bold winning margin badge)
  - [x] 7.4 Build `resources/views/livewire/public/group-standings.blade.php` as a Livewire component: tables for each group showing Pos, Team, P, W, L, D, Pts, NRR, "Qualified" badge on top 2 teams, strictly reflecting finished matches only
  - [x] 7.5 Ensure no edit controls, action buttons, or scorer-specific links are leaked to the public views — verify all scorer actions are hidden from guests
  - [x] 7.6 Write `tests/Feature/PublicScoreboardTest.php`: verify public guest can access `/scoreboard`, sees upcoming/live/finished cards, standings match finished games, no edit controls or scorer actions are present in the HTML
  - [x] 7.7 Run `php artisan test` and verify all tests pass

- [x] 8.0 Knockout Stages — QF/SF/Final Scheduling & Bracket Slots — Build knockout match creation with bracket position assignment, slot locking rules, and stage-specific validation
  - [x] 8.1 Add QF match creation flow to the scorer match-create modal: show bracket position selector (Slots 1-4) when stage = 'QF', populate Team 1 and Team 2 dropdowns from `group_teams.qualified = true` (8 teams), allow creating as Upcoming, Live, or Finished
  - [x] 8.2 Add SF match creation flow: show slot selector (SF 1 / SF 2) when stage = 'SF', populate Team 1 and Team 2 dropdowns from QF winners (`TournamentMatch::where('stage', 'QF')->whereNotNull('winner_id')->pluck('winner_id')`)
  - [x] 8.3 Add Final match creation flow: populate Team 1 and Team 2 from SF winners, when saving as finished validate balls field uses `config('tournament.balls_per_over.F') - 1` (0-5 range) instead of the default 0-3
  - [x] 8.4 Implement knockout draw rejection: when stage is 'QF', 'SF', or 'F' and status = 'finished', the "Tied" outcome option must be disabled/hidden and validation must reject `is_draw = true`
  - [x] 8.5 Build `resources/views/livewire/scorer/bracket-slot-manager.blade.php`: visual display of QF slots 1-4, show which match is in each slot, allow drag-and-drop or button swap of slot positions, show SF1 (slots 1+2) and SF2 (slots 3+4) mapping
  - [x] 8.6 Implement slot locking rule: once an SF match is created that references slots (e.g., SF1 created), lock slots 1 & 2 from rearrangement. Once SF2 is created, lock slots 3 & 4. Show a lock icon and disable swap for locked slots
  - [x] 8.7 Add `bracket_position` validation: QF must use 1-4, SF must use 1-2, F must use 1. Reject values outside the allowed range for each stage
  - [x] 8.8 Write `tests/Feature/KnockoutMatchEntryTest.php`: test scheduling QF/SF/Final as upcoming and live, verify QF and SF finished matches reject draws, Final allows balls 0-5, bracket position assignments are validated per stage, slot locking prevents rearrangement after SF creation
  - [x] 8.9 Run `php artisan test` and verify all tests pass

- [x] 9.0 Public Bracket — Dynamic Tournament Bracket Tree — Build the interactive visual tournament bracket with dynamic node states for upcoming, live, and finished matches
  - [x] 9.1 Add public bracket route in `routes/web.php`: `GET /bracket` (no auth required) or integrate into the existing `/scoreboard` page as a tab/section
  - [x] 9.2 Build `resources/views/livewire/public/tournament-bracket.blade.php` as a Livewire component: render a visual bracket tree (QF → SF → Final) using CSS/HTML, two halves (Top: slots 1+2 → SF1, Bottom: slots 3+4 → SF2) feeding into the Final
  - [x] 9.3 Implement dynamic node states: Upcoming nodes show team names with an "Upcoming" pill, Live nodes show teams with a pulsing animated "LIVE" badge, Finished nodes show scores/wickets and highlight the advancing winner in vibrant green
  - [x] 9.4 Make the bracket mobile-responsive: horizontal scroll on small screens or collapsible stage view
  - [x] 9.5 Write `tests/Feature/PublicBracketViewTest.php`: verify bracket accurately displays upcoming pairings, live matches with badges, finished winners with scores, and correct SF/Final connections
  - [x] 9.6 Run `php artisan test` and verify all tests pass

- [x] 10.0 Match Editing & Cascade Recalculation — Build match edit modal with bidirectional status transitions and automatic standings/NRR recalculation
  - [x] 10.1 Build `resources/views/livewire/scorer/match-edit-modal.blade.php` as a Livewire component: pre-fill all match data (teams, scores, status, bracket position), include bidirectional status selector (Upcoming ⇄ Live ⇄ Finished), include all score fields when editing a finished match, include confirmation modal before saving changes
  - [x] 10.2 Implement optimistic locking: check `updated_at` timestamp on save — if the match was modified by another scorer since the modal was opened, show a conflict warning and require the editor to reload before saving
  - [x] 10.3 Implement cascade recalculation on group match edit: when a finished group match's scores, winner, or is_draw are changed, call `TournamentStandingsService::recalculateForGroup()` to recompute all stats from scratch
  - [x] 10.4 Implement cascade recalculation on status revert: when a finished match is moved backward to `live` or `upcoming`, null out score fields (or keep them for recovery — design decision), call `TournamentStandingsService::recalculateForGroup()` to remove the match's contributions from standings
  - [x] 10.5 Implement knockout match edit safety: if editing a QF match's winner and the old winner already has an SF match entry, show a warning that the SF match references this winner and may become invalid
  - [x] 10.6 Write `tests/Feature/MatchEditCascadeTest.php`: verify editing a finished group match's score or winner immediately recalculates team stats, standings, and NRR for both teams involved
  - [x] 10.7 Write `tests/Feature/MatchStatusRevertCascadeTest.php`: verify reverting a finished match to live or upcoming recalculates standings and removes its points/NRR contribution
  - [x] 10.8 Run `php artisan test` and verify all tests pass

- [x] 11.0 UI Polish, Responsive Optimization & Full Test Suite — Responsive layouts, loading indicators, edge case handling, and complete test suite verification
  - [x] 11.1 Review and optimize all scorer dashboard views for mobile, tablet, and desktop responsive breakpoints
  - [x] 11.2 Review and optimize all public scoreboard and bracket views for mobile, tablet, and desktop
  - [x] 11.3 Add Livewire loading indicators (wire:loading) to all interactive components: match creation, status transitions, qualification actions, bracket slot swaps
  - [x] 11.4 Add smooth CSS animations: pulsing live badge, card transitions on filter change, bracket node highlights on state change
  - [x] 11.5 Handle all edge cases documented in the plan: team scores 0/0, all-out in 3.2 overs, all group draws, identical points and NRR, qualifying before all matches finished, editing group match after QF started, un-qualifying after knockout match exists
  - [x] 11.6 Verify graceful error handling: network failures on Livewire actions, validation error display, 403/404 pages for unauthorized access
  - [x] 11.7 Run the complete test suite: `php artisan test` — verify 100% pass rate across all security, role, scoring, calculation, bracket, and UI permission tests
  - [x] 11.8 Review test coverage and add any missing edge case tests identified during development
