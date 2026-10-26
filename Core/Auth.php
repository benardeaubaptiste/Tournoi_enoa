<?php

namespace tournoi_enoa\Core;

use tournoi_enoa\Entities\Admin;

class Auth
{
    /**
     * L'utilisateur en session est-il un administrateur connecté ?
     */
    public static function check(): bool
    {
        return !empty($_SESSION['admin_id']);
    }

    /**
     * Informations (non sensibles) de l'administrateur connecté.
     */
    public static function user(): ?object
    {
        return $_SESSION['admin'] ?? null;
    }

    public static function id(): ?int
    {
        return isset($_SESSION['admin_id']) ? (int) $_SESSION['admin_id'] : null;
    }

    /**
     * Rôle du compte connecté ('admin' ou 'gestionnaire'). Purement informatif : les deux
     * rôles ont exactement les mêmes droits, aucune action de l'appli ne vérifie le rôle.
     */
    public static function role(): ?string
    {
        return self::user()->role ?? null;
    }

    public static function login(Admin $admin): void
    {
        session_regenerate_id(true);
        $_SESSION['admin_id'] = (int) $admin->id;
        $_SESSION['admin'] = (object) [
            'id' => (int) $admin->id,
            'username' => $admin->username,
            'display_name' => $admin->display_name ?: $admin->username,
            'role' => $admin->role ?: 'gestionnaire',
        ];
    }

    public static function logout(): void
    {
        unset($_SESSION['admin_id'], $_SESSION['admin']);
        session_regenerate_id(true);
    }
}
