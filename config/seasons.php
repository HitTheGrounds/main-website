<?php

// Past-season results, shared by the Past Seasons page and the mobile home page.
return [
    // [logo path, true if the logo only exists in white and needs a dark tile]
    'logos' => [
        'GTN' => ['/images/rankings/gtn.avif'],
        'Synopsys' => ['/images/rankings/synopsys.avif'],
        'Codegen' => ['/partner/codegen.avif'],
        'Virtusa' => ['/images/rankings/virtusa.avif'],
        'SimCentric Technologies' => ['/images/rankings/simcentric.avif'],
        'DirectFN' => ['/images/rankings/directfn.avif', true],
        '99X' => ['/images/rankings/99x.avif'],
        'WSO2' => ['/images/rankings/wso2.avif'],
    ],

    // year => [champion, runner-up], newest first.
    'results' => [
        2025 => ['GTN', 'Synopsys'],
        2024 => ['Synopsys', 'Codegen'],
        2019 => ['Virtusa', 'SimCentric Technologies'],
        2018 => ['DirectFN', '99X'],
        2015 => ['WSO2', 'Virtusa'],
    ],
];
