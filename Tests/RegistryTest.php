<?php

namespace Omnireach\Tests;

use Omnireach\Capabilities;
use Omnireach\Config;
use Omnireach\Exception\InvalidConfigException;
use Omnireach\Exception\InvalidKeyException;
use Omnireach\Exception\NotFoundException;
use Omnireach\Exception\NotSupportedException;
use Omnireach\Exception\ProviderException;
use Omnireach\Http\JsonApi;
use Omnireach\Model\Dimension;
use Omnireach\Model\Metric;
use Omnireach\Model\ReachQuery;
use Omnireach\Model\Report;
use Omnireach\Model\Row;
use Omnireach\ProviderFactory;
use Omnireach\ProviderInterface;
use Omnireach\Registry;
use Omnireach\SearchPerformanceInterface;
use Omnireach\Testing\FixedReachFactory;
use Omnireach\Testing\RecordedHttpClient;
use PHPUnit\Framework\TestCase;

final class RegistryTest extends TestCase
{
    public function testProvidersByNameEachBuiltOnce(): void
    {
        $registry = new Registry([new FixedReachFactory([new Row(['query' => 'q'], ['clicks' => 1])]), self::searchOnly()], [
            'site' => ['factory' => 'fixed'],
            'bing' => ['factory' => 'search', 'options' => ['api_key' => 'example-not-a-key']],
        ]);

        self::assertSame($registry->get('site'), $registry->search('site'));
        self::assertSame($registry->get('site'), $registry->audience('site'));
        self::assertSame(['site', 'bing'], $registry->names());
        self::assertSame(['fixed', 'search'], $registry->factories());
        self::assertSame(['api_key' => 'example-not-a-key'], $registry->options('bing'));
        self::assertSame(['searchPerformance', 'audience'], $registry->get('site')->capabilities()->contracts());
        self::assertSame([['query' => 'q']], array_map(static fn (Row $r) => $r->dimensions, $registry->search('site')->searchPerformance(new ReachQuery(new \DateTimeImmutable('2026-10-01'), new \DateTimeImmutable('2026-10-01'), [Dimension::Query]))->rows));

        try {
            $registry->audience('bing');
            self::fail('A search engine only.');
        } catch (NotSupportedException $e) {
            self::assertStringContainsString('does not report the audience', $e->getMessage());
        }
        $this->expectException(InvalidConfigException::class);
        $registry->get('elsewhere');
    }

    public function testARequiredOption(): void
    {
        $this->expectExceptionMessage('The "search" provider needs: api_key.');
        (new Registry([self::searchOnly()], ['bing' => ['factory' => 'search']]))->get('bing');
    }

    public function testConfig(): void
    {
        $config = new Config(['a' => 'yes', 'b' => '0', 'c' => '12', 'd' => '']);

        self::assertSame([true, false, 12, null, 'fallback'], [$config->bool('a'), $config->bool('b'), $config->int('c'), $config->string('d'), $config->defaults(['d2' => 'fallback'])->get('d2')]);
    }

    public function testJsonApiTurnsFailuresIntoOmnireachExceptions(): void
    {
        $api = new JsonApi(RecordedHttpClient::routes([
            'GET /ok' => '{"rows":[{"clicks":1}]}',
            'GET /refused' => [403, '{"error":{"code":403,"message":"User does not have sufficient permission for site"}}'],
            'GET /broken' => [400, '{"error":"Invalid metric"}'],
        ]), 'Example');

        self::assertSame(1, $api->request('GET', 'https://x.example/ok')->list('rows')[0]->int('clicks'));
        foreach (['/refused' => [InvalidKeyException::class, 'Example refused the credentials (403) for it: User does not have sufficient permission for site'], '/missing' => [NotFoundException::class, 'Example has no it: not recorded: GET /missing'], '/broken' => [ProviderException::class, 'Example answered 400 for it: Invalid metric']] as $path => [$class, $message]) {
            try {
                $api->request('GET', 'https://x.example'.$path, [], 'it');
                self::fail($path);
            } catch (ProviderException $e) {
                self::assertSame([$class, $message], [$e::class, $e->getMessage()]);
            }
        }
    }

    /**
     * Not reached: the message names the method and the path, and the HTTP client's reason
     * without its address - Symfony's transport messages quote the URL, query and key included.
     */
    public function testAnUnreachableApiIsNamedByItsPathNeverItsQuery(): void
    {
        $http = new \Symfony\Component\HttpClient\MockHttpClient(static function (string $method, string $url): never {
            throw new \Symfony\Component\HttpClient\Exception\TransportException(\sprintf('Could not resolve host: api.example for "%s".', $url));
        });

        try {
            (new JsonApi($http, 'Example'))->request('GET', 'https://user:pass@api.example/v1/stats', ['query' => ['site' => 'a.example', 'apikey' => 'sk-secret-123']], 'the statistics');
            self::fail('no exception');
        } catch (\Omnireach\Exception\UnreachableException $e) {
            self::assertSame('Example could not be reached for the statistics (GET /v1/stats): Could not resolve host: api.example.', $e->getMessage());
            self::assertStringNotContainsString('sk-secret-123', $e->getMessage());
            self::assertStringNotContainsString('pass', $e->getMessage());
        }
    }

