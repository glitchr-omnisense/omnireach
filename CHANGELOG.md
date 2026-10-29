# Changelog

## Unreleased (1.x)

### Fixed
- An `UnreachableException` no longer quotes the URL called: its message names the method and the
  path (`... could not be reached for GetQueryStats (GET /json/GetQueryStats): ...`) and the HTTP
  client's reason without the address it quotes. Symfony's transport messages end with the whole
  URL, so Bing's `apikey=` stood in the message.

### Added
- `Omnireach\Http\JsonApi::reason()`: an HTTP client's message without the addresses it quotes.
