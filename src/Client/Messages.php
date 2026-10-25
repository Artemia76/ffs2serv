<?php
declare(strict_types=1);

namespace Ffs2\Client;

/**
 * Messages affichés dans la console du client ffs2play (numérotation héritée de l'ancien serveur)
 *   0-100 information, 101-200 avertissement, 201-300 erreur, 301-400 debug
 */
final class Messages
{
    private const TEXTS = [
        1 => "\nBienvenue sur le serveur de %site%\n\n",
        2 => "\nBonne fin de journée\n",
        10 => "Vous êtes identifié\n",
        201 => "Connexion interrompue à la demande du serveur.\n",
        202 => "Votre version de FFS2play n'est plus supportée par le serveur.\n",
        205 => "Échec de l'authentification : indicatif ou mot de passe incorrect.\n",
        207 => "Impossible de créer un nouveau slot de connexion.\n",
        215 => "Échec de l'échange de la clé de cryptage.\n",
        219 => "Votre IP ne correspond pas aux normes IPV4.\n",
        220 => "Votre slot de connexion n'existe plus, reconnexion nécessaire.\n",
        222 => "Votre compte n'est pas encore activé : validez votre adresse e-mail sur le site.\n",
        223 => "Votre compte est suspendu.\n",
        224 => "Trop de tentatives de connexion, réessayez dans quelques minutes.\n",
        302 => "Connexion au tableau multijoueur (serveur %server%)\n",
    ];

    private static array $replacements = [];

    public static function setReplacement(string $name, string $value): void
    {
        self::$replacements['%' . $name . '%'] = $value;
    }

    public static function text(int $id): string
    {
        return strtr(self::TEXTS[$id] ?? "Message $id\n", self::$replacements);
    }
}
