# omnisense/omnireach

Omnireach is one PHP contract for what outside tools know of a site's audience. The search engines
say which queries the site shows for, on which pages, at which position and with how many clicks;
the analytics tools say who visits, from where, landing on which page. An application asks every
tool the same question - a `ReachQuery`: a period of whole days, the dimensions to break it down
by, the metrics, exact filters, a limit - and gets the same answer back: a `Report` of `Row`s, in
one set of units whoever answered. Each tool is a package of its own (`omnireach/search-console`,
`omnireach/matomo`...) that turns the query into the tool's API calls.

```php
$google = $registry->search('google');            // a search engine: SearchPerformanceInterface
$stats = $registry->audience('stats');            // an analytics tool: AudienceInterface

$google->searchPerformance(ReachQuery::lastDays(28, [Dimension::Query]));        // queries, clicks, position
$stats->audience(ReachQuery::lastDays(28, [Dimension::EntryPage]));             // where visits start
$stats->audience(new ReachQuery($from, $to, [Dimension::Date], [Metric::Visits])); // a day-by-day history
```

The design is the one of the other Omnisense families: a provider's **factory** fills a
configuration with its name, its required options and the defaults of the others, then builds the
**provider**; a **registry** holds the application's providers by name. A provider says what it
can be asked in its **capabilities** (its dimensions and metrics, whether dimensions combine, its
terms and limits), and a query it cannot answer is refused before any call. The core needs no
framework: it requires `symfony/http-client-contracts` and nothing else, and every provider takes
the application's HTTP client in its factory's constructor.

Published as `omnisense/omnireach` (it `replace`s the former name `omniscient/omnireach`). The
namespace is `Omnireach\`; providers keep the `omnireach/` vendor and the `Omnireach\<Provider>\`
namespace.

## When to use it

- A site taken over from someone else: its search history and its audience over any period the
  tools still keep, before and after a redesign, page by page.
- A back office that shows the same "queries, pages, visits" panels whichever tool the site uses
  (Search Console or Bing for search, Matomo, Plausible or Google Analytics for the audience), or
  several at once.
- Checking what became of an old address: the queries and positions of one page
  (`filters: ['page' => 'https://example.org/old-page']`), the visits that still land on it.

It is not a visit counter of its own (nothing is counted on the site), not a tracker (no script is
added to a page) and not a rank checker scraping search results: every figure comes from a tool's
reporting API, with the site owner's own access. It stores nothing either: caching a report is the
application's job, and the tools' quotas make it worth doing.

## Providers

Five packages. The dimensions and metrics are each provider's `capabilities()`; "one at a time"
means its `combined` is `false`: one dimension besides the date.

| Package | Factory name | Contract | Dimensions | Metrics | Credentials |
|---|---|---|---|---|---|
| [`omnireach/search-console`](https://github.com/glitchr-omnisense/omnireach-search-console) | `search-console` | search performance | query, page, country, device, date (combined) | clicks, impressions, ctr, position | a service account's key, or an access token |
| [`omnireach/bing-webmaster`](https://github.com/glitchr-omnisense/omnireach-bing-webmaster) | `bing-webmaster` | search performance | query or page, date (one at a time) | clicks, impressions, ctr, position | an API key |
| [`omnireach/matomo`](https://github.com/glitchr-omnisense/omnireach-matomo) | `matomo` | audience | entry page, page, source, country or device, date (one at a time) | visitors, visits, pageviews, bounce rate, visit duration | a `token_auth`, or none for public reports |
| [`omnireach/plausible`](https://github.com/glitchr-omnisense/omnireach-plausible) | `plausible` | audience | page, entry page, source, country, device, date (combined) | visitors, visits, pageviews, bounce rate, visit duration | a Stats API key |
| [`omnireach/ga4`](https://github.com/glitchr-omnisense/omnireach-ga4) | `ga4` | audience | page, entry page, source, country, device, date (combined) | visitors, visits, pageviews, bounce rate, visit duration | a service account's key, or an access token |

No provider answers both contracts; the core's test provider, `Omnireach\Testing\FixedReach`,
does ([Testing](testing.md)). Each provider's own manual lists its options, the calls it makes and
how it maps the tool's answer.

## How this manual is organised

- [Installation](installation.md): requirements, a first script in plain PHP, a provider's API.
- [Symfony](symfony.md) and [Laravel](laravel.md): wiring the registry in a framework (no bridge
  yet: a service definition of the application's).
- [Concepts](concepts.md): providers, capabilities, factories, the registry, the query, the
  dimensions and metrics, rows and reports.
- Usage: [search performance](usage-search.md), [audience](usage-audience.md).
- [Configuration reference](configuration.md): the registry's configuration and every provider's
  options.
- [Errors](errors.md): every exception and when it is thrown.
- [Testing](testing.md): the fixed provider, the recorded HTTP client, the load check.
- [The Docker harness](harness.md): the core and every provider installed, its commands.
- [Writing a provider](providers.md).
- [Verified, and not](verified.md).
