<?php

namespace Omnireach;

/**
 * One outside tool that knows the site's audience, configured: Search
 * Console, Bing Webmaster Tools, Matomo, Plausible, Google Analytics...
 * Its Capabilities say what it can be asked - how the site shows in search
 * results (SearchPerformanceInterface), who visits it (AudienceInterface).
 */
interface ProviderInterface
{
    /** The factory's name: "search-console", "matomo"... */
    public function getName(): string;

    public function getTitle(): string;

    public function capabilities(): Capabilities;
}
