<?php
/*
 * admin.php
 *
 * Back-end for http://juedische-geschichte-online.net/
 *
 * (c) 2008-2018 daniel.burckhardt@sur-gmbh.ch
 *
 * Version: 2018-05-22 dbu
 *
 * Changes:
 *
 */

// local includes
define('INC_PATH', '../inc/');
require_once INC_PATH . 'local.inc.php';
require_once INC_PATH . 'sitesettings.inc.php';

require_once INC_PATH . 'admin/adminpage.inc.php';

$page = new AdminPage(new DB(), $SITE_DESCRIPTION);
$page->init(array_key_exists('pn', $_REQUEST) ? $_REQUEST['pn'] : NULL);

// from here on, $page->include is defined

require_once INC_PATH . 'admin/pagedisplay.inc.php';
require_once INC_PATH . 'admin/' . ('login' == $page->include ? '' : 'admin_')
           . $page->include . '.inc.php';

$page->display();
