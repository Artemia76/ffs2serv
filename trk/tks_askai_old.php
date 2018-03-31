<?php
/**
 * Created by PhpStorm.
 * User: Flo
 * Date: 19/08/2016
 * Time: 07:14
 */

$db_m_5 = Db_mark5::getInstance ('MARK_5_ENR');

//$xml = array_to_object ($data_xml);

$search_checksum = $xml->askai->search;
$search_title = $xml->askai->title;


$where = "`hash` like '%" . $xml->askai->search . "%'";
$data = '`title`, `hash`';

$db_search = $db_m_5->select ('`' . PF_MARK_5_ENR . 'mp_aircraft`', $data, $where, '');

$data = array ();

$pilot_list = json_decode ($xml->askai->my_list);//récupération des checksum dispo dans le simulateur client

if ($db_search == false) //si un autre checksum correspond au même avion if ($db_list != false)
{


	$checksum_result = json_decode ($db_search->hash); //extraction des checksums correspondant au même avion
	$title_result = json_decode ($db_search->title);//extraction des titre correspondant au même avion(tableau multidimensionel

	foreach ($checksum_result as $key => $item)//recherche de jumeaux par cheksum
	{
		foreach ($pilot_list as $item2)
		{
			if ($item == $item2)
			{
				$equal_checksun = $item2; //
				$equal_position = $key;
				$twins = 1;
				break(2);
			}
		}
	}
	if ($twins == 1)//si des jumeaux ont été repérés grace au cheksum if($twins==1)
	{
		if (in_array ($xml->askai->title, $title_result[$equal_position]))//si c'est un vrai jumeaux
		{
			unset($data);
			$data['checksum'] = $equal_checksun;
			$data['title'] = $xml->askai->title;
			$data['method'] = 'T_twins';

			$data['erreur'] = 'Le ' . $xml->askai->title . ' va être remplacé par votre ' . $data['title'];
			goto end;
		}
		else//sinon c'est un faux jumeuax et on envoie un titre que le pilote a
		{
			unset($data);
			$data['checksum'] = $equal_checksun;
			$random = count ($title_result[$equal_position]);
			$data['title'] = $title_result[$equal_position][rand (0, $random)];
			$data['method'] = 'F_twins';
			$data['erreur'] = 'Le ' . $xml->askai->title . ' va être remplacé par votre ' . $data['title'];
			goto end;
		}
	}
	else
	{
		goto title_test;
	}
}
else
{
	title_test:
	$where = " 1 ";
	$data = '`title`, `hash`,`atc_model`,`static_cg_height`,`static_pitch`';

	$db_full_list = $db_m_5->select ('`' . PF_MARK_5_ENR . 'mp_aircraft`', $data, $where, '', 'list');
	$tab_percent_title = array ();

	foreach ($db_full_list as $key => $value)
	{
		foreach ($pilot_list as $key_2 => $value_2)
		{
			if ($pos = stripos ($value->hash, $value_2) === false) //compraison entre ce que j'ai et ce qui est dispo
			{
			}
			else
			{
				foreach (json_decode ($value->hash) as $key_3 => $value_3)
				{
					$temp_title = json_decode ($value->title);
					$my_aircraft_info[$value_2]['title'] = $temp_title[$pos];
					$my_aircraft_info[$value_2]['atc_model'] = $value->atc_model;
					$my_aircraft_info[$value_2]['static_cg_height'] = $value->static_cg_height;
					$my_aircraft_info[$value_2]['static_pitch'] = $value->static_pitch;
				}
			}
		}
	}

	foreach ($my_aircraft_info as $key => $value)
	{
		$atc_model = $value['atc_model'];
		$static_pitch = $value['static_pitch'];
		$static_cg_height = $value['static_cg_height'];
		foreach ($value['title'] as $item)
		{
			similar_text ($item, $xml->askai->title, $percent_title);
			if ($percent_title > $best_similar_title)
			{
				$best_similar_title = $percent_title;
				//echo $xml->askai->title . " => " . $percent_title . ' % ' . $item . ' <br>';
				$best_similar_title_data = array ('score' => $best_similar_title, 'title' => $item, 'atc_model' => $atc_model, 'static_pitch' => $static_pitch, 'static_cg_height' => $static_cg_height);
			}
		}
	}

	if ($best_similar_title_data['score'] >= 50)
	{
		unset($data);
		$data['atc_model'] = $xml->askai->atc_model;
		$data['title'] = $xml->askai->title;
		$data['atc_model_ans'] = $best_similar_title_data['atc_model'];
		$data['title_ans'] = $best_similar_title_data['title'];
		$data['static_cg_height'] = $best_similar_title_data['static_cg_height'];
		$data['static_pitch'] = $best_similar_title_data['static_pitch'];
		$data['method'] = 'TITLE_score';
		$data['score'] = $best_similar_title_data['score'];


		$data['erreur'] = 'Le ' . $xml->askai->title . ' va être remplacé par votre ' . $data['title'];
		goto end;
	}


	foreach ($my_aircraft_info as $key => $value)
	{
		//var_dump($value['atc_model']);
		similar_text ($value['atc_model'], $xml->askai->atc_model, $percent_atc);

		if ($percent_atc > $best_similar_atc)
		{
			$best_similar_atc = $percent_atc;
			//echo $percent_atc.'% entre '.$xml->askai->atc_model.' et '.$value['atc_model'].' atc_model  <br>';


			///
			/*$data['checksum'] = $equal_checksun;
			$random = count ($value['title']);
			$data['title'] = $title_result[$equal_position][rand (0, $random)];
			$data['method'] = 'F_twins';
			$data['erreur2'] = 'Le ' . $xml->askai->title . ' va être remplacé par votre ' . $data['title'] . '\n';*/

			///
			if (is_array ($value['title']))
			{
				$random = count ($value['title']);
				$title = $value['title'][rand (0, $random)];
			}
			else
			{
				$title = $value['title'];
			}
			//var_dump($value['title']);


			$best_similar_atc_data = array ('score' => $best_similar_atc, 'title' => $title, 'atc_model' => $value['atc_model'], 'static_pitch' => $static_pitch, 'static_cg_height' => $static_cg_height);
		}

	}

	if ($best_similar_atc_data['score'] >= 70)
	{
		unset($data);

		$data['atc_model'] = $xml->askai->atc_model;
		$data['title'] = $xml->askai->title;
		$data['atc_model_ans'] = $best_similar_atc_data['atc_model'];
		$data['title_ans'] = $best_similar_atc_data['title'];
		$data['static_cg_height'] = $best_similar_atc_data['static_cg_height'];
		$data['static_pitch'] = $best_similar_atc_data['static_pitch'];
		$data['method'] = 'ATC_model_score';
		$data['score'] = $best_similar_atc_data['score'];


		/*$data['title'] = $best_similar_atc_data['title'];
		$data['atc_model'] = $best_similar_atc_data['atc_model'];
		$data['method'] = 'Atc_model_score';
		$data['score'] = $best_similar_atc_data['score'];*/

		$data['erreur'] = 'Le ' . $xml->askai->title . ' va être remplacé par votre ' . $data['title'];
		goto end;
	}

}

unset($data);

$data['atc_model'] = $xml->askai->atc_model;
//$data['title'] = $xml->askai->title;
$data['atc_model_ans'] = 'unknown';
//$data['title_ans'] = 'unknown';
$data['static_cg_height'] = 'unknown';
$data['static_pitch'] = 'unknown';
$data['method'] = 'unknown';
$data['score'] = 'unknown';
$data['erreur'] = 'Le ' . $xml->askai->title . ' ne peut être remplacé.';


end:
