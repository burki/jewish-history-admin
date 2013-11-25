<?php
/*
 * admin_ws.php
 *
 * support-code for docupedia backend
 *
 * (c) 2008-2010 daniel.burckhardt@sur-gmbh.ch
 *
 * Version: 2010-06-03 dbu
 *
 * Changes:
 *
 */


// local includes
define('INC_PATH', '../../inc/');
include_once INC_PATH . 'local.inc.php';
include_once INC_PATH . 'sitesettings.inc.php';

$response = NULL;

if (array_key_exists('pn', $_REQUEST)
   && in_array($_REQUEST['pn'], array('user', 'publication', 'article', 'feed'))) {
  // here we know that we have a valid $request
  include_once INC_PATH . 'admin/wshandler.inc.php';
  include_once INC_PATH . 'admin/ws_' . $_REQUEST['pn'] . '.inc.php';
  $handler = WsHandlerFactory::getInstance($_REQUEST['pn']);
  $response = $handler->buildResponse();
}

if ($response !== NULL) {
  if ($response instanceof JsonResponse)
    $response->sendJson();
  else
    $response->send();
}
