<?php
/*
 * page.inc.php
 *
 * Functions to initialize the page (browser, session, login-stuff, ...)
 *
 * (c) 2009-2015 daniel.burckhardt@sur-gmbh.ch
 *
 * Version: 2015-02-13 dbu
 *
 * Changes:
 *
 */

// text translation
function tr($msg) {
  static $method = -1; // 0: custom, 1: gettext
  if ($method == -1) {
    // determine method
    $method = (defined('GETTEXT_AVAILABLE') && !GETTEXT_AVAILABLE) || !function_exists('gettext') ? 0 : 1;
  }
  if ($method) {
    return gettext($msg);
  }
  return Page::gettext($msg);
}

class Page
{
  static $languages = array('de_DE' => 'deutsch', 'en_US' => 'english');
  static $lang = 'en_US';
  static $locale = NULL;
  private static $init_lang = NULL;

  protected $gettext_utf8_encode = FALSE;
  var $name;
  var $include;
  var $user = array();
  var $login_preset;
  var $path;
  var $parameters;
  var $renderer;
  var $SERVER_NAME;
  var $SERVER_PORT;
  var $BASE_PATH = './';
  var $BASE_URL;
  var $STRIP_SLASHES;
  var $use_session = FALSE;
  var $use_session_register = FALSE;
  var $dbconn;
  var $expired = FALSE;
  var $site_description;
  var $msg = '';

  static function gettext ($msg) {
    global $GETTEXT_MESSAGES;

    return isset($GETTEXT_MESSAGES[$msg]) ? $GETTEXT_MESSAGES[$msg] : $msg;
  }

  static function initGettext ($utf8_encode = FALSE) {
    if (self::$lang == self::$init_lang) {
      return TRUE;
    }

    // init-locale
    require_once 'Zend/Locale.php';
    self::$locale = new Zend_Locale(self::$lang);
    Zend_Locale::setDefault(self::$lang); // not sure yet if this is also needed

    // set it into the registry for date-formatting
    require_once 'Zend/Registry.php';
    Zend_Registry::set('Zend_Locale', self::$locale);

    if ((defined('GETTEXT_AVAILABLE') && !GETTEXT_AVAILABLE) || !function_exists('gettext')) {
      // use our custom method
      if (file_exists(INC_PATH . 'messages/' . self::$lang . '.inc.php')) {
        global $GETTEXT_MESSAGES;
        require_once INC_PATH . 'messages/' . self::$lang . '.inc.php';
        if ($utf8_encode) {
          foreach ($GETTEXT_MESSAGES as $key => $msg) {
            $GETTEXT_MESSAGES[$key] = utf8_encode($msg);
          }
        }
      }
    }
    else {
      setlocale(LC_ALL, self::$lang);

      // Set the text domain as 'messages'
      $domain = 'messages';
      bindtextdomain($domain, GETTEXT_PATH);
      textdomain($domain);
    }
  }

  function __construct ($dbconn, $site_description = '') {
    $this->dbconn = $dbconn;
    $this->STRIP_SLASHES = defined('STRIP_SLASHES') ? STRIP_SLASHES : get_magic_quotes_gpc();
    if (!empty($site_description)) {
      $this->site_description = $site_description;
    }

    $this->SERVER_NAME = defined('SERVER_NAME') ? SERVER_NAME : $_SERVER['SERVER_NAME'];
    $this->SERVER_PORT = defined('SERVER_PORT') ? SERVER_PORT : $_SERVER['SERVER_PORT'];
    $this->BASE_PATH = defined('BASE_PATH') ? BASE_PATH : preg_replace('/[^\/]*$/', '', $_SERVER['PHP_SELF']);
    $this->BASE_URL =
      ($this->SERVER_PORT == '443' ? 'https' : 'http') // Protocoll
      . '://' . $this->SERVER_NAME . ($this->SERVER_PORT != '80' ? ':' . $this->SERVER_PORT : '')
      . $this->BASE_PATH;

    $this->PHP_SELF = $_SERVER['PHP_SELF'];
    $this->URL_SELF = ($this->SERVER_PORT == '443' ? 'https' : 'http') // Protocoll
      . '://' . $this->SERVER_NAME . ($this->SERVER_PORT != '80' ? ':' . $this->SERVER_PORT : '')
      . $this->PHP_SELF;
  }

