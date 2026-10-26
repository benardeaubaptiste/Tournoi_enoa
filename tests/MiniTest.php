<?php

namespace tournoi_enoa\Tests;

/**
 * Micro-framework de test sans dépendance (pas de PHPUnit installé sur le projet) :
 * juste assez pour écrire des tests fonctionnels lisibles et avoir un résumé PASS/FAIL
 * en sortie de `php tests/run.php`.
 */
class MiniTest
{
    private static int $passed = 0;
    private static int $failed = 0;
    /** @var array<int, array{suite:string, name:string, message:string}> */
    private static array $failures = [];
    private static string $currentSuite = '';

    public static function suite(string $name): void
    {
        self::$currentSuite = $name;
        echo "\n=== {$name} ===\n";
    }

    public static function test(string $name, callable $fn): void
    {
        try {
            $fn();
            self::$passed++;
            echo "  [OK]   {$name}\n";
        } catch (\Throwable $e) {
            self::$failed++;
            self::$failures[] = [
                'suite' => self::$currentSuite,
                'name' => $name,
                'message' => $e->getMessage(),
            ];
            echo "  [FAIL] {$name}\n";
            echo "         -> " . $e->getMessage() . "\n";
        }
    }

    public static function assertTrue(bool $cond, string $message = 'attendu true'): void
    {
        if (!$cond) {
            throw new \RuntimeException($message);
        }
    }

    public static function assertFalse(bool $cond, string $message = 'attendu false'): void
    {
        self::assertTrue(!$cond, $message);
    }

    public static function assertSame($expected, $actual, string $label = ''): void
    {
        if ($expected !== $actual) {
            $msg = sprintf(
                '%sattendu %s, obtenu %s',
                $label ? $label . ' : ' : '',
                var_export($expected, true),
                var_export($actual, true)
            );
            throw new \RuntimeException($msg);
        }
    }

    public static function assertLessOrEqual($max, $actual, string $label = ''): void
    {
        if (!($actual <= $max)) {
            throw new \RuntimeException(sprintf('%sattendu <= %s, obtenu %s', $label ? $label . ' : ' : '', $max, $actual));
        }
    }

    public static function assertGreaterOrEqual($min, $actual, string $label = ''): void
    {
        if (!($actual >= $min)) {
            throw new \RuntimeException(sprintf('%sattendu >= %s, obtenu %s', $label ? $label . ' : ' : '', $min, $actual));
        }
    }

    public static function fail(string $message): void
    {
        throw new \RuntimeException($message);
    }

    public static function summary(): int
    {
        $total = self::$passed + self::$failed;
        echo "\n=== Résumé ===\n";
        echo "{$total} test(s), " . self::$passed . " OK, " . self::$failed . " échec(s)\n";
        if (self::$failures) {
            echo "\nDétail des échecs :\n";
            foreach (self::$failures as $f) {
                echo " - [{$f['suite']}] {$f['name']} : {$f['message']}\n";
            }
        }
        return self::$failed > 0 ? 1 : 0;
    }
}
