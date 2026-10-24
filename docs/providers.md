# Writing a provider

A provider is a package `omnireach/<name>` with a factory and a provider class:

- the factory extends `Omnireach\ProviderFactory`: `populate()` names it, lists its required
  options and their defaults; `build()` returns the provider. The HTTP client comes through the
  factory's constructor, never created inside.
- the provider implements `SearchPerformanceInterface` or `AudienceInterface` (or both) and
  describes itself in `capabilities()`: its dimensions and metrics, whether dimensions combine,
  its terms and limits. A report starts with `$this->capabilities()->check($query, $metrics, ...)`
  and ends with `$query->apply($rows)`.
- values in the family's units: ratios from 0 to 1 (a bounce rate given in percent is divided),
  seconds, dates Y-m-d, devices desktop/mobile/tablet, countries upper case; a metric the
  provider does not give for a row is left out of it, never zero.
- calls go through `Http\JsonApi`; answers are read with `Support\Data`.
- tests run in the core's harness (`docker/harness/plugins.php`) with recorded or documented
  answers, said so in the test, a `BareTest` (`Testing\LoadCheck`), and a `LiveTest` (group
  `live`) only for what needs no account.
