<?php
/*
 * adminpage.inc.php
 *
 * Page class for admin-section
 *
 * (c) 2009-2018 daniel.burckhardt@sur-gmbh.ch
 *
 * Version: 2018-09-14 dbu
 *
 * Changes:
 *
 */

require_once INC_PATH . 'common/page.inc.php';

class AdminPage
extends Page
{
    protected $gettext_utf8_encode = false;
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
            'anonymous' => false,
        ],
        'pwd' => [
            'title' => 'Recover Password',
            'anonymous' => true,
        ],
        'author' => [
            'title' => 'Authors',
            'anonymous' => false,
        ],
        'article' => [
            'title' => 'Articles',
            'anonymous' => false,
        ],
        'publication' => [
            'title' => 'Sources',
            'anonymous' => false,
        ],
        'communication' => [
            'title' => 'Communication',
            'anonymous' => false,
        ],
        'publisher' => [
            'title' => 'Holding Institutions',
            'anonymous' => false,
        ],
        'person' => [
            'title' => 'Normdata: Persons',
            'anonymous' => false,
        ],
        'place' => [
            'title' => 'Normdata: Places',
            'anonymous' => false,
        ],
        'landmark' => [
            'title' => 'Normdata: Landmark',
            'anonymous' => false,
        ],
        'organization' => [
            'title' => 'Normdata: Organizations',
            'anonymous' => false,
        ],
        'event' => [
            'title' => 'Normdata: Event / Period',
            'anonymous' => false,
        ],
        'term' => [
            'title' => 'Term Sets',
            'anonymous' => false,
        ],
        'convert' => [
            'title' => 'TEI to HTML',
            'anonymous' => false,
        ],
        'zotero' => [
            'title' => 'Zotero Sync',
            'anonymous' => false,
        ],
        'account' => [
            'title' => 'Account',
            'anonymous' => false,
        ],
        'system_information' => [
            'title' => 'System Information',
            'anonymous' => false,
            'display' => false,
        ],
    ],
];
