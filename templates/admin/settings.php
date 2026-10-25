<?php
/** @var \Ffs2\Core\Settings $settings */ /** @var bool $mailConfigured */ /** @var ?string $certificateExpires */
/** @var array $migrations */ /** @var ?string $transportForced */
$checked = fn (string $name) => $settings->bool($name) ? ' checked' : '';
?>
<header class="page-header"><h1>Réglages</h1></header>
<?= $view->partial('admin/nav') ?>

<form method="post" action="<?= e(url('/admin/settings')) ?>" class="form">
    <?= csrf_field() ?>
    <div class="grid two">
        <section class="card">
            <h2>Site</h2>
            <label>Nom du site <input name="site_name" value="<?= e($settings->get('site_name')) ?>" required maxlength="64"></label>
            <label>Description <input name="site_description" value="<?= e($settings->get('site_description')) ?>" maxlength="200"></label>
            <label>Fuseau horaire d'affichage <input name="timezone" value="<?= e($settings->get('timezone')) ?>" list="timezones"></label>
            <datalist id="timezones"><?php foreach (\DateTimeZone::listIdentifiers() as $tz): ?><option value="<?= e($tz) ?>"><?php endforeach; ?></datalist>
            <label class="checkbox"><input type="checkbox" name="registration_open" value="1"<?= $checked('registration_open') ?>> Inscriptions ouvertes</label>
            <label class="checkbox"><input type="checkbox" name="require_email_verification" value="1"<?= $checked('require_email_verification') ?>>
                Validation de l'adresse e-mail à l'inscription<?= $mailConfigured ? '' : ' (inactive tant que l\'envoi d\'e-mails n\'est pas configuré)' ?></label>
            <label class="checkbox"><input type="checkbox" name="users_can_create_servers" value="1"<?= $checked('users_can_create_servers') ?>> Les pilotes peuvent créer des serveurs</label>
            <label>Nombre maximum de serveurs par pilote <input type="number" name="max_servers_per_user" min="0" max="50" value="<?= e($settings->get('max_servers_per_user')) ?>"></label>
        </section>

        <section class="card">
            <h2>E-mails</h2>
            <?php if ($transportForced): ?>
                <p class="alert alert-warning">Transport forcé par la configuration : <?= e($transportForced) ?>.</p>
            <?php endif; ?>
            <label>Envoi
                <select name="mail_transport">
                    <option value="mail"<?= $settings->get('mail_transport') === 'mail' ? ' selected' : '' ?>>Fonction mail() de PHP (hébergeur)</option>
                    <option value="smtp"<?= $settings->get('mail_transport') === 'smtp' ? ' selected' : '' ?>>Serveur SMTP</option>
                </select>
            </label>
            <label>Adresse d'expédition <input type="email" name="mail_from" value="<?= e($settings->get('mail_from')) ?>" placeholder="no-reply@votre-domaine.fr"></label>
            <label>Nom de l'expéditeur <input name="mail_from_name" value="<?= e($settings->get('mail_from_name')) ?>"></label>
            <div class="grid two">
                <label>Serveur SMTP <input name="smtp_host" value="<?= e($settings->get('smtp_host')) ?>"></label>
                <label>Port <input type="number" name="smtp_port" value="<?= e($settings->get('smtp_port')) ?>"></label>
            </div>
            <label>Chiffrement
                <select name="smtp_encryption">
                    <?php foreach (['tls' => 'STARTTLS (587)', 'ssl' => 'SSL/TLS (465)', 'none' => 'Aucun'] as $value => $label): ?>
                        <option value="<?= e($value) ?>"<?= $settings->get('smtp_encryption') === $value ? ' selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Utilisateur SMTP <input name="smtp_user" value="<?= e($settings->get('smtp_user')) ?>" autocomplete="off"></label>
            <label>Mot de passe SMTP <input type="password" name="smtp_pass" placeholder="<?= $settings->get('smtp_pass') !== '' ? '(inchangé)' : '' ?>" autocomplete="new-password"></label>
        </section>
    </div>

    <section class="card">
        <h2>Carte</h2>
        <label>URL des tuiles <input name="tile_url" value="<?= e($settings->get('tile_url')) ?>" required></label>
        <label>Attribution <input name="tile_attribution" value="<?= e($settings->get('tile_attribution')) ?>"></label>
        <p class="muted small">Par défaut OpenStreetMap ; pour un trafic important, utilisez un fournisseur de tuiles dédié.</p>
    </section>

    <section class="card">
        <h2>Mises à jour</h2>
        <div class="grid two">
            <label>FFS2play : obligatoire en dessous de <input name="update_min_version_ffs2play" value="<?= e($settings->get('update_min_version_ffs2play')) ?>" placeholder="ex. 2.5.0" pattern="\d+\.\d+\.\d+"></label>
            <label>xffs2play : obligatoire en dessous de <input name="update_min_version_xffs2play" value="<?= e($settings->get('update_min_version_xffs2play')) ?>" placeholder="ex. 2.1.0" pattern="\d+\.\d+\.\d+"></label>
        </div>
        <p class="muted small">Vide : la mise à jour reste facultative. Les versions publiées sont listées dans <a href="<?= e(url('/admin/releases')) ?>">Releases</a>.</p>
    </section>

    <button type="submit" class="button">Enregistrer les réglages</button>
</form>

<div class="grid two">
    <section class="card">
        <h2>Test d'envoi</h2>
        <form method="post" action="<?= e(url('/admin/settings/test-mail')) ?>">
            <?= csrf_field() ?><button class="button ghost" type="submit">M'envoyer un e-mail de test</button>
        </form>
    </section>
    <section class="card">
        <h2>Clés du serveur</h2>
        <p>Certificat valable jusqu'au <strong><?= e(format_datetime($certificateExpires, 'd/m/Y')) ?></strong>.</p>
        <form method="post" action="<?= e(url('/admin/settings/keys')) ?>" class="confirm" data-confirm="Générer une nouvelle paire de clés ? Les pilotes connectés devront se reconnecter.">
            <?= csrf_field() ?><button class="button ghost" type="submit">Régénérer la paire RSA</button>
        </form>
    </section>
    <section class="card">
        <h2>Base de données</h2>
        <?php if ($migrations): ?>
            <p>Mises à jour en attente : <?= e(implode(', ', $migrations)) ?></p>
            <form method="post" action="<?= e(url('/admin/settings/migrate')) ?>">
                <?= csrf_field() ?><button class="button" type="submit">Appliquer les mises à jour</button>
            </form>
        <?php else: ?>
            <p class="muted">Le schéma est à jour (version <?= e(FFS2_VERSION) ?>).</p>
        <?php endif; ?>
    </section>
</div>
