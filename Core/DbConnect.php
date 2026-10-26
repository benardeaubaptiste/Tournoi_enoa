<?php

namespace tournoi_enoa\Core;

use PDO;
use Exception;

class DbConnect {

protected $connection;
protected $request;

/**
 * Connexion PDO configurée depuis les variables d'environnement DB_HOST, DB_PORT, DB_NAME,
 * DB_USER, DB_PASSWORD (voir Core\Env et .env.example). Aucun identifiant n'est codé en dur.
 */
public static function createPdo(): PDO
{
    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
        Env::get('DB_HOST', 'localhost'),
        Env::get('DB_PORT', '3306'),
        Env::get('DB_NAME', 'tournoi_enoa')
    );
    $pdo = new PDO($dsn, Env::get('DB_USER', ''), Env::get('DB_PASSWORD', ''));

    //activation des erreurs PDO
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    //LEs retours de requêtes seront en tableau objet par defaut
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_OBJ);

    //Encodage des caractères spéciaux en 'utf8'
    $pdo->exec("SET NAMES 'utf8'");

    return $pdo;
}

public function __construct(){

    try {
    $this->connection = self::createPdo();
    } catch (Exception $e) {
        // Le message PDO contient l'hôte et l'utilisateur MySQL : journalisé, jamais affiché en production.
        error_log('Erreur de connexion à la BDD : ' . $e->getMessage());
        http_response_code(500);
        die(Env::bool('APP_DEBUG')
            ? 'Erreur de connexion à la BDD : ' . $e->getMessage()
            : 'Service momentanément indisponible.');
    }

    }
}
