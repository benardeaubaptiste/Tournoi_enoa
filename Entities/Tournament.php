<?php

namespace tournoi_enoa\Entities;

class Tournament
{
    public $id;
    public $numero;
    public $titre;
    public $date_tournoi;
    public $date_limite_inscriptions;
    public $date_limite_paiement;
    public $statut;
    public $created_by;
    public $created_at;
    public $updated_at;
}
