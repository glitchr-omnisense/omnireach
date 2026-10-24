<?php

namespace Omnireach;

use Omnireach\Exception\NotSupportedException;
use Omnireach\Model\Dimension;
use Omnireach\Model\Metric;
use Omnireach\Model\ReachQuery;

/**
 * What a provider is, said before it is asked anything: which reports, by
 * which dimensions and metrics, and the terms and limits whoever runs it
 * must keep.
 */
final readonly class Capabilities
{
    /**
     * @param bool             $searchPerformance it reports search queries, pages and positions (SearchPerformanceInterface)
     * @param bool             $audience          it reports visits, entry pages and sources (AudienceInterface)
     * @param list<Dimension>  $dimensions        the dimensions it breaks a report down by
     * @param list<Metric>     $metrics           the metrics it counts
     * @param bool             $combined          dimensions can be combined; false: one at a time, besides the date
     * @param list<string>     $terms             the terms whoever runs it must follow (URLs)
     * @param list<string>     $limits            the rate limits, quotas and freshness the provider states, in words
     */
    public function __construct(
        public bool $searchPerformance = false,
        public bool $audience = false,
        public array $dimensions = [],
        public array $metrics = [],
        public bool $combined = true,
        public array $terms = [],
        public array $limits = [],
    ) {
    }

    /** @return list<string> what it can be asked, by contract: searchPerformance, audience */
    public function contracts(): array
    {
        return array_keys(array_filter(['searchPerformance' => $this->searchPerformance, 'audience' => $this->audience]));
    }

    /**
     * Throws when the query asks for a dimension, a filter or a metric the
     * provider does not report, or combines dimensions it cannot.
     *
     * @param list<Metric> $metrics the metrics the report will count (the query's or the defaults)
     */
    public function check(ReachQuery $query, array $metrics, string $provider): void
    {
        $dimensions = [...$query->dimensions, ...array_map(static fn (string $d) => Dimension::from($d), array_keys($query->filters))];
        $missing = [
            ...array_map(static fn (Dimension $d) => 'the dimension "'.$d->value.'"', array_filter($dimensions, fn (Dimension $d) => !\in_array($d, $this->dimensions, true))),
            ...array_map(static fn (Metric $m) => 'the metric "'.$m->value.'"', array_filter($metrics, fn (Metric $m) => !\in_array($m, $this->metrics, true))),
        ];
        if ([] !== $missing) {
            throw new NotSupportedException(\sprintf('%s does not report %s.', $provider, implode(', ', array_unique($missing))));
        }
        if (!$this->combined && \count($query->breakdown()) > 1) {
            throw new NotSupportedException(\sprintf('%s reports one dimension at a time besides the date, not %s.', $provider, implode(' and ', array_map(static fn (Dimension $d) => $d->value, $query->breakdown()))));
        }
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'contracts' => $this->contracts(),
            'searchPerformance' => $this->searchPerformance,
            'audience' => $this->audience,
            'dimensions' => array_map(static fn (Dimension $d) => $d->value, $this->dimensions),
            'metrics' => array_map(static fn (Metric $m) => $m->value, $this->metrics),
            'combined' => $this->combined,
            'terms' => $this->terms,
            'limits' => $this->limits,
        ];
    }
}
