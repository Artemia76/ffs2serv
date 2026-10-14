<?php
if (!defined ('PROTECTIONINCLUDE')) {
	die('Accès Interdit');
}

class XML2Array {

	private static $xml = null;
	private static $encoding = 'UTF-8';

	/**
	 * Initialize the root XML node [optional]
	 * @param $version
	 * @param $encoding
	 * @param $format_output
	 */
	public static function init($version = '1.0', $encoding = 'UTF-8', $format_output = true)
	{
		self::$xml = new DOMDocument($version, $encoding);
		self::$xml->formatOutput = $format_output;
		self::$encoding = $encoding;
	}

	/**
	 * Convert an XML to Array
	 * @param string $node_name - name of the root node to be converted
	 * @param array $arr - aray to be converterd
	 * @return DOMDocument
	 */
	public static function &createArray($input_xml)
	{
		$xml = self::getXMLRoot();
		if(is_string($input_xml))
		{
			$parsed = $xml->loadXML($input_xml);
			if(!$parsed)
			{
				throw new Exception('[XML2Array] Error parsing the XML string.');
			}
		}
		else
		{
			if(get_class($input_xml) != 'DOMDocument')
			{
				throw new Exception('[XML2Array] The input XML object should be of type: DOMDocument.');
			}
			$xml = self::$xml = $input_xml;
		}
		$array[$xml->documentElement->tagName] = self::convert($xml->documentElement);
		self::$xml = null;    // clear the xml node in the class for 2nd time use.
		return $array;
	}

	/**
	 * Convert an Array to XML
	 * @param mixed $node - XML as a string or as an object of DOMDocument
	 * @return mixed
	 */
	private static function &convert($node)
	{
		$output = array();

		switch ($node->nodeType)
		{
			case XML_CDATA_SECTION_NODE:
			$output['@cdata'] = trim($node->textContent);
			break;

			case XML_TEXT_NODE:
			$output = trim($node->textContent);
			break;

			case XML_ELEMENT_NODE:

			// for each child node, call the covert function recursively
			for ($i=0, $m=$node->childNodes->length; $i<$m; $i++)
			{
				$child = $node->childNodes->item($i);
				$v = self::convert($child);
				if(isset($child->tagName))
				{
					$t = $child->tagName;

					// assume more nodes of same kind are coming
					if(!isset($output[$t]))
					{
						$output[$t] = array();
					}
					$output[$t][] = $v;
				}
				else {
						//check if it is not an empty text node
					if($v !== '')
					{
						$output = $v;
					}
				}
			}

			if(is_array($output))
			{
				// if only one node of its kind, assign it directly instead if array($value);
				foreach ($output as $t => $v)
				{
					if(is_array($v) && count($v)==1)
					{
						$output[$t] = $v[0];
					}
				}
				if(empty($output))
				{
							//for empty nodes
					$output = '';
				}
			}

			// loop through the attributes and collect them
			if($node->attributes->length)
			{
				$a = array();
				foreach($node->attributes as $attrName => $attrNode)
				{
					$a[$attrName] = (string) $attrNode->value;
				}
				// if its an leaf node, store the value in @value instead of directly storing it.
				if(!is_array($output))
				{
					$output = array('@value' => $output);
				}
				$output['@attributes'] = $a;
			}
			break;
		}
		return $output;
	}

	/*
	 * Get the root XML node, if there isn't one, create it.
	 */
	private static function getXMLRoot(){
		if(empty(self::$xml)) {
			self::init();
		}
		return self::$xml;
	}
}



class Array2XML {

