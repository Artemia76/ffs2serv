<?php
if (!defined ('PROTECTIONINCLUDE')) {
	die('Accès Interdit');
}

/**
 * Slot de connexion d'un joueur au serveur multijoueurs FFS2play
 *
 * Chaque slot est stocké dans un fichier JSON du répertoire SESSION_DIR, nommé d'après le pseudo du pilote.
 * Le pseudo est validé avant d'être utilisé comme nom de fichier (pas de traversée de répertoire possible).
 */
class Session_mp
{
	// délai sans mise à jour au delà duquel un joueur n'est plus listé (secondes)
	const ACTIVE_DELAY = 40;
	// délai au delà duquel un slot est supprimé (secondes)
	const PURGE_DELAY = 300;

	protected $pilot_id;//l'identifiant du pilote
	protected $pilotname;//nom du pilote
	protected $ip;//l'IP distante du pilote
	protected $local_ip;//les IP locales du pilote (StringArray protobuf en base64, transmis tel quel aux autres joueurs)
	protected $port;//port de communication P2P
	protected $pu_key;//clé AES du client en base64, utilisée pour crypter les IP envoyées à ce client
	protected $key_f;//clé de rappel
	protected $attached;//objet de carrière auquel est rattaché cette clé de rappel
	protected $server;//serveur auquel le joueur est connecté, 0=serveur public
	protected $tx_mod;//méthode de transmission, 0=null, 1=normal...
	protected $lastupdate;//timestamp unix de la dernière mise à jour
	protected $err_obj = NULL;

	//attributs accessibles en lecture
	protected $attr_reader = array ('pilot_id', 'pilotname', 'ip', 'local_ip', 'port', 'pu_key', 'key_f', 'attached', 'server', 'tx_mod', 'lastupdate', 'err_obj');

	/**
	 * gestionnaire des accés en lecture
	 *
	 * @param String $attribute: attribut à lire
	 * @return la valeur de l'attribut, NULL si la lecture est refusée
	 */
	public function __get ($attribute)
	{
		if (in_array ($attribute, $this->attr_reader)) {
			return $this->$attribute;
		}
		$this->add_error (get_class ($this) . " Signal: Accés à la lecture refusé pour la variable '$" . $attribute . "'");
		return NULL;
	}

	/**
	 * Chargement du slot d'un pilote
	 *
	 * @param String $pilotname, pseudo du pilote
	 */
	public function __construct ($pilotname = NULL)
	{
		if ($pilotname !== NULL and self::is_valid_pilotname ($pilotname)) {
			$data = self::read_file (self::slot_file ($pilotname));
			if ($data !== NULL) {
				$this->hydrate ($data);
			}
		}
	}

	/**
	 * Vérifie qu'un pseudo est utilisable : mêmes caractères que ceux autorisés par le client
	 * (gui/dialogprofils.cpp), et nom d'élément XML valide car il sert de balise dans le whazzup
	 *
	 * @param String $pilotname
	 * @return Bool
	 */
	public static function is_valid_pilotname ($pilotname)
	{
		return preg_match ('/^[A-Za-z_éèà][A-Za-z0-9_\-\.éèà]{0,31}$/u', strval ($pilotname)) === 1;
	}

	/**
	 * Indique si le slot existe et a été mis à jour récemment
	 *
	 * @return Bool
	 */
	public function is_active ()
	{
		return $this->pilotname !== NULL and $this->lastupdate >= time () - self::ACTIVE_DELAY;
	}

	/**
	 * Recherche du slot correspondant à une clé de rappel
	 *
	 * @param String $key_f, clé de rappel envoyée par le client
	 * @return Session_mp ou NULL si aucun slot ne correspond
	 */
	public static function find_by_key ($key_f)
	{
		$key_f = strval ($key_f);
		if ($key_f === '') {
			return NULL;
		}
		foreach (self::all_slots () as $data) {
			if (isset ($data['key_f']) and hash_equals ($data['key_f'], $key_f)) {
				$slot = new Session_mp();
				$slot->hydrate ($data);
				return $slot;
			}
		}
		return NULL;
	}

	/**
	 * Liste des joueurs actifs sur un serveur, demandeur compris : le client ignore sa propre
	 * entrée, sauf en mode "shadow" où il crée un pair superposé pour tester le P2P en boucle locale
	 *
	 * @param Int $server, numéro du serveur à lister
	 * @param String $crypt_key, clé AES (binaire) utilisée pour crypter les IP
	 * @return Array, tableau des joueurs prêt pour pre_xml()
	 */
	public static function get_whazzup ($server, $crypt_key)
	{
		$whazzup = array ();
		foreach (self::all_slots () as $data) {
			if (!isset ($data['pilotname'], $data['lastupdate'])) {
				continue;
			}
			if ($data['lastupdate'] < time () - self::ACTIVE_DELAY or intval ($data['port']) === 0) {
				continue;
			}
			if (strval ($data['server']) !== strval ($server)) {
				continue;
			}
			$whazzup[] = array (
				'name'       => $data['pilotname'],
				'ip'         => encrypt ($data['ip'], $crypt_key, TRUE),
				'local_ip'   => $data['local_ip'],
				'port'       => $data['port'],
				'server'     => $data['server'],
				'lastupdate' => date ("Y-m-d H:i:s", $data['lastupdate']),
			);
		}
		return $whazzup;
	}

