# Tournament branch code review

## Implementation follow-up

The original review below records the behavior at `d2dfc92`. The working tree now includes fixes for F1-F4 and T1, described here. Later commits already on the branch added tournament environment documentation and public-page revision polling; those changes are preserved. In particular, the original review's manual-refresh limitation describes the earlier revision, not the current polling implementation.

As requested, **the CI/PostgreSQL deadline and dependency lockfiles are unchanged**. Existing dependency advisories remain open. The existing local `composer.json` development-script edit is preserved.

| Original finding | Implemented behavior |
| --- | --- |
| F1: No recovery for incorrect scheduled fixtures | The Matches screen now offers full editing of unused upcoming fixtures and a confirmed delete action. Knockout cards link directly to that match's editor. An editor left open while another scorer deletes the fixture recovers to the list with an explanation. |
| F2: Blank sixes/fours bypassed known boundary checks | Each known boundary count contributes a minimum run total even when another count is unknown. Unknown fields remain unknown in storage. New database guards enforce the same rule. |
| F3: Impossible player figures could be published | Positive batting figures are rejected for “did not bat.” Review/publication checks the effective player set, including retained published rows and replacements/withdrawals, against official team runs, opposing innings bowling deliveries, and wickets. Team-result corrections and restoring reopened results use the same consistency check. |
| F4: Ties were numbered differently | Public standings, qualification guidance, and scorer standings share one ranking implementation. The scorer screen displays the calculated tied rank. |
| T1: Startup inherited local tournament flags | Both PHPUnit configurations start with the tournament disabled. Tests explicitly enable it as needed; the existing local SQLite configuration fix is preserved. |

### How to edit or delete a scheduled match

1. Open **Scorer → Matches → Edit match**. From a knockout card, **Edit match** opens the same editor for that fixture.
2. For an unused **Upcoming** match, change its round, group or bracket position, either team, balls per over, batting-first team, scheduled time, venue, and match number. Choosing a different group/round reloads the relevant teams and clears the old participant choices.
3. Select **Save match details**. The server checks current approval, group membership, qualification/previous-round winners, duplicate pairings, bracket occupancy, and the latest saved versions before committing anything.
4. To remove a mistaken fixture, select **Delete unused fixture** and confirm. This frees its pairing/bracket slot. If it was the group's last fixture and no other records lock the group, the group assignments can then be corrected.

The tournament year remains the configured edition. Five overs and seven wickets remain the current tournament rules; the editable ball count means **four or six balls per over**.

Structural edits/deletion require an upcoming fixture with no saved team-result draft, player entries, official innings, structure lock, or publication history. Discard unpublished drafts first. An in-progress match must only be returned to Upcoming if it has not actually started. Published history remains protected; these operations do not erase results or silently change the teams attached to recorded scores. Normal schedule edits remain available through the existing protected workflow.

For an upstream knockout correction, the scorer can delete an unused downstream fixture, correct/review/publish the upstream result, and create the correct downstream fixture. A regression test covers that complete sequence. Already-published downstream history still requires organizer-led recovery rather than deletion through this feature.

### Database update and existing records

The additive migration [2026_11_19_000001_add_known_player_figure_guards.php](../database/migrations/2026_11_19_000001_add_known_player_figure_guards.php) adds database-level checks for partial-known boundaries and contradictory DNB batting figures. It uses a PostgreSQL check for future writes and SQLite insert/update triggers. The PostgreSQL check is added without retroactively rejecting existing rows, so deployment does not silently alter old score sheets or fail solely because an older row contains one of these contradictions.

Previously inconsistent figures can be corrected or explicitly withdrawn. Withdrawal drafts may retain the old figures as an exact record of what is being withdrawn; they cannot be published as replacement figures. Normal deployment must run the new migration. This work has not run migrations against the application's existing database.

### Verification of these fixes

New regression coverage includes changing teams/format/schedule, moving a fixture between groups, stale edits/deletion, saved-draft/history protection, knockout recovery, browser-locked state, direct database guard checks, legacy-row withdrawal, aggregate player totals, result correction/restoration, and consistent tied ranks. The existing real-HTTP CSRF/permission matrix now includes fixture deletion. PostgreSQL races cover edits/deletion waiting behind another update, a newly saved player draft, and scorer revocation.

- Initial full SQLite run: **1,175 tests, 6,831 assertions passed** with a 512 MB test-process limit.
- The first focused validation/schema/recovery run passed **87 tests, 314 assertions**.
- Fresh final SQLite checks passed **180 tests, 1,616 assertions**, including the shared correction/restoration guard and edit-link/browser-state tests added after the initial full run.
- Fresh final PostgreSQL checks passed **134 tests, 768 assertions**, including the new migration, fixture-editing races, and the final shared publication guard. Its disposable container was removed.
- Dashboard tests passed with the launching process explicitly setting `TOURNAMENT_ENABLED=true`: **8 tests, 130 assertions**. Both configurations override that startup value without changing `.env`.
- Full PostgreSQL run: **780 tests, 5,964 assertions passed**, 11m47s, 162 MB. Its disposable container was removed.
- Final deletion/editor follow-up passed **67 SQLite tests, 961 assertions**, including the scheduling/security matrix, and **9 PostgreSQL fixture-recovery tests, 48 assertions**. This covers an editor left open while another scorer deletes the fixture. The follow-up PostgreSQL container was removed.
- Production asset build passed. PHP formatting and `git diff --check` passed.

The detailed original findings and their original test results are retained below as review history. They are not a claim that the fixed behavior still exists.

---

This report is written for someone taking over the feature without having built it. The first sections explain the intended workflow. The findings then explain where that workflow can fail, what was demonstrated, and what still needs a product or technical decision.

