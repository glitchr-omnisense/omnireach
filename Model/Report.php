<?php

namespace Omnireach\Model;

/** A provider's answer to a ReachQuery: its rows, and what to know about them. */
final readonly class Report
{
    /**
     * @param list<Row> $rows
     * @param ?string   $note what the figures are not: sampled, rounded, a week behind...
     */
    public function __construct(
        public string $provider,
        public string $site,
        public ReachQuery $query,
        public array $rows,
        public ?string $note = null,
    ) {
    }

    /** @return array<string, int|float> the sums of the metrics that add up (clicks, impressions, visits, pageviews) */
    public function totals(): array
    {
        $totals = [];
        foreach ($this->rows as $row) {
            foreach ($row->metrics as $name => $value) {
                if (Metric::tryFrom($name)?->isAdditive()) {
                    $totals[$name] = ($totals[$name] ?? 0) + $value;
                }
            }
        }

        return $totals;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'provider' => $this->provider,
            'site' => $this->site,
            'start' => $this->query->start->format('Y-m-d'),
            'end' => $this->query->end->format('Y-m-d'),
            'dimensions' => array_map(static fn (Dimension $d) => $d->value, $this->query->dimensions),
            'rows' => array_map(static fn (Row $r) => $r->toArray(), $this->rows),
            'note' => $this->note,
        ];
    }
}
