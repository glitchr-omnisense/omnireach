# Installation, in plain PHP

    composer require omniscient/omnireach omnireach/plausible symfony/http-client

Any implementation of `Symfony\Contracts\HttpClient\HttpClientInterface` does: Symfony's
`HttpClient::create()`, or a `MockHttpClient` in a test.

```php
use Omnireach\Model\{Dimension, Metric, ReachQuery, Row};
use Omnireach\Plausible\PlausibleFactory;
use Omnireach\Registry;
use Symfony\Component\HttpClient\HttpClient;

$registry = new Registry([new PlausibleFactory(HttpClient::create())], [
    'stats' => ['factory' => 'plausible', 'options' => ['api_key' => getenv('PLAUSIBLE_KEY'), 'site' => 'example.org']],
]);

$query = new ReachQuery(
    new DateTimeImmutable('2026-09-01'), new DateTimeImmutable('2026-09-30'),
    [Dimension::EntryPage],                       // broken down by landing page
    [Metric::Visitors, Metric::Visits, Metric::BounceRate],
    ['country' => 'FR'],                          // exact filters, by dimension
    limit: 50,
);
$report = $registry->audience('stats')->audience($query);
foreach ($report->rows as $row) {
    printf("%s %d visits, %.0f%% bounce\n", $row->dimension(Dimension::EntryPage), $row->metric(Metric::Visits), 100 * ($row->metric(Metric::BounceRate) ?? 0));
}
echo $report->totals()['visits'] ?? 0;               // what adds up: clicks, impressions, visits, pageviews
```

A provider's `capabilities()` says what it reports; a query it cannot answer throws
`Omnireach\Exception\NotSupportedException` before any call. Every failure is an
`Omnireach\Exception\OmnireachException`.
