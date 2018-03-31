<?php

/**
 * Created by PhpStorm.
 * User: Flo
 * Date: 03/03/2016
 * Time: 12:59
 */
class Mp_aircraft
{
	protected $id;
	protected $uploader;
	protected $title;
	//protected $sim;
	//protected $model;
	protected $atc_model;
	protected $ui_manufacturer;
	//protected $ui_type;
	//protected $ui_variation;
	//protected $ui_typerole;

	protected $static_pitch;
	protected $static_cg_height;


	protected $ui_createdby;
	protected $Category;
	protected $max_gross_weight;
	protected $empty_weight;
	protected $engine_type;
	protected $engine_number;
	protected $flaps_up_stall_speed;
	protected $full_flaps_stall_speed;
	protected $cruise_speed;
	protected $max_indicated_speed;
	protected $max_mach;
	protected $flaps_damaging_speed;
	protected $flaps_blowout_speed;
	protected $hash;
	protected $enable;
	private $db;
	private $err_obj;


	//tableau de gestion pour l'accés en lecture des attribut
	protected $attr_reader = array('id','uploader','title','atc_model','ui_manufacturer','static_pitch','static_cg_height','ui_createdby','Category','max_gross_weight','empty_weight','engine_type','engine_number','flaps_up_stall_speed','full_flaps_stall_speed','cruise_speed','max_indicated_speed','max_mach','flaps_damaging_speed','flaps_blowout_speed','hash','enable','err_obj');
	//tableau de gestion pour l'accés en écriture des attribut
	protected $attr_writer = array ();

	//gestionnaire des accés en lecture
	function __get ($attribute)
	{
		if (in_array ($attribute, $this->attr_reader))
		{
			return $this->$attribute;
		}
		else
		{
			echo $msg = get_class ($this) . " Signal: Accés à la lecture refusé pour la variable '$" . $attribute . "'";
			$this->add_error ($msg);

		}
	}

	//gestionnaire des accés en écriture
	function __set ($attribute, $value)
	{
		if (in_array ($attribute, $this->attr_writer))
		{
			$data = array ($attribute => $value,);
			SELF::hydrate ($data);
		}
		else
		{
			echo $msg = get_class ($this) . " Signal: Accés à l'écriture refusé pour la variable '$" . $attribute . "'";
			$this->add_error ($msg);
		}
	}

	/**
	 * A l'initialisation de l'objet, si un email, un id ou code ainsi que les informations de BD sont fournis
	 * l'objet se remplira, sinon l'objet sera vierge
	 *
	 * @param String $info , email, id 004 ou 4, code TKS0004
	 * @param Objet  $bd   , objet PDO de la BD
	 */
	public function __construct ($data_name = NULL, $data_search = NULL)
	{
		$this->db = Db_mark5::getInstance ('MARK_5_ENR');
		if ($data_name != NULL && $data_search != NULL)
		{
			if ($data_name == 'hash')
			{
				$where = "`hash` like '%" . $data_search . "%'";

				$data = '*';
				SELF::hydrate ($this->db->select ('`' . PF_MARK_5_ENR . 'mp_aircraft`', $data, $where, 'limit 1'));
			}
			else
			{
				$where = array (
					$data_name => $data_search,
				);

				$data = '*';
				SELF::hydrate ($this->db->select ('`' . PF_MARK_5_ENR . 'mp_aircraft`', $data, $where, 'limit 1'));
			}

		}
	}

