<?php

namespace tournoi_enoa\Entities;

class Team
{
    public $id;
    public $tournament_id;
    public $numero_inscription;
    public $nom;
    public $club;
    public $nombre_joueurs;
    public $prix_par_joueur;
    public $montant_a_payer;
    public $statut_paiement;
    public $montant_paye;
    public $date_paiement;
    public $statut;
    public $created_at;
    public $updated_at;

    /**
     * Statut de paiement recalculé à partir des montants réels, pour ne jamais
     * afficher un statut désynchronisé de statut_paiement en base (ex : montant_paye
     * mis à jour manuellement, ancienne donnée, etc.).
     */
    public function statutPaiementReel(): string
    {
        return self::calculerStatutPaiement((float) $this->montant_a_payer, (float) $this->montant_paye);
    }

    public static function calculerStatutPaiement(float $montantAPayer, float $montantPaye): string
    {
        if ($montantAPayer <= 0) {
            return 'paye'; // rien à payer (ex. tournoi gratuit) : jamais "impayé"
        }

        if ($montantPaye <= 0) {
            return 'impaye';
        }

        return $montantPaye >= $montantAPayer ? 'paye' : 'partiel';
    }
}
