<?php
declare(strict_types=1);

namespace Ffs2\Auth;

use Ffs2\Core\App;

/**
 * Inscription, validation d'e-mail, connexion web, mot de passe oublié, changement d'identifiants.
 * Les méthodes de validation retournent la liste des erreurs (vide si tout va bien).
 */
final class AuthService
{
    public const MIN_PASSWORD_LENGTH = 8;
    private const VERIFY_LIFETIME = 172800;   // 48 h
    private const RESET_LIFETIME = 3600;      // 1 h

    private UserRepository $users;
    private TokenRepository $tokens;

    public function __construct(private App $app)
    {
        $this->users = $app->users();
        $this->tokens = new TokenRepository($app->db());
    }

    /** La validation par e-mail est-elle effective (réglage actif et envoi possible) ? */
    public function verificationRequired(): bool
    {
        return $this->app->settings()->bool('require_email_verification') && $this->app->mailer()->isConfigured();
    }

    /**
     * @return array{0: string[], 1: ?int} erreurs, identifiant du compte créé
     */
    public function register(string $callsign, string $email, string $password, string $confirmation): array
    {
        $errors = $this->validateCallsign($callsign);
        $errors = array_merge($errors, $this->validateEmail($email), $this->validatePassword($password, $confirmation));
        if ($errors) {
            return [$errors, null];
        }
        $status = $this->verificationRequired() ? 'pending' : 'active';
        try {
            $id = $this->users->create($callsign, $email, $password, $status);
        } catch (\PDOException) {
            // inscription simultanée avec le même indicatif ou e-mail
            return [['Cet indicatif ou cette adresse e-mail est déjà utilisé.'], null];
        }
        if ($status === 'pending') {
            $this->sendVerification($this->users->find($id));
        }
        return [[], $id];
    }

    public function sendVerification(array $user): void
    {
        $token = $this->tokens->create((int) $user['id'], 'verify_email', self::VERIFY_LIFETIME);
        $this->mail($user['email'], 'Validez votre compte', 'mail/verify', [
            'user' => $user,
            'link' => $this->app->absoluteUrl('/verify', ['token' => $token]),
        ]);
    }

    public function verifyEmail(string $token): ?array
    {
        $userId = $this->tokens->consume($token, 'verify_email');
        if ($userId === null) {
            return null;
        }
        $user = $this->users->find($userId);
        if ($user === null) {
            return null;
        }
        $values = ['email_verified_at' => now_utc()];
        if ($user['status'] === 'pending') {
            $values['status'] = 'active';
        }
        $this->users->update($userId, $values);
        return $this->users->find($userId);
    }

    /**
     * Connexion au site par indicatif ou e-mail.
     * @return array{0: ?array, 1: ?string} utilisateur, message d'erreur
     */
    public function attempt(string $login, string $password, string $ip): array
    {
        $limiter = $this->app->limiter();
        if ($limiter->tooManyAttempts('web', $ip, $login)) {
            return [null, 'Trop de tentatives, réessayez dans quelques minutes.'];
        }
        $user = $this->users->findByLogin($login);
        if ($user === null || !$this->users->checkPassword($user, $password)) {
            $limiter->hit('web', $ip, $login);
            return [null, 'Indicatif, e-mail ou mot de passe incorrect.'];
        }
        $limiter->clear('web', $login);
        if ($user['status'] === 'banned') {
            return [null, 'Ce compte est suspendu.'];
        }
        if ($user['status'] === 'pending') {
            return [null, "Ce compte n'est pas encore activé : utilisez le lien reçu par e-mail (vous pouvez le renvoyer depuis la page de connexion)."];
        }
        return [$user, null];
    }

    /** Toujours silencieux : ne révèle pas si l'adresse existe */
    public function requestPasswordReset(string $email, string $ip): void
    {
        $limiter = $this->app->limiter();
        if ($limiter->tooManyAttempts('reset', $ip, $email)) {
            return;
        }
        $limiter->hit('reset', $ip, $email);
        $user = $this->users->findByEmail($email);
        if ($user === null || $user['status'] === 'banned') {
            return;
        }
        $token = $this->tokens->create((int) $user['id'], 'reset_password', self::RESET_LIFETIME);
        $this->mail($user['email'], 'Réinitialisation de votre mot de passe', 'mail/reset', [
            'user' => $user,
            'link' => $this->app->absoluteUrl('/reset', ['token' => $token]),
        ]);
    }

