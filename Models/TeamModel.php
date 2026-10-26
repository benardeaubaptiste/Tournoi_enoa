<?php

namespace tournoi_enoa\Models;

use tournoi_enoa\Core\DbConnect;
use tournoi_enoa\Entities\Team;

class TeamModel extends DbConnect
{
    public function byTournament(int $tournamentId): array
    {
        $query = $this->connection->prepare('SELECT * FROM teams WHERE tournament_id = :tournament_id ORDER BY numero_inscription');
        $query->execute(['tournament_id' => $tournamentId]);
        return $query->fetchAll(\PDO::FETCH_CLASS, Team::class);
    }

    /**
     * Prochain numéro d'inscription disponible pour ce tournoi.
     * Sert uniquement au tri interne (génération des poules) : jamais affiché.
     */
    public function nextRegistrationNumber(int $tournamentId): int
    {
        $query = $this->connection->prepare('SELECT COALESCE(MAX(numero_inscription), 0) + 1 FROM teams WHERE tournament_id = :tournament_id');
        $query->execute(['tournament_id' => $tournamentId]);
        return (int) $query->fetchColumn();
    }

    public function create(array $data): void
    {
        $query = $this->connection->prepare('INSERT INTO teams (tournament_id, numero_inscription, nom, club, nombre_joueurs, prix_par_joueur, montant_a_payer, statut_paiement, montant_paye, statut) VALUES (:tournament_id, :numero_inscription, :nom, :club, :nombre_joueurs, :prix_par_joueur, :montant_a_payer, :statut_paiement, :montant_paye, "inscrite")');
        $query->execute($data);
    }

    public function find(int $id): ?Team
    {
        $query = $this->connection->prepare('SELECT * FROM teams WHERE id = :id');
        $query->execute(['id' => $id]);
        $query->setFetchMode(\PDO::FETCH_CLASS, Team::class);
        $team = $query->fetch();
        return $team ?: null;
    }

    public function updatePayment(int $id, float $montantPaye, string $statutPaiement): void
    {
        $query = $this->connection->prepare('UPDATE teams SET montant_paye = :montant_paye, statut_paiement = :statut_paiement WHERE id = :id');
        $query->execute([
            'montant_paye' => $montantPaye,
            'statut_paiement' => $statutPaiement,
            'id' => $id,
        ]);
    }

    /** @param string $statut 'inscrite'|'confirmee'|'forfait'|'elimine' */
    public function updateStatus(int $id, string $statut): void
    {
        $query = $this->connection->prepare('UPDATE teams SET statut = :statut WHERE id = :id');
        $query->execute(['statut' => $statut, 'id' => $id]);
    }

    public function update(int $id, array $data): void
    {
        $data['id'] = $id;
        $query = $this->connection->prepare(
            'UPDATE teams SET
                nom = :nom,
                club = :club,
                nombre_joueurs = :nombre_joueurs,
                prix_par_joueur = :prix_par_joueur,
                montant_a_payer = :montant_a_payer,
                montant_paye = :montant_paye,
                statut_paiement = :statut_paiement
             WHERE id = :id'
        );
        $query->execute($data);
    }

    /**
     * L'équipe a-t-elle déjà un match généré (quel que soit son statut, y compris "pending") ?
     * Sert à protéger la suppression : team_a_id/team_b_id passent à NULL en cascade
     * (ON DELETE SET NULL) même sur un match pas encore joué, ce qui laisserait un match
     * "pending" planifié (terrain/horaire) sans adversaire — et, en phase finale, un match de
     * bracket devenu à la fois affiché "Exempt" et impossible à saisir (il faut les deux
     * équipes pour être cliquable), qui bloque alors définitivement cette branche du tableau.
     */
    public function hasScheduledMatches(int $id): bool
    {
        $query = $this->connection->prepare(
            'SELECT COUNT(*) FROM matches WHERE team_a_id = :id OR team_b_id = :id2'
        );
        $query->execute(['id' => $id, 'id2' => $id]);
        return ((int) $query->fetchColumn()) > 0;
    }

    public function delete(int $id): void
    {
        $query = $this->connection->prepare('DELETE FROM teams WHERE id = :id');
        $query->execute(['id' => $id]);
    }
}
