<?php
/*
 * ws_article.inc.php
 *
 * Webservices for articles
 *
 * (c) 2009-2014 daniel.burckhardt@sur-gmbh.ch
 *
 * Version: 2014-10-29 dbu
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
  // example-call: http://localhost/juedische-geschichte/admin/admin_ws.php?pn=article&action=generateUrn&_debug=1&url=http://edoc1.cms.hu-berlin.de/Administration/urn/urn.php
  function buildResponse () {
    $valid_actions = array('generateSlug', 'generateUrn');

    $action = array_key_exists('action', $_GET)
      && in_array($_GET['action'], $valid_actions)
      ? $_GET['action'] : $valid_actions[0];
    $action_name = $action . 'Action';

    return $this->$action_name();
  }

  function generateSlugAction () {
    $title = $this->getParameter('title');

    if (isset($title) && !empty($title)) {
      $slugify = new \Cocur\Slugify\Slugify();
      $title_slug = $slugify->slugify($title, '_');
      if (FALSE === $title_slug) {
        $msg = 'slugify failed';
      }
      else {
        $user_id = $this->getParameter('user_id');
        if (!empty($user_id) && intval($user_id) >= 0) {
          require_once INC_PATH . 'common/page.inc.php';
          $dbconn = new DB;

          $page = new Page($dbconn);
          $user = $page->findUserById(intval($user_id));
          if (isset($user) && !empty($user['lastname'])) {
            $user_slug = $slugify->slugify($user['lastname'], '_');
            if (FALSE !== $user_slug) {
              $title_slug = join('-', array($user_slug, $title_slug));
            }
          }
        }
        $status = 1;
        $msg = 'Success';
        $response = array('title_slug' => $title_slug);
      }
    }
    else {
      $msg = 'The title is empty';
    }

    $response = array_merge(array('status' => $status, 'msg' => $msg),
                            $response);

    return new JsonResponse($response);
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
    else {
      $msg = 'The URL is empty';
    }

    $response = array_merge(
        array('status' => $status, 'msg' => $msg), $response);

    return new JsonResponse($response);
  }
}

WsHandlerFactory::registerClass('article', 'WsArticle');
