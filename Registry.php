<?php

namespace Omnireach;

use Omnireach\Exception\InvalidConfigException;
use Omnireach\Exception\NotSupportedException;

/**
 * The application's audience providers by name, each built once from its
 * factory and options:
 *
 *   $registry = new Registry([new SearchConsoleFactory($http), new MatomoFactory($http)], [
 *       'google' => ['factory' => 'search-console', 'options' => ['site' => 'sc-domain:example.org', 'credentials' => '...']],
 *       'stats' => ['factory' => 'matomo', 'options' => ['url' => 'https://stats.example.org', 'site' => '1', 'token' => '...']],
 *   ]);
 *
 *   $registry->search('google')->searchPerformance(ReachQuery::lastDays(28, [Dimension::Query]));
 *   $registry->audience('stats')->audience(ReachQuery::lastDays(28, [Dimension::EntryPage]));
 */
final class Registry
{
    /** @var array<string, ProviderFactoryInterface> */
    private array $factories = [];

    /** @var array<string, ProviderInterface> */
    private array $providers = [];

    /**
     * @param iterable<ProviderFactoryInterface>                                     $factories
     * @param array<string, array{factory: string, options?: array<string, mixed>}> $config
     */
    public function __construct(iterable $factories, private readonly array $config)
    {
        foreach ($factories as $factory) {
            $this->factories[$factory->getName()] = $factory;
        }
    }

    public function get(string $name): ProviderInterface
    {
        return $this->providers[$name] ??= $this->factory($name)->create($this->config[$name]['options'] ?? []);
    }

    public function has(string $name): bool
    {
        return isset($this->config[$name]);
    }

    /** The provider, as a search engine's report. */
    public function search(string $name): SearchPerformanceInterface
    {
        $provider = $this->get($name);

        return $provider instanceof SearchPerformanceInterface ? $provider : throw NotSupportedException::capability($provider, $name, 'report search performance');
    }

    /** The provider, as an analytics report. */
    public function audience(string $name): AudienceInterface
    {
        $provider = $this->get($name);

        return $provider instanceof AudienceInterface ? $provider : throw NotSupportedException::capability($provider, $name, 'report the audience');
    }

    /** @return array<string, mixed> the options a provider is configured with */
    public function options(string $name): array
    {
        return $this->config[$name]['options'] ?? [];
    }

    /** @return list<string> the configured providers' names */
    public function names(): array
    {
        return array_keys($this->config);
    }

    /** @return list<string> the factories installed: what `factory:` may name */
    public function factories(): array
    {
        return array_keys($this->factories);
    }

    private function factory(string $name): ProviderFactoryInterface
    {
        $provider = $this->config[$name] ?? throw new InvalidConfigException(\sprintf('No "%s" audience provider; configured: %s.', $name, implode(', ', array_keys($this->config)) ?: 'none'));

        return $this->factories[$provider['factory']] ?? throw new InvalidConfigException(\sprintf('No "%s" factory for the "%s" audience provider; installed: %s.', $provider['factory'], $name, implode(', ', array_keys($this->factories)) ?: 'none'));
    }
}
