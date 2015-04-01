<?php
/*
 * admin_pwd.inc.php
 *
 * mail out a link to the password-change page
 *
 * (c) 2009-2014 daniel.burckhardt@sur-gmbh.ch
 *
 * Version: 2014-10-29 dbu
 *
 * Changes:
 *
 */
require_once LIB_PATH . 'db_forms.php';        // form library
require_once INC_PATH . 'admin/common.inc.php';

define('SHOW_RECOVERFORM', 0);
define('SHOW_RECOVERSUCCESS', 1);

define('SHOW_RECOVERINVALID', 10);

define('SHOW_PWDFORM', 20);
define('SHOW_PWDCHANGED', 21);
define('FORWARD_LOGINFIRST', 25);

define('SHOW_LOGIN', 30);

class DisplayPasswordRecover extends PageDisplay
{
  var $mode = SHOW_RECOVERFORM;
  var $logins = array();
  var $msg = '';

  var $data = array(); // to pass data from the init to the display-code

  function init () {
    if ('POST' == $_SERVER['REQUEST_METHOD']) {
      if (!empty($_POST['login'])) {
        if (!empty($_POST['submitpwd']))
          list($this->mode, $this->msg) = $this->verifyRecoverCode($_POST['login'], $_POST['r'], $_POST['pwd'], $_POST['pwd_confirm']);
        else
          list($this->mode, $this->msg) = $this->processRecoverRequest($_POST['login']);
      }
    }
    else { // check if we come from a password mail-link
      if (!empty($_GET['login'])) {
        list($this->mode, $this->msg) = $this->processRecoverRequest($_GET['login']);
      }
      else if (array_key_exists('do_login', $_GET) && $_GET['do_login'] == 1) {
        $mode = SHOW_LOGIN;
      }
      else {
        $keys = array_keys($_GET);

        if (count($keys) >= 2 && !in_array('do_signoff', $keys) && !in_array('do_login', $keys)) {  // check if we are in recovery-mode
          $r = $keys[$keys[0] == 'pn' ? 1 : 0]; // url is of the form ?pn=pwd&$magic=$login
          list($this->mode, $this->msg) = $this->verifyRecoverCode(trim($_GET[$r]), $r);
        }
      }
    }
    return $this->mode;
  } // init

  function sendRecoverMail($to, $to_id, $magic) {
    global $MAIL_SETTINGS;

    require_once INC_PATH . '/common/Message.php';

    $replace = array('url_recover' => $this->page->buildLinkFull(array('pn' => $this->page->name, $magic => $to_id)),
                     'remote_addr' => $_SERVER['REMOTE_ADDR']);

    $recipients = array('to' => $to);
    if (isset($MAIL_SETTINGS['bcc_passwordrecover']))
      $recipients['bcc'] = $MAIL_SETTINGS['bcc_passwordrecover'];

    $message = new StyledMessage($this->page, 'pwd_recover', $to_id,
                                 array('replace' => $replace, 'recipients' => $recipients));

    return $message->send();
  } // sendRecoverMail

