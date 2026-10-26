<?php

namespace tournoi_enoa\Models;

use tournoi_enoa\Core\DbConnect;
use tournoi_enoa\Entities\Player;

class PlayerModel extends DbConnect
{
    public function byTeam(int $teamId): array
    {
        $query = $this->connection->prepare('SELECT * FROM players WHERE team_id = :team_id ORDER BY nom, prenom');
        $query->execute(['team_id' => $teamId]);
        return $query->fetchAll(\PDO::FETCH_CLASS, Player::class);
    }

    public function create(array $data): void
    {
        $query = $this->connection->prepare('INSERT INTO players (team_id, prenom, nom, numero_maillot) VALUES (:team_id, :prenom, :nom, :numero_maillot)');
        $query->execute($data);
    }
}
