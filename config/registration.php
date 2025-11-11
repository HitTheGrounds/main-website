<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Company Registration Enabled
    |--------------------------------------------------------------------------
    |
    | This option controls whether new company registrations are allowed.
    | When set to false, users will not be able to register new companies.
    |
    */

    'enabled' => env('REGISTRATION_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Team Registration Enabled
    |--------------------------------------------------------------------------
    |
    | This option controls whether existing companies can create new teams.
    | When set to false, companies will not be able to add new teams.
    | This is useful for closing team additions while keeping companies active.
    |
    */

    'team_enabled' => env('TEAM_REGISTRATION_ENABLED', true),

];
