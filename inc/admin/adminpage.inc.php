<?php
/*
 * adminpage.inc.php
 *
 * Page class for admin-section
 *
 * (c) 2009-2014 daniel.burckhardt@sur-gmbh.ch
 *
 * Version: 2014-06-30 dbu
 *
 * Changes:
 *
 */

require_once INC_PATH . 'common/page.inc.php';

class AdminPage extends Page
{
    protected $gettext_utf8_encode = FALSE;
    var $display = 'admin';

    function init ($pn) {
        $ret = parent::init($pn);

        // now get and set the view
        if (array_key_exists('view', $_GET) && 'xls' == $_GET['view']) {
          $this->display = 'xls';
        }

        return $ret;
    }

    function authenticate () {
        switch($this->name) {
            case 'pwd': // anonymous pages
                break;
            default:
                // access to logged in people
                if (empty($this->user))
                    $this->include = 'login';
        }
    }
}

$URL_REWRITE = array(); // don't do rewrites for the backend

$SITE_DESCRIPTION = array(
    'title' => 'Key-Documents of German-Jewish History',
    'structure' => array(
        'root' => array(
            'title' => 'Administration',
            'anonymous' => FALSE,
        ),
        'pwd' => array(
            'title' => 'Recover Password',
            'anonymous' => TRUE,
        ),
        'subscriber' => array(
            'title' => 'Authors',
            'anonymous' => FALSE,
        ),
        /*
        'review' => array(
            'title' => 'Book Reviews',
            'anonymous' => FALSE,
        ),
        */
        'publication' => array(
            'title' => 'Sources',
            'anonymous' => FALSE,
        ),
        'article' => array(
            'title' => 'Articles',
            'anonymous' => FALSE,
        ),
        'communication' => array(
            'title' => 'Communication',
            'anonymous' => FALSE,
        ),
        'publisher' => array(
            'title' => 'Holding Institutions',
            'anonymous' => FALSE,
        ),
        'term' => array(
            'title' => 'Wertelisten',
            'anonymous' => FALSE,
        ),
        'account' => array(
            'title' => 'Account',
            'anonymous' => FALSE,
        ),
        'system_information' => array(
            'title' => 'System Information',
            'anonymous' => FALSE,
            'display' => FALSE,
        ),
        /*
        'feed' => array(
            'title' => 'Feed Tagging',
            'anonymous' => FALSE,
        ),
        */
    )
);