    public function resendVerification(string $email, string $ip): void
    {
        $limiter = $this->app->limiter();
        if ($limiter->tooManyAttempts('reset', $ip, $email)) {
            return;
        }
        $limiter->hit('reset', $ip, $email);
        $user = $this->users->findByEmail($email);
        if ($user !== null && $user['status'] === 'pending') {
            $this->sendVerification($user);
        }
    }

    public function isResetTokenValid(string $token): bool
    {
        return $this->tokens->isValid($token, 'reset_password');
    }

    /** @return string[] erreurs */
    public function resetPassword(string $token, string $password, string $confirmation): array
    {
        $errors = $this->validatePassword($password, $confirmation);
        if ($errors) {
            return $errors;
        }
        $userId = $this->tokens->consume($token, 'reset_password');
        if ($userId === null) {
            return ['Ce lien a expiré ou a déjà été utilisé.'];
        }
        $this->users->setPassword($userId, $password);
        // le lien reçu par e-mail prouve la possession de l'adresse
        $user = $this->users->find($userId);
        if ($user !== null && $user['status'] === 'pending') {
            $this->users->update($userId, ['status' => 'active', 'email_verified_at' => now_utc()]);
        }
        return [];
    }

    /** @return string[] erreurs */
    public function changePassword(array $user, string $current, string $password, string $confirmation): array
    {
        if (!$this->users->checkPassword($user, $current)) {
            return ['Mot de passe actuel incorrect.'];
        }
        $errors = $this->validatePassword($password, $confirmation);
        if (!$errors) {
            $this->users->setPassword((int) $user['id'], $password);
        }
        return $errors;
    }

    /** @return string[] erreurs */
    public function changeEmail(array $user, string $current, string $email): array
    {
        if (!$this->users->checkPassword($user, $current)) {
            return ['Mot de passe actuel incorrect.'];
        }
        if (mb_strtolower($email) === $user['email']) {
            return [];
        }
        $errors = $this->validateEmail($email);
        if ($errors) {
            return $errors;
        }
        $this->users->update((int) $user['id'], ['email' => mb_strtolower($email), 'email_verified_at' => null]);
        if ($this->app->mailer()->isConfigured()) {
            $this->sendVerification($this->users->find((int) $user['id']));
        }
        return [];
    }

    /** @return string[] */
    public function validateCallsign(string $callsign): array
    {
        if (!UserRepository::isValidCallsign($callsign)) {
            return ["L'indicatif doit faire de 2 à 32 caractères (lettres, chiffres, _ - . é è à) et commencer par une lettre."];
        }
        if ($this->users->findByCallsign($callsign) !== null) {
            return ['Cet indicatif est déjà utilisé.'];
        }
        return [];
    }

    /** @return string[] */
    public function validateEmail(string $email): array
    {
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false || mb_strlen($email) > 191) {
            return ["L'adresse e-mail n'est pas valide."];
        }
        if ($this->users->findByEmail($email) !== null) {
            return ['Cette adresse e-mail est déjà utilisée.'];
        }
        return [];
    }

    /** @return string[] */
    public function validatePassword(string $password, string $confirmation): array
    {
        if (mb_strlen($password) < self::MIN_PASSWORD_LENGTH) {
            return ['Le mot de passe doit contenir au moins ' . self::MIN_PASSWORD_LENGTH . ' caractères.'];
        }
        if ($password !== $confirmation) {
            return ['Les deux mots de passe ne correspondent pas.'];
        }
        return [];
    }

    private function mail(string $to, string $subject, string $template, array $data): void
    {
        $site = $this->app->settings()->get('site_name');
        $body = $this->app->view()->render($template, $data + ['site' => $site]);
        $this->app->mailer()->send($to, "[$site] $subject", $body);
    }
}
