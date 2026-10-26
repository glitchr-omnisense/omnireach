---
title: Concepts
order: 20
---

# Concepts

## Providers and contracts

`Omnireach\ProviderInterface` is one outside tool, configured. What it can be asked comes from the
contracts it implements, one per capability:

| Interface | Method | Default metrics | Implemented by |
|---|---|---|---|
| `SearchPerformanceInterface` | `searchPerformance(ReachQuery $query): Report` | clicks, impressions, ctr, position | search-console, bing-webmaster |
| `AudienceInterface` | `audience(ReachQuery $query): Report` | visitors, visits, pageviews | matomo, plausible, ga4 |

Both extend `ProviderInterface`:

| Method | Returns |
|---|---|
| `getName(): string` | the factory's name: `search-console`, `matomo`... - also the `provider` of every report it returns |
| `getTitle(): string` | the tool's display name: `Google Search Console`, `Matomo` |
| `capabilities(): Capabilities` | what it reports, its terms and limits ([below](#capabilities)) |

"Default metrics" are the ones a report counts when the query names none
(`ReachQuery::metricsOr()`). Both report methods throw `NotSupportedException` for a dimension, a
filter or a metric the provider does not report, before any call; every other failure is one of
the exceptions of [Errors](errors.md).

## Capabilities

`Omnireach\Capabilities` (a readonly value) says what a provider is before it is asked anything:

| Property | Type | Default | |
|---|---|---|---|
| `searchPerformance` | `bool` | `false` | it implements `SearchPerformanceInterface` |
| `audience` | `bool` | `false` | it implements `AudienceInterface` |
| `dimensions` | `list<Dimension>` | `[]` | the dimensions it breaks a report down by (and filters on) |
| `metrics` | `list<Metric>` | `[]` | the metrics it counts |
| `combined` | `bool` | `true` | dimensions combine freely; `false`: one dimension at a time besides the date |
| `terms` | `list<string>` | `[]` | URLs of the terms whoever runs it must follow |
| `limits` | `list<string>` | `[]` | its rate limits, quotas and data freshness, in words |

| Method | |
|---|---|
| `contracts(): list<string>` | `['searchPerformance']`, `['audience']`, or both |
| `check(ReachQuery $query, list<Metric> $metrics, string $provider): void` | throws `NotSupportedException` when the query asks for a dimension or a filter outside `dimensions`, a metric outside `metrics`, or - with `combined` false - more than one dimension besides the date |
| `toArray(): array` | the same as plain values (enums as their strings), for a page that lists the providers |

Every provider starts its report with `check()`, so a refusal costs no call. The messages name what
is missing: `Matomo does not report the dimension "query".`, `Bing Webmaster reports one dimension
at a time besides the date, not query and page.`

## Factory and Config

`Omnireach\ProviderFactoryInterface` builds a provider from options:

| Method | |
|---|---|
| `getName(): string` | the name providers are configured with: `search-console`, `matomo`... |
| `create(array $options = []): ProviderInterface` | a provider, its options checked |

Every provider's factory extends `Omnireach\ProviderFactory`, which does the work:

1. `createConfig($options)` - a `Config` (an `ArrayObject`) holding the options given, then the
   factory's `populate()` fills each key the options did not set with its default;
2. the keys listed under `omnireach.required_options` are checked: one missing, `''` or `[]` is an
   `InvalidConfigException` listing every missing key (`The "bing-webmaster" provider needs:
   api_key, site.`);
3. the factory's `build()` returns the provider.

`getName()` is read from the `Config` (`omnireach.factory_name`), so it costs nothing. The reserved
keys a factory sets:

| Key | |
|---|---|
| `omnireach.factory_name` | the provider's name |
| `omnireach.factory_title` | the provider's title |
| `omnireach.required_options` | the option names that must be filled |

`Config` reads options with types:

| Method | |
|---|---|
| `defaults(array $defaults): self` | sets each key not set yet |
| `validateNotEmpty(list<string> $keys): void` | step 2 above |
| `get(string $key, mixed $default = null): mixed` | the value as it is |
| `string(string $key): ?string` | a scalar as a string; `null` when it is empty |
| `int(string $key, int $default = 0): int`, `float(string $key, float $default = 0.0): float` | a numeric value, else the default |
| `bool(string $key): bool` | `true`, or `"1"`, `"true"`, `"yes"`, `"on"` (an environment variable's spelling) |

Factories take their HTTP client in their constructor and call nothing when they build: a provider
reached over HTTP makes its first call when it is first asked for a report (Google's providers ask
for their token then).

## Registry

`Omnireach\Registry` holds the application's providers by name:

```php
$registry = new Registry(
    [new SearchConsoleFactory($http), new MatomoFactory($http)],     // iterable of factories, keyed by getName()
    [
        'google' => ['factory' => 'search-console', 'options' => ['site' => 'sc-domain:example.org', 'credentials' => '/secrets/sa.json']],
        'stats' => ['factory' => 'matomo', 'options' => ['url' => 'https://stats.example.org', 'site' => '1', 'token' => '...']],
    ],
);
```

| Method | |
|---|---|
| `get(string $name): ProviderInterface` | the provider, built on first use from its factory and options, then kept |
| `search(string $name): SearchPerformanceInterface` | the same, or `NotSupportedException` when it is not a search engine |
| `audience(string $name): AudienceInterface` | the same, or `NotSupportedException` when it is not an analytics tool |
| `has(string $name): bool` | whether a provider of that name is configured |
| `options(string $name): array` | the options it is configured with (`[]` when none) |
| `names(): list<string>` | the configured providers' names |
| `factories(): list<string>` | the factories installed: what `factory:` may name |

A name not configured, or configured with a factory not installed, is an `InvalidConfigException`
(`No "y" audience provider; configured: x.`, `No "matomo" factory for the "x" audience provider;
installed: none.`). The provider's name is the application's (`google`, `stats`); the factory's
name is the package's. Two providers may use the same factory with different options: two
Matomo sites, the Search Console properties of two domains. The query can also name another site
than the configured one (`ReachQuery::$site`).

## The query

`Omnireach\Model\ReachQuery` (readonly) is what a report is asked for:

```php
new ReachQuery(
    \DateTimeImmutable $start,
    \DateTimeImmutable $end,
    array $dimensions = [],    // list<Dimension>
    array $metrics = [],       // list<Metric>; none: the provider's defaults
    array $filters = [],       // array<string, string>, by Dimension value
    int $limit = 1000,
    ?string $site = null,      // another site than the configured one
);
```

| Property | Type | |
|---|---|---|
| `start`, `end` | `\DateTimeImmutable` | the period's first and last days, both included, set to midnight (in the timezone of the dates given) |
| `dimensions` | `list<Dimension>` | what the rows are broken down by, in that order; none: one row for the whole period |
| `metrics` | `list<Metric>` | what the rows count; none: the report's defaults |
| `filters` | `array<string, string>` | exact filters, keyed by a dimension's value: `['country' => 'FR', 'source' => 'google']`; all must hold |
| `limit` | `int` | at most this many rows, 1000 by default |
| `site` | `?string` | the site, property or domain to report on, when not the provider's `site` option |

The constructor throws `Omnireach\Exception\InvalidArgumentException` when the period ends before
it starts, when `limit` is below 1, or when a filter names no dimension (`No "city" dimension to
filter on.`).

| Method | |
|---|---|
| `static lastDays(int $days, array $dimensions = [], array $metrics = [], ?\DateTimeImmutable $today = null): self` | the last `$days` whole days (at least 1), yesterday the last: today's figures are complete nowhere. `$today` for a test |
| `has(Dimension $d): bool` | whether the query breaks down by it |
| `filter(Dimension $d): ?string` | the filter's value on it, if any |
| `metricsOr(list<Metric> $defaults): list<Metric>` | the query's metrics, or the defaults when it names none |
| `breakdown(): list<Dimension>` | the dimensions but the date: what `combined: false` counts |
| `apply(iterable<Row> $rows): list<Row>` | the filters and the limit put on rows ([below](#filters-and-limit)) |

### Filters and limit

Every provider passes the rows it read through `$query->apply()` before returning them, so a query
means the same everywhere, whether or not the tool filtered upstream. `apply()` keeps a row unless
it has a filtered dimension with another value - a row that does not carry the filtered dimension
(a report not broken down by it, where the tool applied the filter itself) is kept - and stops at
`limit` rows. The comparison is exact and case-sensitive, on the values in the family's units
(below): filter `country` with `FR`, `device` with `mobile`.

## Dimensions

`Omnireach\Model\Dimension`, a string-backed enum; the value is the key of `Row::$dimensions` and of
`ReachQuery::$filters`.

| Case | Value | Meaning | Reported by |
|---|---|---|---|
| `Query` | `query` | the search query typed | search engines |
| `Page` | `page` | a page: shown in search results (search engines, a full URL), or viewed (analytics, a path) | all |
| `EntryPage` | `entry_page` | the page a visit started on, the landing page | analytics |
| `Country` | `country` | the visitor's country: ISO 3166-1 alpha-2, upper case (`FR`); alpha-3 for Search Console (`FRA`) | search-console, matomo, plausible, ga4 |
| `Device` | `device` | `desktop`, `mobile`, `tablet` (lower case; Matomo also gives its other types) | search-console, matomo, plausible, ga4 |
| `Source` | `source` | where a visit came from: a search engine, a site, `direct`, as the tool names it | analytics |
| `Date` | `date` | the day, `Y-m-d`: a period's history | all (Bing: its own weekly periods) |

## Metrics

`Omnireach\Model\Metric`, a string-backed enum; the value is the key of `Row::$metrics`.

| Case | Value | Unit | Search | Adds up |
|---|---|---|---|---|
| `Clicks` | `clicks` | clicks from search results to the site | yes | yes |
| `Impressions` | `impressions` | times the site was shown in search results | yes | yes |
| `Ctr` | `ctr` | clicks per impression, a ratio from 0 to 1 | yes | no |
| `Position` | `position` | the average position in search results, 1 the first | yes | no |
| `Visitors` | `visitors` | distinct visitors (users) | no | no |
| `Visits` | `visits` | visits (sessions) | no | yes |
| `Pageviews` | `pageviews` | pages viewed | no | yes |
| `BounceRate` | `bounce_rate` | visits of one page only, a ratio from 0 to 1 | no | no |
| `VisitDuration` | `visit_duration` | the average length of a visit, in seconds | no | no |

`isSearch()` is the "Search" column, `isAdditive()` the "Adds up" one: a sum of averages, ratios
or distinct counts over rows means nothing, so `Report::totals()` leaves them out. Values are `int`
for counts and `float` for ratios, positions and durations (Plausible's visit duration as
Plausible gives it). Ratios the providers compute or convert are rounded to 4 decimals, positions
and durations to 1.

A metric the tool does not give for a row is left out of that row, never set to zero:
`$row->metric(Metric::Position)` is `null` then.

## Rows and reports

`Omnireach\Model\Row` (readonly) is one line of a report:

| Member | |
|---|---|
| `array<string, string> $dimensions` | by Dimension value: `['query' => 'atelier poterie', 'country' => 'FRA']` |
| `array<string, int\|float> $metrics` | by Metric value: `['clicks' => 42, 'position' => 3.6]` |
| `dimension(Dimension $d): ?string` | one dimension's value |
| `metric(Metric $m): int\|float\|null` | one metric's value |
| `toArray(): array` | `['dimensions' => ..., 'metrics' => ...]` |

`Omnireach\Model\Report` (readonly) is a provider's answer:

| Member | |
|---|---|
| `string $provider` | the provider's name: `search-console`, `matomo`... |
| `string $site` | the site, property or domain reported on (the query's, else the configured one) |
| `ReachQuery $query` | the query answered |
| `list<Row> $rows` | the rows, filtered and cut to the limit |
| `?string $note` | what the figures are not, when the provider knows: provisional days, Bing's weekly periods, Matomo's distinct visitors summed by day |
| `totals(): array<string, int\|float>` | the sums of the metrics that add up, over the rows |
| `toArray(): array` | provider, site, start and end (`Y-m-d`), the dimensions' values, the rows, the note: a plain array to cache or to send as JSON |

The order of the rows is each provider's ([Search performance](usage-search.md),
[Audience](usage-audience.md)): sort them in the application when it matters.

## The HTTP layer

Providers call their tool through `Omnireach\Http\JsonApi`, which the core gives them:
`new JsonApi(HttpClientInterface $http, string $provider)`, then
`request(string $method, string $url, array $options = [], string $what = 'the request'): Data`.
The options are the HTTP client's (`query`, `json`, `body`, `headers`, `auth_bearer`...). A
transport error becomes `UnreachableException`; a 401 or 403 `InvalidKeyException`; a 404
`NotFoundException`; any other status of 400 or more, or a 2xx body that is not JSON,
`ProviderException` - each with the tool's own message when the answer carries one, looked up
under `error_description`, `error.message`, `message`, `Message`, `detail`, `errors.0.message`,
`errors.0.detail`, `fault.faultstring`, then `error` (`JsonApi::message()`).

The answer is an `Omnireach\Support\Data`: the decoded JSON read with types at dotted paths
(`string('rows.0.keys.1')`, `int()`, `float()`, `bool()`, `date()`, `list()`, `data()`,
`items()`, `raw()`, `has()`), each giving `null` (or an empty list) when the answer holds something
else - so an answer of an unexpected shape gives empty rows rather than a `\TypeError`.