Start with sections 1-3 for the feature itself, then read F1-F4 for the application problems. T1-T2 explain test-running problems. The limitations section contains decisions for the organizers, and the suggested work order explains what I recommend addressing first. Source-code links are included for the developer; you can understand the explanations without opening them.

Reviewed 19 November 2026. Branch: `score-updating-feature`, HEAD `d2dfc92`. Base: `main`, `84a06ea` (also the merge base). Scope: 246 changed files, including tournament services, schema, public pages, scorer screens, account management, registration protections, and tests.

**Recommendation: hold production release until the fixture recovery gap and player validation defects are addressed, and the existing dependency advisories are triaged and patched.** The feature has substantial authorization and transaction protection, but passing its current tests does not establish match-day readiness.

“Hold” is my review recommendation. There is no automatic block or permission requirement imposed by this document. It means I would fix the important correctness issues and review the dependency risks before relying on this system during the tournament.

Application code was not changed. The pre-existing local edits to `composer.json` and `phpunit.xml` were preserved. The full SQLite result below uses that local PHPUnit configuration; the committed configuration was also checked separately. All database execution used synthetic data in guarded SQLite memory databases or the repository's disposable localhost PostgreSQL runner. No application database was migrated or seeded.

## 1. What this feature adds to the website

This branch adds a tournament administration and results system to the existing registration website. It reuses registered teams and players, lets designated scorers arrange matches and enter results, and gives spectators a public tournament page.

It is a system for entering and publishing match summaries. A scorer records the team innings totals and individual player figures. A match can be labelled “In progress,” but this feature does not provide ball-by-ball scoring or automatically stream a changing live score to spectators.

There are three main audiences:

| Person | What they do in this feature |
| --- | --- |
| Administrator | Creates dedicated scorer accounts and removes scorer access. Admin access does not automatically grant scorer access. |
| Scorer | Sets up groups, assigns approved teams, creates fixtures, enters and publishes team results and player figures, adjusts points, selects qualifiers, and arranges knockout fixtures. |
| Public visitor | Opens `/tournament` without logging in and views Matches, Table, Bracket, and Stats. The visitor cannot enter or publish results. |

Company/university representatives still use the existing registration and team-management area. A dedicated scorer account has a separate role and is not associated with an organization. Scorers use the existing email-code login: an administrator creates the account, and the scorer requests a code through the normal Login page.

### The pages and their responsibilities

| Page | What it is for | What a successful change affects |
| --- | --- | --- |
| Scorer: Groups | Create group names and put existing approved teams into groups for the selected tournament year. | Which teams can be selected for group fixtures. |
| Scorer: Matches | Create a planned match and manage its schedule/status. A planned match is called a **fixture**. | The match list; a saved upcoming/live fixture can appear publicly before a result exists. |
| Scorer: Match result | Enter the two team innings, outcome, and winner; save a draft, review it, then publish. | Public result, group points/standings, and NRR. A correction can also change whether existing player figures count in rankings. |
| Scorer: Player figures | Enter each player's runs, boundaries, wickets, bowling deliveries, dot balls, and batting status; review and publish saved changes. | Public player rankings and tournament player totals. |
| Scorer: Standings | Check group records and enter an official point adjustment with a reason. | Group points and table order. Adjustments have a recorded history. |
| Scorer: Knockouts | Manually confirm advancing teams and arrange quarter-final, semi-final, and final fixtures. | Qualification markers and the knockout bracket. |
| Admin: Scorer accounts | Create a scorer account or revoke its scorer permission. | Who may use scorer actions; revoking permission preserves recorded results. |

The four public tabs show different parts of the same tournament:

- **Matches:** upcoming, in-progress, or finished fixtures. Finished scores require a published result. The default selection is Finished.
- **Table:** each group's team record, points, NRR, and saved qualification marker (`Q`).
- **Bracket:** the actual quarter-final, semi-final, and final matchups entered by scorers, including a champion after a final win is published.
- **Stats:** leaders for runs, wickets, fours, sixes, dot balls, and highest individual innings. The service selects the top five player positions and includes everyone tied at the cutoff, so more than five players can appear.

## 2. The intended match-day workflow

The following is the normal sequence, assuming setup is correct and no recovery is needed:

1. **Enable the feature for the intended tournament year.** `TOURNAMENT_ENABLED` is the on/off setting; `TOURNAMENT_YEAR` identifies the season. Neither setting gives an account permission to score.
2. **An administrator creates scorer accounts.** Scorers sign in using email codes.
3. **A scorer finishes group setup.** Teams must already be registered and approved. A team has one group assignment per tournament year. Group counts and sizes are flexible.
4. **A scorer creates fixtures.** They choose the two teams and match format, and may enter a time, venue, and display number. The program does not automatically generate every group pairing.
5. **A scorer enters a team-result draft.** Saving a draft stores work for later; it does not replace the public result. A draft can be incomplete.
6. **The scorer reviews and publishes the team result.** Publication makes it official in this application, finishes the match, and recalculates the relevant standings. Normal wins and ties need the required innings details; exceptional outcomes have different requirements.
7. **The scorer separately enters, reviews, and publishes player figures.** Draft player entries can be prepared earlier, but publication requires a finished, published win or tie. Published abandoned/no-result matches do not qualify for player-stat publication in the current rules.
8. **Visitors load or refresh the public page.** The newly published data appears on their next request. A page already open does not update itself.
9. **At qualification time, a scorer chooses advancing teams.** Table order is guidance. The software allows partial selections and manual choices rather than automatically advancing the highest-ranked teams.
10. **The scorer creates knockout fixtures.** Quarter-final participants must be confirmed qualifiers. Semi-final and final participants must have a published win in the previous round. Matchups are chosen manually.

