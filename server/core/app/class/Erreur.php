<?php

/**
 * Created by PhpStorm.
 * User: Flo
 * Date: 11/04/2016
 * Time: 10:38
 */
class Erreur
{
	private $com = NULL;
	private $error = NULL;

	//private $message ;

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

	public function getMessage ()
	{
		return $this->com;
	}

	public function getError ()
	{
		return $this->error;
	}
}