  function lang () {
    return self::$lang;
  }

  function expire ($when = 0, $cache = 0) {
    static $expired = FALSE;

    if ($expired || headers_sent()) {
      return;
    }

    if ($when == 0) {
      header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');    // Date in the past
    }
    else {
      header('Expires: ' . gmdate('D, d M Y H:i:s', $when) . ' GMT');
    }
    header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');  // always modified
    if (!$cache) {
      header('Cache-Control: no-cache, must-revalidate');  // HTTP/1.1
      header('Cache-Control: post-check=0, pre-check=0');  // to make back-button work on IE for post-pages
      header('Pragma: no-cache');                          // HTTP/1.0
    }
    else {
      header('Pragma: cache');                          // HTTP/1.0
    }
    $expired = TRUE;
  }

  function identify () {
    global $AUTH_METHODS;
    if (empty($_SESSION['user'])) {
      foreach ($AUTH_METHODS as $method => $value) {
        // echo "Trying $method $value";
        $done = FALSE;
        switch ($method) {
          case 'AUTH_LOCAL':
            $status = $this->processLoginAuthLocal();
            if ($status < 0) {
              $this->msg = tr('Sorry, the e-mail or password you entered is incorrect. Please try again.');
            }
            $done = TRUE;
            break;               // failed - go to next method
          case 'AUTH_FORCE':
            $this->setLogin($value);
            $done = TRUE;
            break;
          case 'AUTH_ANONYMOUS':
            $done = TRUE;
            break;
        }
        if ($done) {
          break;
        }
      } // foreach
    }
    else if (isset($AUTH_METHODS['AUTH_LOCAL']) && array_key_exists('do_logout', $_GET) && $_GET['do_logout']) {
      $this->clearLogin();
    }

    // we need to have a user for certain pages
    if (!empty($_SESSION['user'])) {
      $this->user = $_SESSION['user'];
    }
  }

  function determineLang () {
    // set a new language
    if (isset($_REQUEST['lang']) && isset(self::$languages[$_REQUEST['lang']])) {
      self::$lang = $_SESSION['lang'] = $_REQUEST['lang'];
    }
    else {
      if (isset($_SESSION['lang']) && isset(self::$languages[$_SESSION['lang']])) {
        self::$lang = $_SESSION['lang'];
      }
      else {
        // default to first available language
        reset(self::$languages); list(self::$lang, $dummy) = each(self::$languages);

        if (FALSE) {
          // try to get the language from user settings (cookie oder prefs from database
        }
        else if (array_key_exists('HTTP_ACCEPT_LANGUAGE', $_SERVER)) {
          // TODO: get default language either from browser settings
          $language_prefs = $_SERVER['HTTP_ACCEPT_LANGUAGE'];
          if (!empty($language_prefs)) {
            $available_languages = array_keys(self::$languages);
//          de-ch,de-de;q=0.8,de;q=0.6,en-us;q=0.4,en;q=0.2
            $languages = explode(',', $language_prefs);
            for ($i = 0; $i < count($languages); $i++) {
              $language = preg_replace('/\;.*$/', '', $languages[$i]);
              $language = preg_replace_callback('/\-(.+)/',
                                                function ($matches) {
                                                  return '_' . strtoupper($matches[1]);
                                                },
                                                $language);
              if (in_array($language, $available_languages)) {
                self::$lang = $language;
                break;
              }
              else {
                $language = preg_replace('/(\_.+)/', '', $language);
                $language_expression = '/^' . preg_quote($language) . '/';
                for ($j = 0; $j < count($available_languages); $j++) {
                  if (preg_match($language_expression, $available_languages[$j])) {
                    self::$lang = $available_languages[$j];
                    break 2;
                  }
                }
              }
            }
          }
        }
      }
    }
    if (!empty(self::$lang)) {
      self::initGettext($this->gettext_utf8_encode);
    }
  }