  function processRecoverRequest ($login) {
    global $MAIL_SETTINGS;

    $mode = SHOW_RECOVERFORM;

    $msg = NULL;
    if (_MailValidate($login, 2) != 0) { // check if we have a syntactically valid e-mail address
      $msg = tr("You didn't specify a valid e-mail address");
    }
    else {
      global $RIGHTS_EDITOR;

      $dbconn = new DB;


      $querystr = sprintf("SELECT User.id AS id, User.email AS email, recover IS NOT NULL AS recover_active, UNIX_TIMESTAMP(recover_datetime) AS last_recover, UNIX_TIMESTAMP(NOW()) AS now"
                          . " FROM User WHERE status <> %d AND LOWER(User.email)=LOWER('%s')"
                          . " ORDER BY privs & $RIGHTS_EDITOR DESC, status DESC",
                          STATUS_DELETED,
                          $dbconn->escape_string($login));
      $dbconn->query($querystr);

      if ($dbconn->next_record()) {
        if ($dbconn->Record['recover_active']
          && defined('PWDRECOVER_TIMEOUT')
          && $dbconn->Record['now'] - $dbconn->Record['last_recover'] < PWDRECOVER_TIMEOUT)
        {
          $msg = tr('You or someone else requested this e-mail a short while ago. It may take a few minutes until it reaches your inbox. Please wait a moment before retrying.');
        }
        else {
          $login_id = $dbconn->Record['id'];
          $email    = $dbconn->Record['email'];

          // now generate a unique key, store it in the database and send out an email
          $magic = substr(md5(uniqid(rand())), 2, 7);
          $querystr = "UPDATE User SET recover='$magic', recover_datetime=NOW() WHERE id=$login_id";
          $dbconn->query($querystr);
          if ($dbconn->affected_rows() == 1) {
            $mode = SHOW_RECOVERSUCCESS;

            // now we are ready to send the mail
            $success = $this->sendRecoverMail($email, $login_id, $magic);
          }
          if ($success) {
            $this->data['email_sent'] = $email;
          }
          else {
            $msg = tr('There was an error sending out the e-mail.') . '<br />'
                 . sprintf(tr('Please <a href="%s">contact us for technical assistance</a>.'),
                           htmlspecialchars($this->page->buildLink(array('pn' => 'contact', 'email' => $email))));
          }
        }
      }
      else
        $msg = tr("Your e-mail didn't match an entry in our database.");
    }

    return array($mode, $msg);
  }

  function verifyRecoverCode ($login, $recover_code, $pwd = '', $pwd_confirm = '') {
    $mode = SHOW_RECOVERINVALID;

    $page = &$this->page;

    $dbconn = new DB;

    $msg = NULL;
    $valid = is_numeric($login);

    if ($valid) {
      $querystr = "SELECT id, email, recover FROM User WHERE id=".intval($login);
      $dbconn->query($querystr);
      if ($dbconn->next_record()) {
        $login_id = $dbconn->Record['id'];
        $magic     = $dbconn->Record['recover'];
        $email     = $dbconn->Record['email'];

        $params = array('pn' => $page->name);
        if (!empty($magic)) {
          // only set this if there is a recover-request to avoid e-mail grabbing
          $params['login'] = $email;
        }

        $url_request = $page->buildLink($params);

        if (empty($magic)) {
          $msg = '<p>' . tr('You are using an outdated recover-code.') . '<br />'
               . tr('Please') . ' <a href="' . $url_request . '">'
               . tr('request a new code') . '</a>.</p>';
        }
        else if ($magic != $recover_code) {
          $msg = '<p>'
               . tr('The URL you entered contains an invalid or outdated recover-code.')
               . ' ' . tr('Please make sure you entered the URL exactly as specified in your e-mail.')
               . ' ' . tr("If it still doesn't work, please")
               . ' <a href="' . $url_request . '">'
               . tr('request a new code') . '</a>.</p>';
        }
        else {
          $mode = SHOW_PWDFORM;
          // preset to rebuild the form
          $this->data['recover_code'] = $recover_code;
          $this->data['login_id'] = $login_id;

          if (isset($pwd) && trim($pwd) != '') {
            // check the password
            $pwd_ok  = $page->passwordValid($pwd, $pwd_confirm);

            if ($pwd_ok <= 0) {
              switch($pwd_ok) {
                case -1 :
                  $msg = tr('Your password is not long enough (at least six characters)');
                  break;
                case -2 :
                  $msg = tr("The password you entered wasn't correctly confirm. Please try again.");
                  break;
                default :
                  $msg = tr('Invalid password specified.');
              }
              $msg .= '<br />';
            }
            else {
              $querystr = sprintf(
                "UPDATE User SET pwd='%s', recover=NULL, recover_datetime=NULL WHERE id=$login_id",
                $dbconn->escape_string($page->passwordCrypt($pwd))
              );
              $dbconn->query($querystr);

              // set the login
              $page->setLogin($login_id);

              $mode = SHOW_PWDCHANGED;
            }
          }
        }
      }
      else
        $valid = FALSE;
    }

     if (!$valid) {
      $url_request = $page->buildLink(array('pn' => $page->name)); // we don't have a valid login-info to preset mail
        $msg = '<p>'
             . tr('The URL you entered contains an invalid or outdated recover-code.')
             . ' ' . tr('Please make sure you entered the URL exactly as specified in your e-mail.')
             . ' ' . tr("If it still doesn't work, please")
             . ' <a href="' . $url_request . '">'
             . tr('request a new code') . '</a>.</p>';
    }
    return array($mode, $msg);
  }

