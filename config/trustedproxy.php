<?php

return [
    // Comma-separated proxy IP addresses or CIDR ranges; empty trusts none.
    'proxies' => array_values(array_filter(array_map(
        'trim',
        explode(',', env('TRUSTED_PROXIES', '')),
    ))),
];
