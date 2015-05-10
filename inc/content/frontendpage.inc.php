<?php
/*
 * frontendpage.inc.php
 *
 * Page class for frontend-section
 *
 * (c) 2009-2015 daniel.burckhardt@sur-gmbh.ch
 *
 * Version: 2015-03-24 dbu
 *
 * Changes:
 *
 */

require_once INC_PATH . 'common/page.inc.php';

class FrontendPage extends Page
{
    protected $gettext_utf8_encode = FALSE;
    var $display = 'frontend';
}

$URL_REWRITE = array(); // don't do rewrites for the backend

$SITE_DESCRIPTION = array(
    'title' => 'Online Quellenedition',
    'structure' => array(
        'home' => array(
            'title' => 'Home',
            'anonymous' => TRUE,
        ),
        'pwd' => array(
            'title' => 'Recover Password',
            'anonymous' => TRUE,
        ),
    )
);
