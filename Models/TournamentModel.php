<?php

namespace tournoi_enoa\Models;

use tournoi_enoa\Core\DbConnect;
use tournoi_enoa\Entities\Tournament;

class TournamentModel extends DbConnect
{
    /** Ordre du cycle de vie (colonne tournaments.statut) : ne sert qu'à ne jamais reculer automatiquement. */
    private const STATUT_ORDER = [
        'draft', 'inscriptions', 'poules_a_generer', 'poules_validees',
        'planning_genere', 'en_cours', 'phase_finale', 'termine', 'archive',
    ];

    /**
     * Fait avancer le statut du tournoi vers $target, seulement si $target est plus loin
     * dans le cycle de vie que le statut actuel (jamais de retour en arrière automatique,
     * jamais d'écrasement d'une progression déjà plus avancée par une action refaite en double).
     */
    public function advanceStatut(int $id, string $target): void
    {
        $tournament = $this->find($id);
        if ($tournament === null) {
            return;
        }

        $currentIndex = array_search($tournament->statut, self::STATUT_ORDER, true);
        $targetIndex = array_search($target, self::STATUT_ORDER, true);
        if ($currentIndex === false || $targetIndex === false || $targetIndex <= $currentIndex) {
            return;
        }

        $query = $this->connection->prepare('UPDATE tournaments SET statut = :statut WHERE id = :id');
        $query->execute(['statut' => $target, 'id' => $id]);
    }

    public function all(): array
    {
        $query = $this->connection->query('SELECT * FROM tournaments ORDER BY date_tournoi DESC, id DESC');
        return $query->fetchAll();
    }

    public function dashboard(int $id): ?object
    {
        $query = $this->connection->prepare('SELECT * FROM tournament_dashboard WHERE id = :id');
        $query->execute(['id' => $id]);
        return $query->fetch() ?: null;
    }

    public function find(int $id): ?Tournament
    {
        $query = $this->connection->prepare('SELECT * FROM tournaments WHERE id = :id');
        $query->execute(['id' => $id]);
        $query->setFetchMode(\PDO::FETCH_CLASS, Tournament::class);
        return $query->fetch() ?: null;
    }

    /**
     * Tournoi à afficher par défaut sur une page qui n'en précise pas (ex. lien de nav
     * générique vers le suivi live) : celui effectivement en cours de jeu en priorité, sinon
     * le plus récent, pour ne jamais tomber sur un vieux tournoi archivé par hasard.
     */
    public function currentOrLatest(): ?Tournament
    {
        $query = $this->connection->prepare(
            "SELECT * FROM tournaments WHERE statut IN ('en_cours', 'phase_finale')
             ORDER BY date_tournoi DESC, id DESC LIMIT 1"
        );
        $query->execute();
        $query->setFetchMode(\PDO::FETCH_CLASS, Tournament::class);
        if ($tournament = $query->fetch()) {
            return $tournament;
        }

        $query = $this->connection->prepare('SELECT * FROM tournaments ORDER BY date_tournoi DESC, id DESC LIMIT 1');
        $query->execute();
        $query->setFetchMode(\PDO::FETCH_CLASS, Tournament::class);
        return $query->fetch() ?: null;
    }

    public function prixParJoueur(int $tournamentId): float
    {
        $query = $this->connection->prepare('SELECT prix_par_joueur FROM tournament_settings WHERE tournament_id = :tournament_id');
        $query->execute(['tournament_id' => $tournamentId]);
        $value = $query->fetchColumn();
        return $value !== false ? (float) $value : 0.0;
    }

    public function settings(int $tournamentId): ?object
    {
        $query = $this->connection->prepare('SELECT * FROM tournament_settings WHERE tournament_id = :tournament_id');
        $query->execute(['tournament_id' => $tournamentId]);
        return $query->fetch() ?: null;
    }

    public function nextNumero(): int
    {
        $query = $this->connection->query('SELECT COALESCE(MAX(numero), 0) + 1 FROM tournaments');
        return (int) $query->fetchColumn();
    }

    public function create(array $data): int
    {
        $query = $this->connection->prepare(
            'INSERT INTO tournaments (numero, titre, date_tournoi, date_limite_inscriptions, date_limite_paiement, statut, created_by) '
            . 'VALUES (:numero, :titre, :date_tournoi, :date_limite_inscriptions, :date_limite_paiement, :statut, :created_by)'
        );
        $query->execute($data);
        return (int) $this->connection->lastInsertId();
    }

    public function createSettings(int $tournamentId, array $data): void
    {
        $data['tournament_id'] = $tournamentId;
        $query = $this->connection->prepare(
            'INSERT INTO tournament_settings (tournament_id, prix_par_joueur, heure_debut_tournoi, heure_fin_tournoi) '
            . 'VALUES (:tournament_id, :prix_par_joueur, :heure_debut_tournoi, :heure_fin_tournoi)'
        );
        $query->execute($data);
    }

    public function updateSettings(int $tournamentId, array $data): void
    {
        $data['tournament_id'] = $tournamentId;
        $query = $this->connection->prepare(
            'UPDATE tournament_settings SET
                heure_debut_tournoi = :heure_debut_tournoi,
                heure_fin_tournoi = :heure_fin_tournoi,
                nombre_max_equipes = :nombre_max_equipes,
                nombre_poules = :nombre_poules,
                mode_repartition = :mode_repartition,
                nombre_terrains = :nombre_terrains,
                heure_debut_qualifications = :heure_debut_qualifications,
                heure_fin_qualifications = :heure_fin_qualifications,
                duree_match_qualification = :duree_match_qualification,
                temps_entre_matchs_qualification = :temps_entre_matchs_qualification,
                heure_debut_phase_finale = :heure_debut_phase_finale,
                heure_fin_phase_finale = :heure_fin_phase_finale,
                duree_match_phase_finale = :duree_match_phase_finale,
                temps_entre_matchs_phase_finale = :temps_entre_matchs_phase_finale,
                duree_grande_finale = :duree_grande_finale,
                prix_par_joueur = :prix_par_joueur,
                max_matchs_consecutifs_qualifications = :max_matchs_consecutifs_qualifications,
                max_matchs_consecutifs_phase_finale = :max_matchs_consecutifs_phase_finale,
                points_victoire = :points_victoire,
                points_defaite = :points_defaite,
                nombre_equipes_upper = :nombre_equipes_upper,
                nombre_equipes_lower = :nombre_equipes_lower
            WHERE tournament_id = :tournament_id'
        );
        $query->execute($data);
    }

    public function delete(int $id): void
    {
        $query = $this->connection->prepare('DELETE FROM tournaments WHERE id = :id');
        $query->execute(['id' => $id]);
    }
}
