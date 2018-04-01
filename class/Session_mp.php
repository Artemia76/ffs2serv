<?php 

/**
 *	Base class for FFS multi
 *
 *	@package e107_plugins
 *	@subpackage tks_mp
 */



class Session_mp
{
	protected $id;//clé unique dans la base de données
	protected $pilot_id;//l'identifiant du pilote
	protected $ip;//l'IP distance du pilote
	protected $local_ip;//l'IP local du pilote
	protected $pilotname;//nom du pilote
	protected $port;//port de communication
	protected $pu_key=null;//clé de décryptage
	protected $key_f;//clé de rappel
	protected $attached;//objet de carriére auquel est rattaché cette clé de rappel
	protected $server=0;//serveur auquel le joueur est connecté, 0=serveur public
	protected $tx_mod=1;//méthode de transmition, 0=null, 1=normal...
	protected $lastupdate;//dernier date ou le slot à été réservé
	private $err_obj=NULL;

	protected $attr_reader = array();
	protected $attr_writer = array ();

	//tableau de gestion pour l'accés en lecture des attributs, si l'attribut n'est pas dans la liste, il ne pourra pas être lu à la volé
	protected $Mp_slot_attr = array('id', 'pilot_id', 'ip','local_ip', 'pilotname', 'online', 'port','pu_key','key_f','attached','server', 'tx_mod', 'lastupdate', 'err_obj');
	//tableau de gestion pour l'accés en écriture des attributs, si l'attribut n'est pas dans la liste, il ne pourra pas être écris à la volé
	protected $Mp_slot_attw = array();

	/**
	* chargeur des définitions lisible et inscritible
	* les attributs attr_reader et attr_writer seront remplis par la classe mére est ses filles
	*
	* @param Array $attr: variable autorisé à la lecture
	* @param Array $attw: variable autorisé à l'écriture
	* @return Aucun
	*/
	protected function set_atrw($attr,$atw)
	{
		foreach ($attr as $key => $value){if(!in_array($value, $this->attr_reader)){$this->attr_reader[]=$value;}}
		foreach ($atw as $key => $value){if(!in_array($value, $this->attr_writer)){$this->attr_writer[]=$value;}}
	}

	/**
	* gestionnaire des accés en lecture
	*
	* @param String $attribute: attribut dont les droits de lecture sont à tester
	* @return si les droits sont valident l'attribut est retourné sinon un message d'erreur est stocké
	*/
	
	function __get ($attribute)
	{
		if (in_array ($attribute, $this->attr_reader)){return $this->$attribute;}
		else
		{
			echo $msg = get_class ($this) . " Signal: Accés à la lecture refusé pour la variable '$" . $attribute . "'";
			self::add_error ($msg);

		}
	}

	/**
	* gestionnaire des accés en écriture
	*
	* @param String $attribute: attribut dont les droits d'écriture sont à tester
	* @return si les droits sont valident l'attribut est modifié sinon un message d'erreur est stocké
	*/
	function __set ($attribute, $value)
	{
		if (in_array ($attribute, $this->attr_writer))
		{
			$data = array ($attribute => $value,);
			self::hydrate ($data);
		}
		else
		{
			echo $msg = get_class ($this) . " Signal: Accés à l'écriture refusé pour la variable '$" . $attribute . "'";
			self::add_error ($msg);
		}
	}

	/**
	* A l'initialisation de l'objet, l'objet sera vierge
	*
	* @param Int $aircraft
	* @param String $type
	*/
	public function __construct($data,$mod='pilotname')
	{
		self::set_atrw($this->Mp_slot_attr,$this->Mp_slot_attw);

		//$players=Get_FFST_Files ('./nobd/Session_mp',  array ("php"), $recursif = true);



		//$sql = e107::getDb();
		if($mod=='pilotname')
		{
			$player=file_get_contents('./nodb/Session_mp/'.$data.'.ser');

			$datas = unserialize($player);
			self::hydrate ($datas);
		}
		elseif($mod=='key_f')
		{}
		

	}


