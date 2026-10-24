<?php

namespace Omnireach\Testing;

use Omnireach\AudienceInterface;
use Omnireach\Capabilities;
use Omnireach\Model\Dimension;
use Omnireach\Model\Metric;
use Omnireach\Model\ReachQuery;
use Omnireach\Model\Report;
use Omnireach\Model\Row;
use Omnireach\SearchPerformanceInterface;

/**
 * A provider that answers from rows it was given: an application's tests,
 * a demo, the harness's bare script. Both reports, no network; the rows
 * given are filtered, cut and projected on the query's dimensions.
 */
final class FixedReach implements SearchPerformanceInterface, AudienceInterface
{
    /**
     * @param list<Row> $search   search engine rows
     * @param list<Row> $audience analytics rows
     */
    public function __construct(private readonly array $search = [], private readonly array $audience = [], private readonly string $site = 'https://example.org/')
    {
    }

    public function getName(): string
    {
        return 'fixed';
    }

    public function getTitle(): string
    {
        return 'Fixed';
    }

    public function capabilities(): Capabilities
    {
        return new Capabilities(true, true, Dimension::cases(), Metric::cases());
    }

    public function searchPerformance(ReachQuery $query): Report
    {
        return $this->report($query, $this->search);
    }

    public function audience(ReachQuery $query): Report
    {
        return $this->report($query, $this->audience);
    }

    /** @param list<Row> $rows */
    private function report(ReachQuery $query, array $rows): Report
    {
        $this->capabilities()->check($query, $query->metrics, 'Fixed');
        $wanted = array_flip(array_map(static fn (Dimension $d) => $d->value, $query->dimensions));
        $metrics = array_flip(array_map(static fn (Metric $m) => $m->value, $query->metrics));
        $inPeriod = array_filter($rows, static function (Row $row) use ($query): bool {
            $date = $row->dimension(Dimension::Date);

            return null === $date || ($date >= $query->start->format('Y-m-d') && $date <= $query->end->format('Y-m-d'));
        });
        $projected = array_map(static fn (Row $row) => new Row(array_intersect_key($row->dimensions, $wanted), [] !== $metrics ? array_intersect_key($row->metrics, $metrics) : $row->metrics), $query->apply($inPeriod));

        return new Report('fixed', $query->site ?? $this->site, $query, $projected);
    }
}
