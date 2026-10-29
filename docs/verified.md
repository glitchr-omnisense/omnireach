---
title: Verified, and not
order: 90
---

# Verified, and not

## What the tests prove

Run on 2026-10-26 in the [harness](harness.md), every package from its checkout, PHP 8.4,
PHPUnit 11.5: 41 tests, 157 assertions, green.

- The core (`Tests/ModelTest.php`, `Tests/RegistryTest.php`): the query (whole days, its
  validation, filters then limit, `lastDays()` ending yesterday), the capabilities refusing what a
  provider cannot report, reports and their totals, the registry and its typed getters,
  `JsonApi`'s errors.
- In plain PHP (`Tests/BareTest.php`): reports asked through a `Registry` and a recorded JSON API,
  loading nothing but Omnireach and Symfony's HTTP client contracts.
- Each provider: its requests and its mapping on answers recorded or composed (below), its
  refusals, a refused key, its factory through a `Registry`, and a `BareTest` loading every class
  with the package's requirements alone.
- PHPStan at the highest level on the core and the five providers, no error baselined
  (`docker compose run --rm omnireach phpstan`, 2026-10-26).

| Provider | Answers its tests use |
|---|---|
| search-console | composed in the shape the Search Console reference documents; the service account's JWT checked with a key made by the test |
| bing-webmaster | the JSON sample of `GetQueryStats` in Bing's reference, and rows composed in its shape |
| matomo | recorded from Matomo's public demo (demo.matomo.cloud, site 1) on 2026-10-24, trimmed of unread fields |
| plausible | composed in the shape the Stats API v2 documents |
| ga4 | composed in the shape the `runReport` reference documents; a service account's token with a key made by the test |

There is no conformance suite shared by the providers ([Testing](testing.md#no-conformance-suite)).

## What was run live

- `docker compose run --rm omnireach live` (`Omnireach\Matomo\Tests\LiveTest`), 2026-10-24:
  Matomo's public demo, the whole site over a week and its entry pages day by day.
- For this manual, 2026-10-26, 14:49-14:51 UTC: the [Installation](installation.md#a-providers-api)
  script against the same demo, and a report by device and with device filters
  ([Audience](usage-audience.md#devices)), which showed two defects of Matomo's devices, fixed on
  2026-10-29 (three `mobile` rows, a `mobile` filter refused).
  Every segment the demo was asked for (`deviceType==smartphone`, `deviceType==desktop`) answered
  0 visits: the demo does not process segments on the fly, so filters on Matomo were not verified
  live.

## What is not verified

- Search Console, Bing Webmaster, Plausible and Google Analytics were never called: each needs an
  account and a key. Their answers in the tests follow their published references.
- A self-hosted Matomo with a token.
- Positions as Bing gives them (`AvgImpressionPosition`), and how Search Console and Plausible
  match a device filter's case (`mobile` is sent as given).
- The [Symfony](symfony.md) and [Laravel](laravel.md) pages were written from the classes'
  constructors, not run in a kernel or an application.

## Known defects

None left: those the manuals found were fixed on 2026-10-29.

## Responsibility

`omnisense/omnireach` and its `omnireach/*` providers are unofficial clients, affiliated with none
of the services they reach. The MIT licence covers the code only and grants no right to any
service or to its data. Whoever runs them complies with each service's terms (named in each
provider's README and manual, and in `Capabilities::$terms`) and obtains what they require.