	/**
	* Fonction d'hydratation permettant le remplissage de l'objet
	*
	* @param Array , tableau des données à mettre en attributs
	*/
	protected function hydrate ($data)
	{
		if ($data != FALSE)
		{
			foreach ($data as $key => $value)
			{
				$method = 'set' . ucfirst ($key);

				if (method_exists ($this, $method)){$this->$method($value);}
				else{$this->$key=$value;}
			}
		}
		return;
	}

	/**
	* Fonction de récupération des joueurs présents sur un serveur
	*
	* @param Int $server, numéro du serveur à lister
	* @param String $New_crypt, clé à utilisé pour l'encryptage des donnés
	* @param Bool $FFS2_PLAY, utilisation de la derniére génération de cryptage pour le client C++
	* @return Array $data, tableaux de tous les joueurs connecté au même serveur
	*/
	public function get_other_slot($server=0,$New_crypt=false,$FFS2_PLAY=false,$TX_MOD=0)
	{
		//création de date limite de validité de la connexion
		$now = $date = date("Y-m-d H:i:s");
		$date = new DateTime($now);
		$date->sub(new DateInterval('PT35S'));
		$time = $date->format("Y-m-d H:i:s");

		//interrogation de la base de donnée avec la limite de temps
		$sql = e107::getDb();
		//$TX_MOD=0;
		if($TX_MOD==0)
		{
			$sql->select("tks_mp", "*","(`lastupdate` between  '". $time ."' and '" . $now . "') and server= '" . $server . "'");
		}
		else
		{
			$sql->select("tks_mp", "*","(`lastupdate` between  '". $time ."' and '" . $now . "') and (server= '" . $server . "' or tx_mod= '1')");
			//echo "(`lastupdate` between  '". $time ."' and '" . $now . "') and (server= '" . $server . "' or tx_mod= '1'";
		}
		

		$key_a=0;

		//var_dump($sql);

		//parcour du résultat et préparation du wazzup pour le client
		while($row = $sql->fetch())
		{
			$key_a++;
			if ($row["port"]!=0)
			{
				//suppression des caractéres interdits dans le xml
				$data[$key_a]["name"] = str_replace("[adm]", "_ADM_",str_replace("[ADM]", "_ADM_", $row['pilotname']));
				//cryptage des ip
				$data[$key_a]["ip"] = encrypt( $row["ip"] , $New_crypt,$FFS2_PLAY);
				//info de l'IP local
				$data[$key_a]["local_ip"] = $row["local_ip"];
				$data[$key_a]["port"] = $row["port"];
				$data[$key_a]["server"] = $row["server"];
				$data[$key_a]["lastupdate"] = $row["lastupdate"];
				$data[$key_a]['position']="icao";

					/*$req_1= "SELECT `lng`,`lat` FROM `".PF_MARK_5_ENR."acarsdata` WHERE `pilot_id`='".$row['pilot_id']."'";
					$acar_map = $this->db->query($req_1);

					$req = "SELECT `icao`,`lng`,`lat`,`name`,`country`,( 3959 * acos ( cos ( radians('".$acar_map->lat."') ) * cos( radians( lat ) ) * cos( radians( lng ) - radians('".$acar_map->lng."') ) + sin ( radians('".$acar_map->lat."') ) * sin( radians( lat ) ) ) ) AS distance FROM `".PF_MARK_5_ENR."mega_airports` HAVING distance < 60 ORDER BY distance LIMIT 0 , 1";
					$airport = $this->db->query($req);

					if ($airport != FALSE) {
						$data[$key]['position']= "Prés de " . $airport->name . " " . $airport->icao . ", distance: " . round($airport->distance, 1) . "nm";
					} else {
						$data[$key]['position']= "Aucune installation dans ses 60 nm";
					}*/
					
				}
			}
			return $data;
		}

