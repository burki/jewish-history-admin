<?php
/*
 * sitesettings.inc.php
 *
 * Sitewide settings
 * (put machine dependent stuff like hardwired paths, logins and passwords in inc/local.inc.php)
 *
 * (c) 2009-2014 daniel.burckhardt@sur-gmbh.ch
 *
 * Version: 2014-06-22 dbu
 *
 * Changes:
 *
 */

// General Settings
define('STRIP_SLASHES', get_magic_quotes_gpc());
define('SESSION_NAME', 'sid');

/* CRYPT_PWD stores the length of the salt for encryption which decides which method is used
   CRYPT_PWD = 0 means no encryption takes place */
if (defined('CRYPT_MD5')) {
    define('CRYPT_PWD', 12);
}
else {
    define('CRYPT_PWD', defined('CRYPT_STD_DES') ? 2 : 0);
}

define('PWDRECOVER_TIMEOUT', 300);       // wait 5 minutes before sending a new recover-mail

define('LOCALE_DEFAULT', 'en_US'); // so strtoupper works correct - if you don't want to use it, set it to 0;
define('GETTEXT_AVAILABLE', FALSE); // don't use system-gettext

//
define('COUNTRIES_FROM_DB', FALSE);

//
$RIGHTS_EDITOR = 0x02;  // these see all data
$RIGHTS_REFEREE = 0x10; // referees (currently no login, might change later)
$RIGHTS_ADMIN = 0x04;   // these can handle restricted system settings

define('STATUS_DELETED', -1); // reserved value in the database

// settings for mails that are sent through php
// should wrap this into a mail/configuration class
define('MAIL_LINELENGTH', 72);

$SITE = array(
  'pagetitle' => 'Key-Documents of German-Jewish History',
);

$COUNTRIES_FEATURED = array('DE', 'AT', 'CH', 'UK', 'US', 'CA',
                            'FR', 'IT', 'ES', 'NL', 'BE',
                            'DK', 'SE', 'NO', 'FI',
                            'AU', 'JP');

$MAIL_SETTINGS = array(
  'from'                 => 'burckhardtd@geschichte.hu-berlin.de',
  // 'from_communication'   => 'burckhardtd@geschichte.hu-berlin.de', // don't use users from for communication
  'reply_to'             => 'burckhardtd@geschichte.hu-berlin.de',
  'assistance'           => 'burckhardtd@geschichte.hu-berlin.de',
 // 'return_path'          => 'daniel.burckhardt@sur-gmbh.ch',

 /*
  'from_listserv'        => 'subscription@arthist.net',
  'listserv'             => 'listserv@h-net.msu.edu', */
  // 'bcc_listserv'         => 'daniel.burckhardt@sur-gmbh.ch',

  'bcc_passwordrecover'  => 'burckhardtd@geschichte.hu-berlin.de',
  'technical_assistance' => 'burckhardtd@geschichte.hu-berlin.de',

  // notification
  'change_notify'        => 'burckhardtd@geschichte.hu-berlin.de',
  'bcc_change_notify'    => 'burckhardtd@geschichte.hu-berlin.de',

  // further stuff
  'subject_prepend'      => 'Key-Documents of German-Jewish History - ',
);

$MEDIA_EXTENSIONS = array('image/gif' => '.gif', 'image/jpeg' => '.jpg', 'image/png' => '.png',
                          'application/pdf' => '.pdf',
                          );

$STATUS_REMOVED = '-1';
$STATUS_EDIT = '0';
$MESSAGE_STATUS = array($STATUS_EDIT => 'draft', '1' => 'publish', $STATUS_REMOVED => 'removed');

// $MESSAGE_TYPES
$MESSAGE_REVIEW_PUBLICATION = 100;

$MESSAGE_ARTICLE = 200;

// MEDIA TYPES
$TYPE_MESSAGE = 0;
$TYPE_PUBLICATION = 50;

$UPLOAD_TRANSLATE = array(
    $TYPE_MESSAGE => 'upload', $TYPE_PUBLICATION => 'publication',
);

$JAVASCRIPT_CONFIRMDELETE = <<<EOT
    function confirmDelete(txt, url) {
      if (confirm(txt)) {
        window.location.href = url;
      }
    }
EOT;