  function determinePage ($pn) {
    if (isset($this->site_description) && isset($this->site_description['structure'])) {
      $path = array();
      foreach ($this->site_description['structure'] as $name => $descr) {
        if (count($path) == 0) {
          $path[] = $name;
        }
        if ($name == $pn) {
          $this->name = $name;
          if ($name != $path[0]) {
            $path[] = $name;
          }
          break;
        }
      }
      if (empty($this->name)) {
        $this->name = $path[0];
      }
      $this->path = $path;
    }
    else {
      $this->name = 'root';
      $this->path = array($this->name);
    }

    if (!isset($this->include)) {
      $this->include = $this->name;
    }
  }

  function authenticate () {
    /* $anonymous_pages = array(); // TODO: determine $anonymous_pages
    if (!in_array($this->name, $anonymous_pages)) {
      $this->include = 'login';
    } */
  }

  function setParameters () {
    $parameters = array();
    foreach ($_GET as $name => $value) {
      if ($name != 'logout' && $name != 'pn' && $name != 'frame') {
        $parameters[$name] = $value;
      }
    }
    if ($this->STRIP_SLASHES) {
      $parameters = array_map('stripslashes', $parameters);
    }

    $this->parameters = &$parameters;
  }

  function init ($pn) {
    global $COOKIE_EXPIRE;

    if (defined('SESSION_NAME')) {
      // for ie 6.x to take cookies
      // see also http://msdn.microsoft.com/library/default.asp?url=/library/en-us/dnpriv/html/ie6privacyfeature.asp
      header('P3P: CP="NOI NID ADMa OUR IND UNI COM NAV"');

      session_name(SESSION_NAME);         // set the session name
      session_start();                    // and start it
      session_cache_limiter('private');

      $this->use_session = TRUE;
      if (1 == ini_get('register_globals')) {
        $this->use_session_register = TRUE;
      }
    }

    $this->expire();

    $this->identify();

    $this->determineLang();

    if (defined('LOCALE_DEFAULT')) {
      // TODO: we might override this in the future with settings that depend on
      // $this->lang and $this->user
      setlocale(LC_CTYPE, LOCALE_DEFAULT);
    }

    $this->determinePage($pn);

    // with user and page set, we can authenticate
    // state: allow / login / deny
    $this->authenticate();

    $this->setParameters();
  } // init

  function findUserById ($id, $additional_fields = array()) {
    if (empty($id)) {
      // immediately return on empty $id
      return;
    }

    $dbconn = isset($this->dbconn) ? $this->dbconn : new DB();

    if (!empty(self::$USER_TABLE_ADDITIONAL)) {
      $additional_fields = array_merge(self::$USER_TABLE_ADDITIONAL, $additional_fields);
    }

    $querystr = sprintf("SELECT id, firstname, lastname, email, privs%s FROM User WHERE id=%d",
                        !empty($additional_fields) ? ', ' . join(', ', $additional_fields) : '',
                        intval($id));
    $dbconn->query($querystr);
    if ($dbconn->next_record()) {
      return $dbconn->Record;
    }
  }


