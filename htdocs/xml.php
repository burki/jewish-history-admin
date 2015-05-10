<?php
/*
 * xml.php
 *
 * show transformed XML
 *
 * (c) 2015 daniel.burckhardt@sur-gmbh.ch
 *
 * Version: 2015-05-09 dbu
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
$xslt_dir = BASE_FILEPATH . '../docs/tei2html/';
$cmd = sprintf('java -cp %s net.sf.saxon.Transform -s:%s -xsl:%s',
                realpath($xslt_dir . 'saxon9he.jar'),
                realpath($fname),
                realpath($xslt_dir . 'dtabf.xsl'));
$res = `$cmd`;
echo $res;