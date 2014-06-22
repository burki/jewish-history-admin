<?php
/*
 * wshandler.inc.php
 *
 * Simple base class for Ajax-Web Services
 *
 * (c) 2007-2010 daniel.burckhardt@sur-gmbh.ch
 *
 * Version: 2010-06-03 dbu
 *
 * Changes:
 *
 */


class SearchSimpleParser
{
  var $min_length = 1; // only non-empty stuff
  var $output = array();

 /**
  * Callback function (or mode / state), called by the Lexer. This one
  * deals with text outside of a variable reference.
  * @param string the matched text
  * @param int lexer state (ignored here)
  */

  function accept($match, $state) {
    // echo "$state: -$match-<br />";
    if ($state == LEXER_UNMATCHED && strlen($match) < $this->min_length)
      return TRUE;

    if ($state == LEXER_MATCHED)
      return TRUE;

    if (preg_match('/\S/', $match))
      $this->output[] = $match;
    return TRUE;
  }

  function writeQuoted($match, $state) {
    static $words;

    switch ($state) {
      // Entering the variable reference
      case LEXER_ENTER:
        $words = array();
        break;

      // Contents of the variable reference
      case LEXER_MATCHED:
        break;

      case LEXER_UNMATCHED:
        if (strlen($match) >= $this->min_length)
          $words[] = $match;
        break;

      // Exiting the variable reference
      case LEXER_EXIT:
        if (count($words) > 0)
          $this->output[] = implode(' ', $words);
        break;
    }
    return TRUE;
  }
}

function split_quoted ($search) {
  require_once LIB_PATH . 'simpletest_parser.php';

  $parser = new SearchSimpleParser();
  $lexer = new SimpleLexer($parser);
  $lexer->addPattern("\\s*[\\,\\+\\s]\\s*");
  $lexer->addEntryPattern('"', 'accept', 'writeQuoted');
  $lexer->addPattern("\\s+", 'writeQuoted');
  $lexer->addExitPattern('"', 'writeQuoted');

  // check if '"' are balanced
  if (substr_count($search, '"') % 2 != 0)
    $search .= '"';

  // do it
  $lexer->parse($search);

  // var_dump($parser->output);

  return $parser->output;
}

class WsHandlerFactory
{
  // private constructor
  private function __construct(){}

  private static $class_map = array();

  static function getInstance ($name) {
    if (array_key_exists($name, self::$class_map))
      return new self::$class_map[$name];
  }

  static function registerClass ($name, $class_name) {
    self::$class_map[$name] = $class_name;
  }
}

class WsHandler
{
  var $STRIP_SLASHES = FALSE;

  function __construct(){
    $this->STRIP_SLASHES = defined('STRIP_SLASHES')
      ? STRIP_SLASHES :get_magic_quotes_gpc();
  }

  function initSession () {
    static $initialized = FALSE;

    if ($initialized)
      return;

    if (defined('SESSION_NAME'))
      session_name (SESSION_NAME);         // set the session name

    // for ie 6.x to take cookies
    // see also http://msdn.microsoft.com/library/default.asp?url=/library/en-us/dnpriv/html/ie6privacyfeature.asp
    header('P3P: CP="NOI NID ADMa OUR IND UNI COM NAV"');

    session_start();                      // and start it
    session_cache_limiter('private');

    $initialized = TRUE;
  }

  function getUser () {
    $this->initSession();
    if (isset($_SESSION['user']))
      return $_SESSION['user'];
  }

  function isAllowed ($privs = -1) {
    // check if the user is logged
    $user = $this->getUser();

    if (!isset($user))
      return FALSE;

    // check if he has enough privs
    if (-1 == $privs)
      return TRUE;

    return 0 != ($privs & $user['privs']);
  }

  function getParameter ($key) {
    if (!isset($_REQUEST[$key]))
      return;

    $val = $_REQUEST[$key];
    if ($this->STRIP_SLASHES)
      $val = is_array($val) ? array_map('stripslashes', $val) : stripslashes($val);

    // $val = is_array($val) ? array_map('utf8_decode', $val) : utf8_decode($val);

    return $val;
  }

  function invalidAction () {
    if (empty($_GET['action'])) {
      $status = 0;
      $msg = 'No action specified';
    }
    else {
      $status = -1;
      $msg = 'Invalid action ' . $_GET['action'];
    }
    return new JsonResponse(array('status' => $status, 'msg' => $msg));
  }

}

class JsonResponse
{
  var $response;

  function __construct ($response = NULL) {
    if (isset($response))
      $this->response = $response;
  }

  static function encode ($valueToEncode) {
    if (function_exists('json_encode')) {
      return json_encode($valueToEncode);
    }
    require_once LIB_PATH . 'JSON.php';
    $json = new Services_JSON();
    return $json->encode($valueToEncode);
  }

  function sendJson () {
    if (array_key_exists('_debug', $_GET) && $_GET['_debug'] == 1) {
      header("Content-type: text/plain; charset=UTF-8");
      echo self::encode($this->response);
    }
    else
      header('X-JSON:' . self::encode($this->response));
  }
}

class HtmlResponse
{
  var $response;

  function __construct ($response) {
    $this->response = $response;
  }

  function send () {
    echo $this->response;
  }
}

class AutocompleterResponse
{
  var $entries;

  function __construct ($entries) {
    $this->entries = $entries;
  }

  function htmlEncode ($txt) {
    $match   = array('/&(?!\#x?\d+;)/s', '/</s', '/>/s', '/"/s');
    $replace = array('&amp;', '&lt;', '&gt;', '&quot;');
    // return utf8_encode(preg_replace($match, $replace, $txt, -1));
    return preg_replace($match, $replace, $txt, -1); // data already in utf-8
  }

  function send () {
    $ret = '<ul>';
    foreach ($this->entries as $entry) {
      $ret .= isset($entry['id'])
      ? sprintf('<li id="%s">', $this->htmlEncode($entry['id']))
      : '<li>';
      $ret .= $this->htmlEncode($entry['item']);
      $ret .= '</li>';
    }
    $ret .= '</ul>';
    echo $ret;
  }
}
