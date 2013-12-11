<?php
/***************************************************************
*  Copyright notice
*  (c) 2013 Christoph Fischer (chris@toph.de)
*  Based on the original kOOL vCard implementation
*  (c) 2003-2012 Renzo Lauper (renzo@churchtool.org)
*  All rights reserved
*
*  This script is free software; you can redistribute it and/or modify
*  it under the terms of the GNU General Public License as published by
*  the Free Software Foundation; either version 2 of the License, or
*  (at your option) any later version.
*
*  The GNU General Public License can be found at
*  http://www.gnu.org/copyleft/gpl.html.
*  A copy is found in the textfile GPL.txt and important notices to the license
*  from the author is found in LICENSE.txt distributed with these scripts.
*
*  kOOL is distributed in the hope that it will be useful,
*  but WITHOUT ANY WARRANTY; without even the implied warranty of
*  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
*  GNU General Public License for more details.
*
*  This copyright notice MUST APPEAR in all copies of the script!
***************************************************************/

namespace Peregrinus\CardDAV;
 
require_once('lib/libphonenumber/PhoneNumberUtil.php');

$vCardProperties = array(
	'version' => '2.1',
	'defaultCountry' => 'CH',
	'phone' =>	array(
		'PREF;HOME;VOICE' => 'telp',
		'PREF;WORK;VOICE' => 'telg',
		'PREF;CELL;VOICE' => 'natel',
		'PREF;FAX' => 'fax',
	),
	'address' => array(
		'HOME;POSTAL' => array('adresse', 'plz', 'ort', 'land'),
	),
	'url' => array(
		'WORK' => 'web',
	),
	'email' => array(
		'INTERNET' => 'email',
	),
	'fields' => array(
		// set an array element named _[sep] to define another separator
		// set an array element named _[noenc] to false to switch off encoding
		'VERSION' => array('_' => array('text' => '2.1', 'noenc' => TRUE)),
			
		// organization
		'O' => array('_' => array('sep' => ' '), 0 => 'firm', 1 => 'department'),
		
		// name
		'N' => array('nachname', 'vorname', null, 'anrede', null),
		'FN' => array('_' => array('sep' => ' '), 'anrede', 'vorname', 'nachname'),
		
		// birthday
		'BDAY' => array('geburtsdatum'),
		
		// phone:
		'TEL;HOME;VOICE' => array('telp'),
		'TEL;CELL;VOICE' => array('natel'),
		'TEL;WORK;VOICE' => array('telg'),
		'TEL;WORK;FAX' => array('fax'),
		
		// address:
		'ADR;HOME;POSTAL' => array(null, null, 'adresse', 'ort', null, 'plz', 'land'),
		// url
		'URL;WORK' => array('web'),
		
		// email
		'EMAIL;INTERNET' => array('email'),
		
					
		// modified:
		'REV' => array('lastchange'),		
	),
	'format' => array(
		'telp' => array('phone', 'DE'),
		'telg' => array('phone', 'DE'),
		'natel' => array('phone', 'DE'),
		'fax' => array('phone', 'DE'),
		'geburtsdatum' => array('date', null),
		'lastchange' => array('tzdate', 'UTC'),
	),
	'encoding' => array(
		'N' => 'QUOTED-PRINTABLE',
		'FN' => 'QUOTED-PRINTABLE',
		'O' => 'QUOTED-PRINTABLE',
		'ADR;HOME;POSTAL' => 'QUOTED-PRINTABLE',
	),
);

