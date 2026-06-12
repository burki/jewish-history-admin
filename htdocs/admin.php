<?php

/*
 * admin.php
 *
 * Back-end https://juedische-geschichte-online.net/admin/
 *
 * (c) 2008-2026 daniel.burckhardt@sur-gmbh.ch
 *
 * Version: 2018-06-12 dbu
 *
 * Changes:
 *
 */

// local includes
define('INC_PATH', realpath(__DIR__ . '/../inc/')  . DIRECTORY_SEPARATOR);

require_once INC_PATH . 'local.inc.php';
require_once INC_PATH . 'sitesettings.inc.php';

require_once INC_PATH . 'admin/adminpage.inc.php';

$page = new AdminPage(new DB(), $SITE_DESCRIPTION);
$page->init(array_key_exists('pn', $_REQUEST) ? $_REQUEST['pn'] : null);

// from here on, $page->include is defined

require_once INC_PATH . 'admin/pagedisplay.inc.php';
require_once INC_PATH . 'admin/' . ('login' == $page->include ? '' : 'admin_')
           . $page->include . '.inc.php';

$page->display();
