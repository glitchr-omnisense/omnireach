---
title: Errors
order: 50
---

# Errors

Every exception Omnireach throws implements `Omnireach\Exception\OmnireachException` (a
`\Throwable`):

| Exception | Extends | When |
|---|---|---|
| `InvalidArgumentException` | `\InvalidArgumentException` | a query no report can answer: the period ends before it starts, a limit below 1, a filter on no dimension |
| `InvalidConfigException` | `\InvalidArgumentException` | a provider not configured, a factory not installed, a required option missing or empty, a factory given no HTTP client, Google credentials missing or unreadable |
| `NotSupportedException` | `\LogicException` | a provider asked for what it cannot do: a dimension, a filter or a metric it does not report, dimensions it cannot combine, search performance from an analytics tool (`Registry::search()`) or the reverse. Thrown before any call |
| `ProviderException` | `\RuntimeException` | the tool answered, and not with what was asked: an error status, a body that is not JSON, an error inside a 200 (Matomo), no token from Google |
| `InvalidKeyException` | `ProviderException` | the tool refused the credentials: 401 or 403, or Matomo's "access"/"token" error |
| `NotFoundException` | `ProviderException` | nothing under that identifier: 404 (a site or property that does not exist, or not this account's) |
| `UnreachableException` | `\RuntimeException` | the tool could not be reached: a network error, a timeout |

`ProviderException`'s code is the HTTP status (200 for an error carried in a 200 answer or a body
that is not JSON); `UnreachableException`'s is 0 and its previous exception a copy of the HTTP
client's.
`UnreachableException`'s message names the method and the path called, and the HTTP client's
reason without the address it quotes (`JsonApi::reason()`): never the query string, where Bing's
API key travels. The previous exception is a copy of the client's (`Http\Redaction::copy()`): the
same class and code, the message with the URL's key parameters and password masked (`apikey=***`),
its own previous copied the same way. Neither keeps its trace's arguments, where the request's
query, headers and bearer token stood: a log that writes the whole chain, arguments included
(`zend.exception_ignore_args` off), holds no key. Nor does an error answered (a refused key, nothing there, a server error, a body
that is not JSON): every exception `JsonApi::request()` throws leaves its trace's arguments out.

## The messages

Seen in the [harness](harness.md) on 2026-10-26, Plausible's provider on recorded answers, Bing's
on a host that does not exist (its line as the message reads since 2026-10-29, the key no longer
in it):

```
401       InvalidKeyException (code 401): Plausible refused the credentials (401) for the query: Invalid API key or site ID
404       NotFoundException (code 404): Plausible has no the query: not found
429       ProviderException (code 429): Plausible answered 429 for the query: Too many requests
500 html  ProviderException (code 500): Plausible answered 500 for the query.
200 html  ProviderException (code 200): Plausible answered 200 for the query: an answer that is not JSON
network   UnreachableException: Bing Webmaster could not be reached for GetQueryStats (GET /json/GetQueryStats): Could not resolve host: bing.invalid.
query     InvalidArgumentException: The period ends (2026-10-01) before it starts (2026-10-02).
query     InvalidArgumentException: A report has at least one row: limit 0.
query     InvalidArgumentException: No "city" dimension to filter on.
config    InvalidConfigException: The "plausible" provider needs: api_key.
config    InvalidConfigException: The "plausible" provider needs an HTTP client: give one to PlausibleFactory.
config    InvalidConfigException: A Google API needs a service account's key or an access token.
config    InvalidConfigException: The credentials are not a service account's JSON key (client_email, private_key), nor the path of one.
registry  InvalidConfigException: No "y" audience provider; configured: x.
registry  InvalidConfigException: No "matomo" factory for the "x" audience provider; installed: none.
```

The tool's own message is added after a colon when the answer carries one
([Concepts](concepts.md#the-http-layer)); the tool's error code is not kept apart.


## What to catch where

| Where | Catch |
|---|---|
| a dashboard panel | `OmnireachException`: show "unavailable" and the last report cached, log the message |
| a screen that offers several providers | `NotSupportedException`, or check `capabilities()` first: the provider cannot answer that query |
| a scheduled export | `UnreachableException` and a `ProviderException` of code 429 or 5xx: retry later; `InvalidKeyException`: alert someone, the key was revoked or lost its access |
| building the registry, a deploy | `InvalidConfigException`: a configuration error, to fix, not to retry |
| a form that builds a query | `Omnireach\Exception\InvalidArgumentException`: the period or the limit typed is wrong |

A tool's message is the tool's, untranslated: show a message of the application's, keep the
tool's for the logs.
