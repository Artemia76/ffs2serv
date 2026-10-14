<?php
if (!defined ('PROTECTIONINCLUDE')) {
	die('Accès Interdit');
}

$msg = array();
//0 à 100 normal
$msg[0]="";
$msg[1]="\nBienvenue sur le serveur de FFS2play\n\n";
$msg[2]="\nBonne fin de journée\n";
$msg[3]="\nBonjour commandant:\n\n";
$msg[4]="\nAucune réservation disponible\nVous pouvez faire un vol libre ou simplement voler entre amis.\n\n";
$msg[5]="Vous pouvez faire un vol libre ou simplement voler entre amis.\n\n";
$msg[6]="Cela est toujours un plaisir de vous voir piloter monsieurs ;)\n";
$msg[7]="Création du pireps distant réussi.\n";
$msg[8]="Envoie du dossier au serveur distant réussi.\nVous pouvez quitter FFSTracker si vous le souhaitez.\n";
$msg[9]="Envoie du dossier au serveur distant impossible. Sauvegarder le dossier sur votre pc et envoyé le à un administrateur.\n";
$msg[10]="Vous avez fournis le bon mot de passe\n";
$msg[11]="Vous avez été connecté en tant que visiteur\n";



//101 à 200 warning
$msg[101]="Attention votre version n'est pas à jour(w)\n";
$msg[102]="Voici la derniére version ".htmlentities("http://download.ffsimulateur2.fr/ffstracker.php?download=1")." (w)\n";
$msg[103]="Vous avez été identifié comme Beta Testeur(w)\n";
$msg[104]="Impossible de mettre à jour votre posistion GPS(w)\n";


//201 à 300 erreur
$msg[201]="Connexion avec FFSTracker interrompue à la demande du serveur pirep.(e)\n";
$msg[202]="Votre version de FFSTracker n'est plus supporté par le serveur pirep(e) \n";
$msg[203]="Vous n'avez pas accepté le règlement  se trouvant à cette adresse:  http://ffsimulateur2.fr/forum/viewtopic.php?f=189&t=11875 .(e)\n";
$msg[204]="Vous ne vous êtes pas présenté. Pour le faire allez à cette adresse: http://ffsimulateur2.fr/forum/posting.php?mode=post&f=191 .(e)\n";
$msg[205]="Echec de l'authentification(e)\n";
$msg[206]="Impossible de supprimer l'ancien Slot de connexion(e) \n";
$msg[207]="Impossible de créer un nouveau Slot de connexion(e) \n";
$msg[208]="Impossible de supprimer l'anciene position(e) \n";
$msg[209]="Utilisateur non reconnu par le forum(e) \n";
$msg[210]="Impossible de créer une position radar.(e) \n";
$msg[211]="Impossible d'inscrire une nouvelle clé.(e) \n";
$msg[212]="Impossible de trouver ou de charger votre réservation.(e) \n\n";
$msg[213]="Connexion avec FFSTracker maintenue.(e) \n";
$msg[214]="Echec de la défragmentaion de la boîte noire.(e) \n";
$msg[215]="Echec de l'échange clé de cryptage.(e) \n";
$msg[216]="L'utilisateur ne semble ne pas exister.(e) \n";
$msg[217]="Vous allez être redirigé vers le serveur public.(e) \n";
$msg[218]="Ce pseudo est déja en cours d'utilisation. Patientez 30 secondes ou prenez un autre pseudo.(e) \n";
$msg[219]="Votre ip ne correspond pas aux normes IPV4(e). \n";
$msg[220]="Impossible d'actualiser votre Slot de connexion.(e) \n";
$msg[221]="Pseudo invalide : 32 caractères maximum parmi lettres, chiffres, _ - . é è à, en commençant par une lettre.(e) \n";




//301 à 400 debug
$msg[301]="Vous utilisez la derniére version de FFSTracker\nMerci de nous suivre pendant cette aventure (d)\n";
$msg[302]="Connection au tableau multi-joueurs (d)\n";
$msg[303]="Suppression d'ancien Slot de connexion (d)\n";
$msg[305]="Création d'un nouveau Slot de connexion (d)\n";
$msg[307]='Mise à jours du slot';
$msg[308]="Suppression de l'ancienne position (d)\n";
$msg[309]="Création du contact radar (d)\n";
$msg[310]="Position radar établi (d)\n";
$msg[311]="Ouvertue d'un slot primaire (d)\n";
$msg[312]="Création d'une position primaire (d)\n";
$msg[313]="Création d'une clé primaire (d)\n";
$msg[314]="Collect de CFG en cours (d)\n";
$msg[315]="Fin de la récupération des CFG (d)\n";
$msg[316]="Création du Slot réussi (d)\n\n";
$msg[317]="Enregistrement de la boîte noire prêt (d)\n";
$msg[318]="Fin du vol détecté, cloture de la boîte noire (d)\n";
$msg[319]="Défragmentaion de la boîte noire réussi. (d)\n";
$msg[320]="Création d'un fichier d'enregistrement (d)\n";


class Erreur
{
	private $com = NULL;
	private $error = NULL;
	private $warning = NULL;
	private $debug = NULL;

	public function addMessage ($id, $message, $add = NULL)
	{

		if ($add != NULL)
		{
			$this->com = $this->com . '' . $add;
		}
		else
		{
			$this->com = $this->com . '' . $message[$id];
		}
	}

	public function addWarning ($id, $message, $add = NULL)
	{

		if ($add != NULL)
		{
			$this->warning = $this->warning . '' . $add;
		}
		else
		{
			$this->warning = $this->warning . '' . $message[$id];
		}
	}

	public function addError ($id, $message, $add = NULL)
	{

		if ($add != NULL)
		{
			$this->error = $this->error . '' . $add;
		}
		else
		{
			$this->error = $this->error . '' . $message[$id];
		}
	}

	public function addDebug ($id, $message, $add = NULL)
	{

		if ($add != NULL)
		{
			$this->debug = $this->debug . '' . $add;
		}
		else
		{
			$this->debug = $this->debug . '' . $message[$id];
		}
	}

	

	public function getMessage ()
	{
		return $this->com;
	}

	public function getWarning ()
	{
		return $this->warning;
	}

	public function getError ()
	{
		return $this->error;
	}

	public function getDebug ()
	{
		return $this->debug;
	}
}