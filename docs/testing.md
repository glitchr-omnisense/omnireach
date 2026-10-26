---
title: Testing
order: 60
---

# Testing

Testing an application that reports with Omnireach. Writing a provider and its tests:
[Writing a provider](providers.md).

## The fixed provider as a fake

`Omnireach\Testing\FixedReach` answers both contracts from rows it was given, with no network; its
factory, `FixedReachFactory`, registers it under the name `fixed`. Configure the provider the
application uses with it in tests:

```php
use Omnireach\Model\Row;
use Omnireach\Registry;
use Omnireach\Testing\FixedReachFactory;

$registry = new Registry([new FixedReachFactory(
    search: [new Row(['query' => 'atelier poterie', 'date' => '2026-10-20'], ['clicks' => 12, 'impressions' => 340])],
    audience: [new Row(['entry_page' => '/', 'date' => '2026-10-20'], ['visits' => 260])],
)], [
    'google' => ['factory' => 'fixed'],
    'stats' => ['factory' => 'fixed', 'options' => ['site' => 'https://atelier.example.org/']],
]);
```

What it does with a query:

- its capabilities are every dimension and every metric, combined: it refuses nothing but a metric
  or dimension that does not exist;
- a row with a `date` outside the period is left out; a row without one is kept;
- the query's filters and limit are applied (`ReachQuery::apply()`);
- each row is projected on the query's dimensions, and on its metrics when it names some. Rows are
  not summed: two rows of the same query on two days, asked by query only, stay two rows. Give it
  the rows as the test expects them;
- the report's `provider` is `fixed`, its `site` the query's, else the `site` option, else
  `https://example.org/`.

## A provider on recorded answers

To test the application's handling of a real tool's answers - and of its failures - give the
provider's factory `Omnireach\Testing\RecordedHttpClient` (or Symfony's `MockHttpClient`):

```php
use Omnireach\Exception\InvalidKeyException;
use Omnireach\Model\Dimension;
use Omnireach\Model\Metric;
use Omnireach\Model\ReachQuery;
use Omnireach\Model\Row;
use Omnireach\Plausible\PlausibleFactory;
use Omnireach\Registry;
use Omnireach\Testing\FixedReachFactory;
use Omnireach\Testing\RecordedHttpClient;
use PHPUnit\Framework\TestCase;

final class EntryPagesPanelTest extends TestCase
{
    public function testTheBusiestEntryPages(): void
    {
        $registry = new Registry([new FixedReachFactory(audience: [
            new Row(['entry_page' => '/', 'date' => '2026-10-20'], ['visits' => 260]),
            new Row(['entry_page' => '/ateliers', 'date' => '2026-10-21'], ['visits' => 110]),
        ])], ['stats' => ['factory' => 'fixed']]);

        $report = $registry->audience('stats')->audience(
            ReachQuery::lastDays(28, [Dimension::EntryPage], [Metric::Visits], today: new DateTimeImmutable('2026-10-26')),
        );

        self::assertSame(['/', '/ateliers'], array_map(static fn (Row $r) => $r->dimension(Dimension::EntryPage), $report->rows));
        self::assertSame(['visits' => 370], $report->totals());
    }

    public function testWhatPlausibleIsSent(): void
    {
        $http = RecordedHttpClient::routes([
            'POST /api/v2/query' => '{"results":[{"dimensions":["/"],"metrics":[3644,54]}]}',
        ]);
        $registry = new Registry([new PlausibleFactory($http)], [
            'stats' => ['factory' => 'plausible', 'options' => ['api_key' => 'test-key', 'site' => 'example.org']],
        ]);

        $report = $registry->audience('stats')->audience(new ReachQuery(
            new DateTimeImmutable('2026-10-01'), new DateTimeImmutable('2026-10-31'),
            [Dimension::EntryPage], [Metric::Visits, Metric::BounceRate],
        ));

        self::assertSame(['visits' => 3644, 'bounce_rate' => 0.54], $report->rows[0]->metrics);
        self::assertSame('test-key', $http->requests[0]['options']['auth_bearer']);
        self::assertSame(['visit:entry_page'], $http->requests[0]['options']['json']['dimensions']);
    }

    public function testARevokedKey(): void
    {
        $http = RecordedHttpClient::routes(['POST /api/v2/query' => [401, '{"error":"Invalid API key"}']]);
        $plausible = (new PlausibleFactory($http))->create(['api_key' => 'revoked', 'site' => 'example.org']);

        $this->expectException(InvalidKeyException::class);
        $plausible->audience(ReachQuery::lastDays(7));
    }
}
```

```
$ vendor/bin/phpunit EntryPagesPanelTest.php
...                                                                 3 / 3 (100%)

OK (3 tests, 6 assertions)
```

(run on 2026-10-26 in the [harness](harness.md), PHPUnit 11.5, PHP 8.4)

`RecordedHttpClient` (shipped with the core; it needs no `symfony/http-client`):

| Member | |
|---|---|
| `new RecordedHttpClient(\Closure $answer)` | answers each request with `$answer($method, $url, $options)`, which returns `[status, body]` or `[status, body, headers]` |
| `static routes(array $routes): self` | answers by route: `"METHOD /path"` => a body (status 200) or `[status, body]`; the first route whose method matches and whose path ends the URL's path answers; anything else gets a 404 `{"error":"not recorded: ..."}` |
| `array $requests` | every request made: `['method' => ..., 'url' => ..., 'options' => ...]`, the options as the provider gave them (`json`, `body`, `query`, `auth_bearer`...) |
| `stream()` | throws `\LogicException`: it does not stream (no provider streams) |

Its responses (`RecordedResponse`) never throw: a provider sees the status and the body, as with a
real client called with `getContent(false)`. A network error cannot be recorded with it; use
`MockHttpClient` with a `MockResponse` whose `error` option is set, which `JsonApi` turns into an
`UnreachableException`.

## The load check

`Omnireach\Testing\LoadCheck` is the check every package of the family runs in its `BareTest`: each
class of the package loaded in a PHP process of its own, then what came from outside.

| Member | |
|---|---|
| `LoadCheck::FOREIGN` | the classes no package may load: Symfony's but its HTTP client and service contracts and polyfills, Illuminate, Laravel, Doctrine, Twig, League, Guzzle, Nyholm, Http, Omnibridge, Base, App |
| `run(string $root, string $namespace, string $autoload, list<string> $extra = []): array` | runs `bin/load-all` in a child PHP: `['loaded' => classes, 'foreign' => foreign symbols declared, 'files' => files included]`, the files Composer includes at start for every package left out |
| `foreign(list<string> $symbols): list<string>` | the symbols matching `FOREIGN` |
| `outside(list<string> $files, string $root, list<string> $allowed): list<string>` | the files from outside the package and outside the allowed vendor fragments (`/vendor/psr/`...) |
| `autoload(string $class, string $test, string $root): ?string` | the autoloader that knows both the class and the test (the package's or the harness's); `null` when the package sits in an application's `vendor/` |

An application rarely needs it; it is how the family proves that a provider pulls in no framework.

## No conformance suite

Unlike Omnibus, the family has no shared contract test (`Testing\*ContractTests`): each provider's
tests check its own requests and mapping, its refusals and its factory
([Verified, and not](verified.md)).

## In a framework's functional test

The registry is a service of the application's: in Symfony, define it with `FixedReachFactory` in
`config/services_test.yaml`, or replace the `http_client` service with a `MockHttpClient` in the
test environment; in Laravel, bind another `Registry` singleton in the test's `setUp()`.
