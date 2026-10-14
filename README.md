# FFS2play standalone server

Serveur de mise en relation des joueurs [FFS2play](https://github.com/Artemia76/ffs2play) : il authentifie
les clients avec le mot de passe de la session et leur communique la liste des joueurs connectés
(adresse IP cryptée, IP locales, port P2P). Les échanges de position se font ensuite en pair à pair.

Aucune base de données : chaque joueur connecté est un fichier JSON dans `nodb/Session_mp/`.

## Prérequis

- PHP >= 7.4 avec les extensions `openssl`, `mbstring`, `simplexml` et `xmlwriter` (présentes par défaut)
- un serveur web exécutant PHP (Apache, nginx + php-fpm...) ; seul `action.php` doit être accessible

Client supporté : FFS2play 2.x et suivants (Qt 5 et Qt 6 : le protocole est identique).

## Installation

1. Copier les fichiers sur le serveur web.
2. Générer la configuration (clés RSA, mot de passe de session) :

        php tools/generate_config.php --name="Mon serveur" --password="mot de passe de la session"

   `config/config_gen.php` contient la clé privée du serveur : il n'est jamais versionné.
   Le certificat est valide 10 ans (`--days` pour changer). Les clients récupèrent le certificat à chaque
   connexion : changer les clés ne demande aucune mise à jour des clients.
3. Vérifier que le serveur web peut écrire dans le dossier (création automatique de `nodb/Session_mp/`).
4. Dans FFS2play, créer un profil avec l'URL du dossier (le client appelle `<URL>/action.php`),
   un pseudo et le mot de passe de la session.

Le fichier `.htaccess` fourni bloque l'accès web à tout sauf `action.php` (Apache).
Avec nginx, l'équivalent est :

    location / { deny all; }
    location = /action.php { allow all; include fastcgi_params; fastcgi_pass ...; }

## Protocole

Le client envoie en POST un document XML `<FFS2Play><version>ffs2play_2_4_0</version><switch><data>...`

| `switch/data` | Rôle |
|---|---|
| `hello` | renvoie le certificat RSA du serveur |
| `verify` | le client envoie sa clé AES-256 cryptée en RSA PKCS#1 v1.5, son pseudo, le mot de passe crypté en AES-256-CFB, son port P2P et ses IP locales ; le serveur renvoie une clé de rappel et la liste des joueurs |
| `liveupdate` | maintient le slot actif (toutes les quelques secondes) et renvoie la liste des joueurs |
| `logout` | supprime le slot |

Format AES partagé avec le client (`tools/crypto.cpp`) : `base64( chiffré . "-[--IV-[-" . IV 16 octets )`.
Un joueur disparaît de la liste 40 s après sa dernière mise à jour ; son slot est supprimé après 5 minutes.

## Tests

    php tests/run.php

ou, sans PHP installé :

    docker run --rm -v "$PWD":/app -w /app php:8.3-cli php tests/run.php

Les tests vérifient la compatibilité du chiffrement avec le client (vecteur de référence OpenSSL) et
rejouent un scénario complet hello / verify / liveupdate / logout contre le serveur intégré de PHP.

## Licence

The license is as published by the Free Software Foundation and appearing in the file LICENSE.GPLv3 included in the packaging of this software. Please review the following information to ensure the GNU General Public License requirements will be met: https://www.gnu.org/licenses/gpl-3.0.html.
