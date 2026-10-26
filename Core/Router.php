<?php 

namespace tournoi_enoa\Core;

class Router {

    // Seules ces actions restent accessibles sans être connecté en admin
    private const PUBLIC_ROUTES = [
        'Auth' => ['login', 'logout', 'register'],
    ];

    public function routes(){
       // Lus par clé nommée (jamais par position) : l'ordre des paramètres dans l'URL
       // ne doit jamais changer le contrôleur/l'action résolus, ni les valeurs transmises.
       $controllerName = isset($_GET['controller']) ? ucfirst((string) $_GET['controller']) : 'Home';
       $action = isset($_GET['action']) ? (string) $_GET['action'] : 'index';

       $isPublic = isset(self::PUBLIC_ROUTES[$controllerName]) && in_array($action, self::PUBLIC_ROUTES[$controllerName], true);

       if (!$isPublic && !Auth::check()) {
           header('Location: index.php?controller=auth&action=login');
           exit;
       }

       // Protection anti-CSRF : toute requête POST (publique ou non) doit présenter le
       // jeton de la session en cours. Sans ça, un site tiers pourrait faire agir un admin
       // connecté à son insu (suppression d'équipe, saisie de score, etc.).
       if ($_SERVER['REQUEST_METHOD'] === 'POST' && !Csrf::check($_POST['csrf_token'] ?? null)) {
           http_response_code(419);
           echo "Session expirée ou requête invalide. Merci de recharger la page et de réessayer.";
           return;
       }

       $controllerClass = '\\tournoi_enoa\\Controllers\\' . $controllerName . 'Controller';

       if (!class_exists($controllerClass) || !is_subclass_of($controllerClass, '\\tournoi_enoa\\Controllers\\Controller')) {
           http_response_code(404);
           echo "La page recherchée n'existe pas";
           return;
       }

       $controller = new $controllerClass();

       if (!method_exists($controller, $action)) {
           http_response_code(404);
           echo "La page recherchée n'existe pas";
           return;
       }

       // On ne transmet à l'action que les clés de $_GET qui correspondent réellement à
       // l'un de ses paramètres déclarés, appariées par nom : les autres clés (indicateurs
       // d'affichage comme "saved" ou "bracketGenerated") restent lisibles directement via
       // $_GET dans le contrôleur, comme avant. Ça élimine à la fois le crash sur contrôleur
       // inconnu et la confusion de valeurs quand l'ordre des paramètres change dans l'URL.
       $args = [];
       foreach ((new \ReflectionMethod($controller, $action))->getParameters() as $parameter) {
           if (array_key_exists($parameter->getName(), $_GET)) {
               $args[$parameter->getName()] = $_GET[$parameter->getName()];
           }
       }

       // Mémorise le tournoi consulté en session, pour les pages accessibles depuis la nav
       // sans tournoi précisé dans l'URL (ex. "Suivi live") : elles retombent sur le dernier
       // tournoi réellement consulté plutôt que sur un tournoi "courant" arbitraire.
       if ((int) ($_GET['tournamentId'] ?? 0) > 0) {
           $_SESSION['selectedTournamentId'] = (int) $_GET['tournamentId'];
       } elseif ($controllerName === 'Home' && (int) ($_GET['id'] ?? 0) > 0) {
           $_SESSION['selectedTournamentId'] = (int) $_GET['id'];
       }

       $controller->$action(...$args);
    }
}