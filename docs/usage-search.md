---
title: Search performance
order: 31
---

# Search performance

`SearchPerformanceInterface::searchPerformance(ReachQuery $query): Report` reports how the site
shows in a search engine: the queries it appears for, its pages shown, their clicks, impressions,
click-through rate and average position. Two providers answer it: `omnireach/search-console` and
`omnireach/bing-webmaster`. Metrics by default: clicks, impressions, ctr, position.

## The queries of a period

```php
use Omnireach\Model\Dimension;
use Omnireach\Model\Metric;
use Omnireach\Model\ReachQuery;

$report = $registry->search('google')->searchPerformance(ReachQuery::lastDays(28, [Dimension::Query]));

foreach ($report->rows as $row) {
    printf(
        "%-40s %5d clicks %7d impressions %5.1f%% CTR  position %4.1f\n",
        $row->dimension(Dimension::Query),
        $row->metric(Metric::Clicks),
        $row->metric(Metric::Impressions),
        100 * ($row->metric(Metric::Ctr) ?? 0),
        $row->metric(Metric::Position) ?? 0,
    );
}
$totals = $report->totals();            // ['clicks' => ..., 'impressions' => ...]: what adds up
```

CTR and position do not add up over rows: `totals()` leaves them out. The CTR of the whole period
is `$totals['clicks'] / $totals['impressions']`; its position is a report with no dimension.

## One page, before and after

The queries a page shows for, and its position day by day - an old address after a site was taken
over, or a page rewritten:

```php
$from = new \DateTimeImmutable('2026-03-01');
$to = new \DateTimeImmutable('2026-09-30');
$page = 'https://example.org/old-page';

$queries = $registry->search('google')->searchPerformance(
    new ReachQuery($from, $to, [Dimension::Query], filters: ['page' => $page]),
);
$history = $registry->search('google')->searchPerformance(
    new ReachQuery($from, $to, [Dimension::Date], [Metric::Clicks, Metric::Position], ['page' => $page]),
);
```

Search engines give pages as full URLs, as the property knows them: filter with the exact URL
(scheme, host, trailing slash). Filters are exact and case-sensitive ([Concepts](concepts.md#filters-and-limit)).

## Breakdowns and combinations

| | Search Console | Bing Webmaster |
|---|---|---|
| dimensions | query, page, country, device, date | query, page, date |
| combined | freely: `[Query, Country]`, `[Page, Device, Date]` | one at a time besides the date: `[Query, Date]` yes, `[Query, Page]` refused |
| filters | on any of its dimensions, sent to Search Console (`equals`, all together) | only on the dimension the report is broken down by (`query` in a query report), applied to Bing's rows; any other refused |
| the date | each day | Bing's own periods (one row a week, about six months back), those inside the query's days |
| no dimension | one row for the period | the whole site (`GetRankAndTrafficStats`): clicks and impressions, no position |
| row order | as Search Console returns them (the code does not sort) | by clicks then impressions, most first; by the dimension's values with the date |
| largest report | pages of 25,000 rows up to `limit` | what Bing returns, cut to `limit` |
| `Report::$note` | `The last days are provisional (dataState "all").` with `data_state: all` | always: Bing's weekly periods, positions weighted by impressions |

A query one provider cannot answer is refused by its capabilities before any call, with
`NotSupportedException`: check `capabilities()->dimensions` and `->combined` first when the same
screen offers several providers, or catch it and show "not available from Bing".

```php
use Omnireach\Exception\NotSupportedException;

$query = ReachQuery::lastDays(90, [Dimension::Query, Dimension::Country]);
foreach (['google', 'bing'] as $name) {
    try {
        $reports[$name] = $registry->search($name)->searchPerformance($query);
    } catch (NotSupportedException $e) {
        $reports[$name] = null;     // "Bing Webmaster does not report the dimension "country"."
    }
}
```

## Countries and devices

Search Console gives countries as ISO 3166-1 alpha-3, upper-cased here (`FRA`, `DEU`): filter with
the same (`['country' => 'FRA']`; the filter is sent lower-cased, as Search Console writes them).
Devices are `desktop`, `mobile`, `tablet`. Bing reports neither.

## History and freshness

The search engines keep a limited history: 16 months for Search Console, about six months for
Bing. A site taken over should have its history exported while it is still there - a report by
date, kept by the application. Search Console's last two or three days are provisional: with
`data_state: final` (the default) they are not in the report yet; `ReachQuery::lastDays()` ends
yesterday either way.
