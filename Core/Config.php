<?php

namespace tournoi_enoa\Core;

/**
 * Réglages locaux à l'installation, hors connexion base de données (voir DbConnect).
 */
class Config
{
    /**
     * Code à fournir sur la page d'inscription publique (index.php?controller=auth&action=register)
     * pour pouvoir créer un compte gestionnaire. Défini par la variable d'environnement
     * REGISTRATION_CODE (voir .env.example), à communiquer uniquement aux personnes autorisées à
     * gérer des tournois : sans lui, n'importe qui pourrait s'inscrire et obtenir un accès complet
     * à l'application. Vide ou absent = inscription désactivée.
     */
    public static function registrationCode(): string
    {
        return (string) Env::get('REGISTRATION_CODE', '');
    }
}
