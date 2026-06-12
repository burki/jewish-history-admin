<?php

/*
 * admin_ws.php
 *
 * support-code for https://juedische-geschichte-online.net/admin/admin.php
 *
 * (c) 2008-2025 daniel.burckhardt@sur-gmbh.ch
 *
 * Version: 2025-08-19 dbu
 *
 * Changes:
 *
 */


// local includes
define('INC_PATH', '../inc/');
include_once INC_PATH . 'local.inc.php';
include_once INC_PATH . 'sitesettings.inc.php';

$response = null;

if (array_key_exists('pn', $_REQUEST)
   && in_array($_REQUEST['pn'], [ 'user', 'publication', 'article', 'person', 'organization', 'place' ])) {
    // here we know that we have a valid $request
    include_once INC_PATH . 'admin/wshandler.inc.php';
    include_once INC_PATH . 'admin/ws_' . $_REQUEST['pn'] . '.inc.php';

    $handler = WsHandlerFactory::getInstance($_REQUEST['pn']);
    $response = $handler->buildResponse();
}

if ($response !== null) {
    if ($response instanceof JsonResponse) {
        $response->sendJson();
    }
    else {
        $response->send();
    }
}
