---
title: The Docker harness
order: 70
---

# The Docker harness

`docker/` composes this core and every provider (`docker/harness/plugins.php`) into one
installation, PHP 8.4 with Composer, in the image `omnisense/omnireach-harness`:

```sh
cd docker
docker compose run --rm omnireach test       # every package's tests, no network
docker compose run --rm omnireach live       # the checks that need no account (Matomo's public demo)
docker compose run --rm omnireach bare       # the bare script: plain PHP, and what PHP loaded
docker compose run --rm omnireach phpstan    # PHPStan at the highest level, each package
docker compose down -v                       # the harness's volume gone
```

## What it installs

At each start, `docker/harness/setup.php` writes the harness's `composer.json` and `phpunit.xml`
in the volume (`/harness`), and the entrypoint runs `composer install` the first time, `composer
update` when the `composer.json` changed:

- the core from its checkout (mounted at `/omnireach/core`, a path repository, symlinked);
- each provider from a checkout under `OMNIREACH_PLUGINS` when one is there - set it to `../..` in
  `docker/.env` (git-ignored) when the core sits in the workspace beside the providers - else from GitHub
  (`https://github.com/glitchr-omnisense/omnireach-<slug>.git`, branch 1.x, a plain git clone);
- `phpunit/phpunit` 11, `phpstan/phpstan` 2.1 and `symfony/http-client` for the tests;
- one PHPUnit suite per package (`core`, `bing-webmaster`, `ga4`, `matomo`, `plausible`,
  `search-console`) and a link `/harness/packages/<slug>` to each provider's directory.

The service uses Docker's default bridge network (no network of its own) and one named volume,
`omnireach-harness_harness`, which keeps the installation and Composer's cache between runs.

## The commands

| Command | Runs |
|---|---|
| `test` (the default) | `vendor/bin/phpunit --exclude-group live`, extra arguments passed on (`test --filter Matomo`) |
| `live` | `vendor/bin/phpunit --group live`: `Omnireach\Matomo\Tests\LiveTest`, two calls to demo.matomo.cloud |
| `bare` | `docker/harness/bin/bare`: a registry with the fixed provider, a search report, an audience report with a filter, `lastDays()`, a recorded JSON API and a refused key; it prints the results and every class and file PHP loaded, as JSON |
| `phpstan` | PHPStan on the core and each provider with its own `phpstan.neon`; fails when one fails |
| `composer ...`, `php ...`, `sh` | as they are, in `/harness` |

```
$ docker compose run --rm omnireach test
PHPUnit 11.5.57 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.4.26
Configuration: /harness/phpunit.xml

.........................................                         41 / 41 (100%)

Time: 00:00.890, Memory: 30.00 MB

OK (41 tests, 157 assertions)
```

(2026-10-26, every provider from its checkout)

`bare` prints, among the rest (2026-10-26):

```
    "searchTotals": {
        "clicks": 15,
        "impressions": 750
    },
    ...
    "refused": "Example refused the credentials (401) for it: Invalid API key",
```

and its `loaded.symbols` hold, besides PHP's own classes, only `Omnireach\...` and
`Symfony\Contracts\HttpClient\HttpClientInterface` and `ResponseInterface`: no framework class.

## Running a script of your own

Mount a directory and run PHP with the harness's autoloader (`/harness/vendor/autoload.php`):

```sh
docker compose run --rm -v "$PWD/scratch:/scratch" omnireach php /scratch/report.php
```

The examples of this manual were run that way. A provider with an account is called the same way,
its keys in `docker/.env` (git-ignored) or given with `-e NAME=value`.
