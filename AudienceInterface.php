<?php

namespace Omnireach;

use Omnireach\Model\Report;
use Omnireach\Model\ReachQuery;

/**
 * Who visits the site, as an analytics tool counted it: visitors, visits,
 * pages viewed, by entry page, page, source, country, device or day.
 */
interface AudienceInterface extends ProviderInterface
{
    /**
     * Metrics by default: visitors, visits, pageviews.
     *
     * @throws Exception\NotSupportedException for a dimension, filter or metric it does not report
     */
    public function audience(ReachQuery $query): Report;
}
