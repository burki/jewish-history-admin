<?php
/*
 * admin_root.inc.php
 *
 * Start page
 *
 * (c) 2010-2014 daniel.burckhardt@sur-gmbh.ch
 *
 * Version: 2014-06-17 dbu
 *
 * Changes:
 *
 */


class DisplayRoot extends PageDisplay
{
  function buildTasklist () {
    return ''; // '<p>There are no current tasks.</p>';
  }

  function buildActions () {
    global $RIGHTS_ADMIN;

    $actions = array(
      array(
        'name' => 'article',
        'title' => 'Articles'
      ),
      array(
        'name' => 'subscriber',
        'title' => 'Authors'
      ),
      'communication' => array(
        'name' => 'communication',
        'title' => 'Communication',
      ),
      /*
      'feed' => array(
        'name' => 'feed',
        'title' => 'Feed Tagging',
      ),
      array(
        'name' => 'publication',
        'title' => 'Books'
      ),
      array(
        'name' => 'publisher',
        'title' => 'Publishers'
      ),
      array(
        'name' => 'convert',
        'title' => 'Convert Quotes'
      ),
      */
      array(
        'name' => 'account',
        'title' => 'Accounts',
        'privs' => $RIGHTS_ADMIN,
      ),
    );

    $ret = '';
    foreach ($actions as $action) {
      if (!isset($action['privs']) || 0 != ($action['privs'] & $this->page->user['privs'])) {
        if (empty($ret))
          $ret = '<ul>';
        $url = isset($action['url']) ? $action['url'] : $this->page->buildLink(array('pn' => $action['name']));
        $ret .= '<li><a href="'.$url.'">'.$this->formatText(tr($action['title'])).'</a></li>';
      }
    }
    if (!empty($ret))
      $ret .= '</ul>';

    return $ret;
  }

  function buildWorkplaceInternal () {
    return $this->buildTasklist()
           . $this->buildActions();
  }

  function buildWorkplaceExternal () {
    global $RIGHTS_REFEREE;

    if (0 != ($this->page->user['privs'] & $RIGHTS_REFEREE)) {
      return 'TODO: Gutachterliste';
    }
    return '';
  }

  function buildContent () {
    global $RIGHTS_ADMIN;

    return $this->is_internal || 0 != ($this->page->user['privs'] & $RIGHTS_ADMIN)
      ? $this->buildWorkplaceInternal()
      : $this->buildWorkplaceExternal();
  } // buildContent

}

$page->setDisplay(new DisplayRoot($page));