The distinction between steps 6 and 7 matters. If Team A's win has been published but its player figures are still drafts, the public table can correctly show the win while the public player rankings still show older totals. That is expected behavior. If organizers expect one button to publish everything, the current workflow does not meet that expectation.

### A concrete example

Suppose Team A bats first against Team B and wins 60/3 against 50/7. The scorer enters both innings, selects Team A as the winner, saves the draft, reviews it, and publishes. The public match result and the group standings then change.

Suppose a Team A player scored 24 runs, including two fours and two sixes. Those individual figures are entered on the Player figures screen and published through its separate review. Only then do those figures contribute to the player's public tournament rankings.

If the scorer later realizes that the player scored 25, they save a correction draft. The public still sees the previously published contribution until that correction is reviewed and published. The system replaces the contribution and recalculates totals; it should not add a second copy of the performance.

### The cricket rules currently encoded

These are implementation rules from [config/tournament.php](../config/tournament.php), not confirmation that the organizers have approved them:

| Rule | Current behavior |
| --- | --- |
| Innings length | Five overs. |
| Maximum wickets | Seven. |
| Balls per over | The scorer chooses four or six for each fixture. Defaults are four for group/quarter-final matches and six for semi-final/final matches. |
| Group points | Win: 2; loss: 0; tie: 1; no result/abandonment: 1. Official adjustments can change a team's final points. |
| Table order | Points first, then NRR. Equal sporting records are intended to remain tied. F4 describes a scorer-screen inconsistency. |
| Knockout capacity | Four quarter-finals, two semi-finals, and one final: up to eight selected quarter-final teams. |
| Group fixture coverage | One match per distinct pair of teams. A four-team group therefore expects six fixtures. |
| Exceptional results | The result entry supports Super Over decisions, coin tosses, official decisions, and revised targets. Knockout matches need a decided winner. |

For example, seven groups of four teams would require 42 group fixtures. A full eight-team knockout adds seven more. The code supports flexible group setup; the seven-group arrangement is not a hard database restriction.

**Overs notation is not a decimal number.** `3.2` means three complete overs and two balls in the next over. That is 14 deliveries in a four-ball format, or 20 in a six-ball format. The result form uses separate whole-over and ball fields so the application can store an actual delivery count.

**NRR means net run rate.** It compares a team's scoring rate with the rate it concedes across eligible group innings. In a simple example with complete five-over innings, scoring 60 and conceding 50 gives a rate difference of `60/5 - 50/5 = +2`. The implementation combines eligible innings across matches, accounts for the recorded balls-per-over format, and uses the full allocation for an all-out innings. Abandoned/no-result matches are excluded; revised-target results need an explicit official NRR decision. NRR can be unknown, shown as a dash, when the required denominators are unavailable. A dash is not the same as an NRR of zero.

**Blank player figures mean unknown, not zero.** If a scorer leaves sixes blank, the system does not know the six count. Entering `0` says the player definitely hit no sixes. This is useful for partial score sheets, but the system must still reject contradictions in the figures that are known. F2 explains a place where it fails to do that.

## 3. How the stored data and safeguards fit together

A database table is a structured list of records. You do not need to know SQL to understand these responsibilities:

| Stored information | Purpose |
| --- | --- |
| Existing teams and team members | The registered people and teams used by the tournament. This feature reuses them. |
| Tournament groups and group assignments | Which group a team belongs to in a particular year, plus its calculated group record and saved qualification flag. |
| Matches | Who plays whom, format, schedule, status, saved result draft, and current published team result. |
| Player match statistics | A player's saved draft and/or current published figures for one match. |
| Player tournament statistics | Calculated totals from eligible published player performances. Public rankings read these totals. |

“Calculated” or **derived** data means data computed from other records. For example, a player's tournament runs are calculated from their eligible published match performances. These totals are not a second score sheet that the scorer is expected to enter manually.

Several safeguards are already implemented and are worth keeping:

| Safeguard | Plain explanation |
| --- | --- |
| Permission checks | Logging in is not enough: the server checks whether the account is allowed to perform the action. Public visitors and organization representatives cannot simply call a scorer action. |
| Draft versus published records | Work in progress is kept separate from what spectators see. |
| Version checks | If two scorers open the same record and one saves first, the other person's old version can be rejected instead of silently overwriting the newer work. |
| Review receipt/hash | The system creates a fingerprint of the data reviewed. Publication checks it so that a changed draft cannot be published using an old review. |
| Database transaction | Related changes are committed together. For example, a result and its standings updates should both succeed; on failure, the operation should roll back instead of leaving half of it saved. |
| Database locks | Updates that could interfere with each other wait their turn. This reduces the risk of two scorers losing each other's changes. |
| Public field selection | Public responses are built from selected fields rather than exposing complete private database records. |
| Protection for referenced registrations | A team/player already linked to tournament records cannot simply be deleted or moved in ways that break that history. This is why some existing registration-management code also changed in this branch. |

The summary rebuild command recalculates standings and player totals from saved source records. It is a maintenance tool. It does not correct a wrong fixture or decide which score is right, so it is not a solution to F1 or a substitute for score validation.

## 4. How to interpret the findings

The labels distinguish the type and urgency of the work:

