---
title: Writing a provider
order: 80
---

# Writing a provider

A provider is a package `omnireach/<name>`, namespace `Omnireach\<Name>\`, with a factory and a
provider class. It requires `omnisense/omnireach` and `symfony/http-client-contracts`, nothing else
(`symfony/http-client` and PHPUnit in `require-dev`).

## The rules

- **The factory** extends `Omnireach\ProviderFactory`: `populate()` sets its name
  (`omnireach.factory_name`, kebab-case: `search-console`), its title, its required options and
  the defaults of the others; `build()` returns the provider. The HTTP client comes through the
  factory's constructor, never created inside; with none, `build()` throws
  `InvalidConfigException`.
- **The provider** implements `SearchPerformanceInterface` or `AudienceInterface` (or both) and
  describes itself in `capabilities()`: its dimensions and metrics, whether dimensions combine, its
  terms (URLs) and its limits (in words).
- **A report** starts with `$this->capabilities()->check($query, $metrics, '<Title>')`, `$metrics`
  being `$query->metricsOr(<the contract's defaults>)`, so a query it cannot answer costs no call;
  a filter it cannot apply, even on a dimension it reports, is refused with
  `NotSupportedException` too, never dropped. It ends with `$query->apply($rows)`, which puts the
  filters and the limit on what the tool returned.
- **Units** are the family's: ratios from 0 to 1 (a bounce rate in percent is divided by 100),
  seconds, dates `Y-m-d`, devices `desktop`/`mobile`/`tablet`, countries upper case, pages as the
  tool gives them (full URLs for search engines, paths for analytics). A metric the tool does not
  give for a row is left out of it, never zero.
- **Calls** go through `Omnireach\Http\JsonApi` (every failure becomes the family's exception,
  with the tool's message); answers are read with `Support\Data`, so an unexpected shape gives
  empty values, never a `\TypeError`. Keys go in a header or a body when the tool allows it, not in
  the URL, where logs and error messages keep them (Bing's API takes its key only in the query
  string: [Errors](errors.md#the-messages)).
- **Paging**: a tool that pages its answers is called page after page until `limit` rows are read
  or a page comes back short.
- **The note**: `Report::$note` says what the figures are not (provisional, sampled, weekly,
  summed by day), when the provider knows it.

## A skeleton

```php
<?php

// ExampleFactory.php and ExampleProvider.php, here in one listing
namespace Omnireach\Example;

use Omnireach\AudienceInterface;
use Omnireach\Capabilities;
use Omnireach\Config;
use Omnireach\Exception\InvalidConfigException;
use Omnireach\Http\JsonApi;
use Omnireach\Model\Dimension;
use Omnireach\Model\Metric;
use Omnireach\Model\ReachQuery;
use Omnireach\Model\Report;
use Omnireach\Model\Row;
use Omnireach\ProviderFactory;
use Omnireach\ProviderInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class ExampleFactory extends ProviderFactory
{
    public function __construct(private readonly ?HttpClientInterface $http = null)
    {
    }

    protected function populate(Config $c): void
    {
        $c->defaults([
            'omnireach.factory_name' => 'example',
            'omnireach.factory_title' => 'Example Analytics',
            'omnireach.required_options' => ['api_key', 'site'],
            'url' => 'https://api.example-analytics.test',
        ]);
    }

    protected function build(Config $c): ProviderInterface
    {
        return new ExampleProvider(
            new JsonApi($this->http ?? throw new InvalidConfigException('The "example" provider needs an HTTP client: give one to ExampleFactory.'), 'Example Analytics'),
            (string) $c->string('api_key'),
            (string) $c->string('site'),
            rtrim((string) $c->string('url'), '/'),
        );
    }
}

final class ExampleProvider implements AudienceInterface
{
    public function __construct(private readonly JsonApi $api, #[\SensitiveParameter] private readonly string $apiKey, private readonly string $site, private readonly string $url)
    {
    }

    public function getName(): string
    {
        return 'example';
    }

    public function getTitle(): string
    {
        return 'Example Analytics';
    }

    public function capabilities(): Capabilities
    {
        return new Capabilities(
            audience: true,
            dimensions: [Dimension::EntryPage, Dimension::Date],
            metrics: [Metric::Visits, Metric::BounceRate],
            combined: false,
            terms: ['https://example-analytics.test/terms'],
            limits: ['100 requests a minute'],
        );
    }

    public function audience(ReachQuery $query): Report
    {
        $metrics = $query->metricsOr([Metric::Visits]);
        $this->capabilities()->check($query, $metrics, 'Example Analytics');
        $site = $query->site ?? $this->site;
        $answer = $this->api->request('GET', $this->url.'/stats', [
            'query' => ['site' => $site, 'from' => $query->start->format('Y-m-d'), 'to' => $query->end->format('Y-m-d'), 'by' => $query->breakdown()[0]->value ?? 'none'],
            'auth_bearer' => $this->apiKey,
        ], 'the stats of '.$site);

        $rows = [];
        foreach ($answer->list('rows') as $line) {
            $values = [];
            foreach ($metrics as $metric) {
                $value = match ($metric) {
                    Metric::Visits => $line->int('visits'),
                    Metric::BounceRate => null !== ($percent = $line->float('bounce_percent')) ? round($percent / 100, 4) : null,   // percent -> ratio
                    default => null,
                };
                if (null !== $value) {
                    $values[$metric->value] = $value;           // a metric not given is left out, never 0
                }
            }
            $rows[] = new Row($query->has(Dimension::EntryPage) ? ['entry_page' => (string) $line->string('page')] : [], $values);
        }

        return new Report('example', $site, $query, $query->apply($rows));
    }
}
```

Run on recorded answers (2026-10-26, in the [harness](harness.md)):

```php
$http = RecordedHttpClient::routes(['GET /stats' => '{"rows":[{"page":"/","visits":260,"bounce_percent":41.5},{"page":"/ateliers","visits":110}]}']);
$registry = new Registry([new ExampleFactory($http)], ['stats' => ['factory' => 'example', 'options' => ['api_key' => 'k', 'site' => 'example.org']]]);
$report = $registry->audience('stats')->audience(ReachQuery::lastDays(7, [Dimension::EntryPage], [Metric::Visits, Metric::BounceRate], new \DateTimeImmutable('2026-10-26')));
echo json_encode(array_map(static fn ($r) => $r->toArray(), $report->rows)), "\n";
echo json_encode($http->requests[0]['options']['query']), "\n";
```

```
[{"dimensions":{"entry_page":"\/"},"metrics":{"visits":260,"bounce_rate":0.415}},{"dimensions":{"entry_page":"\/ateliers"},"metrics":{"visits":110}}]
{"site":"example.org","from":"2026-10-19","to":"2026-10-25","by":"entry_page"}
```

The second row has no `bounce_rate`: the answer gave none.

## Its tests

- **The provider's own test** (`Tests/<Name>ProviderTest.php`), on recorded answers - from the
  tool when a public account or demo exists (Matomo's are), else composed in the shape the tool's
  reference documents, and said so in the test's docblock: the request sent (URL, body, key), the
  mapping into rows in the family's units, the refusals (`NotSupportedException` for each
  dimension, filter or combination it cannot answer), a refused key (`InvalidKeyException`), the
  factory through a `Registry`.
- **`Tests/BareTest.php`**: every class of the package loaded alone, nothing foreign:

```php
use Omnireach\Testing\LoadCheck;

$autoload = LoadCheck::autoload(ExampleProvider::class, self::class, \dirname(__DIR__)) ?? self::markTestSkipped('installed in an application here');
$report = LoadCheck::run(\dirname(__DIR__), 'Omnireach\\Example\\', $autoload);

self::assertContains(ExampleProvider::class, $report['loaded']);
self::assertSame([], $report['foreign']);
self::assertSame([], LoadCheck::outside($report['files'], \dirname(__DIR__), ['/vendor/omnisense/omnireach/', '/omnireach/core/', '/vendor/symfony/http-client-contracts/', '/vendor/symfony/service-contracts/']));
```

- **`Tests/LiveTest.php`** (`#[Group('live')]`), only for what needs no account, never in the
  default run: `docker compose run --rm omnireach live`.

The provider joins the harness with a line in `docker/harness/plugins.php` (its slug and its tests'
namespace), and its README states what was verified and what was not, and the tool's terms
("Responsibility").
