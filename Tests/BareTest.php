<?php

namespace Omnireach\Tests;

use Omnireach\Registry;
use Omnireach\Support\Data;
use Omnireach\Testing\LoadCheck;
use PHPUnit\Framework\TestCase;

/**
 * omnisense/omnireach with no framework: in PHP processes of their own
 * (this one has loaded PHPUnit), the harness's bare script asks search
 * and audience reports through a Registry and calls a recorded JSON API - and every
 * class of the package is loaded. Nothing may come from outside the
 * package but what it requires: Symfony's HTTP client contracts
 * (interfaces only) - no framework, no HTTP client, no application, even
 * in the harness where they are installed beside it.
 */
final class BareTest extends TestCase
{
    private const ALLOWED = ['/vendor/symfony/http-client-contracts/', '/vendor/symfony/service-contracts/'];

    public function testReportsAskedByHandLoadNothingButOmnireachAndWhatItRequires(): void
    {
        $autoload = self::autoload();
        $process = proc_open([\PHP_BINARY, __DIR__.'/../docker/harness/bin/bare'], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, ['OMNIREACH_AUTOLOAD' => $autoload] + getenv());
        self::assertIsResource($process);
        $out = (string) stream_get_contents($pipes[1]);
        $err = (string) stream_get_contents($pipes[2]);
        self::assertSame(0, proc_close($process), $err.$out);
        $report = Data::of(json_decode($out, true));

        self::assertSame(['query' => 'atelier poterie strasbourg'], $report->raw('search.rows.0.dimensions'));
        self::assertSame(2, \count((array) $report->raw('search.rows')), 'the September row is outside the period');
        self::assertSame(['clicks' => 15, 'impressions' => 750], $report->raw('searchTotals'));
        self::assertSame('https://atelier.example.org/', $report->string('search.site'));
        self::assertSame([['/', 260], ['/ateliers', 110]], $report->raw('entries'), 'filtered on the source, projected on the entry page');
        self::assertSame(['2026-09-26', '2026-10-23'], $report->raw('lastDays'));
        self::assertSame(['/ateliers', 120], $report->raw('api'));
        self::assertSame('Example refused the credentials (401) for it: Invalid API key', $report->string('refused'));

        $symbols = array_values(array_filter((array) $report->raw('loaded.symbols'), 'is_string'));
        self::assertContains('Omnireach\Registry', $symbols);
        self::assertSame([], LoadCheck::foreign($symbols), 'no class of a framework, an HTTP client or an application');
        $eager = array_map('dirname', array_values(array_filter((array) $report->raw('loaded.eager'), 'is_string')));
        $files = array_values(array_filter((array) $report->raw('loaded.files'), static fn ($f) => \is_string($f) && !\in_array(\dirname($f), $eager, true)));
        self::assertSame([], LoadCheck::outside(array_values(array_filter($files, 'is_string')), \dirname(__DIR__), self::ALLOWED), 'no file but the package and what it requires');
    }

    public function testEveryClassLoadsWithTheRequiredPackagesAlone(): void
    {
        $report = LoadCheck::run(\dirname(__DIR__), 'Omnireach\\', self::autoload());

        self::assertGreaterThan(20, \count($report['loaded']));
        self::assertContains('Omnireach\Testing\FixedReach', $report['loaded']);
        self::assertSame([], $report['foreign']);
        self::assertSame([], LoadCheck::outside($report['files'], \dirname(__DIR__), self::ALLOWED));
    }

    /** The check is not blind: an HTTP client's class, once loaded, is seen. */
    public function testTheReportSeesWhatIsLoaded(): void
    {
        if (!class_exists(\Symfony\Component\HttpClient\MockHttpClient::class)) {
            self::markTestSkipped('symfony/http-client is not installed.');
        }
        $report = LoadCheck::run(\dirname(__DIR__), 'Omnireach\\', self::autoload(), [\Symfony\Component\HttpClient\MockHttpClient::class]);

        self::assertContains(\Symfony\Component\HttpClient\MockHttpClient::class, $report['foreign']);
        self::assertNotEmpty(preg_grep('~/symfony/http-client/~', LoadCheck::outside($report['files'], \dirname(__DIR__), self::ALLOWED)));
    }

    private static function autoload(): string
    {
        return LoadCheck::autoload(Registry::class, self::class, \dirname(__DIR__)) ?? self::markTestSkipped('omnireach is installed in an application here, or no autoloader knows its tests: the bare check is the package\'s own.');
    }
}