	/**
	* Fonction de Création du slot d'un joueur
	*
	* @param Int $piloteid, numéro du pilote propriétaire de ce slot
	* @param String $username, nom du pilote
	* @param Object $xml, objet contenant les infos comme le port, l'ip local...
	* @param Int $server, numéro du server sur lequelle la connexion est demandé
	* @param String $pu_key, clé de cryptage fournis par le client qui sera utilisé pour les communications sensibles
	* @param Int $tx_mod, mod de transmition, par encore implémenté
	* @param Int $attached, numéro de reservation auquel est rattache ce slot (utilisé si module carrrière présent)
	* @return Int, retroune 1 ou 0 si la création du slot est faites
	*/
	public function create_slot($piloteid = NULL,$username = NULL, $xml = NULL,$server=0,$pu_key=null,$tx_mod=null,$attached=0)
	{
		if ($piloteid != NULL and $xml != NULL) {
			self::setPilot_id($piloteid);
			self::setIp($_SERVER["REMOTE_ADDR"]);
			self::setLocal_ip($xml->verify->local_ip);
			self::setPilotname($username);
			self::setPort(intval($xml->verify->port));
			self::setPu_key($pu_key);
			self::setKey_f ( $this->pilot_id . "-0000-" . time ());
			self::setAttached($attached);
			self::setServer($server);
			self::setTx_mod($tx_mod);
			self::setLastupdate();
			//$update['lastupdate'] = $this->lastupdate;
			

			$data = array(
				'pilot_id'  => strval ($this->pilot_id),
				'pilotname' => strval ($this->pilotname),
				'ip'        => strval ($this->ip),
				'local_ip'  => strval ($this->local_ip),
				'port'      => strval ($this->port),
				'pu_key'      => strval ($this->pu_key),
				'key_f'      => strval ($this->key_f),
				'attached'      => strval ($this->attached),
				'server'      => strval ($this->server),
				'tx_mod'     => strval ($this->tx_mod),
				'lastupdate'     => strval ($this->lastupdate),
			);

			$data_s=serialize ($data);
			//création de la fiche du joeur s'étant connecté
			if (file_put_contents('./nodb/Session_mp/'.$this->pilotname.'.ser',$data_s)!=false)
			{
				return $data;
			}


			//$sql = e107::getDb();
			//return $sql->insert('tks_mp', $data);
		}
	}

	/**
	* Fonction de mise à jour du slot d'un joueur
	*
	* @param Array $data, tableau des données à actualisé
	* @param String $new_key, nouvelle clé de cryptage à utilisé (dans le cas du recyclage du slot)
	* @return Int, retroune 1 ou 0 si la création du slot est faites
	*/
	public function update_my_slot()
	{
		self::setLastupdate(date("Y-m-d H:i:s"));

		$data = array(
			'pilot_id'  => strval ($this->pilot_id),
			'pilotname' => strval ($this->pilotname),
			'ip'        => strval ($this->ip),
			'local_ip'  => strval ($this->local_ip),
			'port'      => strval ($this->port),
			'pu_key'      => strval ($this->pu_key),
			'key_f'      => strval ($this->key_f),
			'attached'      => strval ($this->attached),
			'server'      => strval ($this->server),
			'tx_mod'     => strval ($this->tx_mod),
			'lastupdate'     => strval ($this->lastupdate),
		);

		$datas=serialize ($data);

		//mise à jour de la fiche du joueur s'étant connecté
		if (file_put_contents('./nodb/Session_mp/'.$this->pilotname.'.ser',$datas)!=false)
		{
			return $datas;
		}
}

	/**
	* remplissage de l'attribut Pilot_id, si celui-ci n'est pas renseigné ou de façon incorrecte, une erreur sera émise
	* 
	* @param Int $data: numéro identifiant du pilote
	*/
	private function setPilot_id($data)
	{
		if ($data == NULL or !is_numeric($data)) {$this->add_error("l'identifiant du pilote n'est pas fourni");}
		else {$this->pilot_id = $data;}
	}


