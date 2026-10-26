<?php

namespace tournoi_enoa\Models;

use tournoi_enoa\Core\DbConnect;

/**
 * Synchronise les terrains d'un tournoi sur le réglage "nombre de terrains" : sans ça,
 * un tournoi n'a jamais aucune ligne dans `courts`, et la planification des matchs
 * (BracketModel/QualificationModel) n'a alors aucun terrain à leur affecter.
 */
class CourtModel extends DbConnect
{
    /**
     * Garantit qu'il existe exactement $nombreTerrains terrains actifs (numérotés 1..N) :
     * crée les numéros manquants, réactive ceux désactivés qui rentrent à nouveau dans le
     * quota, désactive (sans les supprimer, des matchs peuvent déjà y être planifiés) ceux
     * en trop si le nombre de terrains diminue.
     */
    public function syncForTournament(int $tournamentId, int $nombreTerrains): void
    {
        $nombreTerrains = max(1, $nombreTerrains);

        $existingQuery = $this->connection->prepare('SELECT numero, actif FROM courts WHERE tournament_id = :tournament_id');
        $existingQuery->execute(['tournament_id' => $tournamentId]);
        $existing = [];
        foreach ($existingQuery->fetchAll() as $row) {
            $existing[(int) $row->numero] = (int) $row->actif;
        }

        $insert = $this->connection->prepare('INSERT INTO courts (tournament_id, numero, nom, actif) VALUES (:tournament_id, :numero, :nom, 1)');
        $setActif = $this->connection->prepare('UPDATE courts SET actif = :actif WHERE tournament_id = :tournament_id AND numero = :numero');

        for ($numero = 1; $numero <= $nombreTerrains; $numero++) {
            if (!isset($existing[$numero])) {
                $insert->execute(['tournament_id' => $tournamentId, 'numero' => $numero, 'nom' => 'Terrain ' . $numero]);
            } elseif ($existing[$numero] === 0) {
                $setActif->execute(['actif' => 1, 'tournament_id' => $tournamentId, 'numero' => $numero]);
            }
        }

        foreach ($existing as $numero => $actif) {
            if ($numero > $nombreTerrains && $actif === 1) {
                $setActif->execute(['actif' => 0, 'tournament_id' => $tournamentId, 'numero' => $numero]);
            }
        }
    }
}
