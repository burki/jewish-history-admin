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

class WsUser
extends WsHandler
{
  // example-call: http://localhost/juedische-geschichte/admin_ws.php?pn=user&action=matchUser&fulltext=burckhardt&_debug=1
  function buildResponse () {
    $valid_actions = [ 'matchUser' ];

    $action = array_key_exists('action', $_GET)
      && in_array($_GET['action'], $valid_actions)
      ? $_GET['action'] : $valid_actions[0];
    $action_name = $action.'Action';

    return $this->$action_name();
  }

  function matchUserAction () {
    $entries = [];

    $search = $this->getParameter('fulltext');

    if (isset($search) && strlen($search) >= 2) {
      $dbconn = new DB;

      // build the query
      $words = split_quoted($search);
      $fields = [ 'lastname', 'firstname', 'email' ];

      for ($i = 0; $i < count($words); $i++) {
        $parts = [];

        for ($j = 0; $j < count($fields); $j++) {
          $parts[$j] = $fields[$j]
                     . sprintf(" REGEXP '[[:<:]]%s'",
                               $dbconn->escape_string($words[$i]));
        }

        $words[$i] = '('.implode(' OR ', $parts).')';
      }
      $querystr = sprintf("SELECT id, lastname, firstname, email FROM User WHERE status <> %d AND %s",
                          STATUS_DELETED, implode(' AND ', $words))
                . " ORDER BY lastname, firstname";
      $dbconn->query($querystr);

      while ($dbconn->next_record()) {
        $user = $dbconn->Record['lastname'] . ' ' . $dbconn->Record['firstname']
              . (!empty($dbconn->Record['email'])
                 ? ' (' . $dbconn->Record['email'] . ')' : '');
        $entries[] = [ 'id' => $dbconn->Record['id'], 'item' => $user ];
      }
    }

    return new AutocompleterResponse($entries);
  }
}

WsHandlerFactory::registerClass('user', 'WsUser');
