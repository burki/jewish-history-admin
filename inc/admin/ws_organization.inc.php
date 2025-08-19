<?php
/*
 * ws_organization.inc.php
 *
 * Webservices for managing organizations
 *
 * (c) 2007-2025 daniel.burckhardt@sur-gmbh.ch
 *
 * Version: 2025-08-19 dbu
 *
 * Changes:
 *
 */

class WsOrganization
extends WsHandler
{
  // example-calls:
  //    http://localhost/juedische-geschichte/admin/admin_ws.php?pn=organization&action=lookupGnd&_debug=1&term=France
  //    http://localhost/juedische-geschichte/admin/admin_ws.php?pn=organization&action=fetchInfoByGnd&_debug=1&gnd=5156217-0
  function buildResponse () {
    $valid_actions = [ 'lookupGnd', 'fetchInfoByGnd' ];

    $action = array_key_exists('action', $_GET)
      && in_array($_GET['action'], $valid_actions)
      ? $_GET['action'] : $valid_actions[0];
    $action_name = $action.'Action';

    return $this->$action_name();
  }

  function lookupGndAction () {
    require_once INC_PATH . 'common/GndService.php';

    $fullname = $this->getParameter('term'); // parameter seems to come as latin-1?! not sure why this is needed
    $gndService = new GndService();
    $matches = $gndService->lookupOrganizationByName($fullname);

    $ret = [];
    foreach ($matches as $organization) {
        $label = $organization['name'];
        if (!empty($organization['placeLabel'])) {
            $label .= ', ' . $organization['placeLabel'];
        }

        $ret[] = [
            'value' => $organization['gnd'],
            'label' => $label,
        ];
    }

    return new JsonResponse($ret);
  }

  function fetchInfoByGndAction () {
    require_once INC_PATH . 'common/GndService.php';

    $gnd = $this->getParameter('gnd');
    $info = @CorporateBodyData::fetchByGnd($gnd);

    return new JsonResponse((array)$info);
  }
}

WsHandlerFactory::registerClass('organization', 'WsOrganization');
