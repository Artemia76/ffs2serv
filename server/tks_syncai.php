<?php
/**
 * Created by PhpStorm.
 * User: Flo
 * Date: 14/08/2016
 * Time: 12:32
 */
//if ($xml->switch->data == "syncai" or $xml->switch->data == "sendai")
if ($xml->switch->data == "syncai")
{
	$inter_com->addMessage (15, $msg);
	$checksum_list = json_decode ($xml->syncai->md5list);
	$checksum_return = array ();

	foreach ($checksum_list as $key => $value)
	{
		$data_aircraft = New Mp_aircraft('hash', str_ireplace('md5_','',$value));
		if ($data_aircraft->title == null)
		{
			$checksum_return[] = $value;
			$requested_aircraft = New Mp_aircraft_request('chekcsum', str_ireplace('md5_','',$value));
			if ($requested_aircraft->chekcsum == null)
			{
				//$key = New Key('', $xml->switch->key);

				$data2 = array (
					'pilot_id'     => '0',
					'chekcsum'     => str_ireplace('md5_','',$value),
					'request_date' => time (),
				);
				$requested_aircraft->auto_create ($data2);
			}
			else{
				$requested_aircraft->destroy_requested_aircraft (str_ireplace('md5_','',$value));

				//$key = New Key('', $xml->switch->key);

				$data2 = array (
					'pilot_id'     => '0',
					'chekcsum'     => str_ireplace('md5_','',$value),
					'request_date' => time (),
				);
				$requested_aircraft->auto_create ($data2);

			}
		}
	}
	if (count($checksum_return)>0)
	{$data['no_found'] = json_encode ($checksum_return);}
	else{$inter_com->addMessage (16, $msg);}
}
if ($xml->switch->data == "sendai")
{

	$data['key']=strval ($xml->switch->key);

	$name_of_file=explode('%', $data['key']);
	//$key = New Key('', $xml->switch->key);

	$ai_tab = array ();
	foreach ($xml->sendai as $key2 => $value2)
	{
		foreach ($value2 as $key3 => $value3)
		{
			$ai_tab[] = array (
				'checksum' => $key3,
				'data'     => $value3,
				);

		}

	}

	foreach ($ai_tab as $key2 => $value2)
	{
		$data_aircraft = New Mp_aircraft('hash', str_ireplace('md5_','',$value2['checksum']));
		$requested_aircraft = New Mp_aircraft_request();
		if ($data_aircraft->hash === null)
		{

			$text_of_airplane = gzinflate (base64_decode($value2['data']));
			if ($data_aircraft->make_aircraft ($text_of_airplane, str_ireplace('md5_','',$value2['checksum']),'0'))
			{

				$requested_aircraft->destroy_requested_aircraft (str_ireplace('md5_','',$value2['checksum']));
			}
		}
		else
		{
			//var_dump()
			$requested_aircraft->destroy_requested_aircraft (str_ireplace('md5_','',$value2['checksum']));
		}


	}

	//$key = New Key('', $xml->switch->key);
	unset($requested_aircraft);
	$requested_aircraft = New Mp_aircraft_request();
	$list=$requested_aircraft->requested_aircraft('0');
	foreach ($list as $key_2=>$value_2)
	{
		//echo $value_2;
		$checksum_return[] = $value_2->chekcsum;
	}

	//var_dump($list);
	/*$key = New Key('', $xml->switch->key);
	$requested_aircraft = New Mp_aircraft_request()->requested_aircraft($key->pilot_id);*/

		if (count($checksum_return)>0)
		{$data['no_found'] = json_encode ($checksum_return);}
		else{$inter_com->addMessage (16, $msg);}


}
