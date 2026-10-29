<?php

namespace Omnireach\Http;

use Omnireach\Exception\InvalidKeyException;
use Omnireach\Exception\NotFoundException;
use Omnireach\Exception\ProviderException;
use Omnireach\Exception\UnreachableException;
use Omnireach\Support\Data;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * A JSON API called through the application's HTTP client (Symfony's
 * contracts: its own client, or a MockHttpClient in a test), the answer
 * turned into Data and every failure into one of omnireach's exceptions:
 * not reached (UnreachableException), the key refused (401, 403:
 * InvalidKeyException), nothing there (404: NotFoundException), any other
 * error or an answer that is not JSON (ProviderException) - with the
 * provider's own message when it gives one.
 *
 * An UnreachableException's message names the method and the path, and the
 * HTTP client's reason without the address it quotes: never the query, where
 * a key may travel (Bing's apikey). Its previous exception is a copy of the
 * client's (Redaction::copy(): same class and code, the key masked in its
 * message), and neither keeps its trace's arguments, where the request's
 * query and headers stood.
 */
final class JsonApi
{
    public function __construct(private readonly HttpClientInterface $http, private readonly string $provider)
    {
    }

    /** @param array<string, mixed> $options the HTTP client's options (query, headers, json, body, auth_bearer...) */
    public function request(string $method, string $url, array $options = [], string $what = 'the request'): Data
    {
        try {
            $response = $this->http->request($method, $url, $options);
            $status = $response->getStatusCode();
            $body = $response->getContent(false);
        } catch (TransportExceptionInterface $e) {
            throw Redaction::withoutArguments(new UnreachableException(\sprintf('%s could not be reached for %s (%s %s): %s', $this->provider, $what, $method, parse_url($url, \PHP_URL_PATH) ?: '/', self::reason($e->getMessage())), 0, Redaction::copy($e, Redaction::secrets($url, $options))));
        }
        $decoded = json_decode($body, true);
        $message = self::message(Data::of($decoded));
        if (401 === $status || 403 === $status) {
            throw InvalidKeyException::refused($this->provider, $what, $status, $message);
        }
        if (404 === $status) {
            throw NotFoundException::of($this->provider, $what, $message);
        }
        if ($status >= 400) {
            throw ProviderException::answered($this->provider, $what, $status, $message);
        }
        if (!\is_array($decoded)) {
            throw ProviderException::answered($this->provider, $what, $status, 'an answer that is not JSON');
        }

        return new Data($decoded);
    }

    /**
     * An HTTP client's message without the addresses it quotes: Symfony's
     * ' for "<url>"' dropped, any other address cut to its scheme, host and
     * path (no credentials, no query, no fragment).
     */
    public static function reason(string $message): string
    {
        $message = (string) preg_replace('~\s+for\s+"[a-z][a-z0-9+.\-]*://[^"]*"~i', '', $message);
        $message = (string) preg_replace_callback('~[a-z][a-z0-9+.\-]*://[^\s"\'<>]+~i', static function (array $match): string {
            $parts = parse_url($match[0]);
            if (false === $parts || !isset($parts['scheme'], $parts['host'])) {
                return '(an address)';
            }

            return $parts['scheme'].'://'.$parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : '').($parts['path'] ?? '');
        }, $message);
        $message = rtrim(trim($message), '.');

        return '' !== $message ? $message.'.' : 'no reason given.';
    }

    /** The error message an answer carries, under the names APIs give it. */
    public static function message(Data $answer): ?string
    {
        foreach (['error_description', 'error.message', 'message', 'Message', 'detail', 'errors.0.message', 'errors.0.detail', 'fault.faultstring', 'error'] as $path) {
            if (null !== ($message = $answer->string($path))) {
                return $message;
            }
        }

        return null;
    }
}
