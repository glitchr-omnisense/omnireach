<?php

namespace Omnireach\Testing;

use Omnireach\Config;
use Omnireach\Model\Row;
use Omnireach\ProviderFactory;
use Omnireach\ProviderInterface;

/** FixedReach under the name "fixed", for a Registry. */
final class FixedReachFactory extends ProviderFactory
{
    /**
     * @param list<Row> $search
     * @param list<Row> $audience
     */
    public function __construct(private readonly array $search = [], private readonly array $audience = [])
    {
    }

    protected function populate(Config $c): void
    {
        $c->defaults(['omnireach.factory_name' => 'fixed', 'omnireach.factory_title' => 'Fixed', 'omnireach.required_options' => []]);
    }

    protected function build(Config $c): ProviderInterface
    {
        return new FixedReach($this->search, $this->audience, $c->string('site') ?? 'https://example.org/');
    }
}