//quoted_printable_encode() is part of PHP starting with v5.3. So only define if not defined yet.
if(!function_exists('quoted_printable_encode')) {
	function quoted_printable_encode($input, $line_max = 76) {
		$hex = array('0','1','2','3','4','5','6','7','8','9','A','B','C','D','E','F');
		$lines = preg_split("/(?:\r\n|\r|\n)/", $input);
		$eol = "\r\n";
		$linebreak = "=0D=0A";
		$escape = "=";
		$output = "";

		for ($j=0;$j<count($lines);$j++) {
			$line = $lines[$j];
			$linlen = strlen($line);
			$newline = "";
			for($i = 0; $i < $linlen; $i++) {
				$c = substr($line, $i, 1);
				$dec = ord($c);
				if ( ($dec == 32) && ($i == ($linlen - 1)) ) { // convert space at eol only
					$c = "=20"; 
				} elseif ( ($dec == 61) || ($dec < 32 ) || ($dec > 126) ) { // always encode "\t", which is *not* required
					$h2 = floor($dec/16); $h1 = floor($dec%16); 
					$c = $escape.$hex["$h2"].$hex["$h1"]; 
				}
				if ( (strlen($newline) + strlen($c)) >= $line_max ) { // CRLF is not counted
					$output .= $newline.$escape.$eol; // soft line break; " =\r\n" is okay
					$newline = "    ";
				}
				$newline .= $c;
			} // end of for
			$output .= $newline;
			if ($j<count($lines)-1) $output .= $linebreak;
		}
		return trim($output);
	}//quoted_printable_encode()
}



class vCard {
	var $properties;
	var $filename;
	var $output;
	var $utf8;
	var $config;


	function __construct($_utf8=FALSE) {
		global $vCardProperties;
		$this->utf8 = $_utf8;
		$this->config = $vCardProperties;
	}
	
	// override field names through config
	// e.g. $vCardProperties['override']['telp']='telg';
	//      would use the telg field instead of telp.
	function _o($name) {
		return ($this->config['override'][$name] ? $this->config['override'][$name] : $name);
	}
	
	// format field according to defined formatters 
	function _f($field, $value) {
		if ($formatConf = $this->config['format'][$field]) {
			$formatObject = '\Peregrinus\CardDav\vCard_formatter_'.$formatConf[0];
			$value = $formatObject::format($value, $formatConf[1]);
			return $value;
		} else return $value;
	}
	
	// encode field value
	function encode($prop, $value) {
		if ($e = $this->config['encoding'][$prop]) {
			switch ($e) {
				case 'QUOTED-PRINTABLE':
					$value = $this->utf8 ? utf8_encode($value) : $value;
					return $this->escape(quoted_printable_encode($value));
					break;				
			}
		} else return $value;	
	}
	
	function getEncoding($prop) {
		return $this->config['encoding'][$prop];
	}


	function addPerson($person) {
		global $access;

		unset($this->properties);
				
		// add fields 
		foreach ($this->config['fields'] as $propKey => $prop) {
			$propConfig = $prop['_'];
			unset ($prop['_']);
			
			$separator = ($propConfig['sep'] ? $propConfig['sep'] : ';');
			$encoding = $this->getEncoding($propKey); 
			$fullKey = ($encoding ? $propKey.';ENCODING='.$encoding : $propKey);
			
			if ($propConfig['text']) {
				$this->properties[$fullKey] = $propConfig['text'];
			} else {
				$tmp = array();
				foreach($prop as $field) {
					if (!is_null($field)) {
						// override, if necessary
						$field = $this->_o($field);
						if ($person[$field]) {
							// format, if necessary
							$value = $this->_f($field, $person[$field]);
							$tmp[] = $this->encode($propKey, $value); 
						}
					} else $tmp[] = '';
				}
				if (count($tmp)) $this->properties[$fullKey] = join($separator, $tmp);
			}
		}
		ksort($this->properties);
				
		$this->output .= $this->getVCard();
	}//addPerson()



	function writeCard() {
		global $ko_path;

		$filename = $ko_path.'download/kOOL_'.date('Ymd_His').'.vcf';

		$fp = @fopen($filename, 'w');
		fputs($fp, $this->output);
		fclose($fp);

		return $filename;
	}//writeCard()


	function outputCard() {
		$filename = $this->getFileName();

		header('Cache-Control:');
		header('Content-Disposition: attachment; filename='.$filename);
		header('Content-Length: '.strlen($output));
		header('Connection: close');
		header('Content-Type: text/x-vCard; name='.$filename.'');

		echo $this->output;
	}//outputCard();

	
	function setPhoneNumber($number, $type="") {
			
		
	// type may be PREF | WORK | HOME | VOICE | FAX | MSG | CELL | PAGER | BBS | CAR | MODEM | ISDN | VIDEO or any senseful combination, e.g. "PREF;WORK;VOICE"
		$key = "TEL";
		if ($type!="") $key .= ";".$type;
		$key.= ";ENCODING=QUOTED-PRINTABLE";
		$this->properties[$key] = $this->encode($number);
	}
	
