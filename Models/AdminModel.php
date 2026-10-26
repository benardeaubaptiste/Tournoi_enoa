<?php

namespace tournoi_enoa\Models;

use tournoi_enoa\Core\DbConnect;
use tournoi_enoa\Entities\Admin;

class AdminModel extends DbConnect
{
    public function findByUsername(string $username): ?Admin
    {
        $query = $this->connection->prepare('SELECT * FROM admins WHERE username = :username AND is_active = 1');
        $query->execute(['username' => $username]);
        $query->setFetchMode(\PDO::FETCH_CLASS, Admin::class);
        return $query->fetch() ?: null;
    }

    public function touchLastLogin(int $id): void
    {
        $query = $this->connection->prepare('UPDATE admins SET last_login_at = NOW() WHERE id = :id');
        $query->execute(['id' => $id]);
    }

    public function usernameExists(string $username): bool
    {
        $query = $this->connection->prepare('SELECT 1 FROM admins WHERE username = :username');
        $query->execute(['username' => $username]);
        return (bool) $query->fetchColumn();
    }

    /**
     * Crée un compte depuis la page d'inscription publique. Le rôle 'gestionnaire' a les
     * mêmes droits que 'admin' (aucune vérification de rôle dans l'appli) : c'est juste une
     * étiquette pour distinguer le compte d'origine des comptes auto-inscrits.
     */
    public function create(string $username, string $passwordHash, string $displayName, string $role = 'gestionnaire'): Admin
    {
        $query = $this->connection->prepare('INSERT INTO admins (username, password_hash, display_name, role) VALUES (:username, :password_hash, :display_name, :role)');
        $query->execute([
            'username' => $username,
            'password_hash' => $passwordHash,
            'display_name' => $displayName,
            'role' => $role,
        ]);

        $admin = new Admin();
        $admin->id = (int) $this->connection->lastInsertId();
        $admin->username = $username;
        $admin->password_hash = $passwordHash;
        $admin->display_name = $displayName;
        $admin->role = $role;
        $admin->is_active = 1;

        return $admin;
    }

    public function logAction(?int $adminId, string $action, ?string $description = null, ?int $tournamentId = null): void
    {
        $query = $this->connection->prepare('INSERT INTO admin_logs (admin_id, tournament_id, action, description) VALUES (:admin_id, :tournament_id, :action, :description)');
        $query->execute([
            'admin_id' => $adminId,
            'tournament_id' => $tournamentId,
            'action' => $action,
            'description' => $description,
        ]);
    }
}
