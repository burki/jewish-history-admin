<?php
/*
 * index.php
 *
 * frontend
 *
 * (c) 2015 daniel.burckhardt@sur-gmbh.ch
 *
 * Version: 2015-03-24 dbu
 *
 * Changes:
 *
 */

// local includes
define('INC_PATH', '../inc/');
require_once INC_PATH . 'local.inc.php';
require_once INC_PATH . 'sitesettings.inc.php';

require_once INC_PATH . 'content/frontendpage.inc.php';

$page = new FrontendPage(new DB(), $SITE_DESCRIPTION);
$page->init(array_key_exists('pn', $_REQUEST) ? $_REQUEST['pn'] : NULL);

// ab hier ist $page->pagename definiert
require_once INC_PATH . 'content/frontenddisplay.inc.php';
require_once INC_PATH . 'content/'
           . $page->include . '.inc.php';

$page->display();
