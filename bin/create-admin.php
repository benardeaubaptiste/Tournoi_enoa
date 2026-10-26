<?php

/**
 * Crée (ou réinitialise le mot de passe d') un compte administrateur en ligne de commande.
 *
 *   php bin/create-admin.php <identifiant> <mot_de_passe> [nom affiché]
 *   docker compose exec web php bin/create-admin.php <identifiant> <mot_de_passe>
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require __DIR__ . '/../autoloader.php';
\tournoi_enoa\Autoloader::register();
\tournoi_enoa\Core\Env::load();

[$script, $username, $password, $displayName] = array_pad($argv, 4, null);
if ($username === null || $password === null) {
    fwrite(STDERR, "Usage : php bin/create-admin.php <identifiant> <mot_de_passe> [nom affiché]\n");
    exit(1);
}
if (strlen($password) < 8) {
    fwrite(STDERR, "Le mot de passe doit contenir au moins 8 caractères.\n");
    exit(1);
}

$pdo = \tournoi_enoa\Core\DbConnect::createPdo();
$hash = password_hash($password, PASSWORD_BCRYPT);

$stmt = $pdo->prepare('SELECT id FROM admins WHERE username = ?');
$stmt->execute([$username]);
if ($stmt->fetch()) {
    $pdo->prepare('UPDATE admins SET password_hash = ? WHERE username = ?')->execute([$hash, $username]);
    echo "Mot de passe du compte « $username » mis à jour.\n";
} else {
    $pdo->prepare("INSERT INTO admins (username, password_hash, display_name, role) VALUES (?, ?, ?, 'admin')")
        ->execute([$username, $hash, $displayName ?? 'Administrateur']);
    echo "Compte administrateur « $username » créé.\n";
}
