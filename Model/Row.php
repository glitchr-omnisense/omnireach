<?php

namespace Omnireach\Model;

/** One line of a report: its dimensions' values and its metrics. */
final readonly class Row
{
    /**
     * @param array<string, string>    $dimensions by Dimension value
     * @param array<string, int|float> $metrics    by Metric value
     */
    public function __construct(public array $dimensions, public array $metrics)
    {
    }

    public function dimension(Dimension $dimension): ?string
    {
        return $this->dimensions[$dimension->value] ?? null;
    }

    public function metric(Metric $metric): int|float|null
    {
        return $this->metrics[$metric->value] ?? null;
    }

    /** @return array{dimensions: array<string, string>, metrics: array<string, int|float>} */
    public function toArray(): array
    {
        return ['dimensions' => $this->dimensions, 'metrics' => $this->metrics];
    }
}
