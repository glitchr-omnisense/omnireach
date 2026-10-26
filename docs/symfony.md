---
title: Symfony
order: 15
---

# Symfony

There is no Omnireach bundle, and omnibridge/symfony has no Omnireach wiring yet: a Symfony
application declares the registry as a service of its own. Every factory takes the HTTP client as
its one constructor argument (`?Symfony\Contracts\HttpClient\HttpClientInterface`), which
autowiring fills with the application's `http_client` service (FrameworkBundle's `http_client`
section, enabled by default when `symfony/http-client` is installed).

## The registry as a service

```yaml
# config/services.yaml
services:
    _defaults:
        autowire: true

    Omnireach\SearchConsole\SearchConsoleFactory: ~
    Omnireach\Matomo\MatomoFactory: ~

    Omnireach\Registry:
        arguments:
            $factories:
                - '@Omnireach\SearchConsole\SearchConsoleFactory'
                - '@Omnireach\Matomo\MatomoFactory'
            $config:
                google:
                    factory: search-console
                    options:
                        site: 'sc-domain:example.org'
                        credentials: '%env(resolve:GOOGLE_SERVICE_ACCOUNT)%'
                stats:
                    factory: matomo
                    options:
                        url: 'https://stats.example.org'
                        site: '1'
                        token: '%env(MATOMO_TOKEN)%'
```

```sh
# .env.local, or bin/console secrets:set
GOOGLE_SERVICE_ACCOUNT=%kernel.project_dir%/config/secrets/search-console.json   # the key's path, or its JSON
MATOMO_TOKEN=...
```

`credentials` takes the service account's JSON itself or the path of its file: a secret holding
the JSON works as well as a path. The factories can also be gathered by a tag rather than listed:

```yaml
services:
    _instanceof:
        Omnireach\ProviderFactoryInterface:
            tags: ['omnireach.factory']

    Omnireach\Registry:
        arguments:
            $factories: !tagged_iterator omnireach.factory
            $config: '%omnireach.providers%'
```

with the factories declared as services (or loaded by a `resource:` line) and the providers'
configuration in a parameter `omnireach.providers` of the same shape as `$config` above.

## Using it

```php
use Omnireach\Model\Dimension;
use Omnireach\Model\ReachQuery;
use Omnireach\Registry;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

final class SearchPanel
{
    public function __construct(private readonly Registry $reach, private readonly CacheInterface $cache)
    {
    }

    /** @return array<string, mixed> */
    public function queries(): array
    {
        return $this->cache->get('reach.queries.28', function (ItemInterface $item): array {
            $item->expiresAfter(6 * 3600);

            return $this->reach->search('google')->searchPerformance(ReachQuery::lastDays(28, [Dimension::Query]))->toArray();
        });
    }
}
```

Nothing is built when the container compiles: a provider is built, and its required options
checked, the first time the registry is asked for it (`get()`, `search()`, `audience()`), then kept
for the rest of the request. A report is not cached by Omnireach: the tools' quotas (Search
Console's load quotas, Plausible's 600 requests an hour) make the application's cache worth it, as
above; `Report::toArray()` gives a plain array to keep.

There is no command, event, Twig function or route: the registry is a service, used from the
application's controllers, message handlers and commands.

This page was written from the classes' constructors; it was not run in a Symfony kernel.
