<?php

namespace tournoi_enoa\Core;

/**
 * Lecture des réglages sensibles ou propres à l'installation (identifiants BDD, code
 * d'inscription, mode debug...) depuis les variables d'environnement.
 *
 * En local (WAMP), ils sont lus depuis le fichier .env à la racine du projet (jamais versionné,
 * voir .env.example). En conteneur/production, les vraies variables d'environnement sont
 * prioritaires : une valeur déjà définie par le serveur n'est jamais écrasée par le .env.
 */
class Env
{
    private static bool $loaded = false;

    public static function load(?string $file = null): void
    {
        if (self::$loaded) {
            return;
        }
        self::$loaded = true;

        $file = $file ?? dirname(__DIR__) . '/.env';
        if (!is_readable($file)) {
            return;
        }

        foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) {
                continue;
            }
            [$key, $value] = array_map('trim', explode('=', $line, 2));
            if (str_starts_with($key, 'export ')) {
                $key = trim(substr($key, 7));
            }
            // Guillemets optionnels autour de la valeur : KEY="valeur avec espaces"
            if (strlen($value) >= 2 && ($value[0] === '"' || $value[0] === "'") && $value[-1] === $value[0]) {
                $value = substr($value, 1, -1);
            }
            if (getenv($key) !== false) {
                continue;
            }
            putenv("$key=$value");
            $_ENV[$key] = $value;
        }
    }

    public static function get(string $key, ?string $default = null): ?string
    {
        self::load();
        $value = getenv($key);
        if ($value === false) {
            $value = $_ENV[$key] ?? null;
        }
        return $value === null ? $default : (string) $value;
    }

    public static function bool(string $key, bool $default = false): bool
    {
        $value = self::get($key);
        if ($value === null || $value === '') {
            return $default;
        }
        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    public static function isProduction(): bool
    {
        return self::get('APP_ENV', 'production') === 'production';
    }
}