	// UNTESTED !!!
	function setPhoto($type, $photo) { // $type = "GIF" | "JPEG"
		$this->properties["PHOTO;TYPE=$type;ENCODING=BASE64"] = base64_encode($photo);
	}
	
	function setAddress($postoffice="", $extended="", $street="", $city="", $region="", $zip="", $country="", $type="HOME;POSTAL") {
	// $type may be DOM | INTL | POSTAL | PARCEL | HOME | WORK or any combination of these: e.g. "WORK;PARCEL;POSTAL"
		$key = "ADR";
		if ($type!="") $key.= ";$type";
		$key.= ";ENCODING=QUOTED-PRINTABLE";
		$this->properties[$key] = $this->encode($name).";".$this->encode($extended).";".$this->encode($street).";".$this->encode($city).";".$this->encode($region).";".$this->encode($zip).";".$this->encode($country);
		
		if ($this->properties["LABEL;$type;ENCODING=QUOTED-PRINTABLE"] == "") {
			//$this->setLabel($postoffice, $extended, $street, $city, $region, $zip, $country, $type);
		}
	}
	
	function setLabel($postoffice="", $extended="", $street="", $city="", $region="", $zip="", $country="", $type="HOME;POSTAL") {
		$label = "";
		if ($postoffice!="") $label.= "$postoffice\r\n";
		if ($extended!="") $label.= "$extended\r\n";
		if ($street!="") $label.= "$street\r\n";
		if ($zip!="") $label.= "$zip ";
		if ($city!="") $label.= "$city\r\n";
		if ($region!="") $label.= "$region\r\n";
		if ($country!="") $country.= "$country\r\n";
		
		$this->properties["LABEL;$type;ENCODING=QUOTED-PRINTABLE"] = $this->encode($label);
	}
	
	function setEmail($address) {
		$this->properties["EMAIL;INTERNET"] = $address;
	}

	function setO($o) {
		$this->properties["O;ENCODING=QUOTED-PRINTABLE"] = $this->encode($o);
	}
	
	function setNote($note) {
		$this->properties["NOTE;ENCODING=QUOTED-PRINTABLE"] = $this->encode($note);
	}
	
	function setURL($url, $type="") {
	// $type may be WORK | HOME
		$key = "URL";
		if ($type!="") $key.= ";$type";
		$this->properties[$key] = $url;
	}

	function setRev($date) {
		$this->properties['REV'] = date_convert_timezone($date, 'UTC');
	}
	
	function getVCard() {
		$text = "BEGIN:VCARD\r\n";
		$text .= 'VERSION:'.$this->properties['VERSION']."\r\n";
		$props = $this->properties;
		unset($props['VERSION']);
		foreach($props as $key => $value) {
			$text.= "$key:".$value."\r\n";
		}
		$text.= "END:VCARD\r\n";
		return $text;
	}
	
	function getFileName() {
		return ($this->filename != '.vcf' && $this->filename != '') ? $this->filename : 'vcard.vcf';
	}


	function escape($string) {
		return str_replace(";","\;",$string);
	}//escape()


}//class vCard


class vCard_Formatter_phone {	
	function format($number, $defaultCountry) {
		/*
		if (trim($number)) {
			die ($number);
			$phoneUtil = \com\google\i18n\phonenumbers\PhoneNumberUtil::getInstance();
			$numberProto = $phoneUtil->parse($number, $defaultCountry);
			$number = $phoneUtil->format($numberProto, \com\google\i18n\phonenumbers\PhoneNumberFormat::INTERNATIONAL);
		}
		*/
		return $number; 
	}
}

class vCard_Formatter_date {	
	function format($date, $param) {
		return sql_datum($date);
	}		
}

class vCard_Formatter_tzdate {	
	function format($date, $param) {
		return date_convert_timezone($date, $param);
	}		
}


?>
