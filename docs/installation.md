---
title: Installation
order: 10
---

# Installation

```sh
composer require omnisense/omnireach omnireach/matomo symfony/http-client   # one package per provider
```

## Requirements

- PHP 8.2 or later.
- `omnisense/omnireach` requires `symfony/http-client-contracts` (^3.0) and nothing else.
- Every provider requires the core and `symfony/http-client-contracts` only: the HTTP client
  itself is the application's. Any implementation of
  `Symfony\Contracts\HttpClient\HttpClientInterface` does - `symfony/http-client` (a library that
  stands alone and loads no framework) with `HttpClient::create()`, or a `MockHttpClient` in a
  test. A factory given no client refuses to build its provider (`InvalidConfigException`: `The
  "matomo" provider needs an HTTP client: give one to MatomoFactory.`); none makes a client of its
  own.
- `omnireach/search-console` and `omnireach/ga4` require `ext-openssl`: they sign the JWT a Google
  service account's token is asked with.

Omnireach runs the same in plain PHP, in a worker, in Laravel and in Symfony; there is no bundle
or Laravel provider yet ([Symfony](symfony.md), [Laravel](laravel.md)).

## Plain PHP

A whole script with no provider and no network: the core's `FixedReach` answers from rows it was
given, as a provider would have returned them. It shows the pieces every provider shares.

```php
<?php // bare.php

require __DIR__.'/vendor/autoload.php';

use Omnireach\Exception\NotSupportedException;
use Omnireach\Model\Dimension;
use Omnireach\Model\Metric;
use Omnireach\Model\ReachQuery;
use Omnireach\Model\Row;
use Omnireach\Registry;
use Omnireach\Testing\FixedReachFactory;

// Rows a provider would have returned: search queries by day, visits by entry page and source.
$search = [
    new Row(['query' => 'atelier poterie strasbourg', 'date' => '2026-10-20'], ['clicks' => 12, 'impressions' => 340, 'ctr' => 0.0353, 'position' => 4.2]),
    new Row(['query' => 'cours de poterie', 'date' => '2026-10-21'], ['clicks' => 3, 'impressions' => 410, 'ctr' => 0.0073, 'position' => 11.8]),
    new Row(['query' => 'poterie', 'date' => '2026-09-01'], ['clicks' => 1, 'impressions' => 90, 'ctr' => 0.0111, 'position' => 23.0]),
];
$audience = [
    new Row(['entry_page' => '/', 'source' => 'google'], ['visitors' => 210, 'visits' => 260, 'pageviews' => 700]),
    new Row(['entry_page' => '/ateliers', 'source' => 'google'], ['visitors' => 95, 'visits' => 110, 'pageviews' => 300]),
    new Row(['entry_page' => '/ateliers', 'source' => 'direct'], ['visitors' => 20, 'visits' => 25, 'pageviews' => 40]),
];

$registry = new Registry([new FixedReachFactory($search, $audience)], [
    'site' => ['factory' => 'fixed', 'options' => ['site' => 'https://atelier.example.org/']],
]);

$october = [new DateTimeImmutable('2026-10-01'), new DateTimeImmutable('2026-10-31')];

// Search queries in October (the September row is outside the period).
$report = $registry->search('site')->searchPerformance(new ReachQuery(...$october, dimensions: [Dimension::Query]));
foreach ($report->rows as $row) {
    printf("%-28s %3d clicks %4d impressions  position %4.1f\n", $row->dimension(Dimension::Query), $row->metric(Metric::Clicks), $row->metric(Metric::Impressions), $row->metric(Metric::Position));
}
echo 'totals: ', json_encode($report->totals()), "\n\n";

// Visits by entry page, from Google only.
$entries = $registry->audience('site')->audience(new ReachQuery(...$october, dimensions: [Dimension::EntryPage], metrics: [Metric::Visits], filters: ['source' => 'google']));
foreach ($entries->rows as $row) {
    printf("%-10s %d visits\n", $row->dimension(Dimension::EntryPage), $row->metric(Metric::Visits));
}

// The last 28 whole days, as seen on 2026-10-26: yesterday is the last.
$last = ReachQuery::lastDays(28, today: new DateTimeImmutable('2026-10-26'));
echo "\nlastDays(28): ", $last->start->format('Y-m-d'), ' to ', $last->end->format('Y-m-d'), "\n";

// What a provider's capabilities refuse, before any call.
$registry2 = new Registry([new Omnireach\Matomo\MatomoFactory(new Omnireach\Testing\RecordedHttpClient(static fn () => [500, '']))], [
    'stats' => ['factory' => 'matomo', 'options' => ['url' => 'https://stats.example.org', 'site' => '1']],
]);
try {
    $registry2->audience('stats')->audience(new ReachQuery(...$october, dimensions: [Dimension::Query]));
} catch (NotSupportedException $e) {
    echo $e->getMessage(), "\n";
}
try {
    $registry2->search('stats');
} catch (NotSupportedException $e) {
    echo $e->getMessage(), "\n";
}
```

