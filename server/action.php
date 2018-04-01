<?php
	define ('PROTECTIONINCLUDE','TRUE');

error_reporting (E_ALL);
$data_xml;

define ('debug_ffst', TRUE);



//calcul d'une date antérieur de 5 minutes
$now           = date ("Y-m-d H:i:s");
$date          = new DateTime ($now);
$date->sub (new DateInterval ('PT5M'));
$m_5_minutes   = $date->format ("Y-m-d H:i:s");
$date          = new DateTime ($now);
$date->sub (new DateInterval ('PT2M'));
$m_2_minutes   = $date->format ("Y-m-d H:i:s");
$date          = new DateTime ($now);
$date->sub (new DateInterval ('PT35S'));
$m_35_secondes = $date->format ("Y-m-d H:i:s");




try
{
	//tentative de chargement du protocole xml
	if (!@include_once("./config/config_gen.php")) throw new Exception ("Votre fichier de configuration est introuvable");


    //tentative de chargement du protocole xml
    if (!@include_once("./class/xml.php")) throw new Exception ("Votre systéme XML est introuvable");

    //tentative de chargement des protocoles de cryptage
    if (!@include_once("./class/phpseclib/Crypt/Random.php"))
    {
        throw new Exception ("Votre élément de cryptage Random  est introuvable");
    }

    if (!@include_once("./class/phpseclib/Crypt/Base.php"))
    {
        throw new Exception ("Votre élément de cryptage Base  est introuvable");
    }


    if (!@include_once("./class/phpseclib/Crypt/Rijndael.php"))
    {
        throw new Exception ("Votre élément de cryptage Rijndael  est introuvable");
    }
    if (!@include_once("./class/phpseclib/Crypt/Hash.php"))
    {
        throw new Exception ("Votre élément de cryptage Hash  est introuvable");
    }
    if (!@include_once("./class/phpseclib/Math/BigInteger.php"))
    {
        throw new Exception ("Votre élément de cryptage BigInteger  est introuvable");
    }
    if (!@include_once("./class/phpseclib/Crypt/RSA.php"))
    {
        throw new Exception ("Votre élément de cryptage RSA  est introuvable");
    }
    if (!@include_once("./class/phpseclib/Crypt/AES.php"))
    {
        throw new Exception ("Votre élément de cryptage AES  est introuvable");
    }
    if (!@include_once("./class/crypto.php"))
    {
        throw new Exception ("Le systéme de cryptage simplifié est introuvable");
    }

    //tentative de chargement des logs
    if (!@include_once("./class/erreur_log.php")) throw new Exception ("Le systéme d'inter-com est introuvable");
    //tentative de chargement des sessions


    if (!@include_once("./class/Session_mp.php")) throw new Exception ("Le systéme de sessions est introuvable");

    //tentative de chargement du gestionnaire de carte
    if (!@include_once("./class/map_data.php")) throw new Exception ("Le gestionnaire de carte est introuvable");


}
catch (Exception $e)
{
    //en cas de soucis on affiche l'origine du dysfonctionnement
    echo $e->getMessage ();
    //on stop l'execution du programme
    die;
}

//initialisation de l'objet pour les messages de retour sur FFSTracker
$inter_com = New Erreur();

date_default_timezone_set ("Europe/Brussels");


//si des données arrive par la méthode POST
if ($_SERVER['REQUEST_METHOD'] === 'POST')
{
    //vérification du format de l'ip en IP V4
    preg_match_all ('/\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}/', $_SERVER["REMOTE_ADDR"], $good_ip, PREG_SET_ORDER, 0);

    //si l'ip est bien au format IP V4
    if (count ($good_ip) > 0)
    {
        $data_xml["your_ip"] = $_SERVER["REMOTE_ADDR"];
        //on extrait les données XML
        $xml                 = xml_extract (file_get_contents ('php://input'));

        //si le logiciel client est FFS2play on utilise le dernier mod de cryptographie


        //if ($xml->version == 'ffs2core_2_0_0')
        if (stripos($xml->version, "ffs2play_2")!==false)
        {
            $new_gen_aes = true;
        }
        else
        {
            $new_gen_aes = false;
        }

        //recherche de l'activation ou nom du mod atc

		// si le client fait une demande de cryptage dynamique
        if (strtolower ($xml->switch->data) == "hello")
        {
            try
            {
                //tentative de chargement du gestionnaire de cryptage dynamique
                if (!@include_once("./multi/hello_world.php"))
                {
                    throw new Exception ("Gestionnaire de cryptage dynamique  est introuvable");
                }
            }
            catch (Exception $e)
            {
                //en cas de soucis on affiche l'origine du dysfonctionnement
                echo $e->getMessage ();
                //on stop l'execution du programme
                die;
            }
        }

        // si le client fait une demande d'authentification
        if (strtolower ($xml->switch->data) == "verify")
        {
            try
            {
                //tentative de chargement du gestionnaire d'authentification
                if (!@include_once("./multi/verify.php"))
                {
                    throw new Exception ("Gestionnaire d'authentification  est introuvable");
                }
            }
            catch (Exception $e)
            {
                //en cas de soucis on affiche l'origine du dysfonctionnement
                echo $e->getMessage ();
                //on stop l'execution du programme
                die;
            }
        }

        // si le client fait une demande de mise à jours
        if (strtolower ($xml->switch->data) == "liveupdate")
        {
            try
            {
                //tentative de chargement du gestionnaire de mise à jours
                if (!@include_once("./multi/liveupdate.php")) throw new Exception ("Gestionnaire de mise à jours  est introuvable");
            }
            catch (Exception $e)
            {
                //en cas de soucis on affiche l'origine du dysfonctionnement
                echo $e->getMessage ();
                //on stop l'execution du programme
                die;
            }
        }

        /*
        // si le client fait une demande de mise à jours
        if (strtolower ($xml->switch->data) == "atc")
        {
            try
            {
                //tentative de chargement du gestionnaire de mise à jours
                if (!@include_once("./multi/live_atc.php")) throw new Exception ("Gestionnaire de live atc est introuvable");
            }
            catch (Exception $e)
            {
                //en cas de soucis on affiche l'origine du dysfonctionnement
                echo $e->getMessage ();
                //on stop l'execution du programme
                die;
            }
        }
        */
    }
    else
    {
        $inter_com->addMessage (219, $msg); //Votre ip ne correspond pas aux normes IPV4.
        $inter_com->addMessage (201, $msg); //Connexion avec FFSTracker interrompue...
        $inter_com->addMessage (2, $msg); //Bonne fin de journée
        $data_xml['loginStatus'] = '0'; //on refuse la connexion
    }
}

//Rajout des messages dans le XML
if ($inter_com->getMessage () != NULL)
{
    $data_xml["erreur"] = $inter_com->getMessage ();
}
if ($inter_com->getWarning () != NULL)
{
    $data_xml["erreur1"] = $inter_com->getWarning ();
}
if ($inter_com->getError () != NULL)
{
    $data_xml["erreur2"] = $inter_com->getError ();
}
if ($inter_com->getDebug () != NULL)
{
    $data_xml["erreur3"] = $inter_com->getDebug ();
}

$data2 = pre_xml ($data_xml);
$xml   = new Array2XML();
print $xml->buildXML ($data2);
?>