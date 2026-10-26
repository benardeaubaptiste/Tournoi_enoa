<?php
require_once __DIR__ . '/../autoloader.php';
\tournoi_enoa\Autoloader::register();

// Réglages lus dans l'environnement (.env en local, variables du conteneur en production).
\tournoi_enoa\Core\Env::load();

// APP_DEBUG=true uniquement sur un poste de développement : en production, afficher les
// erreurs PHP (chemins serveur, requêtes SQL...) au visiteur est une fuite d'information.
// Jamais actif quand APP_ENV=production, même si APP_DEBUG est resté à true par erreur.
define('DEBUG_MODE', !\tournoi_enoa\Core\Env::isProduction() && \tournoi_enoa\Core\Env::bool('APP_DEBUG'));

// SESSION_SECURE_COOKIE=true une fois l'appli servie en HTTPS : force le cookie de session à
// ne circuler que sur une connexion chiffrée. À false pour rester utilisable en HTTP sur WAMP
// local (http://localhost/...).
define('FORCE_SECURE_COOKIE', \tournoi_enoa\Core\Env::bool('SESSION_SECURE_COOKIE'));

// Cookie de session durci : inaccessible en JS (HttpOnly), non transmis en navigation
// cross-site (SameSite=Lax, nécessaire pour le lien "Ouvrir" venant d'un e-mail par ex.).
session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'httponly' => true,
    'samesite' => 'Lax',
    'secure'   => FORCE_SECURE_COOKIE,
]);

// Démarrer la session
session_start();

error_reporting(E_ALL);
ini_set('display_errors', DEBUG_MODE ? '1' : '0');
ini_set('log_errors', '1');

if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php'; // dépendances Composer (ex. dompdf pour l'export PDF)
}

use tournoi_enoa\Core\Router;

$router = new Router();
$router->routes();