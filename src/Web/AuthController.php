<?php
declare(strict_types=1);

namespace Ffs2\Web;

use Ffs2\Auth\AuthService;
use Ffs2\Core\Request;

/**
 * Inscription, connexion, déconnexion, validation d'e-mail, mot de passe oublié
 */
final class AuthController extends Controller
{
    private function service(): AuthService
    {
        return new AuthService($this->app);
    }

    public function registerForm(array $errors = [], array $old = []): void
    {
        if ($this->app->auth()->user() !== null) {
            $this->redirect('/profile');
        }
        $this->page('auth/register', [
            'title' => 'Inscription',
            'errors' => $errors,
            'old' => $old,
            'open' => $this->app->settings()->bool('registration_open'),
        ]);
    }

    public function register(): void
    {
        $this->checkCsrf();
        if (!$this->app->settings()->bool('registration_open')) {
            $this->redirect('/register');
        }
        $callsign = Request::post('callsign');
        $email = Request::post('email');
        try {
            [$errors, $userId] = $this->service()->register($callsign, $email, Request::raw('password'), Request::raw('password_confirmation'));
        } catch (\RuntimeException $e) {
            // compte créé mais e-mail non envoyé
            error_log('FFS2play inscription : ' . $e->getMessage());
            $this->flash('warning', "Votre compte est créé mais l'e-mail de validation n'a pas pu être envoyé. Contactez l'administrateur du site.");
            $this->redirect('/login');
        }
        if ($errors) {
            $this->registerForm($errors, ['callsign' => $callsign, 'email' => $email]);
            return;
        }
        if ($this->service()->verificationRequired()) {
            $this->flash('success', "Compte créé ! Un e-mail de validation a été envoyé à $email : cliquez sur le lien qu'il contient pour activer votre compte.");
            $this->redirect('/login');
        }
        $this->app->auth()->login($this->app->users()->find((int) $userId));
        $this->flash('success', 'Bienvenue ! Votre compte est actif : suivez les indications ci-dessous pour connecter ffs2play.');
        $this->redirect('/profile');
    }

    public function loginForm(string $error = '', string $login = ''): void
    {
        if ($this->app->auth()->user() !== null) {
            $this->redirect('/profile');
        }
        $this->page('auth/login', ['title' => 'Connexion', 'error' => $error, 'login' => $login]);
    }

    public function login(): void
    {
        $this->checkCsrf();
        $login = Request::post('login');
        [$user, $error] = $this->service()->attempt($login, Request::raw('password'), Request::ip());
        if ($user === null) {
            $this->loginForm((string) $error, $login);
            return;
        }
        $this->app->auth()->login($user);
        $target = (string) $this->app->session()->get('after_login', '');
        $this->app->session()->remove('after_login');
        // redirection locale uniquement
        if ($target !== '' && str_starts_with($target, '/') && !str_starts_with($target, '//')) {
            header('Location: ' . $target, true, 303);
            exit;
        }
        $this->redirect('/profile');
    }

    public function logout(): void
    {
        $this->checkCsrf();
        $this->app->auth()->logout();
        $this->flash('info', 'Vous êtes déconnecté.');
        $this->redirect('/');
    }

    public function verify(): void
    {
        $user = $this->service()->verifyEmail(Request::query('token'));
        if ($user === null) {
            $this->flash('error', 'Ce lien de validation a expiré ou a déjà été utilisé. Vous pouvez en demander un nouveau.');
            $this->redirect('/verify/resend');
        }
        if ($user['status'] === 'active') {
            $this->app->auth()->login($user);
        }
        $this->flash('success', 'Adresse e-mail validée, votre compte est actif.');
        $this->redirect('/profile');
    }

    public function resendForm(): void
    {
        $this->page('auth/resend', ['title' => 'Renvoyer le lien de validation']);
    }

    public function resend(): void
    {
        $this->checkCsrf();
        try {
            $this->service()->resendVerification(Request::post('email'), Request::ip());
        } catch (\RuntimeException $e) {
            error_log('FFS2play validation : ' . $e->getMessage());
        }
        $this->flash('info', "Si un compte en attente correspond à cette adresse, un nouveau lien vient d'être envoyé.");
        $this->redirect('/login');
    }

    public function forgotForm(): void
    {
        $this->page('auth/forgot', ['title' => 'Mot de passe oublié', 'mail' => $this->app->mailer()->isConfigured()]);
    }

    public function forgot(): void
    {
        $this->checkCsrf();
        try {
            $this->service()->requestPasswordReset(Request::post('email'), Request::ip());
        } catch (\RuntimeException $e) {
            error_log('FFS2play mot de passe oublié : ' . $e->getMessage());
        }
        $this->flash('info', 'Si un compte correspond à cette adresse, un e-mail de réinitialisation vient de lui être envoyé (valable 1 heure).');
        $this->redirect('/login');
    }

    public function resetForm(array $errors = []): void
    {
        $token = Request::input('token');
        if (!$this->service()->isResetTokenValid($token)) {
            $this->flash('error', 'Ce lien a expiré ou a déjà été utilisé.');
            $this->redirect('/forgot');
        }
        $this->page('auth/reset', ['title' => 'Nouveau mot de passe', 'token' => $token, 'errors' => $errors]);
    }

    public function reset(): void
    {
        $this->checkCsrf();
        $errors = $this->service()->resetPassword(Request::post('token'), Request::raw('password'), Request::raw('password_confirmation'));
        if ($errors) {
            $this->resetForm($errors);
            return;
        }
        $this->flash('success', 'Mot de passe modifié, vous pouvez vous connecter (sur le site et dans ffs2play).');
        $this->redirect('/login');
    }
}
