<?php
/*
 * system_information.inc.php
 *
 * Show settings and try to check all system-requirements
 *
 * (c) 2012-2014 daniel.burckhardt@sur-gmbh.ch
 *
 * Version: 2014-12-15 dbu
 *
 * Changes:
 *
 */

class SystemInformationDisplay extends PageDisplay
{

  var $msg = '';

  function init () {
    return true;
  }

  private static function isWindows () {
    return strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
  }

  static function json_indent ($json) {
    $result      = '';
    $pos         = 0;
    $strLen      = strlen($json);
    $indentStr   = '  ';
    $newLine     = "\n";
    $prevChar    = '';
    $outOfQuotes = true;

    for ($i = 0; $i <= $strLen; $i++) {

        // Grab the next character in the string.
        $char = substr($json, $i, 1);

        // Are we inside a quoted string?
        if ($char == '"' && $prevChar != '\\') {
            $outOfQuotes = !$outOfQuotes;

        // If this character is the end of an element,
        // output a new line and indent the next line.
        } else if (($char == '}' || $char == ']') && $outOfQuotes) {
            $result .= $newLine;
            $pos --;
            for ($j=0; $j<$pos; $j++) {
                $result .= $indentStr;
            }
        }

        // Add the character to the result string.
        $result .= $char;

        // If the last character was the beginning of an element,
        // output a new line and indent the next line.
        if (($char == ',' || $char == '{' || $char == '[') && $outOfQuotes) {
            $result .= $newLine;
            if ($char == '{' || $char == '[') {
                $pos ++;
            }

            for ($j = 0; $j < $pos; $j++) {
                $result .= $indentStr;
            }
        }

        $prevChar = $char;
    }

    return $result;
  }

  static function human_filesize ($bytes, $decimals = 2) {
    $sz = 'BKMGTP';
    $factor = floor((strlen($bytes) - 1) / 3);
    return sprintf("%.{$decimals}f", $bytes / pow(1024, $factor)) . @$sz[$factor];
  }

  private static function normalizePath ($path) {
    if (self::isWindows()) {
      // replace \ by /
      $path = preg_replace('/\\\\/', '/', $path);
    }
    return $path;
  }

  private static function checkExecutable ($cmd_line) {
    $parts = preg_split('/\s+/', $cmd_line);
    $cmd = self::normalizePath($parts[0]);
    if (file_exists($cmd)) {
      return is_executable($cmd);
    }

    if (!self::isWindows() && '/' != $cmd[0]) {
      // try to prepend $PATH-component to $cmd
      $paths = split(PATH_SEPARATOR, getenv('PATH'));
      foreach ($paths as $path) {
        $cmd_full = $path . '/' . $cmd;
        if (file_exists($cmd_full) && is_executable($cmd_full)) {
          return TRUE;
        }
      }
    }

    return FALSE;
  }

  function buildSuccessFail ($success) {
    if ($success) {
      return '<button class="btn btn-success" type="button">Success</button>';
    }
    else {
      return '<button class="btn btn-danger" type="button">Fail</button>';
    }
  }

  function buildVariableDisplay ($config) {
    if (version_compare(PHP_VERSION, '5.4.0') >= 0) {
      $parameters_json = json_encode($config, JSON_PRETTY_PRINT);
    }
    else {
      $parameters_json = self::json_indent(json_encode($config));
    }
    return '<tt><pre>' . $parameters_json . '</pre></tt>';
  }

  function buildContent () {
    $ret = '';

    if (!empty($this->msg)) {
      $ret .= $this->htmlSpecialchars($this->msg);
    }

    if (self::isWindows()) {
      $success = function_exists('finfo_open');
      $ret .= '<h2>PHP-Modules</h2>';
      $ret .= '<p>' . $this->buildSuccessFail($success) . ' php_fileinfo.dll DLL file in php.ini</p>';
    }

    /*
    $ret .= '<h2>Logging</h2>';
    $arguments = $this->page->container->getParameter('logger.arguments');
    //echo "file: " . $arguments['handlers']['StreamHandler']['file'] . "<br />";
    $log_dir = self::normalizePath($this->page->container->getParameterBag()->resolveValue(!empty($arguments['handlers']['StreamHandler']['file'])
                                                                                           ? dirname($arguments['handlers']['StreamHandler']['file'])
                                                                                           : '%base_path%/log/'));
    $msg = '';
    $success = file_exists($log_dir);
    if ($success) {
      $success = is_writable($log_dir);
      if (!$success) {
        $msg = " not writable";
      }
    }
    else {
      $msg = " does not exist";
    }

    $ret .= '<p>' . $this->buildSuccessFail($success) . ' Log directory: ' . $this->htmlSpecialchars($log_dir) . $msg . '</p>';
    */

    $ret .= '<h2>Upload Settings</h2>';
    $ret .= $this->buildVariableDisplay(
                array(
                      'MAX_FILE_SIZE' => self::human_filesize(UPLOAD_MAX_FILE_SIZE),
                      'UPLOAD_FILEROOT' => UPLOAD_FILEROOT,
                )
            );

    if (defined('UPLOAD_PATH2MAGICK')) {
      $success = self::checkExecutable($cmd =
                                       UPLOAD_PATH2MAGICK
                                       . 'convert'
                                       . (self::isWindows() ? '.exe' : ''));
      $ret .= '<p>' . $this->buildSuccessFail($success) . ' ' . $this->htmlSpecialchars($cmd) . '</p>';
    }
    else {
      $ret .= '<p>' . $this->buildSuccessFail(false)
            . ' ' . $this->htmlSpecialchars('UPLOAD_PATH2MAGICK not set') . '</p>';
    }


    /*
    $ret .= '<h2>E-Mail Settings</h2>';
    require_once INC_PATH . 'common/MailMessage.php';
    $mailerFactory = new MailerFactory(array());
    $mailer = $mailerFactory->getInstance();

    if (isset($mailer->config)) {
      $config = is_object($mailer->config) ? clone($mailer->config) : $mailer->config; // make sure we manipulate the copy
      if (isset($config['smtp']) && isset($config['smtp']['password']))
        $config['smtp']['password'] = '***';

      $ret .= $this->buildVariableDisplay($config);
    }
    */

    return $ret;
  } // buildMiddle
}

$display = new SystemInformationDisplay($page);
if (FALSE === $display->init()) {
  $page->redirect(array('pn' => ''));
}

$page->setDisplay($display);
