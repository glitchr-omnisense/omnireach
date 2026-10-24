<?php

namespace Omnireach\Model;

/** What a report counts. */
enum Metric: string
{
    /** Clicks from search results to the site. */
    case Clicks = 'clicks';
    /** Times the site was shown in search results. */
    case Impressions = 'impressions';
    /** Clicks per impression, a ratio from 0 to 1. */
    case Ctr = 'ctr';
    /** The average position in search results, 1 the first. */
    case Position = 'position';
    /** Distinct visitors (users). */
    case Visitors = 'visitors';
    /** Visits (sessions). */
    case Visits = 'visits';
    /** Pages viewed. */
    case Pageviews = 'pageviews';
    /** Visits of one page only, a ratio from 0 to 1. */
    case BounceRate = 'bounce_rate';
    /** The average length of a visit, in seconds. */
    case VisitDuration = 'visit_duration';

    /** Whether it is a search engine's figure (clicks, impressions, CTR, position). */
    public function isSearch(): bool
    {
        return \in_array($this, [self::Clicks, self::Impressions, self::Ctr, self::Position], true);
    }

    /** Whether rows' values add up to the total (a sum of averages or of distinct counts does not). */
    public function isAdditive(): bool
    {
        return \in_array($this, [self::Clicks, self::Impressions, self::Visits, self::Pageviews], true);
    }
}
