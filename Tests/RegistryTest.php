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
}
