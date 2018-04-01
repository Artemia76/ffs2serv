<?php

/**
 * Created by PhpStorm.
 * User: Flo
 * Date: 12/02/2016
 * Time: 12:22
 */
class Data_map
{
	protected $map_id;//clé unique dans la base de données
	protected $pilot_id;//l'identifiant du pilote
	protected $flightnum;//numéro de vol (si module de carrière présent et actif)
	protected $pilotname;//nom du pilote
	protected $aircraft;//avion du pilot
	protected $Latitude;//latitude de l'appareil
	protected $Longitude;//longitude de l'appareil
	protected $heading;//cap que suit l'appareil
	protected $alt;//altitude de l'appareil
	protected $gs;//vitesse sol
	protected $ias;// vitesse air
	protected $squawk;//code transpondeurs
	protected $depicao;//code icao de départ (si module de carrière présent et actif)
	protected $depapt;//nom de l'aéroport de départ (si module de carrière présent et actif)
	protected $arricao;//code icao d'arrivé' (si module de carrière présent et actif)
	protected $arrapt;//nom de l'aéroport d'arrivé (si module de carrière présent et actif)
	protected $deptime;//heure de départ (si module de carrière présent et actif)
	protected $timeremaining;//temps restant pour le vol en cours  (si module de carrière présent et actif)
	protected $arrtime;//heure théorique d'arrivée (si module de carrière présent et actif)
	protected $route; //route qui suivera l'appareil
	protected $route_details;//route qui suivera l'appareil (commentaire)
	protected $distremain;//distance restante (si module de carrière présent et actif)
	protected $phasedetail;//action de l'appareil, décollage, monté, descente approche...
	protected $onground;//l'appareil est t'il au sol
	protected $lastupdate;//derniére mies à jours de ce point
	protected $client;//version du logiciel utilisé
	private $err_obj=NULL;

	protected $attr_reader = array();
	protected $attr_writer = array ();