| Label | Meaning in this report |
| --- | --- |
| F1-F4 | Findings in the feature's application behavior. |
| T1-T2 | Findings in test setup or the automated test infrastructure. They do not by themselves prove the production scoring page is broken. |
| P1 | High priority: a realistic mistake can block normal tournament operation. Resolve before relying on the feature. |
| P2 | Important correctness or reliability issue. It may not happen on every request, but the triggering scenario needs attention. |
| Dependency advisory | A published security problem in a third-party library version the project uses. Whether a particular exploit works here depends on how that library is used. |
| Product limitation | Current behavior may be deliberate, but organizers need to decide whether it meets their needs. It is not automatically an implementation bug. |

“Confirmed” means the described behavior was demonstrated or directly established from the code. It does not mean that a real scorer has already made that mistake in production. Suggested resolutions below are proposed work; none has been implemented by this review.

### Other technical terms used below

| Term | Meaning in this project |
| --- | --- |
| `.env` / environment setting | A deployment or developer-machine setting, such as the tournament on/off flag. A local setting is not automatically the setting used on the production server. |
| Committed / working-tree edit | Committed code is saved in the Git branch. A working-tree edit is a local change not yet saved in a commit. This distinction matters because the local PHPUnit configuration already contains a flag fix that HEAD's committed version lacks. |
| HEAD / base / regression | HEAD is the branch revision reviewed. The base is the `main` revision used for comparison. A regression is behavior that becomes worse because of a change; the unchanged dependency versions are existing risks rather than new regressions. |
| Validation / database constraint | Validation checks input in application code. A database constraint is an additional rule enforced when a record is stored. F2 affects both layers, which is why fixing just the form would be incomplete. |
| Middleware / endpoint | Middleware checks a request before the requested page/action proceeds, for example verifying login and scorer permission. An endpoint is the URL/action the request calls. |
| CSRF protection | A protection against another website tricking a logged-in browser into submitting an unwanted change. It complements permission checks; it does not validate cricket scores. |
| Livewire / Volt / Alpine | Tools used to build the interactive screens. Livewire handles server-backed component updates, Volt defines components used here, and Alpine controls browser interactions such as opening dialogs. |
| Snapshot / replay | A snapshot represents a component's state sent between the page and server. Replay testing checks that resubmitting an older or differently authenticated interaction does not bypass current rules. |
| Concurrency / race | Two actions overlap in time, such as two scorers saving a match. A race is a problem where the outcome depends on which overlapping action gets ahead. |
| Migration / additive migration | A migration changes the database structure. An additive migration adds feature tables/columns while intending to preserve existing records. Testing this locally does not replace checking the real deployment's database version and migration procedure. |
| Frontend build | Preparing the JavaScript and CSS files that the deployed browser pages load. A successful build proves those assets can be generated, not that all interactions work correctly in a browser. |
| Staging | A separate rehearsal deployment that resembles production, used before affecting the live site. |

## Confirmed feature findings

### F1 — P1: An incorrect fixture can permanently block normal scorer operations

**In everyday terms:** the system has strong rules to stop someone damaging an existing match, but it lacks a normal way to undo a match that was created incorrectly. A harmless setup mistake can therefore need developer/database intervention. “Permanently” here means the available scorer workflow cannot recover it; it does not mean recovery is technically impossible for a developer.

For a knockout example, imagine A beats B in a quarter-final and a scorer creates a semi-final containing A. Organizers then discover that B was actually the winner. The program blocks changing the quarter-final winner because the semi-final still contains A. That block protects the bracket from becoming inconsistent. The missing part is a safe way to remove/correct the unplayed semi-final, correct the quarter-final, and create the right semi-final containing B. This later semi-final is the **downstream** or **dependent** fixture.

