<?php

  while (list($key, $val) = each($_SERVER))
    $GLOBALS[$key] = $val;

  while (list($key, $val) = each($_GET))
    $GLOBALS[$key] = $val;

  $error = 0;
  $fatalerror = 0; // badArgument, badVerb

  // echo "functions\n";
  require("functions.inc.php");
  // echo "ende functions\n";
  //  echo "initglobals\n";
  require("initglobals.inc");
  // echo "ende initglobals\n";


  if (!empty($verb)) {

    if ($verb=="GetRecord") {
      //	echo "getrecord\n";
      include("getrecord.inc");
      // echo "ende getrecord\n";
    }
    elseif ($verb=="ListRecords" || $verb=="ListIdentifiers") {
      include("listrecords.inc.php");
    }
    elseif ($verb=="Identify") {
      include("identify.inc.php");
    }
    elseif ($verb=="ListMetadataFormats") {
      include("listmetadataformats.inc.php");
    }
    elseif ($verb=="ListSets") {
      // currently no sets
      $error = -1;
      $errors = getError("noSetHierarchy");
    }
    else {
      $error = -1;
      $errors = getError("badVerb", isset($arg) && sizeof($arg) > 0 ? $arg[1] : '');
    }
  }

  else {
    $error = -1;
    $errors = getError("noVerb", isset($arg) && sizeof($arg) > 0 ? $arg[1] : '');
  }

  header("Content-Type: text/xml");

  if ($fatalerror)
    $outputtop = ereg_replace("%ATTRIBUTES%", "", $outputtop);
  else
    $outputtop = ereg_replace("%ATTRIBUTES%", !empty($request_attributes) ? $request_attributes : '', $outputtop);

  // echo $outputtop;


  echo utf8_encode($outputtop);
  if (!$error)
    echo utf8_encode($output);
  else
    echo utf8_encode($errors);
  echo utf8_encode($outputbottom);
