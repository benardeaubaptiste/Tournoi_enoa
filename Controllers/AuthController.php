<?php

namespace tournoi_enoa\Controllers;

use tournoi_enoa\Core\Auth;
use tournoi_enoa\Core\Config;
use tournoi_enoa\Models\AdminModel;

class AuthController extends Controller
{
    /** Au-delà de ce nombre d'échecs consécutifs, le formulaire de connexion est bloqué temporairement. */
    private const MAX_LOGIN_ATTEMPTS = 5;
    /** Durée du blocage, en secondes, une fois le seuil atteint. */
    private const LOGIN_LOCKOUT_SECONDS = 60;

    public function login(): void
    {
        if (Auth::check()) {
            header('Location: index.php?controller=home&action=index');
            exit;
        }

        $error = null;
        $lockedUntil = (int) ($_SESSION['login_locked_until'] ?? 0);
        $remainingLock = $lockedUntil - time();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if ($remainingLock > 0) {
                $error = "Trop de tentatives échouées. Merci de réessayer dans {$remainingLock} secondes.";
            } else {
                $username = trim($_POST['username'] ?? '');
                $password = (string) ($_POST['password'] ?? '');
                $model = new AdminModel();
                $admin = $username !== '' ? $model->findByUsername($username) : null;

                if ($admin && password_verify($password, $admin->password_hash)) {
                    unset($_SESSION['login_attempts'], $_SESSION['login_locked_until']);
                    Auth::login($admin);
                    $model->touchLastLogin($admin->id);
                    $model->logAction($admin->id, 'login', 'Connexion administrateur');
                    header('Location: index.php?controller=home&action=index');
                    exit;
                }

                $attempts = (int) ($_SESSION['login_attempts'] ?? 0) + 1;
                $_SESSION['login_attempts'] = $attempts;
                if ($attempts >= self::MAX_LOGIN_ATTEMPTS) {
                    $_SESSION['login_locked_until'] = time() + self::LOGIN_LOCKOUT_SECONDS;
                    $_SESSION['login_attempts'] = 0;
                    $error = 'Trop de tentatives échouées. Merci de réessayer dans ' . self::LOGIN_LOCKOUT_SECONDS . ' secondes.';
                } else {
                    $error = 'Identifiant ou mot de passe incorrect.';
                }
            }
        }

        $this->render('auth/login', compact('error'));
    }

    public function register(): void
    {
        if (Auth::check()) {
            header('Location: index.php?controller=home&action=index');
            exit;
        }

        $error = null;
        $model = new AdminModel();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $username = trim($_POST['username'] ?? '');
            $displayName = trim($_POST['display_name'] ?? '');
            $password = (string) ($_POST['password'] ?? '');
            $passwordConfirm = (string) ($_POST['password_confirm'] ?? '');
            $registrationCode = (string) ($_POST['registration_code'] ?? '');

            // Sans ce code, l'inscription (publique par nature) donnerait un accès admin
            // complet à n'importe qui : voir Core\Config::registrationCode(). Un code vide
            // désactive l'inscription (sinon hash_equals('', '') laisserait tout passer).
            $expectedCode = Config::registrationCode();
            if ($expectedCode === '' || !hash_equals($expectedCode, $registrationCode)) {
                $error = "Code d'inscription incorrect.";
            } elseif ($username === '' || $displayName === '') {
                $error = 'Merci de renseigner votre identifiant et votre nom.';
            } elseif (strlen($password) < 8) {
                $error = 'Le mot de passe doit contenir au moins 8 caractères.';
            } elseif ($password !== $passwordConfirm) {
                $error = 'Les deux mots de passe ne correspondent pas.';
            } elseif ($model->usernameExists($username)) {
                $error = 'Cet identifiant est déjà utilisé.';
            } else {
                $admin = $model->create($username, password_hash($password, PASSWORD_BCRYPT), $displayName, 'gestionnaire');
                Auth::login($admin);
                $model->touchLastLogin($admin->id);
                $model->logAction($admin->id, 'register', 'Création du compte gestionnaire « ' . $username . ' »');
                header('Location: index.php?controller=home&action=index');
                exit;
            }
        }

        $this->render('auth/register', compact('error'));
    }

    public function logout(): void
    {
        if (Auth::check()) {
            (new AdminModel())->logAction(Auth::id(), 'logout', 'Déconnexion administrateur');
        }
        Auth::logout();
        header('Location: index.php?controller=auth&action=login');
        exit;
    }
}