	private $version;
	private $encoding;
	/*
	 * Construct ArrayToXML object with selected version and encoding 
	 *
	 * for available values check XmlWriter docs http://www.php.net/manual/en/function.xmlwriter-start-document.php
	 * @param string $xml_version XML Version, default 1.0
	 * @param string $xml_encoding XML Encoding, default UTF-8
	 */
	public function __construct($xmlVersion = '1.0', $xmlEncoding = 'UTF-8') {
		$this->version = $xmlVersion;
		$this->encoding = $xmlEncoding;
	}
	/**
	 * Build an XML Data Set
	 *
	 * @param array $data Associative Array containing values to be parsed into an XML Data Set(s)
	 * @param string $startElement Root Opening Tag, default data
	 * @return string XML String containig values
	 * @return mixed Boolean false on failure, string XML result on success
	 */
	public function buildXML($data, $startElement = 'info'){
		if(!is_array($data)){
			echo  'Invalid variable type supplied, expected array not found on line '.__LINE__." in Class: ".__CLASS__." Method: ".__METHOD__;
			//trigger_error($err);
			//if($this->_debug) echo $err;
			return false; //return false error occurred
		}
		$xml_ffst = new XmlWriter();
		$xml_ffst->openMemory();
		$xml_ffst->startDocument($this->version, $this->encoding);
		$xml_ffst->startElement($startElement);
		$this->writeEl($xml_ffst, $data);
		$xml_ffst->endElement();//write end element
		//returns the XML results
		return $xml_ffst->outputMemory(true);
	}
	/**
	 * Write keys in $data prefixed with @ as XML attributes, if $data is an array. 
	 * When an @ prefixed key is found, a '%' key is expected to indicate the element itself, 
	 * and '#' prefixed key indicates CDATA content
	 *
	 * @param object $xml XMLWriter Object
	 * @param array $data with attributes filtered out
	 */
	protected function writeAttr(XMLWriter $xml, $data) {
		if(is_array($data)) {
			$nonAttributes = array();
			foreach($data as $key => $val) {
				//handle an attribute with elements
				if($key[0] == '@') {
					$xml->writeAttribute(substr($key, 1), $val);
				} else if($key[0] == '%') {
					if(is_array($val)) $nonAttributes = $val;
					else $xml->text($val);
				} elseif($key[0] == '#') {
					if(is_array($val)) $nonAttributes = $val;
					else {
						$xml->startElement(substr($key, 1));
						$xml->writeCData($val);
						$xml->endElement();
					}
				}
				//ignore normal elements
				else $nonAttributes[$key] = $val;
			}
			return $nonAttributes;
		}
		else return $data;
	}
	/**
	 * Write XML as per Associative Array
	 *
	 * @param object $xml XMLWriter Object
	 * @param array $data Associative Data Array
	 */
	protected function writeEl(XMLWriter $xml, $data) {
		foreach($data as $key => $value) {
			if(is_array($value) && !$this->isAssoc($value)) { //numeric array
				foreach($value as $itemValue){
					if(is_array($itemValue)) {
						$xml->startElement($key);
						$itemValue = $this->writeAttr($xml, $itemValue);
						$this->writeEl($xml, $itemValue);
						$xml->endElement();
					} else {
						$itemValue = $this->writeAttr($xml, $itemValue);
						$xml->writeElement($key, "$itemValue");
					}
				}
			} else if(is_array($value)) { //associative array
				$xml->startElement($key);
				$value = $this->writeAttr($xml, $value);
				$this->writeEl($xml, $value);
				$xml->endElement();
			} else { //scalar
				$value = $this->writeAttr($xml, $value);
				$xml->writeElement($key, "$value");
			}
		}
	}
	/*
	 * Check if array is associative with string based keys
	 * FROM: http://stackoverflow.com/questions/173400/php-arrays-a-good-way-to-check-if-an-array-is-associative-or-sequential/4254008#4254008
	 *
	 * @param array $array Array to check
	 */
	protected function isAssoc($array) {
		return (bool)count(array_filter(array_keys($array), 'is_string'));
	}
}



/**
 * Crypte des données selon la péthode AES
 *
 * @param STRING $text , contenu à crypter
 * @param STRING $pkey , clé de crypage à 32 caractéres
 *
 * @return STRING contenu crypté avec la clé secondaire de décryptage confondu dans le contenu
 *
 */