  function processLoginAuthLocal () {
    global $RIGHTS_EDITOR;

    $login = $this->getPostValue('_login_field');
    if (empty($login)) {
      return 0; // empty login
    }

    $dbconn = isset($this->dbconn) ? $this->dbconn : new DB();

    $querystr = sprintf(
      "SELECT id, email AS login, email, pwd, lastname, firstname, privs FROM User WHERE status <> %d AND LOWER(email)=LOWER('%s') ORDER BY privs & $RIGHTS_EDITOR DESC, status DESC",
      STATUS_DELETED,
      $dbconn->escape_string($login)
    );
    $dbconn->query($querystr);
    $found = $dbconn->next_record();

    if ($found) {
      // user exists -> check password for every matching login
      $pwd = $this->getPostValue('_pwd');

      $success = FALSE;
      while(!$success) {
        $login = $dbconn->Record['login'];
        if ($this->passwordCheck($pwd, $dbconn->Record['pwd'])) {
          $success = TRUE;
          $this->setLogin($dbconn->Record['id']);
          return 1;
        }
        if (!$dbconn->next_record()) {
          break;
        }
      }
      if (!$success) {
        return -2; // wrong password
      }
    }

    return -1; // wrong login
  }

  function setLogin ($id) {
    $success = FALSE;

    $dbconn = isset($this->dbconn) ? $this->dbconn : new DB();
    $querystr = "SELECT id, email AS login, email AS email, privs FROM User WHERE id=".intval($id);
    $dbconn->query($querystr);
    if ($dbconn->next_record()) {
      $_SESSION['user'] = $dbconn->Record;
      $success = TRUE;
    }
    return $success;
  }

  function clearLogin () {
    $this->user = array();
    unset($_SESSION['user']);
  }

  function getLoginLocation ($issue = -1) {
    $location_ids = array();

    // this should probably be part of a user object later on
    $dbconn = isset($this->dbconn) ? $this->dbconn : new DB();

    if (isset($_SESSION['user']) && intval($_SESSION['user']['id']) > 0) {
      $querystr = sprintf("SELECT DISTINCT id_location FROM Subscription, Location WHERE Subscription.id_login=%d AND Subscription.id_location=Location.id AND Location.status >= 0 ORDER BY IFNULL(sortoverride, name)",
                          intval($_SESSION['user']['id']));
      $dbconn->query($querystr);
      while ($dbconn->next_record()) {
        $location_ids[] = $dbconn->Record['id_location'];
      }
    }
    if (count($location_ids) == 0) {
      return;
    }

    $locations = array();
    foreach ($location_ids as $id_location) {
      $querystr = "SELECT name, IssueLocation.flags AS flags, issue FROM IssueLocation, Location WHERE id_location=$id_location AND id_location=Location.id";
      if ($issue > 0) {
        $querystr .= " AND issue=$issue";
      }
      else {
        $querystr .= " ORDER BY issue DESC";
      }
      $dbconn->query($querystr);
      $issue = -1; $flags = 0; $name = '';
      while($dbconn->next_record() && (-1 == $issue || $issue == $dbconn->Record['issue'])) {
        $issue = $dbconn->Record['issue'];
        $name = $dbconn->Record['name'];
        $flags |= $dbconn->Record['flags']; // TODO: koennte man vermutlich auch über SUM(flags) GROUP BY id_location/issue hinkriegen
      }
      $locations[] = array('id' => $id_location, 'name' => $name, 'flags' => $flags, 'issue' => $issue);
    }

    return count($locations) == 1 ? $locations[0] : $locations;
  }

  function getPostValue ($key) {
    if (!isset($_POST[$key]))
      return;

    $val = $_POST[$key];
    if ($this->STRIP_SLASHES)
      $val = is_array($val) ? array_map('stripslashes', $val) : stripslashes($val);

    return $val;
  }

  function getRequestValue ($key, $persist_session = false) {
    if (!isset($_REQUEST[$key])) {
      if ($persist_session)
        return $this->getSessionValue($key);

      return;
    }

    $val = $_REQUEST[$key];
    if ($this->STRIP_SLASHES) {
      $val = is_array($val) ? array_map('stripslashes', $val) : stripslashes($val);
    }

    if ($persist_session) {
      $this->setSessionValue($key, $val);
    }

    return $val;
  }

