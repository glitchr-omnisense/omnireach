---
title: Audience
order: 32
---

# Audience

`AudienceInterface::audience(ReachQuery $query): Report` reports who visits the site, as an
analytics tool counted it: visitors, visits, pages viewed, bounce rate and visit length, by entry
page, page, source, country, device or day. Three providers answer it: `omnireach/matomo`,
`omnireach/plausible` and `omnireach/ga4`. Metrics by default: visitors, visits, pageviews.

## Where visits start

```php
use Omnireach\Model\Dimension;
use Omnireach\Model\Metric;
use Omnireach\Model\ReachQuery;

$report = $registry->audience('stats')->audience(
    ReachQuery::lastDays(28, [Dimension::EntryPage], [Metric::Visits, Metric::BounceRate, Metric::VisitDuration]),
);

$rows = $report->rows;
usort($rows, static fn ($a, $b) => ($b->metric(Metric::Visits) ?? 0) <=> ($a->metric(Metric::Visits) ?? 0));
foreach ($rows as $row) {
    printf(
        "%-40s %6d visits %5.1f%% bounce %4.0f s\n",
        $row->dimension(Dimension::EntryPage),
        $row->metric(Metric::Visits),
        100 * ($row->metric(Metric::BounceRate) ?? 0),
        $row->metric(Metric::VisitDuration) ?? 0,
    );
}
```

Rows come in the order the tool gives them; sort them when the order matters, as above (Matomo's
demo answered entry pages in an order that is not by visits: [Installation](installation.md#a-providers-api)).

## A history

```php
$history = $registry->audience('stats')->audience(new ReachQuery(
    new \DateTimeImmutable('2026-01-01'),
    new \DateTimeImmutable('2026-09-30'),
    [Dimension::Date],
    [Metric::Visits, Metric::Pageviews],
    limit: 366,
));
$series = array_map(static fn ($row) => [$row->dimension(Dimension::Date), $row->metric(Metric::Visits)], $history->rows);
```

With the date and another dimension, each day has a row per value: `[Dimension::Source,
Dimension::Date]` gives each source's visits day by day. The limit counts rows, so a long history
by source needs a higher one than the default 1000.

## Sources, countries, devices

```php
$query = new ReachQuery($from, $to, [Dimension::EntryPage], [Metric::Visits], ['source' => 'google', 'country' => 'FR']);
$fromGoogleInFrance = $registry->audience('stats')->audience($query);

$sources = $registry->audience('stats')->audience(ReachQuery::lastDays(28, [Dimension::Source]));
```

Filters are exact and case-sensitive, on the family's values: a country `FR`, a device `mobile`
([Matomo's](#devices) included), a source as the tool names it - Matomo's referrer name, GA4's
`sessionSource`, Plausible's `visit:source` - so a report by source shows which values to filter
with. The query is readonly: build a new one for other filters, `lastDays()` takes none.

## What each tool does

| | Matomo | Plausible | Google Analytics 4 |
|---|---|---|---|
| dimensions | entry page, page, source, country, device, date | page, entry page, source, country, device, date | page, entry page, source, country, device, date |
| combined | one at a time besides the date | freely | freely |
| filters | source, country, device: a Matomo segment; page or entry page: only in a report broken down by it | any of its dimensions (`is`) | any of its dimensions (`EXACT`, an `andGroup` for several) |
| visitors over a range | Matomo's distinct visitors when the installation processes them, else the sum of each day's (`Report::$note` says so) | distinct visitors | `totalUsers` |
| pageviews of entry pages | the actions of the visits that started there (`entry_nb_actions`) | pageviews | `screenPageViews` |
| bounce rate | bounces over visits | Plausible's percent divided by 100 | `bounceRate` (GA4's definition: sessions not engaged) |
| largest report | Matomo's `filter_limit` (= `limit`, per day in a history), cut to `limit` | pages of 10,000 up to `limit` | pages of 250,000 up to `limit` |
| row order | Matomo's | Plausible's | GA4's; by date when the date is asked |

The same name does not always mean the same count: a GA4 "session" and a Matomo "visit" end on
different rules, a GA4 bounce is a session that was not engaged. Compare a site with itself over
time, not one tool with another.

## Devices

Plausible and GA4 give `desktop`, `mobile`, `tablet`. Matomo has more types: its smartphones,
phablets and feature phones become one `mobile` row (their counts summed), and a `device` filter
`mobile` is sent to Matomo as those three types; every other type is Matomo's own label lower-cased
(`desktop`, `tablet`, `console`, `tv`, `portable media player`, `unknown`...), and types with no
visit are kept as rows of 0.

## Pages, before and after

The visits a page still receives after a redesign - an address to redirect, a page to keep:

```php
$old = $registry->audience('stats')->audience(new ReachQuery(
    new \DateTimeImmutable('2026-01-01'),
    new \DateTimeImmutable('2026-09-30'),
    [Dimension::EntryPage, Dimension::Date],
    [Metric::Visits],
    ['entry_page' => '/ancienne-page'],
));
```

Analytics tools give pages as paths (`/ateliers`), Matomo's with a leading slash added when its
label has none. With Matomo, a filter on a page works only in a report broken down by that page
(as above); filtered by page in a report by country, it is refused (`NotSupportedException`).
