<?php

/*
 * The omnireach/* providers the harness installs beside the core: their
 * tests' namespace, and what their tests need besides (require-dev).
 */
return [
    'bing-webmaster' => ['Omnireach\\BingWebmaster\\Tests\\'],
    'ga4' => ['Omnireach\\Ga4\\Tests\\'],
    'matomo' => ['Omnireach\\Matomo\\Tests\\'],
    'plausible' => ['Omnireach\\Plausible\\Tests\\'],
    'search-console' => ['Omnireach\\SearchConsole\\Tests\\'],
];