  function getSessionValue ($name, $thispage_only = true) {
    static $PREPEND = '_';

    $key = $thispage_only ? $PREPEND . $this->name : $PREPEND . $name;
    if (!isset($_SESSION[$key])) {
      return;
    }

    return $thispage_only
      ? (isset($_SESSION[$key][$name]) ? $_SESSION[$key][$name] : NULL)
      : (isset($_SESSION[$key]) ? $_SESSION[$key]: NULL);
  }

  function setSessionValue ($name, $value, $thispage_only = true) {
    static $PREPEND = '_';

    $key = $thispage_only ? $PREPEND . $this->name : $PREPEND . $name;

    if ($this->use_session_register && !session_is_registered($key)) {
      session_register($key);
    }

    if ($thispage_only) {
      $_SESSION[$key][$name] = $value;
    }
    else {
      $_SESSION[$key] = $value;
    }

    return $value;
  }

  function passwordValid ($pwd, $pwd_confirm) {
    if (strlen($pwd) < 6) {
      return -1;
    }
    if ($pwd != $pwd_confirm) {
      return -2;
    }
    return 1;
  }

  function passwordCrypt ($pwd_plain) {
    $pwd_crypted = $pwd_plain;          // start with plain-text

    // now try to do something better
    if (defined('CRYPT_PWD') && CRYPT_PWD > 0) {
      $salt = md5(uniqid(rand()));     // generate a salt
      if (CRYPT_PWD == 2 || CRYPT_PWD == 12) {
        $salt = CRYPT_PWD == 12
          ? '$1$' . substr($salt, 0, 8) . '$' // MD5 encryption with a twelve character salt starting with $1$
          : substr($salt, 0, 2);          // Standard DES encryption with a 2-char SALT

        $pwd_crypted = crypt($pwd_plain, $salt);
      }
      else
        die('crypt_password: further crypt-methods not implemented yet');
    }

    return $pwd_crypted;
  }

  function passwordCheck ($pwd_plain, $pwd_encoded) {
    if (!isset($pwd_encoded)) {
      return 0;
    }

    if (defined('CRYPT_PWD') && CRYPT_PWD > 0) {
      $salt = substr($pwd_encoded, 0, CRYPT_PWD);
      return crypt($pwd_plain, $salt) == $pwd_encoded;
    }

    return $pwd_plain == $pwd_encoded; // no encryption
  }

  function buildPageTitle ($name) {
    $title = $name;

    if (isset($this->site_description) && isset($this->site_description['structure'])) {
      if (isset($this->site_description['structure'][$name]['title'])) {
        $title = tr($this->site_description['structure'][$name]['title']);
      }
    }

    return $title;
  }

  function title () {
    $titles = array();
    if (isset($this->site_description) && isset($this->site_description['title'])) {
      $titles[] = tr($this->site_description['title']);
    }
    if (isset($this->path)) {
      $ignore = count($this->path) > 1 ? $this->path[0] : NULL;
      foreach ($this->path as $entry) {
        if (!isset($ignore) || $ignore != $entry) {
          $titles[] = $this->buildPageTitle($entry);
        }
      }
    }

    return implode(' / ', $titles);
  }

  function setDisplay ($renderer) {
    $this->renderer = $renderer;
  }

  function display () {
    if (isset($this->renderer)) {
      $res = $this->renderer->show();
      if (FALSE === $res) {
        $this->redirect();
      }
    }
    else {
      echo 'no renderer set';
    }
  }

