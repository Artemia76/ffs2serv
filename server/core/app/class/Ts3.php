<?php

/**
 * Created by PhpStorm.
 * User: Flo
 * Date: 26/02/2016
 * Time: 17:54
 */
class Ts3
{
	private function execute($cmd=NULL,$return=FALSE)
	{
		if ($cmd!=NULL)
		{
			$socket = fsockopen(TS3_ADRESSE, TS3_QUERY_PORT, $errno, $errstr); 

			if($return==TRUE)
			{
				if($socket) 
				{ 
					echo "Connected <br /><br />"; 
				} 
				else 
				{ 
					echo "Connection failed!<br /><br />"; 
				} 
			}

			usleep(250);
			fputs($socket, "login client_login_name=".TS3_LOGIN." client_login_password=".TS3_PASSWORD." \r\n");
			usleep(100);
			fputs($socket, "use 1 \r\n");
			usleep(100); 
			fputs($socket, $cmd);
			usleep(100);
			fputs($socket, "quit \r\n"); 
			usleep(100);

			if($return==TRUE)
			{
				$buffer = ""; 

				while(!feof($socket)) 
				{ 
					$buffer .=fgets($socket, 4096); 
				} 

				var_dump($buffer);
			}
			fclose($socket); 
		}
		
	}

	public function send_msg($msg=NULL,$priority=1,$return=false,$mod=NULL,$target=NULL)
	{
		if($msg!=NULL)
		{
			$msg=str_replace(' ','\s',$msg);
			//$msg=str_replace(chr(92).chr(47) ,chr(47) ,$msg);
			switch ($priority) {
					case 1:
						$color='black';
						break;
					case 2:
						$color='blue';
						break;
					case 3:
						$color='green';
						break;
					case 4:
						$color='orange';
						break;
					case 5:
						$color='red';
						break;
				}
			$cmd='gm msg=[color='.$color.']'.$msg.'[/color] \r\n';
			SELF::execute($cmd,$return);
		}
	}
}