	//tableau de gestion pour l'accés en lecture des attributs, si l'attribut n'est pas dans la liste, il ne pourra pas être lu à la volé
	protected $Map_data_attr = array('map_id','pilot_id','flightnum','pilotname','aircraft','Latitude','Longitude','heading','alt','gs','ias','squawk','depicao','depapt','arricao','arrapt','deptime','timeremaining','arrtime','route','route_details','distremain','phasedetail','onground','lastupdate','client','err_obj');
	//tableau de gestion pour l'accés en écriture des attributs, si l'attribut n'est pas dans la liste, il ne pourra pas être écris à la volé
	protected $Map_data_attw = array();

	

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
	* @param String $pilotname
	* @param String $type
	*/
	public function __construct($data)
	{
		self::set_atrw($this->Map_data_attr,$this->Map_data_attw);
		
		$sql = e107::getDb();
		$row = $sql->retrieve("tks_acarsdata", "*","pilotname='".$data."'");

		self::hydrate ($row);
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
/*
	

	public function clear_map($pilotid)
	{
		if (is_numeric($pilotid)) {
			$where = "`pilot_id`= " . $pilotid;
			return $this->db->delete('`'.PF_MARK_5_ENR.'acarsdata`', $where);
		}
	}
*/
	public function create_map($pilot_id = NULL,$pilot_name = NULL, $xml)
	{
		if ($pilot_name != NULL and $pilot_id != NULL)
		{
			date_default_timezone_set("Europe/Brussels" );
			$this->err_obj=NULL;
			$data = array(
				'pilot_id'    	=> $pilot_id,
				'pilotname'  	=> $pilot_name,
				'aircraft'		=>$xml->liveupdate->registration,
				'Latitude'			=>floatval(str_replace(",", ".",$xml->liveupdate->latitude)),
				'Longitude'			=>floatval(str_replace(",", ".",$xml->liveupdate->longitude)),
				'ias'			=>intval($xml->liveupdate->iaspeed),
				'alt'			=>intval($xml->liveupdate->altitude),
				'heading'		=>intval($xml->liveupdate->heading),
				'gs'			=>intval($xml->liveupdate->groundSpeed),
				'phasedetail'	=>$xml->liveupdate->status,
				'lastupdate'	=> date("Y-m-d H:i:s"),
				'client'    	=> $xml->version,
				);

			self::hydrate ($data);

			

			if ($this->err_obj==NULL)
			{
				$sql = e107::getDb();
				return $sql->insert('tks_acarsdata', $data);
			}
			else
			{
				//var_dump($this->err_obj);
				return FALSE;
			}
			
		}
	}
	/*public function update_map_atc($xml = NULL, $key = NULL)
	{
		if ($xml != NULL and $key != NULL)
		{
			$lat = str_replace(',', '.', $xml->latitude);
			$lng = str_replace(',', '.', $xml->longitude);

			$data = array(
				'lat'           => $lat,
				'lng'           => $lng,
				'lastupdate'    => date("Y-m-d H:i:s"),
				'heading'       => $xml->heading,
				'alt'           => $xml->altitude,
				'gs'            => $xml->groundSpeed,
				'ias'			=> $xml->iaspeed,
				'squawk'		=> $xml->squawk,
				);
			$where = '`'.PF_MARK_5_ENR.'acarsdata`.`pilot_id` = ' . $this->pilot_id;
			return $this->db->update('`'.PF_MARK_5_ENR.'acarsdata`', $data, $where);
		}
	}*/
	
	public function update_map_atc($pilotname = NULL, $xml)
	{
		if ($pilotname != NULL and $xml != NULL)
		{
			date_default_timezone_set("Europe/Brussels" );
			$this->err_obj=NULL;
			$update = array(
				'Latitude'			=>floatval(str_replace(",", ".",$xml->atc->latitude)),
				'Longitude'			=>floatval(str_replace(",", ".",$xml->atc->longitude)),
				'ias'			=>intval($xml->atc->iaspeed),
				'alt'			=>intval($xml->atc->altitude),
				'heading'		=>intval($xml->atc->heading),
				'gs'			=>intval($xml->atc->groundSpeed),
				'squawk'		=> intval($xml->atc->squawk),
				'lastupdate'	=> date("Y-m-d H:i:s"),
				);

			//var_dump($xml);

			self::hydrate ($update);

			

			if ($this->err_obj==NULL)
			{
				$update['WHERE']="pilotname='".$pilotname."'";
				$sql = e107::getDb();
				return $sql->update('tks_acarsdata', $update);
				//return $sql->insert('tks_acarsdata', $data);
			}
			else
			{
				//var_dump($this->err_obj);
				return FALSE;
			}
			
		}

		
	}

	public function update_map($pilotname = NULL, $xml)
	{
		if ($pilotname != NULL and $xml != NULL)
		{
			$this->err_obj=NULL;
			$update = array(
				'aircraft'		=>$xml->liveupdate->registration,
				'Latitude'		=>floatval(str_replace(",", ".",$xml->liveupdate->latitude)),
				'Longitude'		=>floatval(str_replace(",", ".",$xml->liveupdate->longitude)),
				'ias'			=>intval($xml->liveupdate->iaspeed),
				'alt'			=>intval($xml->liveupdate->altitude),
				'heading'		=>intval($xml->liveupdate->heading),
				'gs'			=>intval($xml->liveupdate->groundSpeed),
				'phasedetail'	=>$xml->liveupdate->status,
				'squawk'		=> intval($xml->liveupdate->squawk),
				'onground'		=>$xml->liveupdate->onground,
				'lastupdate'	=> date("Y-m-d H:i:s"),

				);

			self::hydrate ($update);

			

			if ($this->err_obj==NULL)
			{
				$update['WHERE']="pilotname='".$this->pilotname."'";
				$sql = e107::getDb();
				return $sql->update('tks_acarsdata', $update);
				//return $sql->insert('tks_acarsdata', $data);
			}
			else
			{
				return FALSE;
			}
			
		}

		/*if ($xml != NULL and $key != NULL) {
			$lat = str_replace(',', '.', $xml->latitude);
			$lng = str_replace(',', '.', $xml->longitude);


			if ($key->attached == 0)
			{
				$this->flightnum="En vol libre";

				if ($this->aircraft == NULL or $this->aircraft=="")
				{
					$this->aircraft = $xml->registration;
				}
				$this->arricao = '----';
				$this->timeremaining = '00:00:00';
				$this->arrtime = '0';
				$this->route = 'inconnu';
				$this->route_details = 'inconnu';
				$this->distremain = '0';

				$req = "SELECT `icao`,`lng`,`lat`,`name`,`country`,( 3959 * acos ( cos ( radians('".$lat."') ) * cos( radians( lat ) ) * cos( radians( lng ) - radians('".$lng."') ) + sin ( radians('".$lat."') ) * sin( radians( lat ) ) ) ) AS distance FROM `".PF_MARK_5_ENR."mega_airports` HAVING distance < 60 ORDER BY distance LIMIT 0 , 1";
				$airport = $this->db->query($req);

				if ($xml->status == "Embarquement")
				{
					if ($this->depicao == 'INF' and $airport != FALSE)
					{
						$this->depicao = $airport->icao;
						$this->depapt = $airport->name;
					}
					if ($this->aircraft == NULL)
					{
						$this->aircraft = $xml->registration;
					}
					if ($this->deptime == "00:00:00")
					{
						$this->deptime = date("H:i:s");
					}
				}

				if ($airport != FALSE)
				{
					$this->arrapt = "Prés de " . $airport->name . " " . $airport->icao . ", distance: " . round($airport->distance, 1) . "nm";
				}
				else
				{
					$this->arrapt = "Aucune installation dans les 60 nm";
				}
			}
			else
			{
				$resa=New Resa();
				$resa->load_resa($key->pilot_id, $key->attached);
				$aircraft= New P_aircraft($resa->aircraft_p_id);
				$aircraft->scan();

				if ($xml->status == "Décollage")
				{
					$aircraft->setOn_ground('0');
					$aircraft->update_sate();
				}
				if ($xml->status == "Montée")
				{
					$aircraft->setOn_ground('0');
					$aircraft->update_sate();
				}
				if ($xml->status == "Débarquement")
				{
					//$aircraft->setOn_ground('1');
					//$aircraft->update_sate();
				}
				if ($xml->status == "Roulage vers la porte")
				{
					//$aircraft->setOn_ground('1');
					//$aircraft->update_sate();
				}
				$dep_airpot= New Airport($resa->depicao);
				$arr_airpot= New Airport($resa->arricao);


				$this->flightnum=$resa->flightnum;
				$this->aircraft=$aircraft->fullname;
				$this->depicao=$dep_airpot->icao;
				$this->depapt=$dep_airpot->name;
				$this->arricao=$arr_airpot->icao;
				$this->arrapt=$arr_airpot->name;
				$this->deptime=$resa->deptime;
				$this->route = $resa->route;
				$this->distremain=Tools::dist_a_b($lat, $lng, $arr_airpot->lat, $arr_airpot->lng, $unit = 'nmi', $decimals = 2);
				$this->timeremaining=Tools::get_ETA($xml->groundSpeed,$this->distremain);
				$this->arrtime=Tools::get_arrival_time(date("Y-m-d H:i:s"),$this->timeremaining);
			}
			if($this->distremain<25)
			{
				$db_airac = Db_mark5::getInstance ('AIRAC');

				$where= "`ICAO`='".$this->arricao."'";
				$data = "*";
				$com=$db_airac->select( '`Com_airports`', $data, $where, ' ORDER BY `Frequency`','list');

				foreach ($com as $key => $value)
				{
					//$freq=intval($value->Frequency*100);
					//$freq=floatval ($freq/10);
					$freq=substr($value->Frequency, 0,-1);
					//$TS3=new Ts3;
					//$TS3->create_channel(50,2,false,$freq.' Mhz '.$value->ICAO.' '.$value->Name.' '.$value->Type);
					//$channel= new Channel();
					//$channel->Create_channel(50,$freq.'\sMhz\s'.$value->ICAO.'\s'.$value->Name.'\s'.$value->Type,'p');
					//usleep(100);

				}
			}

			$data = array(
				'flightnum'      => $this->flightnum,
				'aircraft'      => $this->aircraft,
				'depicao'       => $this->depicao,
				'depapt'        => $this->depapt,
				'arricao'       => $this->arricao,
				'arrapt'        => $this->arrapt,
				'deptime'       => $this->deptime,
				'lat'           => $lat,
				'lng'           => $lng,
				'lastupdate'    => date("Y-m-d H:i:s"),
				'heading'       => $xml->heading,
				'alt'           => $xml->altitude,
				'gs'            => $xml->groundSpeed,
				//'ias'			=> $xml->iaspeed,
				'timeremaining' => $this->timeremaining,
				'arrtime'       => $this->arrtime,
				'route'         => $this->route,
				'route_details' => $this->route_details,
				'distremain'    => $this->distremain,
				'phasedetail'   => $xml->status,
				//'squawk'		=> $xml->squawk,

				);
			$where = '`'.PF_MARK_5_ENR.'acarsdata`.`pilot_id` = ' . $this->pilot_id;
			return $this->db->update('`'.PF_MARK_5_ENR.'acarsdata`', $data, $where);
		}
		*/
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

	private function setFlightnum($data)
	{
		$this->flightnum = $data;
	}

	private function setPilotname($data)
	{
		$this->pilotname = $data;
	}

	private function setAircraft($data)
	{
		$this->aircraft = $data;
	}

	/**
	* remplissage de l'attribut Latitude, si celui-ci n'est pas renseigné ou de façon incorrecte, une erreur sera émise
	* 
	* @param Float $data: lattitude
	*/
	private function setLatitude($data)
	{
		if (($data == NULL or !is_float($data)) and $data!=0) {$this->add_error("la latitude est incorrecte: ".$data);}
		else {$this->Latitude = $data;}
	}

	/**
	* remplissage de l'attribut Longitude, si celui-ci n'est pas renseigné ou de façon incorrecte, une erreur sera émise
	* 
	* @param Float $data: longitude
	*/
	private function setLongitude($data)
	{
		if (($data == NULL or !is_float($data)) and $data!=0) {$this->add_error("la longitude est incorrecte: ".$data);}
		else {$this->Longitude = $data;}
	}

	/**
	* remplissage de l'attribut Heading, si celui-ci n'est pas renseigné ou de façon incorrecte, une erreur sera émise
	* 
	* @param Int $data: cap suivi
	*/
	private function setHeading($data)
	{
		if (!is_numeric($data)) {$this->add_error("le cap est incorrecte: ".$data);}
		else {$this->heading = $data;}
	}

	/**
	* remplissage de l'attribut Altitude, si celui-ci n'est pas renseigné ou de façon incorrecte, une erreur sera émise
	* 
	* @param Int $data: altitude
	*/
	private function setAlt($data)
	{
		if (($data == NULL or !is_numeric($data)) and $data!=0) {$this->add_error("l'altitude est incorrecte: ".$data);}
		else {$this->alt = $data;}
	}

	/**
	* remplissage de l'attribut Ground Speed, si celui-ci n'est pas renseigné ou de façon incorrecte, une erreur sera émise
	* 
	* @param Int $data: vitesse sol
	*/
	private function setGs($data)
	{
		if (($data == NULL or !is_numeric($data)) and $data!=0) {$this->add_error("la vitesse au sol est incorrecte: ".$data);}
		else {$this->gs = $data;}
	}

	/**
	* remplissage de l'attribut Ias, si celui-ci n'est pas renseigné ou de façon incorrecte, une erreur sera émise
	* 
	* @param Int $data: vitesse air
	*/
	private function setIas($data)
	{
		if (($data == NULL or !is_numeric($data)) and $data!=0) {$this->add_error("la vitesse air est incorrecte: ".$data);}
		else {$this->gs = $data;}
	}

	/**
	* remplissage de l'attribut Squawk, si celui-ci n'est pas renseigné ou de façon incorrecte, une erreur sera émise
	* 
	* @param Int $data: Transpondeur
	*/
	private function setSquawk($data)
	{
		//if ($data == NULL or !is_numeric($data)) {$this->add_error("Le code transpondeur est incorrect: ".$data);}
		//else {$this->squawk = $data;}
		$this->squawk = $data;
	}

	/**
	* remplissage de l'attribut Squawk, si celui-ci n'est pas renseigné ou de façon incorrecte, une erreur sera émise
	* 
	* @param Int $data: Transpondeur
	*/
	private function setOnground($data)
	{
		//if ($data == NULL or !is_numeric($data)) {$this->add_error("Le code transpondeur est incorrect: ".$data);}
		//else {$this->squawk = $data;}
		if ($data==="True")
		{
			$this->onground = 1;
		}
		if ($data==="False")
		{
			$this->onground = 0;
		}
		if ($data===1)
		{
			$this->onground = 1;
		}
		if ($data===0)
		{
			$this->onground = 0;
		}
		//$this->onground = $data;
	}

	private function setLastupdate($data)
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