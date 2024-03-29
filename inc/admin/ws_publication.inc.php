<?php
/*
 * ws_publication.inc.php
 *
 * Webservices for managing publications (books)
 *
 * (c) 2007-2023 daniel.burckhardt@sur-gmbh.ch
 *
 * Version: 2023-04-20 dbu
 *
 * Changes:
 *
 */

class WsPublication
extends WsHandler
{
  // example-call: http://localhost/juedische-geschichte/admin_ws.php?pn=publication&action=fetchPublicationByIsbn&isbn=0444503285&_debug=1
  function buildResponse () {
    $valid_actions = [ 'fetchPublicationByIsbn', 'matchPublication' ];

    $action = array_key_exists('action', $_GET)
      && in_array($_GET['action'], $valid_actions)
      ? $_GET['action'] : $valid_actions[0];
    $action_name = $action . 'Action';

    return $this->$action_name();
  }

  function fetchPublicationByIsbnAction () {
    $status = 0;
    $response = [];

    $isbn = $this->getParameter('isbn');

    if (empty($isbn)) {
      $msg = 'The ISBN is empty';
    }
    else {
      require_once INC_PATH . 'common/biblioservice.inc.php';

      $valid = BiblioService::validateIsbn($isbn);
      if (!$valid) {
        $msg = 'Invalid ISBN';
        $status = -1;
      }
      else {
        $biblio_client = BiblioService::getInstance();
        $client_params = [ 'cache_external' => -1 ];
        $bibitem = $biblio_client->fetchByIsbn($isbn, $client_params);
        if (isset($bibitem)) {
          if (isset($bibitem['source']) && $bibitem['source'] == 'from_db') {
            $id_publication = $this->getParameter('id_publication');
            // requery from external if source_id equals id_publication
            if (isset($id_publication) && $id_publication == $bibitem['source_id']) {
              $client_params['from_db'] = false;
              $bibitem_external = $biblio_client->fetchByIsbn($isbn, $client_params);
              if (isset($bibitem_external)) {
                $bibitem = $bibitem_external;
              }
            }
          }
          $status = isset($bibitem['source']) && $bibitem['source'] == 'from_db' ? 2 : 1;
          $msg = 'Success';
          $response = &$bibitem;
        }
        else {
          $msg = 'Error in looking up ' . $isbn;
        }
      }
    }

    $response = array_merge([ 'status' => $status, 'msg' => $msg ], $response);

    return new JsonResponse($response);
  }

  function matchPublicationAction () {
    $entries = [];

    $search = $this->getParameter('fulltext');

    if (isset($search) && strlen($search) >= 2) {
      $dbconn = new DB;

      // build the query
      $words = split_quoted($search);
      $fields = [ 'title', 'subtitle', 'author', 'editor' ];

      for ($i = 0; $i < count($words); $i++) {
        $parts = [];

        /* if (IS_A_VALID_ISBN($words[$i])
          $parts[] = "isbn = '$normalized_isbn';
          else */

        for ($j = 0; $j < count($fields); $j++) {
          $parts[$j] = $fields[$j]
                     . sprintf(" REGEXP '%s'",
                               $dbconn->escape_string(MYSQL_REGEX_WORD_BEGIN . $words[$i]));
        }

        $words[$i] = '(' . implode(' OR ', $parts) . ')';
      }
      $querystr = sprintf("SELECT id, title, subtitle, author, editor FROM Publication WHERE status <> %d AND %s",
                          STATUS_DELETED, implode(' AND ', $words))
                . " ORDER BY CONCAT(IFNULL(author, ''), editor), title";
      $dbconn->query($querystr);

      while ($dbconn->next_record()) {
        $publication = (isset($dbconn->Record['author']) ? $dbconn->Record['author'] : $dbconn->Record['editor'])
                     . ': ' . $dbconn->Record['title'];
        $entries[] = ['id' => $dbconn->Record['id'], 'item' => $publication];
      }
    }

    return new AutocompleterResponse($entries);
  }
}

WsHandlerFactory::registerClass('publication', 'WsPublication');
