<?php

namespace Omnireach;

/** Builds a provider from its options (a key, a site, a server URL...). */
interface ProviderFactoryInterface
{
    /** The name providers are configured with: "search-console", "matomo"... */
    public function getName(): string;

    /** @param array<string, mixed> $options */
    public function create(array $options = []): ProviderInterface;
}
