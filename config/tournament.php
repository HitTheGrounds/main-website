<?php

return [
    // Enable explicitly with TOURNAMENT_ENABLED=true when the feature is ready.
    // This switch does not replace authentication or scorer/admin authorization.
    'enabled' => env('TOURNAMENT_ENABLED', false) === true,

    // Keep the season explicit so it does not change automatically on New Year's Day.
    'year' => (int) env('TOURNAMENT_YEAR', 2026),

    'timezone' => 'Asia/Colombo',

    // Scorer venue dropdown and public venue filter.
    'venues' => ['Ground 1', 'Ground 2', 'Ground 3', 'Ground 4'],

    // Shared by scorer mutation actions, in addition to the existing network limit.
    'writes_per_minute' => 30,

    // Creation defaults only; the scorer selects four or six for each match.
    'default_balls_per_over' => [
        'group' => 4,
        'quarter_final' => 4,
        'semi_final' => 6,
        'final' => 6,
    ],

    'allowed_balls_per_over' => [4, 6],
    'overs_per_innings' => 5,
    'max_wickets' => 7,

    'points' => [
        'win' => 2,
        'tie' => 1,
        'no_result' => 1, // Includes official abandonment.
        'loss' => 0,
    ],

    // Group/team counts come from assignments. Advancement and matchups are manual.
    // These counts define the available bracket positions in each knockout round.
    'knockout_slots' => [
        'QF' => 4,
        'SF' => 2,
        'F' => 1,
    ],
];