	/**
	 * Suppression des slots qui n'ont pas été mis à jour depuis PURGE_DELAY secondes
	 */
	public static function purge ()
	{
		foreach ((glob (SESSION_DIR . '/*.json') ?: array ()) as $file) {
			if (filemtime ($file) < time () - self::PURGE_DELAY) {
				@unlink ($file);
			}
		}
	}

	/**
	 * Suppression du slot (déconnexion du joueur)
	 *
	 * @return Bool, TRUE si le slot a été supprimé
	 */
	public function remove ()
	{
		if (!self::is_valid_pilotname ($this->pilotname)) {
			return FALSE;
		}
		return @unlink (self::slot_file ($this->pilotname));
	}

	/**
	 * Création du slot d'un joueur
	 *
	 * @param Int $piloteid, numéro du pilote propriétaire de ce slot
	 * @param String $username, nom du pilote (validé par is_valid_pilotname)
	 * @param Object $xml, requête du client contenant le port et les IP locales
	 * @param Int $server, numéro du serveur sur lequel la connexion est demandée
	 * @param String $pu_key, clé AES du client en base64
	 * @param Int $tx_mod, mode de transmission, pas encore implémenté
	 * @param Int $attached, numéro de réservation auquel est rattaché ce slot (module carrière)
	 * @return Bool, TRUE si le slot est enregistré
	 */
	public function create_slot ($piloteid, $username, $xml, $server = 0, $pu_key = NULL, $tx_mod = 0, $attached = 0)
	{
		if (!self::is_valid_pilotname ($username) or $xml === NULL) {
			$this->add_error ("le nom du pilote n'est pas valide");
			return FALSE;
		}
		$port = intval ($xml->verify->port);
		$local_ip = strval ($xml->verify->local_ip);
		$this->pilot_id = intval ($piloteid);
		$this->pilotname = $username;
		$this->ip = $_SERVER["REMOTE_ADDR"];
		$this->local_ip = preg_match ('/^[A-Za-z0-9+\/=]*$/', $local_ip) === 1 && $local_ip !== '' ? $local_ip : 'na';
		$this->port = ($port > 0 and $port < 65536) ? $port : 11111;
		$this->pu_key = $pu_key === NULL ? '' : strval ($pu_key);
		$this->key_f = bin2hex (random_bytes (16));
		$this->attached = intval ($attached);
		$this->server = intval ($server);
		$this->tx_mod = intval ($tx_mod);
		return $this->update_my_slot ();
	}

	/**
	 * Mise à jour du slot d'un joueur (heure de dernière activité)
	 *
	 * @return Bool, TRUE si le slot est enregistré
	 */
	public function update_my_slot ()
	{
		if (!self::is_valid_pilotname ($this->pilotname)) {
			return FALSE;
		}
		$this->lastupdate = time ();
		$data = array (
			'pilot_id'   => $this->pilot_id,
			'pilotname'  => $this->pilotname,
			'ip'         => $this->ip,
			'local_ip'   => $this->local_ip,
			'port'       => $this->port,
			'pu_key'     => $this->pu_key,
			'key_f'      => $this->key_f,
			'attached'   => $this->attached,
			'server'     => $this->server,
			'tx_mod'     => $this->tx_mod,
			'lastupdate' => $this->lastupdate,
		);
		return file_put_contents (self::slot_file ($this->pilotname), json_encode ($data), LOCK_EX) !== FALSE;
	}

	/**
	 * Fonction d'hydratation permettant le remplissage de l'objet
	 *
	 * @param Array , tableau des données à mettre en attributs
	 */
	protected function hydrate ($data)
	{
		foreach ($data as $key => $value) {
			if (property_exists ($this, $key) and in_array ($key, $this->attr_reader)) {
				$this->$key = $value;
			}
		}
	}

	/**
	 * gestionnaire des alertes
	 *
	 * @param String $data: message d'alerte, stocké dans err_obj à disposition du programmeur
	 */
	protected function add_error ($data)
	{
		if ($data != NULL) {
			if (!is_array ($this->err_obj)) {
				$this->err_obj = array ();
			}
			$this->err_obj[] = $data;
		}
	}

	private static function slot_file ($pilotname)
	{
		return SESSION_DIR . '/' . $pilotname . '.json';
	}

	private static function read_file ($file)
	{
		if (!is_file ($file)) {
			return NULL;
		}
		$data = json_decode (file_get_contents ($file), TRUE);
		return is_array ($data) ? $data : NULL;
	}

	private static function all_slots ()
	{
		$slots = array ();
		foreach ((glob (SESSION_DIR . '/*.json') ?: array ()) as $file) {
			$data = self::read_file ($file);
			if ($data !== NULL) {
				$slots[] = $data;
			}
		}
		return $slots;
	}
}
?>
