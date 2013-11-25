<?php

  function xmlstr($string, $charset = 'iso8859-1', $xmlescaped = 'false')
  {
	  $xmlstr = stripslashes(trim($string));
	  // just remove invalid characters
	  $pattern ="/[\x-\x8\xb-\xc\xe-\x1f]/";
	  $xmlstr = preg_replace($pattern, '', $xmlstr);

	  // escape only if string is not escaped
	  if (!$xmlescaped) {
		  $xmlstr = htmlspecialchars($xmlstr, ENT_QUOTES);
	  }

	  if ($charset != "utf-8") {
		  $xmlstr = utf8_encode($xmlstr);
	  }

	  return $xmlstr;
  }

  // takes either an array or a string and outputs them as XML entities
  function xmlformat($record, $element, $attr = '', $indent = 0)
  {
	  global $charset;
	  global $xmlescaped;

	  if ($attr != '') {
		  $attr = ' '.$attr;
	  }

	  $str = '';
	  if (is_array($record)) {
		  foreach  ($record as $val) {
			  $str .= str_pad('', $indent).'<'.$element.$attr.'>'.xmlstr($val, $charset, $xmlescaped).'</'.$element.">\n";
		  }
		  return $str;
	  } elseif ($record != '') {
		  return str_pad('', $indent).'<'.$element.$attr.'>'.xmlstr($record, $charset, $xmlescaped).'</'.$element.">\n";
	  } else {
		  return '';
	  }
  }

  function generatelocaltime($datestring)
  {
    $year = substr($datestring, 0, 4);
    $month = substr($datestring, 5, 2);
    $day = substr($datestring, 8, 2);
    $rettime = date("Y-m-d", mktime(0, 0, 0, $month, $day, $year));
    return $rettime;
  }


  function generategmtime($timestring, $opt)
  {
    $ye = substr($timestring, 0, 4);
    $mo = substr($timestring, 5, 2);
    $da = substr($timestring, 8, 2);
    $ho = substr($timestring, 11, 2);
    $mi = substr($timestring, 14, 2);
    $se = substr($timestring, 17, 2);
    if ($opt == "stamp")
    {
      $rettime = gmdate("Y-m-d#H:i:s_", mktime($ho, $mi, $se, $mo, $da, $ye));
      $rettime = ereg_replace ("#", "T", $rettime);
      $rettime = ereg_replace ("_", "Z", $rettime);
    }
    else
      $rettime = gmdate("Y-M-d H:i:s", mktime($ho, $mi, $se, $mo, $da, $ye));
    return $rettime;
  }


  function checkDateFormat($date, $softcheck=0) {
  	//echo $date;
    global $message;
    global $errorType;
    $preg = "([0-9]{4})-([0-9]{1,2})-([0-9]{1,2})";
    //"^([0-9]{4})-([0-9]{1,2})-([0-9]{1,2})T(([01][0-9])|(2[0-3])):[0-5][0-9]:[0-5][0-9]Z$"
    $ok = preg_match("/$preg/", $date , $regs );
    //echo count($regs);
    //echo "OK:".$ok;
    if ($ok == true)
		{
      //echo "preg_match";
      if (checkdate($regs[2], $regs[3], $regs[1]) ) {
        return 1;
      }
      else {
        $message = "Invalid Date: $date is not a valid date.";
        $errorType = "badGranularity";
        return 0;
      }
    }

    else
		{
			echo "else";
			$preg = "([0-9]{4})-([0-9]{1,2})-([0-9]{1,2})";
			$ok = preg_match("/$preg/", $date , $regs );
		  if ($ok == true)
			{
        $message = "Invalid Date Format: $date does not comply to the date format YYYY-MM-DD.";
        $errorType = "badGranularity";
        return 0;
      }
      else
		  {
        $message = "Invalid Date Format: $date does not comply to the date format YYYY-MM-DD.";
        $errorType = "badGranularity";
        return 0;
      }
    }
  }

  // generiert DB-Abfrage, die alle Datens"atze enth"alt bzw. den Datensatz
  // mit der id $id, falls diese "ubergeben wird.


  function getError($code, $info="", $info2="")
  {
    global $fatalerror;
    switch ($code) {
      case "exclusiveArgument" :
        $text = "The usage of resumptionToken as an argument allows no other arguments.";
        $code = "badArgument";
        break;
      case "missingArgument" :
	      $text = "The required argument $info is missing in the request.";
        $code = "badArgument";
        break;
      case "badArgument" :
        $text = "The argument $info included in the request is not valid.";
        $fatalerror = -1;
        break;
      case "badGranularity" :
        $code = "badArgument";
        $text = "The value of the $info-argument is not valid.";
        $fatalerror = -1;
        if ($info == "different")
          $text = "The granularities of the the from- and the until-argument do not match.";
        break;
      case "badResumptionToken" :
        $text = "The resumptionToken $info does not exist or has already expired.";
        break;
      case "noVerb" :
        $text = "The request does not provide any verb.";
        $code = "badVerb";
        break;
      case "badVerb" :
        $text = "The verb $info provided in the request is illegal.";
        $fatalerror = -1;
        break;
      case "cannotDisseminateFormat" :
        if ($info2)
          $text = "The metadata format given by $info cannot be delivered for the item with the identifier $info2.";
        else
          $text = "The metadata format given by $info is not supported by the repository.";
        break;
      case "idDoesNotExist" :
        $text = "The id $info is illegal for this repository.";
        break;
      case "noRecordsMatch" :
        $text = "The combination of the given values results in an empty list.";
        break;
      case "noSetHierarchy" :
        $text = "The repository does not support sets.";
        break;
      case "multipleValue" :
        $code = "badArgument";
        $text = "multiple values are not allowed for the $info parameter";
        $fatalerror = -1;
        break;
      case "noMetadataFormats" :
        $text = "There are no metadata formats available for the specified item";
        break;
      default :
        return "";
    }
    $error = '
    <error code="'.$code.'">'.$text.'</error>';
    return $error;
  }
?>
