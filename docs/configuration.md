---
title: Configuration reference
order: 40
---

# Configuration reference

## The registry's configuration

`new Registry($factories, $config)` takes the providers by name, each with its factory and options:

```php
[
    '<provider name>' => [
        'factory' => '<factory name>',     // required: a factory's getName()
        'options' => [/* ... */],          // optional, default []: handed to the factory's create()
    ],
]
```

The provider name is the application's (`google`, `bing`, `stats`); the factory name is the
package's. Options are checked when the provider is first asked for, not when the registry is
built: a required option missing, `''` or `[]` is an `InvalidConfigException` naming it.

Every factory takes the HTTP client in its constructor - `new MatomoFactory($http)` - and refuses
to build without one. Options read as strings accept any scalar (a site id given as an integer
included); an empty string is the same as an option not given.

## omnireach/search-console

Factory `Omnireach\SearchConsole\SearchConsoleFactory`, name `search-console`.

| Option | Type | Default | Required | |
|---|---|---|---|---|
| `site` | string | - | yes | the property: `sc-domain:example.org` (a domain property) or `https://example.org/` (a URL-prefix property) |
| `credentials` | string | `''` | one of the two | a service account's JSON key, or the path of its file; the account added as a user of the property |
| `access_token` | string | `''` | one of the two | a token with the `webmasters.readonly` scope, obtained elsewhere; used as it is, never refreshed |
| `data_state` | string | `final` | no | `final`: complete days only; `all`: the last days too, provisional (the report's note says so) |
| `url` | string | `https://searchconsole.googleapis.com/webmasters/v3` | no | the API's base URL, trailing slash removed |

With neither `credentials` nor `access_token`, `create()` throws `InvalidConfigException` (`A
Google API needs a service account's key or an access token.`); credentials that are neither a key
nor the path of one too. With both, the access token is used and the key never.

## omnireach/bing-webmaster

Factory `Omnireach\BingWebmaster\BingWebmasterFactory`, name `bing-webmaster`.

| Option | Type | Default | Required | |
|---|---|---|---|---|
| `api_key` | string | - | yes | Bing Webmaster Tools, Settings, API access |
| `site` | string | - | yes | the site as verified there: `https://example.org/` |
| `url` | string | `https://ssl.bing.com/webmaster/api.svc/json` | no | the JSON endpoint's base URL |

## omnireach/matomo

Factory `Omnireach\Matomo\MatomoFactory`, name `matomo`.

| Option | Type | Default | Required | |
|---|---|---|---|---|
| `url` | string | - | yes | the installation: `https://stats.example.org`, `https://example.matomo.cloud` (trailing slash removed; `/index.php` is added) |
| `site` | string | - | yes | the site's id (`idSite`) |
| `token` | string | `anonymous` | no | a `token_auth` with view access; `anonymous` for reports anyone may view |

## omnireach/plausible

Factory `Omnireach\Plausible\PlausibleFactory`, name `plausible`.

| Option | Type | Default | Required | |
|---|---|---|---|---|
| `api_key` | string | - | yes | a Stats API key |
| `site` | string | - | yes | the site's domain as Plausible knows it: `example.org` |
| `url` | string | `https://plausible.io` | no | plausible.io, or a self-hosted Community Edition |

## omnireach/ga4

Factory `Omnireach\Ga4\Ga4Factory`, name `ga4`.

| Option | Type | Default | Required | |
|---|---|---|---|---|
| `property` | string | - | yes | the property's numeric id (Admin, Property details), not the `G-` measurement id |
| `credentials` | string | `''` | one of the two | a service account's JSON key, or the path of its file; the account given the Viewer role on the property |
| `access_token` | string | `''` | one of the two | a token with the `analytics.readonly` scope, obtained elsewhere |
| `url` | string | `https://analyticsdata.googleapis.com/v1beta` | no | the Data API's base URL |

## Per query

What a report covers is the query's, not the provider's options: the period, the dimensions, the
metrics, the filters, the limit ([Concepts](concepts.md#the-query)). `ReachQuery::$site`, when set,
replaces the provider's `site` (or `property`) for that report only.

## The fixed provider (testing)

`Omnireach\Testing\FixedReachFactory`, name `fixed`, takes its rows in its constructor
(`new FixedReachFactory($searchRows, $audienceRows)`) and one option:

| Option | Type | Default | Required | |
|---|---|---|---|---|
| `site` | string | `https://example.org/` | no | the `site` its reports name |