  function buildContent () {
    global $MAIL_SETTINGS;

    $page = &$this->page; // save some typing

    switch($this->mode) {
      case SHOW_RECOVERSUCCESS:
        if (!empty($this->msg))
          $content = $this->msg;
        else {
          $email_assistance = $MAIL_SETTINGS['technical_assistance'];
          $email_sent = $this->data['email_sent'];

          $content = '<p>'
            . sprintf(tr('An e-mail containing instructions for changing your password has been sent to %s.'),
                      $email_sent)
            . '</p>';
          $content .= '<p>' . tr("If this doesn't solve your problem, please contact us for technical assistance at").' <a href="mailto:' . $email_assistance . '">' . $email_assistance . '</a>.</p>';
        }
        break;

      case SHOW_RECOVERINVALID:
        $content = $this->msg;
        break;

      case SHOW_PWDCHANGED:
        $url_root = $page->buildLink(array('pn' => 'root'));

        $content = '<p>' . tr('Your password has been changed. You will use this password when accessing this site in the future.') . '</p>'
                 . '<p>' . tr('You can now delete the instruction e-mail.') . '</p>'
                 . '<p>' . tr('To choose a new password, you can click on "My Account" or request a new instruction e-mail.') . '</p>';

        $continue = ucfirst(tr('continue'));
        $content .= <<<EOT
      <p><form action="$url_root" method="post"><input type="submit" value="{$continue}" /></form></p>
EOT;
        break;

      case SHOW_PWDFORM:
        $msg_line = '<p class="message">'
                  . (!empty($this->msg) ? $this->msg : tr('You can now pick a new password'))
                  . '</p>';

        $action = $page->buildLink(array('pn' => $page->name));
        $recover_code = $this->data['recover_code'];
        $login_id = $this->data['login_id'];

        $content = <<<EOT
      <form action="$action" name="pwd" method="post">
      <input type="hidden" name="r" value="$recover_code" />
      <input type="hidden" name="login" value="$login_id" />
      $msg_line
EOT;

        $content .= $this->buildContentLine(tr('New Password') . ':', '<input type="password" name="pwd" /> '.tr('Must be at least six characters'));
        $content .= $this->buildContentLine(tr('Confirm new Password') . ':', '<input type="password" name="pwd_confirm" />');
        $content .= $this->buildContentLine('&nbsp;', '<input name="submitpwd" type="submit" value="' . ucfirst(tr('continue')) . '" />');

        $content .= <<<EOT
      </form>
EOT;
        break;

      default:
        $email_assistance = $MAIL_SETTINGS['technical_assistance'];

        $action = $page->buildLink(array('pn' => $page->name));

        $content = '<p>' . tr("In case you forgot your password or haven't set one yet, we will immediatly send out e-mail instructions on how to create a new password. Your current (forgotten) password will remain active until you respond to that mail.") . '</p>';

        $content .= '<form action="' . htmlspecialchars($action) . '" method="post">';

        if (!empty($this->msg)) {
          $content .= '<p class="message">' . $this->msg . '</p>';
        }

        $this_login = !empty($_REQUEST['login']) ? $_REQUEST['login'] : '';

        $content .= $this->buildContentLine(tr('Your E-mail').':', '<input name="login" size="35" value="' . $this->htmlSpecialchars($this_login) . '" />');
        $content .= $this->buildContentLine('&nbsp;', '<input name="submit" type="submit" value="' . tr('Send') . '" />');

        $support_note = '<p>' . tr("If this doesn't solve your problem, please contact us for technical assistance at") . ' <a href="mailto:' . $email_assistance . '">' . $email_assistance . '</a>.</p>';
        $content .= <<<EOT
      </form>
      $support_note
EOT;
    }
    return $content;
  } // buildContent
}

$display = new DisplayPasswordRecover($page);

switch($display->init()) {
  case SHOW_LOGIN:
    $page->redirect(array());
    break;
  /*
  case FORWARD_LOGINFIRST:
    $page->redirect(array('pn' => 'admin_first'));
    break;
  */
  default:
    $page->setDisplay($display);
}