Sources: [MatchSchedulingService.php:118](../app/Services/Tournament/MatchSchedulingService.php#L118), [schedule allowlist:226](../app/Services/Tournament/MatchSchedulingService.php#L226), [KnockoutDependencies.php:21](../app/Services/Tournament/KnockoutDependencies.php#L21), [TournamentGroupService.php:184](../app/Services/Tournament/TournamentGroupService.php#L184).

There is no supported operation to correct participants/format or void/delete an unused fixture. `updateSchedule()` accepts only schedule fields, and statuses are limited to upcoming/live/finished. Pair and knockout-position uniqueness prevent creating a replacement. Group assignments also become immutable as soon as any group fixture exists.

Real scenarios:

- A scorer selects four balls per over instead of six. Even before play or publication, the format cannot be corrected and the same pairing cannot be recreated.
- A fixture is created before a missing group team has been assigned. The group becomes locked against membership correction.
- A quarter-final winner is corrected after an unplayed semi-final was created. The dependency guard correctly rejects invalidating that semi-final, but its instruction to resolve the downstream fixture has no corresponding scorer operation. Reopening it does not remove its participant dependency.

**Evidence:** a temporary reproduction confirmed that changing format, cancelling the unused fixture, and creating its replacement are all rejected. Existing knockout tests verify rejection of upstream corrections, but do not provide a complete recovery journey.

**Suggested resolution:** support versioned, authorized correction/removal of unused fixtures, plus an explicit reviewed recovery process for published downstream history. Keep dependency protection; do not silently remove published records. Test correction of a mistyped fixture and correction of a winner with an unplayed downstream fixture.

### F2 — P2: A blank boundary field bypasses a known impossible batting total

**In everyday terms:** the program checks whether fours and sixes fit inside the player's run total, but only if both boundary counts have been filled in. Leaving one count blank accidentally switches off that check.

If a paper score sheet says “1 run, 2 fours, sixes unknown,” it is already contradictory: the two fours alone account for eight runs. We do not need to guess the six count to know something is wrong. A sensible fix would ask the scorer to correct the known contradiction while still allowing genuinely unknown fields to stay blank. An incorrect public “Most fours” leader is one possible consequence.

Sources: [PlayerStatsValidator.php:52](../app/Services/Tournament/PlayerStatsValidator.php#L52), [player source CHECK constraint](../database/migrations/2026_11_01_000005_create_player_match_stats_table.php), [PlayerStatsValidationTest.php:26](../tests/Feature/PlayerStatsValidationTest.php#L26).

Boundary runs are compared with total runs only when runs, fours, and sixes are all known. Consequently, `runs_scored=1, fours=2, sixes=null` is accepted. Two fours already establish at least eight runs; an unknown six count cannot make that combination possible. The database constraint has the same gap.

**Impact:** a transcription mistake can be reviewed, published, and become an incorrect public boundary leader. **Evidence:** the reproduction published this exact combination and verified that the public fours ranking returned 2. An existing test explicitly expects this invalid input to succeed, so the green suite currently preserves the defect.

**Suggested resolution:** check the lower bound established by each known boundary count while preserving unknown values in storage. Cover fours-only and sixes-only contradictions at validator, source constraint, and publication levels.

### F3 — P2: Player publication does not validate figures against the match or batting status

**In everyday terms:** F2 concerns a contradiction within boundary counts. This finding is broader: the program accepts individual numbers without sufficiently checking whether they make sense for the player's status or the actual match.

“Did not bat” should not coexist with a positive batting score. A bowler's recorded legal deliveries should fit within the innings they bowled in. Similarly, if the official team total is 50, an individual score of 500 would require investigation rather than immediate publication. Those are consistency checks, not predictions about what a player's score should be.

This needs care because incomplete player data is intentionally allowed. Player runs need not add up exactly to the team total: extras may contribute, and some players' figures may still be unknown. The proposed fix is to reject combinations known to be impossible, while keeping valid partial entries possible.

Sources: [PlayerStatsValidator.php:40](../app/Services/Tournament/PlayerStatsValidator.php#L40), [PlayerPublicationService.php:142](../app/Services/Tournament/PlayerPublicationService.php#L142), especially its validator call at line 157.

The generic validator permits up to 2,147,483,647 deliveries and treats `did_not_bat` independently of batting figures. Publication reuses that validator without match-level consistency checks. A player's known runs/boundaries are not reconciled with known official team totals either.

**Evidence:** a player marked `did_not_bat`, with 1 run, 2 fours, and 999 bowling deliveries, was successfully published in a five-over, four-ball match. The legal innings allocation was only 20 deliveries. These values survive into the official source records and derived totals.

**Suggested resolution:** retain incomplete drafts, but reject known contradictions before publication: incompatible batting status, bowling deliveries beyond the relevant recorded innings/allocation, and known player lower bounds exceeding official team figures. Check the effective published batch, including retained published rows. Preserve valid unknowns and account for the sport's extras/exception rules rather than requiring every player sum to equal a team total. Add an equivalent check or explicit organizer review when correcting an already-published team result.

### F4 — P2: Scorer standings break sporting ties by display order

**In everyday terms:** two teams with the same ranking criteria should share a position. The scorer screen instead numbers the first displayed row 1, the next 2, and so on. It confuses “second row on screen” with “second place in the competition.”

For example, if A and B have equal points and equal NRR, they should both show position 1 under the current ranking rule. The scorer screen shows A as 1 and B as 2, even though the public table and qualification guidance recognize the tie. This does not automatically advance the wrong team, because qualification is manual, but it can mislead the person making that decision.

Sources: [standings-manager.blade.php:121](../resources/views/livewire/scorer/standings-manager.blade.php#L121), [ScorerStandingsService.php:29](../app/Services/Tournament/ScorerStandingsService.php#L29).

The scorer table prints `$loop->iteration` as position. Its service sorts equal points/NRR by team ID but does not calculate sporting ranks. Public standings and qualification guidance do calculate tied ranks.

**Evidence:** two teams with equal points and unknown NRR showed public ranks `[1,1]` and scorer ranks `[1,2]`. The scorer screen explicitly says equal records remain tied. This can mislead a scorer choosing qualifiers, especially when all results are abandoned or teams finish with equal records.

**Suggested resolution:** share the ranking rule used by qualification/public standings and render its calculated rank. Test equal known NRR, equal null NRR, and tied runner-up positions across all three screens.

## Test configuration and infrastructure findings

### T1 — P2: Committed suites inherit the local tournament feature flag

**In everyday terms:** tests should start from a predictable setup on every developer's machine. These tests can start differently depending on whether that developer enabled the tournament in `.env`.

A **route** is the application's definition of a URL, such as `/scorer/dashboard`. At startup, the program reads the feature switch and decides whether to define those URLs. A test that expects “the tournament URLs were never defined” can fail if startup already defined them, even if the test turns the feature off afterward. Turning access off and removing an already-defined URL are different operations.

This explains why the same branch can pass in CI, which may have the switch absent, and fail locally, where the switch is enabled. The local `phpunit.xml` edit sets a predictable disabled starting state. Individual tests can then explicitly enable the feature when needed. The PostgreSQL test configuration needs equivalent treatment. This is a test reliability issue, not evidence that a public visitor gained scorer access.

Sources: [phpunit.xml](../phpunit.xml), [phpunit.postgresql.xml](../phpunit.postgresql.xml), [DashboardDestinationTest.php:82](../tests/Feature/DashboardDestinationTest.php#L82), [routes/tournament.php:7](../routes/tournament.php#L7).

Both committed XML configurations omit a deterministic tournament flag. With `TOURNAMENT_ENABLED=true` in the local environment, Laravel registers tournament routes during application boot. Tests that later set the flag false cannot undo that registration, while some tests assert the routes were never registered.

**Evidence:** using a temporary copy of HEAD's committed XML against the existing local environment, `DashboardDestinationTest` ran 8 tests with 1 failure at line 85 (`Route::has('scorer.dashboard')` was true). The existing uncommitted change in `phpunit.xml` pins the flag false and avoids that failure; the PostgreSQL XML does not have that change. The full PostgreSQL run also failed `PublicTournamentHubTest::test_disabled_feature_has_no_public_hub` at inherited feature-test line 26 because the route already existed. Rerunning the PostgreSQL hub class with a process-only disabled startup flag passed all 12 tests and 45 assertions, confirming the configuration cause without changing `.env` or application code.

**Suggested resolution:** pin the disabled startup flag in both suites while allowing individual tests to enable it through configuration. Check test startup with the external flag both true and false. Include this local fix in the eventual branch changes after review.

### T2 — P2: CI's PostgreSQL deadline conflicts with the runner's documented runtime

**In everyday terms:** there are two timers. GitHub's automated job says “stop everything after 15 minutes,” while the script inside it allows its tests up to 30 minutes. The outer timer wins. Downloading dependencies and starting the disposable database also use the outer job's time budget.

**CI** means continuous integration: the automated checks run when code is pushed or a pull request is opened. A timeout can make correct code look broken and prevent a complete test result. The memory problem below is similar: running out of the test program's allowed memory does not itself demonstrate that a production web request runs out of memory. Test processes run many scenarios in succession and have their own resource needs.

Sources: [.github/workflows/tests.yml:60](../.github/workflows/tests.yml#L60), [scripts/test-postgresql.php:112](../scripts/test-postgresql.php#L112).

The job has a 15-minute total deadline, including dependency installation and image startup. The runner explicitly records that a verified full run exceeded 15 minutes and permits its test child 30 minutes. CI can terminate a valid run before the runner's own deadline. Actual timing depends on the runner; this is a reliability risk, not a demonstrated application defect.

The normal SQLite command also exhausted this machine's default 128 MB CLI limit partway through the suite. With 512 MB it passed, using 196 MB. Make the supported test resource budget explicit.

## Existing dependency security risks carried into deployment

### What a dependency finding means

The website uses third-party code. Laravel is its main PHP framework, Livewire handles interactive forms, and Symfony supplies lower-level components such as HTTP request handling. Composer manages PHP packages; npm manages JavaScript/build packages. Their **lockfiles** record the exact package versions that will be installed.

A security **advisory** says that a library version has a reported weakness. A **CVE** is an identifier used to track a disclosed vulnerability. Finding an affected version is a reason to investigate and update it; it is not proof that this website has been hacked or that every reported attack is possible through these pages.

These findings matter to this release because deploying the feature also deploys the project's dependencies. However, these package versions already exist on `main`, so they should not be attributed to the scorer feature's author as new changes.

### What the main security terms mean here

| Term | Meaning and possible effect |
| --- | --- |
| XSS, or cross-site scripting | An attacker gets unwanted JavaScript to run in a visitor's browser under the website's origin. Depending on the weakness, it can interact with the page as that visitor. The Livewire advisory requires user interaction; a scorer-specific exploit was not demonstrated in this review. |
| Denial of service | An attacker makes the service slow or unavailable. The Symfony advisory concerns excessive processing of specially formed request headers, potentially consuming server CPU before login checks. |
| CRLF injection | Unwanted line-break characters can interfere with the structure of an email or protocol message. The mail-related advisory needs an applicability review against the application's actual email handling. |
| Authentication bypass | A weakness lets a caller get past a login/authentication check without the intended credentials. The critical Laravel advisory concerns a different API authentication mechanism from the scorer flow inspected here. |
| Transitive dependency | A package installed because another package needs it. `follow-redirects` is an example; the project does not have to call it directly for it to appear in an audit. |

`composer.lock` and `package-lock.json` are unchanged against `main`. These are **existing release risks, not newly introduced feature regressions**. The review ran advisory audits without installing/updating dependencies, executing Composer plugins, or running package scripts.

Composer's production dependency audit returned **52 advisory entries across 15 packages**: 1 critical, 14 high, 26 medium, 5 low, and 6 with no supplied severity. Advisory entries can overlap; this is not a count of 52 proven exploitable application flaws. Full details are in [tournament-composer-audit.json](tournament-composer-audit.json).

| Locked component | Relevant advisory and applicability |
| --- | --- |
| Livewire 3.6.4 | [CVE-2026-81887, maintainer advisory](https://github.com/livewire/livewire/security/advisories/GHSA-g3hc-697w-wm82): client-side XSS affecting Livewire 3 through 3.8.2; fixed in 3.8.3. It requires user interaction and does not itself bypass server authorization. This feature uses Livewire extensively. A feature-specific exploit was not attempted. |
| Symfony HTTP Foundation 7.3.4 | [CVE-2026-108082, maintainer advisory](https://symfony.com/blog/cve-2026-108082-redos-in-headerutils-split-reachable-through-the-cache-control-request-header): request-header parsing can consume excessive CPU before authentication. The maintainer describes the vulnerable parser as reachable when constructing requests. The locked version is in the affected range. |
| Laravel 12.32.5 plus mail components | [CVE-2026-48019, maintainer advisory](https://github.com/laravel/framework/security/advisories/GHSA-5vg9-5847-vvmq): default email validation and mail handling can permit CRLF-based interference under particular conditions. Email-code login and registration make applicability worth investigating; exploitation depends on mail handling and was not demonstrated. |
| Laravel 12.32.5 | [CVE-2026-114509, maintainer advisory](https://github.com/laravel/framework/security/advisories/GHSA-f9x3-7vq9-cph6): the critical audit entry concerns a token API guard with particular database configurations. This repository's scorer routes use custom JWT middleware, and `config/auth.php` defines a session guard. The described critical authentication bypass was not found in this scorer flow. |

The npm production dependency audit returned **1 high advisory** for transitive `follow-redirects`; see [tournament-npm-audit.json](tournament-npm-audit.json). Applicability to deployed assets is unproven: the reported behavior concerns server-side redirect handling, while browser Axios normally uses a browser transport. Review build/server uses rather than treating the severity alone as proof of a browser exploit.

Update compatible dependencies and repeat both advisory audits and regression suites. Do not assume the minimum version fixing one advisory clears every advisory on that package.

## Public and scorer limitations to decide before release

These are mostly design/operational constraints rather than additional reproduced defects. Some may be acceptable for this event. The decision should be explicit so organizers do not expect behavior the feature does not provide.

| Limitation | Match-day consequence / decision needed |
| --- | --- |
| Public fixture cards omit schedule, venue, and match format. | These fields are returned by the service but unused by the public cards. Spectators cannot find when/where an upcoming match starts. Identical over notation can represent different delivery counts in this mixed four/six-ball format. Existing UI tests explicitly assert these details are absent, so this is a deliberate product limitation. Decide whether this is acceptable before relying on the hub as the fixture source. |
| Manual refresh and separate team/player publication. | Public pages do not poll. Publishing a result does not publish pending player figures; standings and rankings can legitimately have different freshness. Train scorers on both publication steps. The current UI also removes public publication clocks. |
| Qualification is manual and survives group-result correction. | A corrected table can disagree with saved qualifiers. Existing tests intentionally preserve the selected flags and existing knockout fixtures. Organizers must review qualification after corrections; see F1 for recovery constraints. |
| No complete revision history for results/player replacements. | Actor/version/current publication metadata is stored, but previous result values and replaced player figures are overwritten. Point adjustments have history; result/player corrections do not have equivalent immutable history. This limits dispute investigation and recovery after accidental or abusive scorer edits. |
| Account revocation has no corresponding regrant operation. | Creating the same email again is refused. Accidentally revoked scorers need an administrative recovery process or a different account. |
| PostgreSQL is the supported production target. | Tournament writes/public snapshots explicitly support PostgreSQL and development SQLite; raw migration SQL is not MySQL-compatible. The feature flag does not skip migrations. The README's general MySQL option should not be read as tournament support. Match the deployment PostgreSQL version and rehearse migration with representative existing data. |
| Feature routes are registered conditionally at boot/cache build time. | Enabling the environment flag against a route cache built while disabled leaves routes absent. Rebuild configuration and routes in the intended enabled state and verify both public and scorer URLs. |
| Scorer operations serialize by tournament year; some reads also use that lock. | Player-entry reads, qualification selection, and knockout options can wait behind writes. The common lock has a three-second wait budget. Public reads use separate snapshots, but there is no concurrent HTTP capacity demonstration. |
| Logo fallback differs between views. | The bracket loads uploaded company logos, while most badge calls pass only the team name. An unmapped team's logo can appear in the bracket and become initials in Matches/Table/Stats or scorer pages. Cosmetic, lower priority. |

### Examples of how these limitations affect organizers

- **Schedule visibility:** a spectator opens Upcoming and sees A versus B, but cannot find “8:30 AM, Ground 2” on that card even if the scorer entered it. If another channel is the official schedule, that may be acceptable; if this page is supposed to be the schedule, it is incomplete.
- **Saved qualifiers after a correction:** A is marked qualified, then a corrected group result puts B ahead. The system does not switch the `Q` marker or rebuild the bracket automatically. An organizer must review the saved selection; existing knockout fixtures can constrain changes as described in F1.
- **Limited audit history:** if someone replaces 60 with 80 and later another scorer questions it, a version number proves there was an update but does not by itself reconstruct the old score. The proposed improvement is a record of what changed, who changed it, when, and why. This is different from the temporary draft kept before publication.
- **Feature switch and route cache:** deployment may save a precomputed list of URLs for speed. That is the **route cache**. If the list was generated while the tournament was disabled, merely changing the environment flag does not add the missing URLs to the saved list. Deployment must rebuild that list in the intended state.
- **Database choice:** SQLite is the lightweight database used for local checks. PostgreSQL is the database exercised for production-style transactions and concurrency. The tournament includes PostgreSQL/SQLite-specific behavior, so a MySQL deployment cannot be assumed to work just because the older website README mentions MySQL.
- **Several scorers working at once:** the year-level lock makes conflicting updates wait rather than run over each other. That protects correctness, but a person may see “another update is in progress” and need to reload/retry. The review checked individual database races; it did not demonstrate how the deployed site behaves under a crowd of visitors and many active scorers together.

## Are the existing tests sufficient?

### What the reported numbers actually tell us

A **test** is a programmed scenario. An **assertion** is one expectation inside that scenario, such as “saving a draft did not change the public score.” One test may contain several assertions. Neither number measures the percentage of real-world behavior that is correct.

A green result means the implementation met the expectations written into those tests. It cannot catch a missing expectation. F2 is a useful example: a test explicitly says that 1 run with 2 fours should be accepted, so passing it confirms an incorrect rule rather than detecting it.

The review also used temporary **reproduction checks**. Those deliberately asserted that the problematic behavior exists. Their passing result means “the issue was reproduced,” not “the issue was fixed.” They were removed after the review; the proposed permanent regression tests still need to be added with the desired corrected behavior.

Testing against both databases serves different purposes. SQLite in-memory tests are fast and disposable. PostgreSQL tests exercise the actual database engine's constraints, locks, and transaction behavior. A disposable PostgreSQL container is a temporary local server created for the tests and removed afterward; it is not the application's existing database.

### What is already covered and what remains

**No, not for release approval yet.** They provide strong coverage of access control, CSRF, replayed Livewire snapshots, stale versions, publication hashes, transactional rollback/retry, NRR calculations, player total rebuilding, public data allowlists, restrictive history references, additive migrations, and PostgreSQL write contention. The PostgreSQL suite reuses many feature scenarios and adds independent-connection race checks; its count should not be treated as entirely different business scenarios.

In plain language, these tests already check many important protections: an unauthorized caller cannot publish, old edits are rejected, failed publication should not leave half-updated totals, private draft fields stay private, and competing database updates preserve consistency. That is useful coverage. It does not address all of the missing recovery and sports-rule cases above.

The main missing or incorrect checks are:

1. A complete recovery journey after an incorrect unused fixture or an upstream winner correction with a dependent fixture.
2. Partial-known boundary contradictions, DNB inconsistencies, per-match delivery bounds, and effective published player batches checked against known team figures. Replace the test that expects 1 run with 2 fours to succeed.
3. Matching tied ranks in scorer standings, qualification, and public standings.
4. Deterministic boot-time feature flags in both database suites.
5. Real-browser journeys for Alpine dialogs and tabs, keyboard/focus behavior, mobile entry, pending input reaching review, and close/reopen/publish actions. Volt and HTTP tests do not execute the browser's Alpine/DOM behavior.
6. Concurrent public HTTP traffic alongside several active scorers against deployment-like PHP workers, database connections, and cache infrastructure. `TournamentScaleReadTest` explicitly measures sequential local requests, not concurrent network load.
7. Dependency advisory checks and a production frontend build in CI. The checked-in workflows do not currently run these checks.

The browser gap deserves particular attention because the scorer screens use popups and tabs. A server-side test can prove that calling `publishResult()` works without proving that a person on a phone can open the popup, see the validation error, keep their input, close/reopen the dialog, and reach Publish using the actual interface. Similarly, 100 requests made one after another do not prove that 100 visitors making requests at once will have acceptable response times.

## Suggested order of work before proceeding

This is a proposed work sequence, not a list of changes already made:

1. **Agree on recovery behavior and fix F1.** Decide what a scorer may correct before play, what may be removed before publication, and who handles corrections after dependent results are published. This is the main missing operational workflow.
2. **Fix F2 and F3, with regression tests.** Keep incomplete score sheets usable, but block contradictions before they become official and affect public awards. Confirm the exact cricket rules with the organizers where exceptions matter.
3. **Fix F4 and test all ranking screens together.** A tie should mean the same thing to the scorer and the public.
4. **Make tests reproducible.** Address both database suites' startup flags, document the memory budget, and align CI's deadline with the intended suite runtime. Then rerun the full suites on the resulting code.
5. **Update and triage dependencies.** Have the developer review applicable advisories and install compatible patched versions, then rerun the audits and tests. Updating packages can change behavior, so this should be verified as a change of its own.
6. **Make the product decisions in the limitations section.** In particular, confirm whether public time/venue/format, freshness timestamps, correction history, and scorer reactivation are required for this event.
7. **Rehearse deployment and a match day.** On a staging environment that resembles production, set up teams, enter a match, publish team and player data separately, correct an error, handle a simultaneous scorer edit, and progress into knockouts. Include actual browser/mobile use and realistic simultaneous traffic.

For someone taking over the project, the most useful first conversation with the implementer is: “Show me how a scorer recovers after selecting the wrong fixture format or creating a semi-final with the wrong quarter-final winner.” The current feature can demonstrate preventing inconsistent changes; the review found that it cannot complete the corresponding recovery through the scorer workflow.

## Executed verification

| Check | Result |
| --- | --- |
| Full SQLite suite, existing working-tree configuration, PHP 8.4.26, 512 MB | **PASS: 1,142 tests, 6,686 assertions**, 3m39s, 196 MB. |
| Same suite at default 128 MB | Incomplete: memory exhausted after more than 488 tests; no test assertion failure was reported before the fatal error. |
| HEAD's committed XML, `DashboardDestinationTest`, existing enabled local flag | **FAIL: 8 tests, 127 assertions, 1 failure**, confirming T1. |
| Temporary synthetic reproduction checks | **PASS: 3 checks, 10 assertions**, confirming the undesirable behavior in F1–F4 and omitted public schedule details. These checks asserted current behavior, not corrected behavior. |
| Disposable PostgreSQL 17 suite, committed XML, existing enabled local flag | **744 tests, 5,798 assertions, 1 failure**: `PublicTournamentHubTest::test_disabled_feature_has_no_public_hub`, confirming T1. Other tests completed without reported failures/errors. Test child time 10m44s, 158 MB; initial image setup added time. Container cleaned up. |
| PostgreSQL hub class with process-only `TOURNAMENT_ENABLED=false` | **PASS: 12 tests, 45 assertions**, 11s. Confirms the full-run failure's startup-flag cause. The entire 744-test suite was not rerun under this override. Container cleaned up. |
| `npm.cmd run build` | **PASS**. Nonfatal dependency CSS `@property` warning and existing large-chunk warning. |
| `git diff --check main...HEAD` | Three trailing blank-line warnings in scorer Blade files; cosmetic. |
| Production Composer and npm advisory audits | Completed; findings above. No dependency updates applied. |

No live production environment, real email transport, deployed reverse proxy, or browser rendering session was exercised. No security exploit was attempted against a live service. No claim of complete vulnerability absence is made.
