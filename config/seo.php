<?php

// Public pages that search engines should index. Everything else gets noindex and is left
// out of the sitemap and robots.txt allowance.
return [
    'indexable' => ['home', 'rules', 'timeline', 'awards', 'seasons', 'memories', 'registrations'],
];