    /**
     * Nothing of the key anywhere in what a log of the whole chain writes: the messages, the
     * previous exception's (a copy of the HTTP client's, its class and code kept, the key's
     * parameter and the URL's password masked), and the traces' arguments, which PHP keeps
     * unless zend.exception_ignore_args is on.
     */
    public function testNoPartOfAnUnreachableChainHoldsTheKey(): void
    {
        $ignoreArgs = ini_set('zend.exception_ignore_args', '0');
        $http = new \Symfony\Component\HttpClient\MockHttpClient(static function (string $method, string $url): never {
            throw new \Symfony\Component\HttpClient\Exception\TransportException(\sprintf('Could not resolve host: api.example for "%s".', $url), 6);
        });

        try {
            (new JsonApi($http, 'Example'))->request('GET', 'https://user:url-password-123@api.example/v1/stats', ['query' => ['site' => 'a.example', 'apikey' => 'sk-secret-123'], 'auth_bearer' => 'bearer-secret-456'], 'the statistics');
            self::fail('no exception');
        } catch (\Omnireach\Exception\UnreachableException $e) {
            $previous = $e->getPrevious();
            self::assertInstanceOf(\Symfony\Component\HttpClient\Exception\TransportException::class, $previous);
            self::assertSame(6, $previous->getCode());
            self::assertStringContainsString('apikey=***', $previous->getMessage());
            for ($one = $e; null !== $one; $one = $one->getPrevious()) {
                foreach (['sk-secret-123', 'url-password-123', 'bearer-secret-456'] as $secret) {
                    self::assertStringNotContainsString($secret, $one->getMessage());
                    self::assertStringNotContainsString($secret, print_r($one->getTrace(), true));
                    self::assertStringNotContainsString($secret, $one->getTraceAsString());
                }
            }
            self::assertStringNotContainsString('sk-secret-123', (string) $e);
        } finally {
            ini_set('zend.exception_ignore_args', false !== $ignoreArgs ? $ignoreArgs : '1');
        }
    }

    private static function searchOnly(): ProviderFactory
    {
        return new class extends ProviderFactory {
            protected function populate(Config $c): void
            {
                $c->defaults(['omnireach.factory_name' => 'search', 'omnireach.factory_title' => 'Search', 'omnireach.required_options' => ['api_key']]);
            }

            protected function build(Config $c): ProviderInterface
            {
                return new class implements SearchPerformanceInterface {
                    public function getName(): string
                    {
                        return 'search';
                    }

                    public function getTitle(): string
                    {
                        return 'Search';
                    }

                    public function capabilities(): Capabilities
                    {
                        return new Capabilities(searchPerformance: true, dimensions: [Dimension::Query], metrics: [Metric::Clicks]);
                    }

                    public function searchPerformance(ReachQuery $query): Report
                    {
                        return new Report('search', 'x', $query, []);
                    }
                };
            }
        };
    }

    /**
     * An error answered - the key refused, nothing there, a server error, a body that is not JSON:
     * the exception thrown where the request's options stood keeps no argument in its trace, so a
     * log of it (zend.exception_ignore_args off) holds no key.
     */
    public function testAnErrorAnswerKeepsNoKeyInItsTrace(): void
    {
        $ignoreArgs = ini_set('zend.exception_ignore_args', '0');
        try {
            foreach ([[401, '{"message": "no"}'], [404, '{}'], [500, '{"message": "down"}'], [200, 'plain text']] as [$status, $body]) {
                $http = new \Symfony\Component\HttpClient\MockHttpClient(new \Symfony\Component\HttpClient\Response\MockResponse($body, ['http_code' => $status]));
                try {
                    (new JsonApi($http, 'Example'))->request('GET', 'https://api.example/v1/stats', ['query' => ['apikey' => 'error-key-1234567'], 'auth_bearer' => 'error-token-1234567'], 'the statistics');
                    if (404 === $status) {
                        continue;
                    }
                    self::fail("no exception on $status");
                } catch (\Omnireach\Exception\OmnireachException $e) {
                    self::assertNotEmpty($e->getTrace());
                    foreach (['error-key-1234567', 'error-token-1234567'] as $secret) {
                        self::assertFalse(str_contains($e->getMessage(), $secret), $e->getMessage());
                        self::assertFalse(str_contains(print_r($e->getTrace(), true), $secret), "$status: the trace holds $secret");
                    }
                }
            }
        } finally {
            ini_set('zend.exception_ignore_args', false !== $ignoreArgs ? $ignoreArgs : '1');
        }
    }
}