	public function make_aircraft ($text, $md5,$uploader)
	{


		$text = str_replace ('  ', '', $text);
		$bloc = explode ("[", trim ($text));
		$title = array ();
		$tab_title = array ();

		$flaps_damaging_speed = array ();
		$flaps_blowout_speed = array ();

		foreach ($bloc as $key => $item)
		{
			$ligne = preg_split ('/\n|\r\n?/', $item);

			if (strlen ($ligne[0]) != 0 && (stripos ($ligne[0], "fltsim") !== false))
			{
				for ($i = 1; $i <= count ($ligne); $i ++)
				{
					if (stripos ($ligne[$i], "title") !== false)
					{
						$code = explode ("=", $ligne[$i]);
						$title[] = trim ($code[1]);
					}
				}
			}


			if (strlen ($ligne[0]) != 0 && (stripos ($ligne[0], "General") !== false))
			{
				for ($i = 1; $i <= count ($ligne); $i ++)
				{
					if (stripos ($ligne[$i], "Category") !== false)
					{
						$code = explode ("=", $ligne[$i]);
						$data["Category"] = $code[1];
					}
					if (stripos ($ligne[$i], "atc_model") !== false)
					{
						$code = explode ("=", $ligne[$i]);
						$data["atc_model"] = trim ($code[1]);
					}
				}
			}
			if (strlen ($ligne[0]) != 0 && (stripos ($ligne[0], "flaps") !== false))
			{
				for ($i = 1; $i <= count ($ligne); $i ++)
				{
					if (stripos ($ligne[$i], "damaging-speed") !== false)
					{
						$code = explode ("=", $ligne[$i]);
						$flaps_damaging_speed[] = floatval ($code[1]);
					}
					if (stripos ($ligne[$i], "blowout-speed") !== false)
					{
						$code = explode ("=", $ligne[$i]);
						$flaps_blowout_speed[] = floatval ($code[1]);
					}
				}
			}

			if (strlen ($ligne[0]) != 0 && (stripos ($ligne[0], "weight_and_balance") !== false))
			{
				for ($i = 1; $i <= count ($ligne); $i ++)
				{
					if (stripos ($ligne[$i], "max_gross_weight") !== false)
					{
						$code = explode ("=", $ligne[$i]);
						$data["max_gross_weight"] = floatval ($code[1]);
					}
					if (stripos ($ligne[$i], "empty_weight ") !== false or stripos ($ligne[$i], "EMPTY_WEIGHT=") !== false )
					{
						$code = explode ("=", $ligne[$i]);
						$data["empty_weight"] = floatval ($code[1]);
					}
				}
			}

			if (strlen ($ligne[0]) != 0 && (stripos ($ligne[0], "contact_points") !== false))
			{
				for ($i = 1; $i <= count ($ligne); $i ++)
				{
					if (stripos ($ligne[$i], "static_pitch") !== false)
					{
						$code = explode ("=", $ligne[$i]);
						$data["static_pitch"] = floatval ($code[1]);
					}
					if (stripos ($ligne[$i], "static_cg_height") !== false)
					{
						$code = explode ("=", $ligne[$i]);
						$data["static_cg_height"] = floatval ($code[1]);
					}
				}
			}

			if (strlen ($ligne[0]) != 0 && (stripos ($ligne[0], "generalenginedata") !== false))
			{
				for ($i = 1; $i <= count ($ligne); $i ++)
				{
					if (stripos ($ligne[$i], "engine_type") !== false)
					{
						$code = explode ("=", $ligne[$i]);
						$data["engine_type"] = floatval ($code[1]);
					}
					if (stripos ($ligne[$i], "Engine.") !== false)
					{
						//$code = explode ("=", $ligne[$i]);
						//$data["engine_type"] = floatval ($code[1]);

						$data['engine_number']++;
					}
				}
			}

			if (strlen ($ligne[0]) != 0 && (stripos ($ligne[0], "reference speeds") !== false))
			{
				for ($i = 1; $i <= count ($ligne); $i ++)
				{
					if (stripos ($ligne[$i], "cruise_speed") !== false)
					{
						$code = explode ("=", $ligne[$i]);
						$data["cruise_speed"] = floatval ($code[1]);
					}
					if (stripos ($ligne[$i], "flaps_up_stall_speed") !== false)
					{
						$code = explode ("=", $ligne[$i]);
						$data["flaps_up_stall_speed"] = floatval ($code[1]);
					}
					if (stripos ($ligne[$i], "full_flaps_stall_speed") !== false)
					{
						$code = explode ("=", $ligne[$i]);
						$data["full_flaps_stall_speed"] = floatval ($code[1]);
					}
					if (stripos ($ligne[$i], "max_indicated_speed") !== false)
					{
						$code = explode ("=", $ligne[$i]);
						$data["max_indicated_speed"] = floatval ($code[1]);
					}
					if (stripos ($ligne[$i], "max_mach") !== false)
					{
						$code = explode ("=", $ligne[$i]);
						$data["max_mach"] = floatval ($code[1]);
					}
				}
			}


			for ($i = 1; $i <= count ($ligne); $i ++)
			{
				if (stripos ($ligne[$i], "ui_manufacturer") !== false)
				{
					$code = explode ("=", $ligne[$i]);
					$data["ui_manufacturer"] = trim ($code[1]);
				}
				
				if (stripos ($ligne[$i], "ui_createdby") !== false)
				{
					$code = explode ("=", $ligne[$i]);
					$data["ui_createdby"] = trim ($code[1]);
				}


			}

		}
		$data['title'] = json_encode ($title);
		

		$data['flaps_damaging_speed'] = json_encode ($flaps_damaging_speed);
		$data['flaps_blowout_speed'] = json_encode ($flaps_blowout_speed);
		//$md5_temp = array ($md5);
		$data['hash'] = $md5;
		$data['uploader'] = $uploader;

		SELF::hydrate ($data);
		return SELF::create ();
	}

