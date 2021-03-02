<?php
/*
 * login.inc.php
 *
 * login-form
 *
 * (c) 2009-2020 daniel burckhardt daniel@thing.net
 *
 * Version: 2020-12-07 dbu
 * Changes:
 *
 */

class DisplayLogin
extends PageDisplay
{
  function buildLogin () {
    $params = [];
    if (isset($this->page->parameters)) {
      $params = $this->page->parameters;
      unset($params['do_signoff']);
    }
    $params['pn'] = $this->page->name; // to get back to the page from where we cam


    $ret = '<h3>' . $this->htmlSpecialchars(tr('Sign-in')) . '</h3>';

    if (!empty($this->page->msg)) {
      $ret .= '<p class="message">' . $this->page->msg . '</p>';
    }

    $ret .= '<form name="login_form" action="'.$this->page->buildLink($params).'" method="post">';
    /* if ($page['cookie_test'] !== false) {
      $ret .= '<input type="hidden" name="login_sid" value="'.htmlspecialchars(session_id()).'" />';
    } */

    $login = $this->page->getPostValue('_login_field');

    $login_line = [
      tr('Your E-mail') . ':',
      '<input type="text" name="_login_field" value="' . $this->htmlSpecialchars($login) . '" size="35" />',
    ];

    $ret .= $this->buildContentlineMultiple([
      $login_line,
      [ tr('Password') . ':', '<input type="password" name="_pwd" value="" size="35" />' ],
      [ '&nbsp;', '<input class="submit" type="submit" value="' . $this->htmlSpecialchars('Sign-in') . '" />' ],
    ]);

    $ret .= '<p>'
          . $this->htmlSpecialchars(tr("Forgot your password or didn't set one yet?"))
          . ' <a href="' . htmlspecialchars($this->page->buildLink([ 'pn' => 'pwd' ])) . '">'
          . $this->htmlSpecialchars(tr('Click here')) . '</a> '
          . $this->htmlSpecialchars(tr('to create a new password'))
          . '.</p>'
          ;

    if (isset($GLOBALS['MAIL_SETTINGS']['technical_assistance'])) {
      $ret .= '<p>'
            . $this->htmlSpecialchars(tr('For further assistance please contact'))
            . sprintf(' <a href="mailto:%s">%s</a>.</p>',
                      $GLOBALS['MAIL_SETTINGS']['technical_assistance'],
                      $GLOBALS['MAIL_SETTINGS']['technical_assistance'])
            . '</p>';
    }

    return $ret;
  }

  function buildContent () {
    return $this->buildLogin();
  }
}

$page->setDisplay(new DisplayLogin($page));
