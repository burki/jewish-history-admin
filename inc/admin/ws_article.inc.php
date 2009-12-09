<?php
/*
 * ws_article.inc.php
 *
 * Webservices for articles
 *
 * (c) 2009 daniel.burckhardt@sur-gmbh.ch
 *
 * Version: 2009-08-11 dbu
 *
 * Changes:
 *
 */

require_once LIB_PATH . 'UrnAllocation.inc.php';

class MyUrnAllocation extends UrnAllocation
{
  function dbConnection () {
    return new DB();
  }

  function dbFetchOne ($querystr) {
    $dbconn = $this->dbConnection();

    $dbconn->query($querystr);
    if ($dbconn->next_record()) {
      return $dbconn->Record;
    }
  }

  function dbExecute ($querystr) {
    $dbconn = $this->dbConnection();

    return $dbconn->query($querystr);
  }

}

class WsArticle extends WsHandler
{
  // example-call: http://localhost/docupedia/admin/admin_ws.php?pn=artcile&action=generateUrn&_debug=1&url=http://edoc1.cms.hu-berlin.de/Administration/urn/urn.php
  function buildResponse () {
    $valid_actions = array('generateUrn');

    $action = array_key_exists('action', $_GET)
      && in_array($_GET['action'], $valid_actions)
      ? $_GET['action'] : $valid_actions[0];
    $action_name = $action . 'Action';
    return $this->$action_name();
  }

  function generateUrnAction () {
    $entries = array();

    $status = 0;
    $response = array();

    $url = $this->getParameter('url');

    if (isset($url) && !empty($url)) {
      $urnAllocation = new MyUrnAllocation();
      $urn = $urnAllocation->allocate($url);
      if (FALSE === $urn) {
        $msg = 'urn allocation failed';
      }
      else {
        $status = 1;
        $msg = 'Success';
        $response = array('urn' => $urn);
      }
    }
    else
        $msg = 'The URL is empty';

    $response = array_merge(
        array('status' => $status, 'msg' => $msg), $response);

    return new JsonResponse($response);
  }
}

WsHandlerFactory::registerClass('article', 'WsArticle');
