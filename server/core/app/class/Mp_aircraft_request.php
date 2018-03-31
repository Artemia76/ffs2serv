<?php

/**
 * Created by PhpStorm.
 * User: Flo
 * Date: 14/08/2016
 * Time: 14:34
 */
class Mp_aircraft_request
{
	protected $id;
	protected $pilot_id;
	protected $chekcsum;
	protected $request_date;
	private $db;
	private $err_obj;

	//tableau de gestion pour l'accés en lecture des attribut
	protected $attr_reader = array('id','pilot_id','chekcsum','request_date','err_obj');

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
				$where = array (
					$data_name => $data_search,
				);

				$data = '*';
				SELF::hydrate ($this->db->select ('`' . PF_MARK_5_ENR . 'mp_aircraft_request`', $data, $where, 'limit 1'));


		}
	}

	public function requested_aircraft($pilot_id)
	{
		$where = '`pilot_id`='.$pilot_id.' and `request_date` >'.(time()-30);
		//$where = array('pilot_id'=>$pilot_id,);
		//'request_date'=>time()	);
		$data = 'chekcsum';

		$list=$this->db->select ('`' . PF_MARK_5_ENR . 'mp_aircraft_request`', $data, $where, 'limit 5 ', 'list');

		if(count($list)==0)
		{
			$where = array('pilot_id'=>$pilot_id);
			$this->db->delete('`' . PF_MARK_5_ENR . 'mp_aircraft_request`', $where);
		}
		return $list;
	}

	public function destroy_requested_aircraft($cheksum)
	{
		$where = array('chekcsum'=>$cheksum);
		$this->db->delete('`' . PF_MARK_5_ENR . 'mp_aircraft_request`', $where);
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
			$hash='';
			foreach ($data as $key => $value)
			{
				$method = 'set' . ucfirst ($key);

				if (method_exists ($this, $method))
				{
					$this->$method($value);
					$hash=$hash.".".$value;
				}
			}
			//SELF::setHash(md5($hash));
			//echo md5($hash);
		}

		return;
	}

	public function auto_create ($data = null)
	{

		Return $this->db->insert ('`' . PF_MARK_5_ENR . 'mp_aircraft_request`', $data);


	}

	private function setId($data){$this->id = $data;}
	private function setPilot_id($data){$this->pilot_id = $data;}
	private function setChekcsum($data){$this->chekcsum = $data;}
	private function setRequest_date($data){$this->request_date = $data;}
}