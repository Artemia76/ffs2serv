<?php

/**
 * Gestion des accés à la base de données
 */
Class Db_mark5
{
	private $dbase_dns;
	private $dbase_server;
	private $dbase_name;
	private $dbase_user;
	private $dbase_pass;
	private $db_pdo = NULL;
	private static $_instance=array();
	//private static $_instance = NULL;

	private function __construct($info)
	{
		if ($info === NULL) {
			return FALSE;
		}
		$this->dbase_dns = constant(DBASE_ . $info . _DNS);
		$this->dbase_server = constant(DBASE_ . $info . _SERVER);
		$this->dbase_name = constant(DBASE_ . $info . _NAME);
		$this->dbase_user = constant(DBASE_ . $info . _USER);
		$this->dbase_pass = constant(DBASE_ . $info . _PASS);


		$pdo = new PDO($this->dbase_dns, $this->dbase_user, $this->dbase_pass,
				array(PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8"));
		$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		$this->db_pdo = $pdo;
	}

	/**
	 * Méthode qui crée l'unique instance de la classe
	 * si elle n'existe pas encore puis la retourne.
	 */
	public static function getInstance($info)
	{
		if (array_key_exists($info,self::$_instance))
		{
			//echo "<br>=====<br>";var_dump(self::$_instance);echo "<br>=====<br>";
		}
		else{
			self::$_instance[$info]=new Db_mark5($info);
		}
		return self::$_instance[$info];
		/*if (is_null(self::$_instance)) {
			self::$_instance = new Db_mark5($info);
		}

		return self::$_instance;*/
	}


	/**
	 * Méthode de sélection préparée PDO
	 */
	public function select($tab, $data, $para, $limit = '', $mod = "unique")
	{
		$fields = array();
		$exec = 0;
		if ($para != NULL) {

			if (is_array($para)) {
				foreach ($para as $key => $value) {
					$fields[] = $key . ' = :' . $key;
				}
				$where = implode(' and ', $fields);
				//print("SELECT $data FROM $tab  WHERE $where $limit");
				$stmt = $this->db_pdo->prepare("SELECT $data FROM $tab  WHERE $where $limit");
				if ($stmt->execute($para)) {
					$exec = 1;
				}
			} else {
				$where = $para;
				//echo "SELECT $data FROM $tab  WHERE $where $limit";
				$stmt = $this->db_pdo->query("SELECT $data FROM $tab  WHERE $where $limit");
				if ($stmt != NULL) {
					$exec = 1;
				}
			}

			if ($exec == 1) {
				$data = array();
				if ($mod == "list") {
					while ($resultat = $stmt->fetch(PDO::FETCH_OBJ)) {
						$data[] = $resultat;
					}

					return $data;
				} elseif ($mod == "unique") {
					return $stmt->fetch(PDO::FETCH_OBJ);
				}
			} else {
				echo "erreur dans le req, ou pas de réponse";
			}
		} else {
			echo "erreur dans le para select" . __LINE__;
		}
	}



	/**
	 * Méthode de sélection préparée PDO
	 * Avec un left join
	 */
	public function select_lft($tab, $data, $lft, $para, $limit = '', $mod = "unique")
	{
		$fields = array();
		$exec = 0;
		if ($para != NULL) {

			if (is_array($para)) {
				foreach ($para as $key => $value) {
					$fields[] = $key . ' = :' . $key;
				}
				$where = implode(' and ', $fields);
				$stmt = $this->db_pdo->prepare("SELECT $data  FROM $tab LEFT JOIN $lft  WHERE $where $limit");
				if ($stmt->execute($para)) {
					$exec = 1;
				}
			} else {
				$where = $para;
				$stmt = $this->db_pdo->query("SELECT $data FROM $tab LEFT JOIN $lft  WHERE $where $limit");
				if ($stmt != NULL) {
					$exec = 1;
				}
			}

			if ($exec == 1) {
				$data = array();
				if ($mod == "list") {
					while ($resultat = $stmt->fetch(PDO::FETCH_OBJ)) {
						$data[] = $resultat;
					}

					return $data;
				} elseif ($mod == "unique") {
					return $stmt->fetch(PDO::FETCH_OBJ);
				}
			} else {
				echo "erreur dans le req, ou pas de réponse";
			}
		} else {
			echo "erreur dans le para select lft" . __LINE__;
		}

	}

	/**
	 * Méthode de supression préparée PDO
	 */
	public function delete($tab, $para, $limit = '')
	{

		$fields = array();
		$exec = FALSE;
		if ($para != NULL) {

			if (is_array($para)) {
				foreach ($para as $key => $value) {
					$fields[] = $key . ' = :' . $key;
				}
				$where = implode(' and ', $fields);
				$stmt = $this->db_pdo->prepare("DELETE FROM $tab  WHERE $where $limit");
				if ($stmt->execute($para)) {
					$exec = TRUE;
				}
			} else {
				$where = $para;
				$stmt = $this->db_pdo->query("DELETE FROM $tab  WHERE $where $limit");
				if ($stmt != NULL) {
					$exec = TRUE;
				}
			}

			return $exec;
		} else {
			echo "erreur dans le para delete" . __LINE__;
		}
	}

	/**
	 * Méthode d'insertion préparée PDO
	 */
	public function insert($tab, $para)
	{

		$fields = array();
		$var = array();
		$exec = FALSE;
		if ($para != NULL) {

			if (is_array($para)) {
				foreach ($para as $key => $value) {
					$fields[] = ' `' . $key . '`';
					$var[] = ' :' . $key;
				}
				$data = ' (' . implode(',', $fields) . ') VALUES (' . implode(',', $var) . ')';
				$stmt = $this->db_pdo->prepare("INSERT INTO $tab  $data");
				if ($stmt->execute($para)) {
					$exec = TRUE;
				}
			} else {
				$data = $para;
				$stmt = $this->db_pdo->query("INSERT INTO $tab  $data");
				if ($stmt != NULL) {
					$exec = TRUE;
				}
			}

			return $exec;
		} else {
			echo "erreur dans le para insert: L" . __LINE__;
		}
	}


	/**
	 * Méthode de mise à jour PDO
	 */
	public function update($tab = NULL, $array = NULL, $WHERE = NULL)
	{
		if (is_array($array)) {
			if ($tab != NULL && $array != NULL && $WHERE != NULL) {
				$fields = array();
				foreach ($array as $key => $value) {
					$fields[] = ' `' . $key . '` = :' . $key;
				}

				$datas = implode(', ', $fields);
				$request = "UPDATE  $tab  SET  $datas   WHERE $WHERE ";
				$req = $this->db_pdo->prepare($request);
				$data = $req->execute($array);

				return $data;
			}
		} else {
			echo "erreur dans le para update" . __LINE__;
		}
	}

///////////////////////////////////////////////////////////////////////////////////
	public function describe_table($table)
	{
		try {
			$sql = "DESCRIBE `" . $table . "`";
			$req = $this->db_pdo->query($sql);
			while ($resultat = $req->fetch(PDO::FETCH_OBJ)) {
				$data[] = $resultat;
			}

			return $data;
		} catch (Exception $e) {
			echo "Erreur..." . $e;
		}


		/*try {
			echo $sql = "DESCRIBE `" . $table . "`";
			$req = $this->db_pdo->query($sql);
			//$data = $req->fetch(PDO::FETCH_OBJ);

			return $req;
		} catch (Exception $e) {
			echo "Erreur..." . $e;
		}*/
	}

	public function search_table($table)
	{
		try {
			$sql = "SHOW TABLES like '%" . $table . "%'";
			$req = $this->db_pdo->query($sql);
			$data = $req->fetch(PDO::FETCH_OBJ);

			return $data;
		} catch (Exception $e) {
			echo "Erreur..." . $e;
		}
	}

	public function query_list($req)
	{
		try {
			$req = $this->db_pdo->query($req);
			while ($resultat = $req->fetch(PDO::FETCH_OBJ)) {
				$data[] = $resultat;
			}

			return $data;
		} catch (Exception $e) {
			echo "Erreur..." . $e;
		}
	}

	public function query($req)
	{
		try {
			$req = $this->db_pdo->query($req);
			$data = $req->fetch(PDO::FETCH_OBJ);

			return $data;
		} catch (Exception $e) {
			echo "Erreur..." . $e;
		}
	}

	public function query_no_fetch($req)
	{
		try {
			$data = $this->db_pdo->query($req);

			return $data;
		} catch (Exception $e) {
			echo "Erreur..." . $e;
		}
	}

	public function update_simple($request)
	{

		return $req = $this->db_pdo->query($request);

	}

	public function insert_array($request, $para)
	{
		try {
			$fields = array();
			$var = array();
			foreach ($para as $key => $value) {
				$fields[] = ' `' . $key . '`';
				$var[] = ' :' . $key;
			}
			$request = $request . ' (' . implode(',', $fields) . ') VALUES (' . implode(',', $var) . ')';
			$req = $this->db_pdo->prepare($request);

			return $req->execute($para);

		} catch (Exception $e) {
			echo "Erreur..." . $e;
		}
	}


	public function delete_prepared($request, $para)
	{
		try {
			$req = $this->db_pdo->prepare($request);
			$req->execute(array($para));
		} catch (Exception $e) {
			echo "Erreur..." . $e;
		}

	}

	public function prepare($request, $para)
	{

		try {
			$req = $this->db_pdo->prepare($request);
			$req->execute(array($para));
			$data = $req->fetch(PDO::FETCH_OBJ);

			return $data;

		} catch (Exception $e) {
			echo "Erreur..." . $e;
		}
	}


}

?>
