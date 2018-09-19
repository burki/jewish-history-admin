<?php
/*
 * ws_article.inc.php
 *
 * Webservices for articles
 *
 * (c) 2009-2018 daniel.burckhardt@sur-gmbh.ch
 *
 * Version: 2018-08-24 dbu
 *
 * Changes:
 *
 */

class WsArticle
extends WsHandler
{
  // example-call: http://localhost/juedische-geschichte/admin/admin_ws.php?pn=article&action=generateSlug&_debug=1
  function buildResponse () {
    $valid_actions = [ 'generateSlug' ];

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
      if (false === $title_slug) {
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
            if (false !== $user_slug) {
              $title_slug = join('-', [ $user_slug, $title_slug ]);
            }
          }
        }
        $status = 1;
        $msg = 'Success';
        $response = [ 'title_slug' => $title_slug ];
      }
    }
    else {
      $msg = 'The title is empty';
    }

    $response = array_merge([ 'status' => $status, 'msg' => $msg ],
                            $response);

    return new JsonResponse($response);
  }
}

WsHandlerFactory::registerClass('article', 'WsArticle');