	/**
	 * Fonction d'hydratation permettant le remplissage du l'objet Resa ou Schedules
	 *
	 * @param Array , talbeau des données à mettre en attributs
	 */
	public function hydrate ($data)
	{
		if ($data != FALSE)
		{
			$hash = '';
			foreach ($data as $key => $value)
			{
				$method = 'set' . ucfirst ($key);

				if (method_exists ($this, $method))
				{
					$this->$method($value);
					$hash = $hash . "." . $value;
				}
			}
			//SELF::setHash(md5($hash));
			//echo md5($hash);
		}

		return;
	}

	public function scan ($data)
	{
		Return true;

	}

	public function create ($data = null)
	{

		$data = array ();
		$data_temp = $this->db->describe_table ("tks_mp_aircraft");

		if($this->atc_model===null or $this->static_pitch===null or $this->static_cg_height===null or $this->Category===null or $this->empty_weight===null  or $this->engine_type===null  or $this->engine_number===null)
			{$this->enable=0;}
		else{$this->enable=1;}

		foreach ($data_temp as $value)
		{
			$attribut = $value->Field;
			$data[$value->Field] = $this->$attribut;
		}

		Return $this->db->insert ('`tks_mp_aircraft`', $data);


	}

	public function destroy_mp_aircraft ($id)
	{
		$where = array ('id' => $id);
		$this->db->delete ('`' . PF_MARK_5_ENR . 'mp_aircraft`', $where);
	}


	private function setId ($data) { $this->id = $data; }

	private function setUploader ($data) { $this->uploader = $data; }

	private function setTitle ($data) { $this->title = $data; }

	private function setAtc_model ($data) { $this->atc_model = $data; }

	private function setUi_manufacturer ($data) { $this->ui_manufacturer = $data; }

	private function setStatic_pitch ($data) { $this->static_pitch = $data; }

	private function setStatic_cg_height ($data) { $this->static_cg_height = $data; }

	private function setUi_createdby ($data) { $this->ui_createdby = $data; }

	private function setCategory ($data) { $this->Category = $data; }

	private function setMax_gross_weight ($data) { $this->max_gross_weight = $data; }

	private function setEmpty_weight ($data) { $this->empty_weight = $data; }

	private function setEngine_type ($data) { $this->engine_type = $data; }

	private function setEngine_number ($data) { $this->engine_number = $data; }

	private function setFlaps_up_stall_speed ($data) { $this->flaps_up_stall_speed = $data; }

	private function setFull_flaps_stall_speed ($data) { $this->full_flaps_stall_speed = $data; }

	private function setCruise_speed ($data) { $this->cruise_speed = $data; }

	private function setMax_indicated_speed ($data) { $this->max_indicated_speed = $data; }

	private function setMax_mach ($data) { $this->max_mach = $data; }

	private function setFlaps_damaging_speed ($data) { $this->flaps_damaging_speed = $data; }

	private function setFlaps_blowout_speed ($data) { $this->flaps_blowout_speed = $data; }

	public function setEnable ($data) { $this->hash = $data; }

	public function setHash ($data) { $this->hash = $data; }


	private
	function add_error ($data)
	{

		if ($data != NULL)
		{
			if (is_array ($this->err_obj))
			{
				$this->err_obj[] = $data;
			}
			else
			{
				$this->err_obj = array ();
				$this->err_obj[] = $data;
			}
		}
	}

}

?>
