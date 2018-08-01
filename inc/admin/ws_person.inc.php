<?php
/*
 * ws_user.inc.php
 *
 * Webservices for managing users
 *
 * (c) 2007-2018 daniel.burckhardt@sur-gmbh.ch
 *
 * Version: 2018-07-23 dbu
 *
 * Changes:
 *
 */

class WsPerson
extends WsHandler
{
  // example-call: http://localhost/juedische-geschichte/admin_ws.php?pn=person&action=fetchBiographyByGnd&_debug=1&gnd=132204991
  function buildResponse () {
    $valid_actions = [ 'lookupGnd', 'fetchBiographyByGnd' ];

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
    $persons = $gndService->lookupByName($fullname);

    $ret = [];
    foreach ($persons as $person) {
      $label = $person['name'];
      if (!empty($person['profession'])) {
        $label .= ', ' . $person['profession'];
      }
      if (!empty($person['lifespan'])) {
        $label .= ' (' . $person['lifespan'] . ')';
      }

      $ret[] = [
        'value' => $person['gnd'],
        'label' => $label,
      ];
    }

    return new JsonResponse($ret);
  }

  function fetchBiographyByGndAction () {
    require_once INC_PATH . 'common/GndService.php';
    $gnd = $this->getParameter('gnd');
    $bio = BiographicalData::fetchByGnd($gnd);

    return new JsonResponse((array)$bio);
  }
}

WsHandlerFactory::registerClass('person', 'WsPerson');
