<?php
/*
 * sitesettings.inc.php
 *
 * Sitewide settings
 * (put machine dependent stuff like hardwired paths, logins and passwords in inc/local.inc.php)
 *
 * (c) 2009-2016 daniel.burckhardt@sur-gmbh.ch
 *
 * Version: 2016-08-07 dbu
 *
 * Changes:
 *
 */

// General Settings
define('STRIP_SLASHES', get_magic_quotes_gpc());
define('SESSION_NAME', 'sid');

date_default_timezone_set('Europe/Berlin');

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
$RIGHTS_EDITOR = 0x02;  // these can see/edit all data
$RIGHTS_REFEREE = 0x10; // these can see but not edit
$RIGHTS_TRANSLATOR = 0x20; // these can see but not edit
$RIGHTS_ADMIN = 0x04;   // these can handle restricted system settings

define('STATUS_DELETED', -1); // reserved value in the database
define('STATUS_USER_DELETED', -100); // -1 stands for rejected

// settings for mails that are sent through php
// should wrap this into a mail/configuration class
define('MAIL_LINELENGTH', 72);

$SITE = array(
  'pagetitle' => 'Hamburg Key-Documents of German-Jewish History',
);

$COUNTRIES_FEATURED = array('DE', 'AT', 'CH', 'UK', 'US', 'CA',
                            'FR', 'IT', 'ES', 'NL', 'BE',
                            'DK', 'SE', 'NO', 'FI',
                            'AU', 'JP');
$GLOBALS['LANGUAGES_FEATURED'] = array('ger', 'eng', 'fre', 'ita', 'spa', 'heb', 'yid');

$GLOBALS['THESAURI'] =
    array(
          'section' => 'Section',
          'sourcetype' => 'Source Type',
          );


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
  'subject_prepend'      => 'Hamburg Key-Documents of German-Jewish History - ',
);

function compute_bytes ($val) {
    if (empty($val)) {
      return 0;
    }
    $val = trim($val);
    $last = strtolower($val[strlen($val)-1]);
    switch ($last) {
      // The 'G' modifier is available since PHP 5.1.0
      case 'g':
          $val *= 1024;
      case 'm':
          $val *= 1024;
      case 'k':
          $val *= 1024;
    }

    return $val;
}

if (!defined('UPLOAD_MAX_FILE_SIZE')) {
    define('UPLOAD_MAX_FILE_SIZE',
           compute_bytes(ini_get('upload_max_filesize')));
}

$MEDIA_EXTENSIONS = array(
                          'image/gif' => '.gif', 'image/jpeg' => '.jpg', 'image/png' => '.png',

                          'application/pdf' => '.pdf',

                          'audio/mpeg' => '.mp3',
                          'video/mp4' => '.mp4',

                          'text/rtf' => '.rtf',
                          'application/vnd.oasis.opendocument.text' => '.odt',
                          'application/msword' => '.doc',
                          'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => '.docx',

                          'application/xml' => '.xml',
                          );

$STATUS_REMOVED = '-1';
$STATUS_EDIT = '0';
$MESSAGE_STATUS = array($STATUS_EDIT => 'draft', '1' => 'publish', $STATUS_REMOVED => 'removed');

$STATUS_OPTIONS = array(
    '-99' => 'angedacht',
    '-76' => 'angefragt Autor',
    '-73' => 'vergeben Autor',
    '-69' => '1. Mahnung',
    '-68' => '2. Mahnung',
    '-67' => '3. Mahnung',
    '-66' => '4. Mahnung',
    '-59' => 'eingegangen Autor',
    '-55' => 'an Gutachter',
    '-53' => '&#220;berarbeitung Autor',
    '-49' => 'inhaltlich ok',
    '-45' => 'formal ok',
    '-39' => 'f&#252;r Auszeichnung bereit',
    '-35' => 'Auszeichnung erstellt',
    '-33' => 'Auszeichnung ok',
    '1'   => 'ver&#246;ffentlicht',
    '-100' => 'abgebrochen Redakteur',
    '-103' => 'abgebrochen bewahrende Institution',
    '-106' => 'abgebrochen Autor',
    '-112' => 'abgelehnt Artikel',
);

$STATUS_TRANSLATION_OPTIONS = array(
    '-29' => 'für Übersetzung bereit',
    '-25' => 'Übersetzung vergeben',
    '-24' => 'Übersetzung eingegangen',
    '-23' => 'Überarbeitung Übersetzung',
    '-15' => 'Übersetzung formal ok',
    '-9' => 'Übersetzung für Auszeichnung bereit',
    '-5' => 'Auszeichnung Übersetzung erstellt',
    '-3' => 'Auszeichnung Übersetzung ok',
    '31' => 'Übersetzung veröffentlicht',
);

$STATUS_SOURCE_OPTIONS = array(
    '-99' => 'angedacht',
    '-76' => 'angefragt bewahrende Institution',
    '-59' => 'eingegangen',
    '-39' => 'f&#252;r Auszeichnung bereit',
    '-35' => 'Auszeichnung erstellt',
    '-33' => 'Auszeichnung ok',
    '-32' => 'Lizenz geklärt',
    '1'   => 'ver&#246;ffentlicht',
    '-100' => 'abgebrochen Redakteur',
    '-103' => 'abgebrochen bewahrende Institution',
);

$LICENSE_OPTIONS = array(
    ''  => '-- unknown --',
    'restricted' => 'restricted (display only)',
    'regular' => 'regular (download for private and educational use)',
    'CC BY-NC-ND' => 'Creative Commons BY-NC-ND',
    'CC BY-SA' => 'Creative Commons BY-SA',
    'PD' => 'Public Domain',
);

// $MESSAGE_TYPES
$MESSAGE_REVIEW_PUBLICATION = 100;

$MESSAGE_ARTICLE = 200;

// MEDIA TYPES
$TYPE_MESSAGE = 0;
$TYPE_PERSON = 10;
$TYPE_PLACE = 20;
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
