<?PHP

  $set_set = 0;
  $set_until = 0;
  $set_from = 0;
  $set_metadataPrefix = 0;
  $set_resumptionToken = 0;
  $error = 0;
  $errors = '';

  if (count($args)>1) {

    $i = 0;

    while ($i < count ($args)) {

      switch ($args[$i]["key"]) {

        case "verb":
          break;

        case "until":
          $set_until = 1;
          $until = $args[$i]["val"];
          break;

        case "from":
          $set_from = 1;
          $from = $args[$i]["val"];
          break;

        case "set":
          $set_set = 1;
          $set = $args[$i]["val"];
          break;

        case "metadataPrefix":
          $metadataPrefix = $args[$i]["val"];
          $set_metadataPrefix = 1;
          if ($metadataPrefix == 'epicur')
          {
			;
		  }
          else
          {
            $error = -1;
            $errors .= getError("cannotDisseminateFormat", $metadataPrefix);
          }
          break;

        case "resumptionToken":
          $set_resumptionToken = 1;
          $resumptionToken = $args[$i]["val"];
          break;

        default:
          $error = -1;
          $errors = getError("badArgument", $args[$i]["key"]);
          break;
      }
      $i++;
    }
  }

  if (!$set_metadataPrefix && !$set_resumptionToken)
  {
    $error = -1;
    $errors .= getError("missingArgument", "metadataPrefix");
  }



// ########################
// neue Listrecords-Anfrage
// ########################

    $deliveredrecords = 0;

    $extquery = "";

    if ($set_from) {
      $from_granularity = checkDateFormat($from, 1);
      //echo $from_granularity;
      if (!$from_granularity) {
        $error = -1;
        $errors .= getError($errorType, "from");
      }

      $from = generatelocaltime($from);
      //echo $from;
      $extquery .= " and DATE(IFNULL(date_modified, date_assigned)) >= \"$from\"";
    }

    if ($set_until) {

      $until_granularity = checkDateFormat($until, 1);
      if (!$until_granularity) {
        $error = -1;
        $errors .= getError($errorType, "until");
      }

      $until = generatelocaltime($until);
      //echo $until;
      $extquery .= " and DATE(IFNULL(date_modified, date_assigned)) <= \"$until\"";
    }

    if ($set_from && $set_until) {
      if ($from_granularity != $until_granularity) {
        $error = -1;
        $errors .= getError("badGranularity", "different");
      }
    }

    if ($set_set)
    {
      $error = -1;
      $errors .= getError("noSetHierarchy");
    }



  include("open.inc.php");
  if (!$error)
  {
    $query1 = "select id, urn, url, status, mime_type, pages, date(date_assigned),";
	$query2 = "time(date_assigned), date(date_modified), time(date_modified),";
    $query3 = "date(date_modified), time(date_modified),date(date_disabled), time(date_disabled) from doku_url_urn_list ";
    $query4 =  " where status=1";

    //echo $extquery;
    $query = $query1.$query2.$query3.$query4;
    $query = $query.$extquery;
	// die($query);
    $result= mysql_query($query,$conn);
    $number = mysql_num_rows($result);
    if ($number<=0)
    {
      $error = -1;
      $errors .= getError("noRecordsMatch");
    }
  }

// ########################
// Output: Allgemeiner Teil
// ########################

  $output = '<' . $verb . '>';

// ###############################
// Bei Fehler: Abbruch der Ausgabe
// ###############################

  if (!$error)
  {


// ##################################
// Output: Record, falls ID vorhanden
// ##################################


    $rowcount = 0;

    while ($tmp_array = mysql_fetch_array($result,MYSQL_NUM))
    {

      $oai_identifier = "oai:".$repositoryIdentifier.":".$tmp_array[1];

      if ((int)$tmp_array[3] == 1)
        $datestamp = $tmp_array[6]." ".$tmp_array[7];
      elseif ((int)$tmp_array[3] == 2)
        $datestamp = $tmp_array[8]." ".$tmp_array[9];
      elseif ((int)$tmp_array[3] == 3)
        $datestamp = $tmp_array[10]." ".$tmp_array[11];
      $datestamp = ereg_replace("/", "-", $datestamp);
      $datestamp = generategmtime($datestamp, "stamp");

	  if ($verb=="ListRecords") {
      $output .= '
    <record>';
	  }
	  $output .= '
      <header>
        <identifier>'.$oai_identifier.'</identifier>
        <datestamp>'.$datestamp.'</datestamp>
      </header>';

	  if ($verb=="ListRecords") {
		if ($metadataPrefix == 'epicur') {
// ###################
// Output: Epicur
// ###################
          include("record_epicur.inc.php");
        }
// ###################
// Output: Record-Ende
// ###################

        $output .= '
      </record>';
	  }
    }


// ############
// Output: Ende
// ############

    $output .= '</' . $verb . '>';
  }
  mysql_close($conn);
