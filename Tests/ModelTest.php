<?php

namespace Omnireach\Tests;

use Omnireach\Capabilities;
use Omnireach\Exception\InvalidArgumentException;
use Omnireach\Exception\NotSupportedException;
use Omnireach\Model\Dimension;
use Omnireach\Model\Metric;
use Omnireach\Model\ReachQuery;
use Omnireach\Model\Report;
use Omnireach\Model\Row;
use PHPUnit\Framework\TestCase;

final class ModelTest extends TestCase
{
    public function testAQuery(): void
    {
        $query = new ReachQuery(new \DateTimeImmutable('2026-10-01 15:00'), new \DateTimeImmutable('2026-10-31'), [Dimension::Page, Dimension::Date], filters: ['country' => 'FR'], limit: 2);

        self::assertSame(['2026-10-01 00:00', '2026-10-31 00:00'], [$query->start->format('Y-m-d H:i'), $query->end->format('Y-m-d H:i')], 'whole days');
        self::assertTrue($query->has(Dimension::Date));
        self::assertSame([Dimension::Page], $query->breakdown());
        self::assertSame('FR', $query->filter(Dimension::Country));
        self::assertSame([Metric::Visits], $query->metricsOr([Metric::Visits]));
        self::assertSame(['2026-10-01', '2026-10-28'], [($q = ReachQuery::lastDays(28, today: new \DateTimeImmutable('2026-10-29')))->start->format('Y-m-d'), $q->end->format('Y-m-d')]);

        $rows = [new Row(['page' => '/a', 'country' => 'FR'], ['visits' => 1]), new Row(['page' => '/b', 'country' => 'DE'], ['visits' => 2]), new Row(['page' => '/c', 'country' => 'FR'], ['visits' => 3]), new Row(['page' => '/d', 'country' => 'FR'], ['visits' => 4])];
        self::assertSame(['/a', '/c'], array_map(static fn (Row $r) => $r->dimension(Dimension::Page), $query->apply($rows)), 'filtered, then cut');
    }

    public function testQueriesThatCannotBeAnswered(): void
    {
        foreach ([
            static fn () => new ReachQuery(new \DateTimeImmutable('2026-10-02'), new \DateTimeImmutable('2026-10-01')),
            static fn () => new ReachQuery(new \DateTimeImmutable('2026-10-01'), new \DateTimeImmutable('2026-10-01'), limit: 0),
            static fn () => new ReachQuery(new \DateTimeImmutable('2026-10-01'), new \DateTimeImmutable('2026-10-01'), filters: ['city' => 'Paris']),
        ] as $i => $make) {
            try {
                $make();
                self::fail('Query '.$i);
            } catch (InvalidArgumentException) {
                self::addToAssertionCount(1);
            }
        }
    }

    public function testCapabilitiesCheckAQuery(): void
    {
        $capabilities = new Capabilities(audience: true, dimensions: [Dimension::EntryPage, Dimension::Country, Dimension::Date], metrics: [Metric::Visits], combined: false);
        $query = self::query(...);

        $capabilities->check($query([Dimension::EntryPage, Dimension::Date]), [Metric::Visits], 'Tool');
        foreach ([
            [$query([Dimension::Query]), [Metric::Visits], 'Tool does not report the dimension "query".'],
            [$query([], ['source' => 'x']), [Metric::Visits], 'Tool does not report the dimension "source".'],
            [$query([]), [Metric::Visits, Metric::Clicks], 'Tool does not report the metric "clicks".'],
            [$query([Dimension::EntryPage, Dimension::Country]), [Metric::Visits], 'Tool reports one dimension at a time besides the date, not entry_page and country.'],
        ] as [$q, $metrics, $message]) {
            try {
                $capabilities->check($q, $metrics, 'Tool');
                self::fail($message);
            } catch (NotSupportedException $e) {
                self::assertSame($message, $e->getMessage());
            }
        }
        self::assertSame(['audience'], $capabilities->contracts());
        self::assertSame(['entry_page', 'country', 'date'], $capabilities->toArray()['dimensions']);
    }

    public function testAReport(): void
    {
        $query = new ReachQuery(new \DateTimeImmutable('2026-10-01'), new \DateTimeImmutable('2026-10-02'), [Dimension::Query]);
        $report = new Report('test', 'https://example.org/', $query, [new Row(['query' => 'a'], ['clicks' => 2, 'impressions' => 10, 'position' => 3.5]), new Row(['query' => 'b'], ['clicks' => 1, 'impressions' => 5, 'position' => 7.0])]);

        self::assertSame(['clicks' => 3, 'impressions' => 15], $report->totals(), 'positions do not add up');
        self::assertSame(['provider' => 'test', 'site' => 'https://example.org/', 'start' => '2026-10-01', 'end' => '2026-10-02', 'dimensions' => ['query'], 'rows' => [['dimensions' => ['query' => 'a'], 'metrics' => ['clicks' => 2, 'impressions' => 10, 'position' => 3.5]], ['dimensions' => ['query' => 'b'], 'metrics' => ['clicks' => 1, 'impressions' => 5, 'position' => 7.0]]], 'note' => null], $report->toArray());
        self::assertTrue(Metric::Position->isSearch());
        self::assertFalse(Metric::Visitors->isAdditive());
    }

    /**
     * @param list<Dimension>       $dimensions
     * @param array<string, string> $filters
     */
    private static function query(array $dimensions, array $filters = []): ReachQuery
    {
        return new ReachQuery(new \DateTimeImmutable('2026-10-01'), new \DateTimeImmutable('2026-10-02'), $dimensions, filters: $filters);
    }
}