	/**
	* remplissage de l'attribut IP du pilote, si celui-ci n'est pas renseigné ou de façon incorrecte, une erreur sera émise
	* 
	* @param String $data: IP externe
	*/
	private function setIp($data)
	{
		if ($data == NULL) {
			$this->add_error("l'IP¨du pilote n'est pas fourni");
		} else {
			$this->ip = $data;
		}
	}

	/**
	* remplissage de l'attribut IP local du pilote, si celui-ci n'est pas renseigné ou de façon incorrecte, une erreur sera émise
	* 
	* @param String $data: IP local
	*/
	private function setLocal_ip($data)
	{
		if ($data == NULL or $data == '') {$this->local_ip = 'na';}
		else {$this->local_ip = $data;}
	}

	private function setPilotname($data)
	{
		if ($data == NULL) {$this->add_error("le nom du pilote n'est pas fourni");}
		else {$this->pilotname = $data;}
	}

	/**
	* remplissage de l'attribut Port de communication, si celui-ci n'est pas renseigné ou de façon incorrecte, une valeur par défaut sera mise
	* 
	* @param Int $data: port de communication
	*/
	private function setPort($data)
	{
		if ($data == NULL or !is_numeric($data)) {$this->port='11111';}
		else {$this->port = $data;}
	}

	/**
	* remplissage de l'attribut clé de décryptage, si celui-ci n'est pas renseigné ou de façon incorrecte, une valeur par défaut sera mise
	* 
	* @param Int $data: port de communication
	*/
	private function setPu_key($data)
	{
		if ($data == NULL) {$this->pu_key="not defined";}
		else {$this->pu_key = $data;}
	}

	/**
	* remplissage de l'attribut clé de décryptage, si celui-ci n'est pas renseigné ou de façon incorrecte, une erreur sera émise
	* 
	* @param Int $data: port de communication
	*/
	private function setKey_f($data)
	{
		if ($data == NULL) {$this->add_error("la clé d'identification n'est pas définis correctement");}
		else {$this->key_f = $data;}
	}

	/**
	* remplissage de l'attribut clé de décryptage, si celui-ci n'est pas renseigné ou de façon incorrecte, une erreur sera émise
	* 
	* @param Int $data: port de communication
	*/
	private function setAttached($data)
	{
		if ($data == NULL) {$this->attached="0";}
		else {$this->attached = $data;}
	}


	/**
	* remplissage de l'attribut server, si celui-ci n'est pas renseigné ou de façon incorrecte, une valeur par défaut sera mise
	* 
	* @param Int $data: serveur multijoueurs
	*/
	private function setServer($data)
	{
		if ($data == NULL) {$this->server="0";}
		else {$this->server = $data;}
	}

	/**
	* remplissage de l'attribut tx_mod, si celui-ci n'est pas renseigné ou de façon incorrecte, une valeur par défaut sera mise
	* 
	* @param Int $data: méthode de transmition, 0=null, 1=normal
	*/
	private function setTx_mod($data)
	{
		if ($data == NULL or !is_numeric($data)) {$this->tx_mod="0";}
		else {$this->tx_mod = $data;}
	}

	private function setLastupdate()
	{
		$this->lastupdate = date("Y-m-d H:i:s");
	}

	/**
	 * gestionnaire des alertes
	 *
	 * @param String $data: message d'alerte 
	 * @return aucun, toutes les alertes envoyés sont stocké dans l'objet err_obj et sont à disposition du programmeur
	 */
	protected function add_error ($data)
	{
		//echo '<br>alert: '.$data.'<br>';
		if ($data != NULL)
		{
			if (is_array ($this->err_obj)){$this->err_obj[] = $data;}
			else
			{
				$this->err_obj = array ();
				$this->err_obj[] = $data;
			}
		}
	}

}