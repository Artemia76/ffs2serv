<?php
if (!defined('PROTECTIONINCLUDE')) {die('Accès Interdit');}


class Auto_loader
{
    /**
     * Enregistre l'autoloader
     */
    static function register()
    {
        spl_autoload_register(array(__CLASS__, 'autoload'));
    }

    /**
     * Inclue le fichier correspondand à la classe appellée
     * @param $class string, nom de class appellée
     */
    static function autoload($class)
    {
        //echo  __DIR__ .'/'.$class.'.php';
        require  __DIR__ .'/class/'.$class.'.php';
    }
}

?>