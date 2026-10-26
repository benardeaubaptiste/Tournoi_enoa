<?php

namespace tournoi_enoa\Core;

/**
 * Jeton anti-CSRF, un par session (pas un par formulaire) : suffisant contre les attaques
 * cross-site classiques tout en restant compatible avec plusieurs onglets/formulaires
 * ouverts simultanément et avec les rafraîchissements partiels en AJAX.
 */
class Csrf
{
    private const SESSION_KEY = 'csrf_token';

    public static function token(): string
    {
        if (empty($_SESSION[self::SESSION_KEY]) || !is_string($_SESSION[self::SESSION_KEY])) {
            $_SESSION[self::SESSION_KEY] = bin2hex(random_bytes(32));
        }
        return $_SESSION[self::SESSION_KEY];
    }

    /** Champ caché à insérer dans chaque formulaire POST. */
    public static function field(): string
    {
        return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(self::token()) . '">';
    }

    public static function check(?string $submitted): bool
    {
        return is_string($submitted) && $submitted !== '' && hash_equals(self::token(), $submitted);
    }
}
