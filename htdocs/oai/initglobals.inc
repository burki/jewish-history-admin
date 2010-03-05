<?PHP

  // Im ersten Teil dieser Datei werden gobale Variablen definiert
  // In zweiten Teil werden die Argumente der GET- bzw. POST-Anfrage in ein
  // einheitliches Array gespeichert und einer ersten "Uberpr"fung
  // unterzogen

  // #########################################
  // 1. Definieren einiger globaler Variablen:
  // #########################################
	$SERVER_NAME = $_SERVER['SERVER_NAME'];
	$SERVER_PORT = $_SERVER['SERVER_PORT'];
	$SERVER_URI = ($SERVER_PORT == '443' ? 'https' : 'http') // Protocoll
	 . '://' . $SERVER_NAME.($SERVER_PORT != '80' && $SERVER_PORT != '443' ? ':'.$SERVER_PORT : '');

  $FULL_URI = $SERVER_URI.$REQUEST_URI;
  $FULL_URI = rawurlencode($FULL_URI);

  $repositoryName = 'Humboldt University of Berlin, GERMANY, Document Server, OAI inferface for URNs';
  $baseURL = $SERVER_URI . $_SERVER['PHP_SELF']; // 'http://edoc1.cms.hu-berlin.de/Administration/oai';
  $adminEmail = 'mailto:oai&#x0040;rz.hu-berlin.de';
  $repositoryIdentifier = 'HUBerlin.de';
  $sampleIdentifier = 'oai:HUBerlin.de:urn:nbn:de:kobv:11-20011';

  // maximale Anzahl der pro Anfrage gelieferten Datens"atze
  // (bei der Anfrage ListRecords)
  // Sollte das Anfrageergebnis mehr Datens"atze enthalten, wird
  // ein ResumptionToken geliefert, mit dem sp"ater weitere Datens"atze
  // angefordert werden k"onnen.

  $maxrecords = 5000;

  // maximale Anzahl der pro Anfrage gelieferten Identifier
  // (bei der Anfrage ListIdentifiers)
  // Sollte das Anfrageergebnis mehr Identifier enthalten, wird
  // ein ResumptionToken geliefert, mit dem sp"ater weitere Datens"atze
  // angefordert werden k"onnen.

  $maxids = 5000;

  // Ermitteln des aktuellen Datums und Speichern im
  // erforderlichen Format

  $datetime = gmdate("Y-m-d");
  $datetime .= "T";
  $datetime .= gmdate("H:i:s");
  $datetime .= "Z";

  // Nach 24 Stunden sind resumptionTokes nicht mehr g"ultig.
  // Dieser Zeitpunk ist in $expirationdatetime gespeichert.

  $expirationdatetime = gmdate("Y-m-d", time()+24*3600);
  $expirationdatetime .= "T";
  $expirationdatetime .= gmdate("H:i:s");
  $expirationdatetime .= "Z";

  $attributes = explode("&" ,$_SERVER['QUERY_STRING']);
  $outputtop = '<?xml version="1.0" encoding="UTF-8"?>
 <OAI-PMH xmlns="http://www.openarchives.org/OAI/2.0/"
          xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
          xsi:schemaLocation="http://www.openarchives.org/OAI/2.0/
          http://www.openarchives.org/OAI/2.0/OAI-PMH.xsd">
   <responseDate>'.$datetime.'</responseDate>
   <request';
	for ($j = 0; $j < count($attributes); $j++)
	{
		if (empty($attributes[$j]))
			continue;
		$pairs = explode("=",$attributes[$j]);
		$outputtop = $outputtop.' '.$pairs[0]."=".'"'.$pairs[1].'"';
	}

	$outputtop = $outputtop.'>'.$baseURL.'</request>';
  $outputbottom = '
 </OAI-PMH>';


  // #####################################################
  // 2. Auswerten der Argumente der GET- bzw. POST-Anfrage
  // #####################################################

  // Testen, ob Argumente mehrmals vorkommen
  // (Laesst sich aus $HTTP_GET_VARS bzw. $HTTP_POST_VARS nicht ableiten,
  // daher auch nur fuer GET implementiert ...)

  if ($REQUEST_METHOD == "GET")
  {
    $decodedQUERY_STRING = urldecode($QUERY_STRING);
    //echo $decodedQUERY_STRING;
    $getargs = split('&', $decodedQUERY_STRING);
    //echo count($getargs);
    $checkargs = array();
    for ($i=0; $i<count($getargs); $i++)
    {
			if (empty($getargs[$i]))
				continue;
      $getarg = split ('=', $getargs[$i]);
      $key = $getarg[0];
      if (array_key_exists($key, $checkargs))
      {
        $error = -1;
        $errors .= getError("multipleValue", $key);
      }
      $checkargs[$key] = 1;
    }
  }

  // Speichern der Argumente in dem Array $args
  // z.B. $args[0]["key"] == "verb"
  //      $args[0]["val"] == "ListIdentifiers"

  $args = array();
  $i = 0;
  $decodedQUERY_STRING = urldecode($QUERY_STRING);
    //echo $decodedQUERY_STRING;
  $getargs = split('&', $decodedQUERY_STRING);
  for ($i=0; $i<count($getargs); $i++)
  {
		if (empty($getargs[$i]))
			continue;
		$getarg = split ('=', $getargs[$i]);
		$key = $getarg[0];
		$val = $getarg[1];
		$args[$i] = array();
		$args[$i]["key"] = $key;
		$args[$i]["val"] = $val;
  }
  //echo "Args:".count($args);
  // Zusammenstellen aller Argumente fuer das XML-Tag <request>

	$request_attributes = '';
  for ($i=0; $i<count($args); $i++)
  {
    $request_attributes .= " ";
    $request_attributes .= $args[$i]["key"]."=\"".$args[$i]["val"]."\"";
  }