function xml_extract($text)
{
	//$text = file_get_contents('php://input');
	if (!is_string ($text) or trim ($text) === '') {
		return false;
	}
	$encoding = mb_detect_encoding ($text, array ('UTF-8', 'ISO-8859-1'), TRUE);
	if ($encoding !== false and $encoding !== 'UTF-8') {
		$text = mb_convert_encoding ($text, 'UTF-8', $encoding);
	}
	//les erreurs de syntaxe ne doivent pas polluer la réponse XML
	libxml_use_internal_errors (TRUE);
	return simplexml_load_string (trim ($text), 'SimpleXMLElement', LIBXML_NONET);
}

function pre_xml($data)
{
	$pre_xml=array();
	foreach ($data as $name => $value)
	{
		if ($name == 'erreur' and $value != '')
		{
			$pre_xml['erreur'][]= array('@error' => '0','%'=>$value);
		} 
		elseif ($name == 'erreur1' and $value != '')
		{
			$pre_xml['erreur'][]= array('@error' => '1','%'=>$value);
		}
		else if ($name == 'erreur2' and $value != '')
		{
			$pre_xml['erreur'][]= array('@error' => '2','%'=>$value);
		}
		else if ($name == 'erreur3' and $value != '')
		{
			$pre_xml['erreur'][]= array('@error' => '3','%'=>$value);
		}
		elseif ($name == 'depICAO' or $name == 'arrICAO')
		{
			if (is_object($value) === TRUE)
			{
				$icao = $value->icao;
				//$info_xml->addChild($name, $icao);
				//$info_xml->$name->addAttribute("lat", $value->lat);
				//$info_xml->$name->addAttribute("lon", $value->lng);
				$pre_xml[$name]=array('@lat' => $value->lat,'@lon' => $value->lng,'%'=>$value->icao,);

			}
			else
			{
				//$info_xml->addChild($name, $value);
				$pre_xml[$name]=$value;

			}
		}
		elseif ($name == 'version')
		{
			//$info_xml->addChild('version', '');
			$version = explode('_', $value);
			//$info_xml->$name->addAttribute("Major", $version[1]);
			//$info_xml->$name->addAttribute("Minor", $version[2]);
			//$info_xml->$name->addAttribute("Build", $version[3]);
			$pre_xml[$name]=array(
				'@Major' => $version[1],
				'@Minor' => $version[2],
				'@Build' => $version[3],
				'%'=>'',);
		}
		elseif ($name == 'whazzup')
		{
			//une liste vide produit <whazzup/> : le client retire alors les joueurs partis
			$pre_xml['whazzup'] = '';
			if (is_array ($value) and count ($value) > 0)
			{
				$pre_xml['whazzup'] = array ();
				foreach ($value as $key2 => $value2)
				{
					$pre_xml['whazzup'][$value2["name"]]=array(
						'@ip' => $value2["ip"],
						'@local_ip' => $value2["local_ip"],
						'@port' => $value2["port"],
						//'@online' => $value2["online"],
						'@server' => $value2["server"],
						//'@statut' => $value2["statut"],
						'@lastupdate' => $value2["lastupdate"],
						'%'=>'',);
				}
			}

		}
		elseif ($name == 'metar')
		{
			//$info_xml->addChild('whazzup');
			if ($value != NULL and $value != '')
			{
				foreach ($value as $key2 => $value2)
				{
					$pre_xml['metar'][$value2["metar_station_id"]]=array(
						'@time' => $value2["observation_time"],
						'@raw' => $value2["raw_text"],
						'@distance' => $value2["distance"],
						'@ICAO' => $value2["station_id"],
						'%'=>'',);
				}
			}

		}
		else
		{
			$pre_xml[$name]=$value;
		}

	}

	return $pre_xml;

}
?>