  function buildLink ($options = '') {
    global $URL_REWRITE;

    $optstring = $anchor = '';
    $prepend = array(); // e.g. mode/cat go directly after 'pn' with no key
    $append = array(); // e.g. id goes last with no key
    $skip = array(); // marks options to skip
    $base = $this->BASE_PATH; // may be overridden through $URL_REWRITE['host'];

    if (isset($options)) {
      if (gettype($options) == 'string' && $options != '') { // split get-options into ass. array
        // TODO: ignore a possible leading ?
        $args = split('&', $options);
        $options = array();
        for ($i=0; $i < count($args); $i++) {
          $keyval = split('=', $args[$i], 2);
          $options[$keyval[0]] = count($keyval) == 2 ? $keyval[1] : '';
        }
      }

      if (gettype($options) == 'array') {
        if (isset($options['pn']) && '' === $options['pn']) // 'pn' => '' shortcut for homepage
          $options['pn'] = 'root';

        foreach ($options as $key => $val) {
          if (gettype($val) == 'string' && empty($val))
            continue;
          if ('anchor' == $key) {
            $anchor = '#' . $val;
          }
          else if ('pn' == $key && isset($URL_REWRITE[$val])) {
            if (gettype($URL_REWRITE[$val]) == 'string') {
              $rewrite = $URL_REWRITE[$val];
              if (preg_match('/^http(s?)\:/', $rewrite))
                $base = '';
            }
            else {
              $rewrite = $val;
              if (isset($URL_REWRITE['host'])){
                $base = $URL_REWRITE['host'] . '/';
              }
              if ('array' == gettype($URL_REWRITE[$val])) {
                foreach (array('prepend', 'append') as $mode) {
                  if (isset($URL_REWRITE[$val][$mode])) {
                    $keys = $URL_REWRITE[$val][$mode];
                    if ('array' != gettype($keys)) {
                      $keys = array($keys);
                    }
                    foreach ($keys as $name) {
                      if (isset($options[$name])) {
                        if ('prepend' == $mode) {
                          $prepend[] = rawurlencode($options[$name]);
                        }
                        else {
                          $append[] = rawurlencode($options[$name]);
                        }
                        $skip[$name] = TRUE;
                      }
                    }
                  }
                }
              }
            }
          }
          else if (!isset($skip[$key])) {
            $optstring = ($optstring == '' ? '' : $optstring . '&')
                       . $key . '=' . rawurlencode($val);
          }
        }

      }
    }
    if (isset($rewrite)) {
      $separator = '/';
      if (preg_match('/\?/', $rewrite)) {
        // TODO: this breaks together with $append
        $separator = !empty($opstring) ? '&' : '';
      }
      return $base
        . ($rewrite != '.' ? $rewrite.$separator : '')
        . (count($prepend) > 0 ? implode('/', $prepend).(!empty($optstring) ? '/' : '') : '')
        . ($rewrite == '.' && !empty($optstring) ? '?' : '')
        . $optstring
        . (count($append) > 0
           ? (count($prepend) > 0 || !empty($optstring) ? '/'
              : '') . implode('/', $append)
           : '')
        . $anchor;
    }

    return $this->PHP_SELF
        . ($optstring != '' ? '?' . $optstring : '')
        . $anchor;
  } // buildLink()

  function buildLinkFull ($options = '') {
    $prot = $this->SERVER_PORT == '443' ? 'https' : 'http';
    return $prot . '://' . $this->SERVER_NAME
         . ($this->SERVER_PORT != '80' ? ':' . $this->SERVER_PORT : '')
         . $this->buildLink($options);
  }

  //------------------------------------------------------------
  // void redirect ($options = '')
  // sends HTTP-Redirect-Header to another page in the site
  //------------------------------------------------------------
  function redirect ($options = '', $delay = 0, $base_url = '') {
    if (gettype($options) == 'string' && preg_match('/^(http|https|ftp)\\:\\/\\//', $options)) {
      $url = $options;
    }
    else {
      $url = $this->buildLinkFull($options, $base_url);
    }

    if ($delay > 0) {
      return <<<EOT
<script type="text/javascript">
Event.observe(window, 'load', function () {
  setTimeout( function() { window.location.href = '$url'; }, {$delay}*1000);
});
</script>
EOT;
    }

    session_write_close(); // might not really be needed
    if (!headers_sent()) {
      header('Location: ' .$url);
    }

    echo <<<EOT
  <html>
  <head><meta http-equiv="refresh" content="0; URL=${url}"></head>
  <body>If your browser doesn't support automatic redirection, please click <a href="${url}">here</a>
  </html>
EOT;
    exit;
  } // redirect

} // class Page
