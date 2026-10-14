# Portail FFS2play

Portail web et serveur de mise en relation pour [FFS2play](https://github.com/Artemia76/ffs2play),
le multijoueur pair à pair pour MSFS, Prepar3D, FSX, X-Plane et FlightGear.

- **Comptes pilotes** : inscription avec validation par e-mail, connexion, mot de passe oublié.
  Le logiciel ffs2play se connecte avec l'indicatif et le mot de passe du site.
- **En direct** : carte (OpenStreetMap) et tableau des pilotes connectés, par serveur, rafraîchis toutes les 5 s.
- **Serveurs** publics ou privés (membres acceptés par leur propriétaire), **mode global**, **équipes**.
- **Profil** : choix du serveur, historique des vols (détectés automatiquement du décollage à l'atterrissage,
  avec leur trace), heures de vol et de connexion, appareils et simulateurs favoris, profil public.
- **Back-office** : membres (activation, suspension, rôles), pilotes connectés (déconnexion), serveurs, équipes,
  réglages (inscriptions, e-mails SMTP, carte), clés du serveur, mises à jour du schéma.

PHP natif sans dépendance (aucun Composer), MariaDB ou MySQL. Se déploie par simple copie sur un hébergement mutualisé.

> La version minimale sans base de données ni interface web (mot de passe de session commun) reste disponible :
> tag [`v1-standalone`](https://github.com/Artemia76/ffs2serv_standalone/tree/v1-standalone).

## Prérequis

- PHP 8.1 ou plus récent, extensions `pdo_mysql`, `openssl`, `mbstring`, `dom`, `simplexml`, `xmlwriter`
- MariaDB 10.3+ ou MySQL 5.7+ (une base vide)
- Apache avec `mod_rewrite` (fichiers `.htaccess` fournis) ou nginx
- Client FFS2play 2.x ou plus récent

## Installation

1. Copiez tous les fichiers sur le serveur.
2. Faites pointer le domaine (racine web) sur le dossier **`public/`**.
   Si votre hébergeur ne le permet pas, laissez le domaine sur le dossier principal : le `.htaccess` racine
   redirige tout vers `public/` et bloque l'accès au code, à la configuration et aux clés.
3. Créez une base de données vide depuis le panneau de votre hébergeur.
4. Ouvrez `https://votre-domaine/install/` : l'installeur vérifie les prérequis, crée les tables,
   la paire de clés RSA du serveur, le compte administrateur et le serveur de jeu « Public ».
   Il se verrouille ensuite (présence de `config/config.php`).
5. Connectez-vous, puis dans **Administration › Réglages** configurez l'envoi d'e-mails
   (fonction `mail()` de l'hébergeur ou serveur SMTP) et faites un envoi de test.

Installation en ligne de commande (VPS) — à lancer **sous l'utilisateur du serveur web** pour qu'il puisse
écrire dans `config/` et `storage/` :

    sudo -u www-data php bin/install.php --db-host=localhost --db-name=ffs2play --db-user=ffs2play --db-pass=... \
        --admin-callsign=Admin --admin-email=admin@example.org --admin-password=... --site-name="FFS2play"

### nginx

```nginx
root /var/www/ffs2play/public;
index index.php;
location / { try_files $uri $uri/ /index.php?$query_string; }
location ~ \.php$ { include fastcgi_params; fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name; fastcgi_pass unix:/run/php/php8.3-fpm.sock; }
```

Sans réécriture d'URL possible, passez `'pretty_urls' => false` dans `config/config.php` (liens en `index.php/page`) ;
l'installeur le détecte automatiquement.

### Hébergement sans dossier privé (YunoHost « My Webapp », nginx sans `.htaccess`)

Copiez **le contenu de `public/`** à la racine web, et à côté les dossiers `src/`, `templates/`, `database/`
et `config/` (disposition « à plat »). Le portail le détecte tout seul. Dans ce cas, tout ce qui est secret est
stocké dans des fichiers PHP (`config/config.php`, `config/keys/*.php`) : une requête web les exécute sans rien
afficher. Ne copiez pas `tests/`, `docker/` ni `bin/` sur un tel hébergement.

### Connecter ffs2play

Dans ffs2play, créez un profil avec l'**URL du site** (ex. `https://ffs2play.fr`), votre **indicatif** et
votre **mot de passe**. Le client appelle `<URL>/action.php`. La page profil du site rappelle ces informations.

## Mise à jour

Remplacez les fichiers (en conservant `config/` et `storage/`), puis appliquez les éventuelles mises à jour
du schéma depuis **Administration › Réglages** ou avec `php bin/install.php --migrate`.

### Depuis le serveur standalone

L'URL appelée par les clients (`action.php`) ne change pas. Les comptes remplacent le mot de passe de session :
chaque pilote crée son compte sur le site avec l'indicatif qu'il utilise dans ffs2play. L'ancien
`config/config_gen.php` n'est plus utilisé ; une nouvelle paire de clés est générée à l'installation
(les clients récupèrent le certificat à chaque connexion, aucune mise à jour de leur côté).

## Fonctionnement

| Client → `action.php` | Rôle |
|---|---|
| `hello` | renvoie le certificat RSA du serveur |
| `verify` | authentification avec le compte du site : clé AES-256 du client cryptée en RSA PKCS#1 v1.5, mot de passe en AES-256-CFB ; ouverture du slot sur le serveur choisi dans le profil ; liste des pilotes |
| `liveupdate` (30 s) | maintien du slot, position, phase de vol, liste des pilotes |
| `atc` (5 s) | position (carte en direct, distance des vols) |
| `logout` | fermeture du slot et de la connexion |

La liste des pilotes (`whazzup`) contient les pilotes actifs du même serveur, plus ceux en mode global si le
demandeur l'est aussi. Le demandeur y figure : le client l'ignore, sauf en **mode shadow** où il crée un pair
superposé pour tester le P2P en boucle locale. L'attribut `team` vaut l'indicatif du chef d'équipe.
Les adresses IP ne sont transmises qu'aux clients, cryptées avec leur propre clé ; elles ne sont jamais affichées sur le site.

```
public/        racine web : index.php (pages), action.php (client ffs2play), install/, assets/
src/           Core (base, routeur, session, e-mails…), Auth, Client (protocole), Domain (serveurs, vols…), Web (pages)
templates/     gabarits HTML et e-mails
database/      migrations SQL
config/        configuration et clés générées (jamais versionnées)
storage/       données de travail
```

## Développement et tests

    docker compose up -d                       # http://localhost:8080 (installeur au premier lancement, base « db »)
    docker compose exec web php tests/run.php  # tests (base ffs2play_test recréée à chaque fois)

Les tests installent le portail dans une base jetable puis rejouent, contre le serveur intégré de PHP :
le chiffrement (vecteur de référence OpenSSL du client), le protocole ffs2play complet (comptes, serveurs, mode
global, équipes, positions), les pages web (inscription → e-mail → validation → profil, CSRF, droits, limitation
des essais) et la détection des vols.

## Licence

The license is as published by the Free Software Foundation and appearing in the file LICENSE.GPLv3 included in the packaging of this software. Please review the following information to ensure the GNU General Public License requirements will be met: https://www.gnu.org/licenses/gpl-3.0.html.
