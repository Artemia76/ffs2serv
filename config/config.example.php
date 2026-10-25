<?php
/*
 * Exemple de config/config.php — ce fichier est normalement généré par l'installeur
 * (public/install ou bin/install.php). Ne versionnez jamais config.php : il contient des secrets.
 */
return [
    'db' => [
        'host' => 'localhost',
        'port' => 3306,
        'name' => 'ffs2play',
        'user' => 'ffs2play',
        'pass' => 'mot de passe',
        'prefix' => 'ffs2_',
    ],
    // 32 octets aléatoires en base64 : base64_encode(random_bytes(32))
    'app_key' => '',
    // adresse publique, à renseigner derrière un proxy ; vide = déduite de la requête
    'base_url' => '',
    // false si le serveur web ne réécrit pas les URL (liens en index.php/page)
    'pretty_urls' => true,
    // affiche le détail des erreurs (développement uniquement)
    'debug' => false,
    // transport d'e-mail imposé (file : écrit les e-mails dans storage/mail, tests) ; absent = réglage du back-office
    // 'mail_transport' => 'file',
    // chemins facultatifs
    // 'paths' => ['keys' => __DIR__ . '/keys', 'storage' => __DIR__ . '/../storage'],
];
