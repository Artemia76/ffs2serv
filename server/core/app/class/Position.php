<?php 
class Position
{
	private $name;
	private $latitude;
	private $longitude;
	private $heading;
	private $altitude;
	private $iaspeed;
	private $status;
	private $registration;
	private $time;


	//tableau de gestion pour l'accés en lecture des attribut
	protected $attr_reader = array('name','latitude','longitude','heading','altitude','iaspeed','status','registration','time');
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
		'latitude' => '".$this->latitude."',
		'longitude' => '".$this->longitude."',
		'heading' => '".$this->heading."',
		'altitude' => '".$this->altitude."',
		'iaspeed' => '".$this->iaspeed."',
		'status' => '".$this->status."',
		'registration' => '".$this->registration."',
		'time' => '".$this->time."',
		);
		?>";


		file_put_contents('./bd_light/map/'.$this->name.'.php',($contents));//création de la fiche du joeur s'étant connecté

		return;
	}

	private function setName($data){$this->name = $data;}
	private function setLatitude($data){$this->latitude = $data;}
	private function setLongitude($data){$this->longitude = $data;}
	private function setHeading($data){$this->heading = $data;}
	private function setAltitude($data){$this->altitude = $data;}
	private function setIaspeed($data){$this->iaspeed = $data;}
	private function setStatus($data){$this->status = $data;}
	private function setRegistration($data){$this->registration = $data;}
	private function setTime($data){$this->time = $data;}




}
?>