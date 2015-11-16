<?php
/*
 * ws_publication.inc.php
 *
 * Webservices for managing publications (books)
 *
 * (c) 2007-2015 daniel.burckhardt@sur-gmbh.ch
 *
 * Version: 2015-10-29 dbu
 *
 * Changes:
 *
 */

class WsPublication extends WsHandler
{
  // example-call: http://localhost/juedische-geschichte/admin_ws.php?pn=publication&action=fetchPublicationByIsbn&isbn=0444503285&_debug=1
  function buildResponse () {
    $valid_actions = array('fetchPublicationByIsbn', 'matchPublication');

    $action = array_key_exists('action', $_GET)
      && in_array($_GET['action'], $valid_actions)
      ? $_GET['action'] : $valid_actions[0];
    $action_name = $action.'Action';

    return $this->$action_name();
  }

  function fetchPublicationByIsbnAction () {
    $status = 0;
    $response = array();

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
        $client_params = array('cache_external' => -1);
        $bibitem = $biblio_client->fetchByIsbn($isbn, $client_params);
        if (isset($bibitem)) {
          if (isset($bibitem['source']) && $bibitem['source'] == 'from_db') {
            $id_publication = $this->getParameter('id_publication');
            // requery from external if source_id equals id_publication
            if (isset($id_publication) && $id_publication == $bibitem['source_id']) {
              $client_params['from_db'] = FALSE;
              $bibitem_external = $biblio_client->fetchByIsbn($isbn, $client_params);
              if (isset($bibitem_external))
                $bibitem = $bibitem_external;
            }
          }
          $status = isset($bibitem['source']) && $bibitem['source'] == 'from_db' ? 2 : 1;
          $msg = 'Success';
          $response = &$bibitem;
        }
        else
          $msg = 'Error in looking up ' . $isbn;
      }
    }

    $response = array_merge(
        array('status' => $status, 'msg' => $msg), $response);

    return new JsonResponse($response);
  }

  function matchPublicationAction () {
    $entries = array();

    $search = $this->getParameter('fulltext');

    if (isset($search) && strlen($search) >= 2) {
      $dbconn = new DB;

      // build the query
      $words = split_quoted($search);
      $fields = array('title', 'subtitle', 'author', 'editor');

      for ($i = 0; $i < count($words); $i++) {
        $parts = array();

        /* if (IS_A_VALID_ISBN($words[$i])
          $parts[] = "isbn = '$normalized_isbn';
          else */

        for ($j = 0; $j < count($fields); $j++)
          $parts[$j] = $fields[$j]
          .sprintf(" REGEXP '[[:<:]]%s'", $dbconn->escape_string($words[$i]));

        $words[$i] = '('.implode(' OR ', $parts).')';
      }
      $querystr = sprintf("SELECT id, title, subtitle, author, editor FROM Publication WHERE status >= 0 AND %s", implode(' AND ', $words))
        ." ORDER BY CONCAT(IFNULL(author, ''), editor), title";
      $dbconn->query($querystr);


      while ($dbconn->next_record()) {
        $publication = (isset($dbconn->Record['author']) ? $dbconn->Record['author'] : $dbconn->Record['editor'])
          .': '.$dbconn->Record['title'];
        $entries[] = array('id' => $dbconn->Record['id'], 'item' => $publication);
      }
    }
    return new AutocompleterResponse($entries);
  }
}

WsHandlerFactory::registerClass('publication', 'WsPublication');
