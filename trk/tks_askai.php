<?php
/**
 * Created by PhpStorm.
 * User: Flo
 * Date: 19/08/2016
 * Time: 07:14
 */

$db_m_5 = Db_mark5::getInstance ('MARK_5_ENR');


$search_checksum = $xml->askai->search;
$search_title = $xml->askai->title;
$callsign=$search_title = $xml->askai->callsign;


$where = "`title` like '%" . $xml->askai->title . "%'";
$data = '`title`, `hash`,`atc_model`,`static_cg_height`,`static_pitch`,`empty_weight`,`engine_type`,`engine_number`,`Category`';

$db_search = $db_m_5->select ('`' . PF_MARK_5_ENR . 'mp_aircraft`', $data, $where, 'order by`empty_weight` DESC');

$data = array ();

$pilot_list = json_decode ($xml->askai->my_list);//récupération des checksum dispo dans le simulateur client


title_test:
//$where = " `enable`=1 ";
$where = " 1 ";
$data = '`title`, `hash`,`atc_model`,`static_cg_height`,`static_pitch`,`empty_weight`,`engine_type`,`engine_number`,`Category`';

$db_full_list = $db_m_5->select ('`' . PF_MARK_5_ENR . 'mp_aircraft`', $data, $where, '', 'list');
$tab_percent_title = array ();

$key_aircraft=0;

foreach ($db_full_list as $key => $value)
{
	foreach ($pilot_list as $key_2 => $value_2)
	{
		if ($pos = stripos ($value->hash, $value_2) === false) //compraison entre ce que j'ai et ce qui est dispo
		{
		}
		else
		{
			foreach (json_decode ($value->title) as $key_3 => $value_3)
			{
				//$temp_title = json_decode ($value->title);
				$my_aircraft_info[$key_aircraft]['title'] = $value_3;
				$my_aircraft_info[$key_aircraft]['atc_model'] = $value->atc_model;
				$my_aircraft_info[$key_aircraft]['static_cg_height'] = $value->static_cg_height;
				$my_aircraft_info[$key_aircraft]['static_pitch'] = $value->static_pitch;
				$my_aircraft_info[$key_aircraft]['empty_weight'] = $value->empty_weight;
				$my_aircraft_info[$key_aircraft]['engine_type'] = $value->engine_type;
				$my_aircraft_info[$key_aircraft]['engine_number'] = $value->engine_number;
				$my_aircraft_info[$key_aircraft]['category'] = $value->Category;
				$key_aircraft++;
			}
		}
	}
}

/*test du title*/
foreach ($my_aircraft_info as $key => $value)
{
	similar_text (strtolower($value['title']), strtolower($xml->askai->title), $percent_title);

	if ($percent_title >= 50)
	{
			//echo " test: ".$value['title']." = >".$percent_title."%\n";
		$my_aircraft_info[$key]['points_title'] = intval ($percent_title / 10);
			/*if($percent_title>=$best_title)
			{
			$value2 = intval ($percent_title / 10);
			$best_title=$percent_title;
		}*/
	}
}

/*test de l'atc model*/
foreach ($my_aircraft_info as $key => $value)
{
	similar_text (strtolower($value['atc_model']), strtolower($xml->askai->atc_model), $percent_atc);

	if ($percent_atc >= 50)
	{
		$my_aircraft_info[$key]['points_atc'] = intval ($percent_atc / 10);
	}
}

/*test de poids*/

foreach ($my_aircraft_info as $key => $value)
{
	if($max_empty_weight!=0)
	{
		$my_aircraft_info[$key]['points_weight'] = 10 - ((ABS($my_aircraft_info[$key]['empty_weight']-$db_search->empty_weight)*10)/$max_empty_weight);

		$result= 10-((ABS($my_aircraft_info[$key]['empty_weight'] - $db_search->empty_weight)*10)/$db_search->empty_weight);

		if($result<0)
		{
			$my_aircraft_info[$key]['points_weight'] =0;
		}
		else if($result>10)
		{
			$my_aircraft_info[$key]['points_weight'] =10;
		}
		else{
			$my_aircraft_info[$key]['points_weight'] =$result;
		}
	}
	


}



/*test du type moteur*/
foreach ($my_aircraft_info as $key => $value)
{
	if ($db_search->engine_type == $value['engine_type'])
	{
		$my_aircraft_info[$key]['points_engine_type'] = 5;
	}
}

/*test du nombre de moteur*/
foreach ($my_aircraft_info as $key => $value)
{
	if ($db_search->engine_number == $value['engine_number'])
	{
		$my_aircraft_info[$key]['points_engine_number'] = 5;
	}
}

/*test de la catégorie*/
foreach ($my_aircraft_info as $key => $value)
{
	if (strtolower($db_search->Category) == strtolower($value['category']))
	{
		$my_aircraft_info[$key]['category'] = 20;
	}
	else{$my_aircraft_info[$key]['category'] = 0;}
}

/*calcul de tous les points*/
foreach ($my_aircraft_info as $key => $value)
{
	unset($points);
	$my_aircraft_info[$key]['points'] = $my_aircraft_info[$key]['points_engine_number'] + $my_aircraft_info[$key]['points_engine_type'] +  $my_aircraft_info[$key]['points_atc'] + $my_aircraft_info[$key]['points_title']+$my_aircraft_info[$key]['points_weight']+$my_aircraft_info[$key]['category'];
}

$max_points=0;
foreach ($my_aircraft_info as $key => $value)
{
	if(intval($my_aircraft_info[$key]['points'])>=$max_points)
	{
		unset($data);
		unset($random);
		if ($xml->askai->title==$my_aircraft_info[$key]['title'])
		{
			$data['title_ans'] =$xml->askai->title;
		}
		else{
			//$random = count ($my_aircraft_info[$key]['title']);
			//$random=rand (0, $random-1);
			$data['title_ans'] = $my_aircraft_info[$key]['title'];
		}

		$data['atc_model'] = $xml->askai->atc_model;
		$data['title'] = $xml->askai->title;
		
		$data['static_cg_height'] = $my_aircraft_info[$key]['static_cg_height'];
		$data['static_pitch'] = $my_aircraft_info[$key]['static_pitch'];

		$data['cheksum'] = $key;
		//$data['random'] = $random;
		//$data['count'] = count ($my_aircraft_info[$key]['title']);

		$data['score_E_number'] = $my_aircraft_info[$key]['points_engine_number'];
		$data['score_E__type'] = $my_aircraft_info[$key]['points_engine_type'];
		$data['score_ATC'] = $my_aircraft_info[$key]['points_atc'];
		$data['score_title'] = $my_aircraft_info[$key]['points_title'];
		$data['score_weight'] =$my_aircraft_info[$key]['points_weight'];
		$data['category'] =$my_aircraft_info[$key]['category'];
		$data['score'] = $my_aircraft_info[$key]['points'];
		$data['callsign'] = $callsign;


		$data["erreur"] =$data["erreur"]. "Le " . $xml->askai->title . " va  être remplacé par votre ".$data["title_ans"]." \nScore:\nNombre de moteur: ".round($data["score_E_number"])." pts \nType de moteur: ".round($data["score_E__type"])." pts \nRessemblance atc mode :".round($data["score_ATC"])." pts \nRessemblance du titre: ".round($data["score_title"])." pts \nEquivalance du poid: ".round($data["score_weight"])." pts \nEquivalance du catégorie:".round($data["category"])." pts \nTOTAL: ".round($data["score"])." pts\n\n";
		
		$max_points	=intval($my_aircraft_info[$key]['points']);
	}

}



//var_dump($my_aircraft_info);
?>