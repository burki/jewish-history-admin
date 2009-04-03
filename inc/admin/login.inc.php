<?php
/*
 * login.inc.php
 *
 * login-form
 *
 * (c) 2009 daniel burckhardt daniel@thing.net
 *
 * Version: 2009-01-29 dbu
 * Changes:
 *
 */


class DisplayLogin extends PageDisplay
{

  function __construct (&$page) {
    parent::__construct($page);
  }

  function buildLogin () {
    global $MAIL_SETTINGS;

    $params = array();
    if(isset($this->page->parameters)) {
      $params = $this->page->parameters;
      unset($params['do_signoff']);
    }
    $params['pn'] = $this->page->name; // to get back to the page from where we cam


    $ret = '<h3>'.tr('Sign-in to view and edit your information').'</h3>';

    if(!empty($this->page->msg))
      $ret .= '<p class="message">'.$this->page->msg.'</p>';

    $ret .= '<form name="login_form" action="'.$this->page->buildLink($params).'" method="post">';
    /* if($page['cookie_test'] !== FALSE) {
      $ret .= '<input type="hidden" name="login_sid" value="'.htmlspecialchars(session_id()).'" />';
    } */

    $login = $this->page->getPostValue('_login_field');

    $login_line = array(tr('Your E-mail').':',
                        '<input type="text" name="_login_field" value="'.$this->htmlSpecialchars($login).'" size="35" />');

    $ret .= $this->buildContentlineMultiple(
        array(
           $login_line,
           array(tr('Password').':', '<input type="password" name="_pwd" value="" size="35" />'),
           array('&nbsp;', '<input class="submit" type="submit" value="'.tr('Sign-in').'" />'),
	)
    );

    $ret .= '<p>'.tr("Forgot your password or didn't set one yet?")
	.' <a href="'.$this->page->buildLink(array('pn' => 'pwd')).'">'.tr('Click here').'</a> '
	.tr('to create a new password').'.</p>';
    if(isset($MAIL_SETTINGS['technical_assistance']))
      $ret .= '<p>'.tr('For further assistance please contact')
		.sprintf(' <a href="mailto:%s">%s</a>.</p>',
			 $MAIL_SETTINGS['technical_assistance'],
			 $MAIL_SETTINGS['technical_assistance'])
		.'</p>';

    return $ret;
  }

  function buildContent () {
    return $this->buildLogin();
  }
}

$page->setDisplay(new DisplayLogin($page));

?>