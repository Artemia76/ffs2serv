<?php
	class Player
	{
		private $name;
		private $ip;
		private $port;
		private $online;
		private $level;
		private $statut;
		private $time;
		private $serial;

		//tableau de gestion pour l'accés en lecture des attribut
		protected $attr_reader = array('name','ip','port','online','level','statut','time','serial');
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
		public function __construct ($data = NULL)
		{
			if ($data!= NULL)
			{
				SELF::hydrate ($data);
			}
		}

		public function hydrate ($data)
		{
			foreach ($data as $key => $value)
			{
				$method = 'set' . ucfirst ($key);

				if (method_exists ($this, $method))
				{
					$this->$method($value);
				}
			}


			//print_r($data);
			return;
		}

		public function	w_file()
		{
			$contents="<?php
		return array(
		'name' => '".$this->name."',
		'ip' => '".$this->ip."',
		'port' => '".$this->port."',
		'online' => '".$this->online."',
		'level' => '".$this->level."',
		'statut' => '".$this->statut."',
		'time' => '".$this->time."',
		'serial' => '".$this->serial."',
		);
		?>";

			file_put_contents('./bd_light/player/'.$this->name.'.php',($contents));//création de la fiche du joeur s'étant connecté

			return;
		}

		private function setName($data){$this->name = $data;}
		private function setIp($data){$this->ip = $data;}
		private function setPort($data){$this->port = $data;}
		private function setOnline($data){$this->online = $data;}
		private function setLevel($data){$this->level = $data;}
		private function setStatut($data){$this->statut = $data;}
		private function setTime($data){$this->time = $data;}
		private function setSerial($data){$this->serial = $data;}
	}
?>