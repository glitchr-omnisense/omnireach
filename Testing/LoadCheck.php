<?php

namespace Omnireach\Testing;

/**
 * The check every package of the family runs in its BareTest: each class
 * of the package loaded in a PHP process of its own, with the autoloader
 * that knows it, then what came from outside - classes of a framework, an
 * HTTP client or an application, and files of packages it does not
 * require.
 */
final class LoadCheck
{
    /** Classes no package of the family may load: a framework, an HTTP client, an application. Symfony's HTTP client contracts are interfaces, allowed. */
    public const FOREIGN = '~^(?:Symfony\\\\(?!Contracts\\\\HttpClient\\\\|Contracts\\\\Service\\\\|Polyfill\\\\)|(?:Illuminate|Laravel|Doctrine|Twig|League|GuzzleHttp|Nyholm|Http|Omnibridge|Base|App)\\\\)~';

    /**
     * @param string       $root      the package's directory
     * @param string       $namespace its namespace, "Omnireach\\Billetweb\\"
     * @param string       $autoload  the autoload.php that knows it
     * @param list<string> $extra     classes to load besides (the check that it is not blind)
     *
     * @return array{loaded: list<string>, foreign: list<string>, files: list<string>} the files without those Composer includes at start for every package
     */
    public static function run(string $root, string $namespace, string $autoload, array $extra = []): array
    {
        $process = proc_open([\PHP_BINARY, __DIR__.'/../bin/load-all', $autoload, $root, $namespace, ...$extra], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (!\is_resource($process)) {
            throw new \RuntimeException('Could not start PHP.');
        }
        $out = (string) stream_get_contents($pipes[1]);
        $err = (string) stream_get_contents($pipes[2]);
        if (0 !== proc_close($process)) {
            throw new \RuntimeException('load-all failed: '.$err.$out);
        }
        $report = json_decode($out, true);
        if (!\is_array($report)) {
            throw new \RuntimeException('load-all printed no report: '.$err.$out);
        }

        $eager = array_map('dirname', self::strings($report['eager'] ?? []));
        $files = array_values(array_filter(self::strings($report['files'] ?? []), static fn (string $file) => !\in_array(\dirname($file), $eager, true)));

        return ['loaded' => self::strings($report['loaded'] ?? []), 'foreign' => self::foreign(self::strings($report['symbols'] ?? [])), 'files' => $files];
    }

    /**
     * @param list<string> $symbols
     *
     * @return list<string>
     */
    public static function foreign(array $symbols): array
    {
        return array_values(array_filter($symbols, static fn (string $symbol) => 1 === preg_match(self::FOREIGN, $symbol)));
    }

    /**
     * The files loaded from outside the package and outside the vendor
     * directories allowed.
     *
     * @param list<string> $files
     * @param list<string> $allowed path fragments: "/vendor/psr/", "/vendor/omnisense/omnireach/"...
     *
     * @return list<string>
     */
    public static function outside(array $files, string $root, array $allowed): array
    {
        $root = rtrim((string) realpath($root), '/').'/';

        return array_values(array_filter($files, static function (string $file) use ($root, $allowed): bool {
            if (str_starts_with($file, $root) && !str_starts_with($file, $root.'vendor/')) {
                return false;
            }
            foreach ([...$allowed, '/vendor/composer/', '/vendor/autoload.php'] as $fragment) {
                if (str_contains($file, $fragment)) {
                    return false;
                }
            }

            return true;
        }));
    }

    /**
     * The autoloader that knows a class and the test asking: the package's
     * own or its harness's. Null when the package sits in an application's
     * vendor/ (the application's autoloader loads what it needs at start:
     * the check is the package's own) or when none knows it.
     */
    public static function autoload(string $class, string $test, string $root): ?string
    {
        $package = (string) realpath($root);
        foreach (\Composer\Autoload\ClassLoader::getRegisteredLoaders() as $vendor => $loader) {
            if ($loader->findFile($class) && $loader->findFile($test)) {
                return str_starts_with($package.'/', (string) realpath($vendor).'/') ? null : $vendor.'/autoload.php';
            }
        }

        return null;
    }

    /** @return list<string> */
    private static function strings(mixed $values): array
    {
        return \is_array($values) ? array_values(array_filter($values, 'is_string')) : [];
    }
}
