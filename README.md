# omniscient/omnireach

What outside tools know of a site's audience, under any PHP host. The search engines say which
queries the site shows for, on which pages, at which position and with how many clicks; the
analytics tools say who visits, from where, landing on which page. One query (`ReachQuery`: a
period, dimensions, metrics, exact filters) and one report (`Report` of `Row`s) whoever answers -
the history of a taken-over site over any period included. Plain PHP: no framework in `require`,
the application's HTTP client injected (Symfony's HTTP client contracts).

```php
use Omnireach\Matomo\MatomoFactory;
use Omnireach\Model\{Dimension, Metric, ReachQuery};
use Omnireach\Registry;
use Omnireach\SearchConsole\SearchConsoleFactory;
use Symfony\Component\HttpClient\HttpClient;

$http = HttpClient::create();
$registry = new Registry([new SearchConsoleFactory($http), new MatomoFactory($http)], [
    'google' => ['factory' => 'search-console', 'options' => ['site' => 'sc-domain:example.org', 'credentials' => '/secrets/service-account.json']],
    'stats' => ['factory' => 'matomo', 'options' => ['url' => 'https://stats.example.org', 'site' => '1', 'token' => '...']],
]);

// The queries of the last 28 days, and the positions of one page.
$queries = $registry->search('google')->searchPerformance(ReachQuery::lastDays(28, [Dimension::Query]));
$page = $registry->search('google')->searchPerformance(new ReachQuery($from, $to, [Dimension::Query], filters: ['page' => 'https://example.org/old-page']));

// Where visits start, and a day-by-day history.
$entries = $registry->audience('stats')->audience(ReachQuery::lastDays(28, [Dimension::EntryPage], [Metric::Visits, Metric::BounceRate]));
$history = $registry->audience('stats')->audience(new ReachQuery($from, $to, [Dimension::Date], [Metric::Visits]));
```

## The shape

- **Models** (`Omnireach\Model`): `ReachQuery` (whole days, dimensions, metrics, exact filters,
  a limit, the site when not the configured one; `lastDays()` ends yesterday), the enums
  `Dimension` (query, page, entry page, country, device, source, date) and `Metric` (clicks,
  impressions, CTR, position; visitors, visits, pageviews, bounce rate, visit duration), `Row`,
  `Report` (rows, totals of the metrics that add up, a note on what the figures are not).
  Units are the same everywhere: ratios from 0 to 1, seconds, dates as Y-m-d, devices
  desktop/mobile/tablet, countries upper case.
- **Contracts by capability**: `SearchPerformanceInterface` (search engines),
  `AudienceInterface` (analytics). `Capabilities` lists each provider's dimensions and metrics,
  whether dimensions combine, its terms and limits, and refuses a query it cannot answer
  (`NotSupportedException`) before any call. `ReachQuery::apply()` puts the filters and the
  limit on what comes back, so a query means the same everywhere. `Registry`, `Config`,
  `ProviderFactory` as in the other omni families.
- **HTTP**: `Http\JsonApi` (failures as the family's exceptions), `Support\Data`.
- **Testing**: `Testing\FixedReach` (both reports from given rows), `Testing\RecordedHttpClient`,
  `Testing\LoadCheck`.
- **Providers** are packages:

| Package | Contract | Dimensions | Auth |
|---|---|---|---|
| [omnireach/search-console](https://github.com/glitchr-studio/omnireach-search-console) | search | query, page, country, device, date (combined) | service account or token |
| [omnireach/bing-webmaster](https://github.com/glitchr-studio/omnireach-bing-webmaster) | search | query or page, date (Bing's weekly rows) | API key |
| [omnireach/matomo](https://github.com/glitchr-studio/omnireach-matomo) | audience | entry page, page, source, country or device, date | token (or anonymous) |
| [omnireach/plausible](https://github.com/glitchr-studio/omnireach-plausible) | audience | page, entry page, source, country, device, date (combined) | API key |
| [omnireach/ga4](https://github.com/glitchr-studio/omnireach-ga4) | audience | page, landing page, source, country, device, date (combined) | service account or token |

- **Hosts**: no bridge yet. omnibridge/symfony and omnibridge/laravel wire the family when the
  first site needs it.

What it is not: the site's own visit counter (`Base\Service\Analytics` counts visits; omnireach
does not duplicate it), a tracker (no script is added to a page), a rank checker scraping search
results.

## Documentation

- [Installation, in plain PHP](docs/installation.md)
- [Writing a provider](docs/providers.md)
- [The harness](docs/harness.md)

## Verified, and not

- Verified (`docker compose run --rm omnireach test` in `docker/`, PHP 8.4, 2026-10-24): the
  query (whole days, validation, filters then limit), capabilities refusing what a provider
  cannot report, reports and totals, the registry, `JsonApi`'s errors; in plain PHP, reports asked
  through a Registry and a recorded JSON API loading nothing but omnireach and Symfony's HTTP
  client contracts (`BareTest`); PHPStan at the highest level, no error baselined, on the core and
  the five providers.
- Verified live (`docker compose run --rm omnireach live`, 2026-10-24): Matomo's public demo
  (demo.matomo.cloud, anonymous) - the whole site over a week and its entry pages day by day.
- Not verified live: Search Console, Bing Webmaster, Plausible and Google Analytics (each needs an
  account and a key). Their tests answer in the shapes their references document; each
  provider's README says which.

## Responsibility

omniscient/omnireach and its omnireach/* providers are unofficial clients, affiliated with none of
the services they reach. The MIT licence covers the code only and grants no right to any service
or its data. Whoever runs them complies with each service's terms (named in each provider's README
and in `Capabilities::$terms`) and obtains what they require.

## Licence

MIT.