```
$ php bare.php
atelier poterie strasbourg    12 clicks  340 impressions  position  4.2
cours de poterie               3 clicks  410 impressions  position 11.8
totals: {"clicks":15,"impressions":750}

/          260 visits
/ateliers  110 visits

lastDays(28): 2026-09-28 to 2026-10-25
Matomo does not report the dimension "query".
The "stats" provider (Omnireach\Matomo\MatomoProvider) does not report search performance.
```

(run on 2026-10-26 in the [harness](harness.md), PHP 8.4, with the harness's autoloader; the
Matomo part needs `omnireach/matomo` installed)

The pieces:

- a **factory** per provider package (`FixedReachFactory`, `MatomoFactory`,
  `SearchConsoleFactory`...), given the HTTP client to call with;
- the **registry**, built from the factories and the providers' options, by name: `get()`, and the
  typed `search()` and `audience()`, which refuse a provider that does not answer that contract;
- the **query**: whole days, both included, dimensions, metrics (none: the provider's defaults),
  exact filters, a limit; `ReachQuery::lastDays()` ends yesterday, since no tool has today's
  figures complete;
- the **report**: rows of dimensions and metrics in the family's units, `totals()` of the metrics
  that add up (clicks, impressions, visits, pageviews - not the averages, not distinct visitors).

The query asking Matomo for search queries was refused by its capabilities: no HTTP call was made
(the client given would have answered 500).

## A provider's API

Matomo publishes a demo whose reports anyone may read, so this one runs without an account:

```php
use Omnireach\Matomo\MatomoFactory;
use Omnireach\Model\Dimension;
use Omnireach\Model\Metric;
use Omnireach\Model\ReachQuery;
use Omnireach\Registry;
use Symfony\Component\HttpClient\HttpClient;

$registry = new Registry([new MatomoFactory(HttpClient::create(['timeout' => 30]))], [
    // Matomo's public demo: site 1, reports anyone may view (token "anonymous", the default).
    'demo' => ['factory' => 'matomo', 'options' => ['url' => 'https://demo.matomo.cloud', 'site' => '1']],
]);
$matomo = $registry->audience('demo');

$week = ReachQuery::lastDays(7);
echo $week->start->format('Y-m-d'), ' to ', $week->end->format('Y-m-d'), "\n";

$site = $matomo->audience(new ReachQuery($week->start, $week->end, metrics: [Metric::Visits, Metric::Pageviews, Metric::BounceRate, Metric::VisitDuration]));
echo json_encode($site->rows[0]->metrics), "\n\n";

$entries = $matomo->audience(new ReachQuery($week->start, $week->end, [Dimension::EntryPage], [Metric::Visits, Metric::BounceRate], limit: 5));
foreach ($entries->rows as $row) {
    printf("%-40s %6d visits  %4.1f%% bounce\n", $row->dimension(Dimension::EntryPage), $row->metric(Metric::Visits), 100 * $row->metric(Metric::BounceRate));
}
```

```
$ php matomo-demo.php
2026-10-19 to 2026-10-25
{"visits":98616,"pageviews":192623,"bounce_rate":0.6655,"visit_duration":180.2}

/cart/                                     2669 visits  49.1% bounce
/jobs                                      5688 visits  52.2% bounce
/                                          3342 visits  47.4% bounce
/checkout/                                 1300 visits  61.4% bounce
/diving/bali                               3813 visits  73.9% bounce
```

(run live on 2026-10-26, 14:49 UTC, in the harness; two calls to demo.matomo.cloud)

The rows come in the order Matomo gives them, not sorted by visits; sort them in the application
when the order matters ([Audience](usage-audience.md)). The other providers need an account and a
key: their manuals show the same calls with their options. Building a provider calls nothing; a
missing required option is reported by `create()` as an `InvalidConfigException` naming it
(`The "plausible" provider needs: api_key.`).

## In a framework

- **Symfony**: no bundle yet; the registry is a service of the application's
  ([Symfony](symfony.md)).
- **Laravel**: no provider in omnibridge/laravel yet; a service provider of the application's
  builds the registry once ([Laravel](laravel.md)).
- **Any other**: build the `Registry` once, where the framework builds its services, as the
  scripts above do.
