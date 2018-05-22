<?php
/*
 * xml.php
 *
 * show transformed XML
 *
 * (c) 2015-2018 daniel.burckhardt@sur-gmbh.ch
 *
 * Version: 2018-05-22 dbu
 *
 * Changes:
 */

// a bunch of common include-files
define('INC_PATH', '../inc/');


// setup all server specific paths and settings
require_once INC_PATH . 'local.inc.php';

// include all site-specific settings
require_once INC_PATH . 'sitesettings.inc.php';

require_once INC_PATH . 'admin/adminpage.inc.php';

require_once INC_PATH . 'admin/pagedisplay.inc.php';

$page = new AdminPage($dbconn = new DB(), array());
$display = new PageDisplay($page);
if (!array_key_exists('media_id', $_GET)) {
  die('media_id missing');
}

$querystr = sprintf("SELECT item_id, type, name, mimetype FROM Media WHERE id=%d",
                    $_GET['media_id']);
$dbconn->query($querystr);
if (!$dbconn->next_record() || 'application/xml' != $dbconn->Record['mimetype']) {
  die('invalid media_id');
}

$img = $dbconn->Record;
$fname = $display->buildImgFname($img['item_id'], $img['type'], $img['name'], $img['mimetype']);
if (array_key_exists('format', $_GET) && in_array($_GET['format'], array('docx'))) {
  $client = new \OxGarage\Client();
  // preprocess
  $temp = tempnam(sys_get_temp_dir(), 'TMP_');
  /*
  header('Content-type: text/xml');
  echo(tranform(realpath($fname), 'preprocess.xsl'));
  exit; */
  file_put_contents($temp, tranform(realpath($fname), 'preprocess.xsl'));

  // $client->convert(file_get_contents($fname), 'txt:text:plain');
  $client->convert($temp);
  unlink($temp);
  exit;
}

echo tranform($fname);

function tranform($fname_xml, $fname_xsl = 'dtabf.xsl') {
  $xslt_dir = BASE_FILEPATH . '../inc/xslt/';
  $cmd = sprintf('java -cp %s net.sf.saxon.Transform -s:%s -xsl:%s',
                  realpath($xslt_dir . 'saxon9he.jar'),
                  realpath($fname_xml),
                  realpath($xslt_dir . $fname_xsl));
  $res = `$cmd`;

  return $res;
}
