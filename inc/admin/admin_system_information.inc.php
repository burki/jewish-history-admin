<?php
/*
 * admin_system_information.inc.php
 *
 * Show settings and try to check all system-requirements
 *
 * (c) 2012-2019 daniel.burckhardt@sur-gmbh.ch
 *
 * Version: 2019-02-07 dbu
 *
 * Changes:
 *
 */

class SystemInformationDisplay
extends PageDisplay
{

  var $msg = '';

  function init () {
    return true;
  }

  private static function isWindows () {
    return strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
  }

  static function human_filesize ($bytes, $decimals = 2) {
    $sz = 'BKMGTP';
    $factor = floor((strlen($bytes) - 1) / 3);

    return sprintf("%.{$decimals}f",
                   $bytes / pow(1024, $factor)) . @$sz[$factor];
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
          return true;
        }
      }
    }

    return false;
  }

  function buildSuccessFail ($success) {
    if ($success) {
      return '<button class="btn btn-success" type="button">Success</button>';
    }

    return '<button class="btn btn-danger" type="button">Fail</button>';
  }

  function buildVariableDisplay ($config) {
    $parameters_json = json_encode($config, JSON_PRETTY_PRINT);

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
                [
                      'MAX_FILE_SIZE' => self::human_filesize(UPLOAD_MAX_FILE_SIZE),
                      'UPLOAD_FILEROOT' => UPLOAD_FILEROOT,
                ]
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

    $ret .= '<h2>E-Mail Settings</h2>';
    require_once INC_PATH . 'common/MailMessage.php';
    $mailerFactory = new MailerFactory([]);
    $mailer = $mailerFactory->getInstance();
    $transport = $mailer->getTransport();

    if (isset($transport)) {
      $config = [
        'class' => get_class($transport),
      ];

      if ('Swift_SmtpTransport' == $config['class']) {
        foreach ([ 'username', 'host', 'port', 'encryption', 'streamOptions' ] as $key) {
          $methodName = 'get' . ucfirst($key);
          $config[$key] = $transport->$methodName();

        }
      }

      $ret .= $this->buildVariableDisplay($config);
    }

    return $ret;
  } // buildMiddle
}

$display = new SystemInformationDisplay($page);
if (false === $display->init()) {
  $page->redirect(['pn' => '']);
}

$page->setDisplay($display);
