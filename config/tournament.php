<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Tournament Rules & Scoring configuration
    |--------------------------------------------------------------------------
    */

    'overs_per_match' => 5,
    
    // Balls per over vary depending on the tournament stage
    'balls_per_over' => [
        'G' => 4,  // Group Stage
        'QF' => 4, // Quarter Finals
        'SF' => 4, // Semi Finals
        'F' => 6,  // Final
    ],

    'max_wickets' => 11,

    'points_win' => 2,
    'points_draw' => 1,
    'points_loss' => 0,

    'teams_qualify_per_group' => 2,
];
