<?php

/*
| Team badges shown on the public tournament match cards.
|
| The registered teams are fixed for the season, so badges are hardcoded here
| instead of being uploaded. To add a team:
|   1. Put its logo in public/images/teams/ (see the existing files).
|   2. Add the team's registered name (exactly as stored in teams.team_name,
|      case and extra spaces do not matter) to "teams", pointing at a badge key.
| A team that is not listed gets an initials badge, so a missing or renamed
| entry never breaks the page.
*/

return [

    // Badge library. "logo" is a file in public/images/teams/. "cse" draws the
    // CSE batch lockup used on the homepage ("CSE | 21") instead of an image.
    'badges' => [
        '99x' => ['label' => '99x', 'logo' => '99x.webp'],
        'aayu' => ['label' => 'Aayu Technologies', 'logo' => 'aayu.svg'],
        'axonect' => ['label' => 'Axonect', 'logo' => 'axonect.webp'],
        'choreo-labs' => ['label' => 'Choreo Labs', 'logo' => 'choreo-labs-stacked-navy.svg'],
        'creative-software' => ['label' => 'Creative Software', 'logo' => 'creative-software.webp'],
        'directfn' => ['label' => 'DirectFN', 'logo' => 'directfn.webp'],
        'enlear' => ['label' => 'Enlear', 'logo' => 'enlear.svg'],
        'gtn' => ['label' => 'GTN', 'logo' => 'gtn.webp'],
        'ironone' => ['label' => 'IronOne Technologies', 'logo' => 'ironone.webp'],
        'pickme' => ['label' => 'PickMe', 'logo' => 'pickme.webp'],
        'senzmate' => ['label' => 'SenzMate', 'logo' => 'senzmate.webp'],
        'short-circuit' => ['label' => 'Short Circuit', 'logo' => 'short-circuit.webp'],
        'synopsys' => ['label' => 'Synopsys', 'logo' => 'synopsys.webp'],
        'thomson-reuters' => ['label' => 'Thomson Reuters', 'logo' => 'thomson-reuters.webp'],
        'xitricon' => ['label' => 'Xitricon', 'logo' => 'xitricon.webp'],
        'yaala' => ['label' => 'Yaala Labs', 'logo' => 'yaala.webp'],
        'cse-18' => ['label' => 'CSE 18', 'cse' => '18'],
        'cse-21' => ['label' => 'CSE 21', 'cse' => '21'],
        'cse-22' => ['label' => 'CSE 22', 'cse' => '22'],
        'cse-23' => ['label' => 'CSE 23', 'cse' => '23'],
        'cse-24' => ['label' => 'CSE 24', 'cse' => '24'],
        'cse-25' => ['label' => 'CSE 25', 'cse' => '25'],
    ],

    // Registered team name => badge key. Two teams from one company share a key.
    // TODO: fill in the 28 registered team names before the tournament goes live.
    'teams' => [
        // 'Example Team Name' => '99x',
    ],

    // Local development only: the demo seeder's "DEMO 2026 A1" style teams get
    // badges from the library in turn, so the cards can be reviewed with logos.
    // Never used outside the local environment.
    'demo_badges' => true,

];
