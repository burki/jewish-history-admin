<?PHP
  $set_identifier = 0;
  $set_metadataPrefix = 0;

// ############################
// Parsen der weiteren Paramter
// ############################
  if (count($args)>1) {

    $i = 0;

    while ($i < count ($args)) {

      switch ($args[$i]["key"]) {

        case "verb":
          break;

        case "identifier":
          $identifier = $args[$i]["val"];
          $set_identifier = 1;
          break;

        case "metadataPrefix":
          $metadataPrefix = $args[$i]["val"];
          $set_metadataPrefix = 1;
          if ($metadataPrefix == 'epicur')
          {}
          else
          {
            $error = -1;
            $errors .= getError("cannotDisseminateFormat", $metadataPrefix);
          }
          break;
        default:
          $error = -1;
          $errors .= getError("badArgument", $args[$i]["key"]);
        break;
      }
      $i++;
    }
  }

  if (!$set_identifier) {
    $error = -1;
    $errors .= getError("missingArgument", "identifier");
  }
  if (!$set_metadataPrefix)
  {
    $error = -1;
    $errors .= getError("missingArgument", "metadataPrefix");
  }

  $firstpart = "oai:$repositoryIdentifier:";
 
  $id = ereg_replace($firstpart, '', $identifier);


// ########################
// Output: Allgemeiner Teil
// ########################

  $output = '
  <GetRecord>';

  include("open.inc.php");
  if (!$error)
  {
    $query1 = "select id, urn, url, status, mime_type, pages, date(date_assigned),";
		$query2 = "time(date_assigned), date(date_modified), time(date_modified),";
    $query3 = "date(date_modified), time(date_modified),date(date_disabled), time(date_disabled) from doku_url_urn_list ";
    $query4 =  " where urn='".$id."' and status=1";
    //echo $query4;
    $query = $query1.$query2.$query3.$query4;
    $result= mysql_query($query,$conn);
    $number = mysql_num_rows($result);
    if ($number<=0)
    {
      $error = -1; 
      $errors .= getError("idDoesNotExist", $identifier);
    }
  }

// ###############################
// Bei Fehler: Abbruch der Ausgabe
// ###############################

  if (!$error)
  {

// ##################################
// Output: Record, falls ID vorhanden
// ##################################

    $res_row = 0;
    $res_count = 0;
    $res_entries = array(); 

    $tmp_array=mysql_fetch_array($result,MYSQL_NUM);

    $oai_identifier = "oai:".$repositoryIdentifier.":".$tmp_array[1];

    if ((int)$tmp_array[3] == 1)
      $datestamp = $tmp_array[6]."T".$tmp_array[7]."Z";
    elseif ((int)$tmp_array[3] == 2)
      $datestamp = $tmp_array[8]."T".$tmp_array[9]."Z";
    elseif ((int)$tmp_array[3] == 3)
      $datestamp = $tmp_array[10]."T".$tmp_array[11]."Z";
    $datestamp = ereg_replace("/", "-", $datestamp); 
    $output .= '
    <record>
      <header>
        <identifier>'.$oai_identifier.'</identifier>
        <datestamp>'.$datestamp.'</datestamp>
      </header>';

// ###################
// Output: Epicur
// ###################
  // echo $metadataPrefix;
      if ($metadataPrefix == 'epicur') {
        include("record_epicur.inc.php");
      }

// ###################
// Output: Record-Ende
// ###################

      $output .= '
    </record>';

// ############
// Output: Ende
// ############

    $output .= '
  </GetRecord>';
  }
  mysql_close($conn);
?>