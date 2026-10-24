<?php

namespace Omnireach\Model;

use Omnireach\Exception\InvalidArgumentException;

/**
 * What a report is asked for: a period (whole days, both included), the
 * dimensions to break it down by, the metrics, exact filters on
 * dimensions, at most how many rows - and the site, when it is not the
 * one the provider is configured with.
 */
final readonly class ReachQuery
{
    public \DateTimeImmutable $start;

    public \DateTimeImmutable $end;

    /**
     * @param list<Dimension>       $dimensions
     * @param list<Metric>          $metrics    none: the provider's defaults for the report asked
     * @param array<string, string> $filters    by Dimension value: rows whose dimension is exactly that value
     */
    public function __construct(
        \DateTimeImmutable $start,
        \DateTimeImmutable $end,
        public array $dimensions = [],
        public array $metrics = [],
        public array $filters = [],
        public int $limit = 1000,
        public ?string $site = null,
    ) {
        $this->start = $start->setTime(0, 0);
        $this->end = $end->setTime(0, 0);
        if ($this->end < $this->start) {
            throw new InvalidArgumentException(\sprintf('The period ends (%s) before it starts (%s).', $this->end->format('Y-m-d'), $this->start->format('Y-m-d')));
        }
        if ($limit < 1) {
            throw new InvalidArgumentException('A report has at least one row: limit '.$limit.'.');
        }
        foreach (array_keys($filters) as $dimension) {
            if (null === Dimension::tryFrom($dimension)) {
                throw new InvalidArgumentException(\sprintf('No "%s" dimension to filter on.', $dimension));
            }
        }
    }

    /**
     * The last $days whole days, yesterday the last (today's figures are not
     * complete anywhere).
     *
     * @param list<Dimension> $dimensions
     * @param list<Metric>    $metrics
     */
    public static function lastDays(int $days, array $dimensions = [], array $metrics = [], ?\DateTimeImmutable $today = null): self
    {
        $end = ($today ?? new \DateTimeImmutable())->modify('-1 day');

        return new self($end->modify('-'.(max(1, $days) - 1).' days'), $end, $dimensions, $metrics);
    }

    public function has(Dimension $dimension): bool
    {
        return \in_array($dimension, $this->dimensions, true);
    }

    public function filter(Dimension $dimension): ?string
    {
        return $this->filters[$dimension->value] ?? null;
    }

    /**
     * @param list<Metric> $defaults
     *
     * @return list<Metric>
     */
    public function metricsOr(array $defaults): array
    {
        return [] !== $this->metrics ? $this->metrics : $defaults;
    }

    /** @return list<Dimension> the dimensions but the date */
    public function breakdown(): array
    {
        return array_values(array_filter($this->dimensions, static fn (Dimension $d) => Dimension::Date !== $d));
    }

    /**
     * The query's filters and limit applied to rows: what a provider that
     * cannot filter (or not on everything) returns through it, so a query
     * means the same everywhere.
     *
     * @param iterable<Row> $rows
     *
     * @return list<Row>
     */
    public function apply(iterable $rows): array
    {
        $kept = [];
        foreach ($rows as $row) {
            foreach ($this->filters as $dimension => $value) {
                if (isset($row->dimensions[$dimension]) && $row->dimensions[$dimension] !== $value) {
                    continue 2;
                }
            }
            $kept[] = $row;
            if (\count($kept) >= $this->limit) {
                break;
            }
        }

        return $kept;
    }
}
