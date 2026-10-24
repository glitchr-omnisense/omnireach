<?php

namespace Omnireach;

use Omnireach\Model\Report;
use Omnireach\Model\ReachQuery;

/**
 * How the site shows in a search engine: the queries it appears for, its
 * pages shown, clicks, impressions, CTR and average position.
 */
interface SearchPerformanceInterface extends ProviderInterface
{
    /**
     * Metrics by default: clicks, impressions, CTR, position.
     *
     * @throws Exception\NotSupportedException for a dimension, filter or metric it does not report
     */
    public function searchPerformance(ReachQuery $query): Report;
}
