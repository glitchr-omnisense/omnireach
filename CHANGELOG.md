# Changelog

## Unreleased (1.x)

### Fixed
- An `UnreachableException`'s chain no longer holds the key. Its previous exception was the HTTP
  client's own, whose message quotes the whole URL (Bing's `apikey=`, a URL's password), and every
  trace kept the arguments of the calls under it, the request's query, headers and bearer token
  among them. The previous is now a copy of the client's exception (`Http\Redaction::copy()`: the
  same class and code, the key's parameters, the URL's user part and the request's secrets masked
  as `***`), and no trace of the chain keeps its arguments (`Redaction::withoutArguments()`).
- An `UnreachableException` no longer quotes the URL called: its message names the method and the
  path (`... could not be reached for GetQueryStats (GET /json/GetQueryStats): ...`) and the HTTP
  client's reason without the address it quotes. Symfony's transport messages end with the whole
  URL, so Bing's `apikey=` stood in the message.

### Added
- `Omnireach\Http\Redaction`: a request's secrets (`secrets()`), a text without them (`text()`),
  a failure's copy to wrap in its place (`copy()`), a trace without arguments
  (`withoutArguments()`).
- `Omnireach\Http\JsonApi::reason()`: an HTTP client's message without the addresses it quotes.
