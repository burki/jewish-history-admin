<?php
/*
 * adminpage.inc.php
 *
 * Page class for admin-section
 *
 * (c) 2009-2018 daniel.burckhardt@sur-gmbh.ch
 *
 * Version: 2018-02-20 dbu
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
        switch ($this->name) {
            case 'pwd': // anonymous pages
                break;
            default:
                // access to logged in people
                if (empty($this->user))
                    $this->include = 'login';
        }
    }

    function isAdminUser () {
        return isset($this->user['privs'])
            && 0 != ($this->user['privs'] & $GLOBALS['RIGHTS_ADMIN']);
    }

}

$URL_REWRITE = []; // don't do rewrites for the backend

$SITE_DESCRIPTION = [
    'title' => 'Hamburg Key-Documents of German-Jewish History',
    'structure' => [
        'root' => [
            'title' => 'Administration',
            'anonymous' => FALSE,
        ],
        'pwd' => [
            'title' => 'Recover Password',
            'anonymous' => TRUE,
        ],
        'author' => [
            'title' => 'Authors',
            'anonymous' => FALSE,
        ],
        'article' => [
            'title' => 'Articles',
            'anonymous' => FALSE,
        ],
        'publication' => [
            'title' => 'Sources',
            'anonymous' => FALSE,
        ],
        'communication' => [
            'title' => 'Communication',
            'anonymous' => FALSE,
        ],
        'publisher' => [
            'title' => 'Holding Institutions',
            'anonymous' => FALSE,
        ],
        'person' => [
            'title' => 'Normdata: Persons',
            'anonymous' => FALSE,
        ],
        'place' => [
            'title' => 'Normdata: Places',
            'anonymous' => FALSE,
        ],
        'organization' => [
            'title' => 'Normdata: Organizations',
            'anonymous' => FALSE,
        ],
        'event' => [
            'title' => 'Normdata: Event / Period',
            'anonymous' => FALSE,
        ],
        'term' => [
            'title' => 'Wertelisten',
            'anonymous' => FALSE,
        ],
        'convert' => [
            'title' => 'TEI nach HTML',
            'anonymous' => FALSE,
        ],
        'zotero' => [
            'title' => 'Zotero Sync',
            'anonymous' => FALSE,
        ],
        'account' => [
            'title' => 'Account',
            'anonymous' => FALSE,
        ],
        'system_information' => [
            'title' => 'System Information',
            'anonymous' => FALSE,
            'display' => FALSE,
        ],
    ],
];
