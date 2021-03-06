<?php
/*
 * admin_account.inc.php
 *
 * handle accounts
 *
 * (c) 2006-2020 daniel.burckhardt@sur-gmbh.ch
 *
 * Version: 2020-08-05 dbu
 *
 *
 * Changes:
 *
 */

require_once LIB_PATH . 'db_forms.php';        // form library
require_once INC_PATH . 'common/tablemanager.inc.php';

class AdminOnlyFlow
extends TableManagerFlow
{
  var $user;

  function __construct ($page) {
    $this->user = $page->user;

    parent::__construct();
  }

  function init ($page) {
    global $RIGHTS_ADMIN;

    if (0 != ($page->user['privs'] & $RIGHTS_ADMIN)) {
      if (isset($page->parameters['action'])
          && 'su' == $page->parameters['action'] && intval($page->parameters['id']) > 0)
      {
        if ($page->setLogin(intval($page->parameters['id']))) {
          $page->redirect([ 'pn' => '' ]);
          return TABLEMANAGER_EDIT;
        }
      }

      return parent::init($page);
    }

    return TABLEMANAGER_EDIT;
  }

  function primaryKey ($id = '') {
    global $RIGHTS_ADMIN;
    if (0 != ($this->user['privs'] & $RIGHTS_ADMIN)) {
      return parent::primaryKey($id);
    }

    // just edit own stuff
    return $this->user['id'];
  }

  function advance ($step) {
    global $RIGHTS_ADMIN;
    if (0 != ($this->user['privs'] & $RIGHTS_ADMIN)) {
      return parent::advance($step);
    }

    // there is no listing for regular users
    return TABLEMANAGER_EDIT;
  }
}

class DisplayAccount
extends DisplayTable
{
  var $page_size = 50;
  var $table = 'User';
  var $fields_listing = [
    'User.id AS id', 'User.email AS email', 'User.lastname AS lastname', 'User.firstname AS firstname', 'privs', 'UNIX_TIMESTAMP(User.created) AS created', 'privs',
  ];
  var $cols_listing = [
    'email' => 'E-Mail',
    'name' => 'Name',
    'privs' => 'Access rights',
    'created' => 'Created',
    '' => '',
  ];

  var $order = [
    'name' => [ 'lastname, firstname', 'lastname DESC, firstname DESC' ],
    'email' => [ 'email, User.id', 'email DESC, User.id DESC' ],
    'privs' => [ 'privs DESC, User.id DESC', 'privs, User.id' ],
    'created' => [ 'created DESC, User.id desc', 'created, User.id' ],
  ];
  var $condition = [
    "User.status <> -100", // deleted user
    [ 'name' => 'search', 'method' => 'buildLikeCondition', 'args' => 'email,lastname,firstname' ],
  ];

  function instantiateRecord ($table = '', $dbconn = '') {
    $record = parent::instantiateRecord($table, $dbconn);

    $record->add_fields([
      new Field([ 'name' => 'id', 'type' => 'hidden', 'datatype' => 'int', 'primarykey' => true ]),
      new Field([ 'name' => 'created', 'type' => 'hidden', 'datatype' => 'function', 'value' => 'NOW()', 'noupdate' => true ]),
      new Field([ 'name' => 'changed', 'type' => 'hidden', 'datatype' => 'function', 'value' => 'NOW()' ]),
      new Field([ 'name' => 'email', 'type' => 'email', 'size' => 30, 'datatype' => 'char', 'maxlength' => 80, 'noupdate' => true, 'null' => true ]),
      new Field([ 'name' => 'lastname', 'type' => 'text', 'size' => 40, 'datatype' => 'char', 'maxlength' => 80 ]),
      new Field([ 'name' => 'firstname', 'type' => 'text', 'size' => 40, 'datatype' => 'char', 'maxlength' => 80, 'null' => true ]),
      new Field([ 'name' => 'pwd', 'type' => 'password', 'size' => 10, 'datatype' => 'char', 'maxlength' => 40, 'nodbfield' => true, 'null' => true ]),
      new Field([ 'name' => 'pwd_confirm', 'type' => 'password', 'size' => 10, 'maxlength' => 40, 'nodbfield' => true, 'null' => true ]),
      new Field([ 'name' => 'privs', 'type' => 'checkbox', 'datatype' => 'bitmap', 'null' => true, 'default' => 0,
                  'labels' => [
                    '',
                    tr('System Editor'),
                    tr('System Administrator'),
                    '',
                    tr('Referee'),
                    tr('Translator'),
                  ],
      ]),
    ]);

    return $record;
  }

  function validateInput () {
    global $RIGHTS_ADMIN;

    if (0 == ($this->page->user['privs'] & $RIGHTS_ADMIN))
      $this->form->set_value('privs', $this->page->user['privs']); // these stay fixed

    $res = parent::validateInput ();
    if (!$res) {
      return $res;
    }

    $form = &$this->form; // save some typing
    $id = $form->get_value('id');

    $check_login = true;
    $dbconn = &$this->page->dbconn;
    if (!empty($id)) {
      // if we have an existing account, check unique login/email only if that one was changed
      $querystr = "SELECT email AS login FROM User WHERE id=$id";

      $dbconn->query($querystr);
      if ($dbconn->next_record()) {
        $old_login = $dbconn->Record['login'];
        $new_login = $form->get_value('email');
        if ($old_login == $new_login) {
          $check_login = false;
        }
        else if (empty($new_login)) {
          $form->set_value('email', $new_login = $old_login);
          $check_login = false;
        }
      }
      else {
        unset($id);
      }
    }

    if ($check_login) {
      // check email/login and make sure the login is unique
      $new_login = $form->get_value('email');

      $querystr = "SELECT COUNT(*) AS countlogin FROM User WHERE LOWER(email)=LOWER('" . $dbconn->escape_string($new_login) . "')";
      $dbconn->query($querystr);
      if ($dbconn->next_record()) {
        if ($dbconn->Record['countlogin'] > 0) {
          $this->page->msg .= 'The new e-mail you have set is already used for another login. Please choose a different one.';
          if (!empty($old_login))
            $form->set_value('email', $old_login); // set back to old value
          return false;
        }
      }
    }

    // the password might have changed
    $pwd = $form->get_value('pwd');
    if (!empty($pwd)) {
      // check if the password is valid and correctly confirmed

      $pwd2 = $form->get_value('pwd_confirm');
      $pwd_ok  = $this->page->passwordValid($pwd, $pwd2);
      if ($pwd_ok <= 0) {
        $res = false;

        switch ($pwd_ok) {
          case -1:
            $this->page->msg .= tr('Your password is not long enough (at least six characters)')
                              . '<br />';
            break;

          case -2:
            $this->page->msg .= tr('The passwords you entered did not match. Please re-enter them.')
                              . '<br />';
            break;

          default:
            $this->page->msg .= tr('Invalid password specified')
                              . '<br />';
        }

        $form->set_value('pwd', '');
        $form->set_value('pwd_confirm', '');
      }
      else {
        $form->set_value('pwd', $this->page->passwordCrypt($pwd));  // store the encrypted pwd
        $form->set_property('pwd', 'nodbfield', false);
      }
    }

    return $res;
  }

  function getEditRows () {
    global $RIGHTS_EDITOR, $RIGHTS_ADMIN, $RIGHTS_REFEREE, $RIGHTS_TRANSLATOR;

    $edit_self = ($this->form->get_value('id') == $this->page->user['id']);

    $fields = [
      'id' => true,
      'email' => [ 'label' => $edit_self ? 'Your Account E-mail' : 'Account E-mail', 'value' => $this->record->get_value('email') ],
      'lastname' => [ 'label' => 'Last Name' ],
      'firstname' => [ 'label' => 'First Name' ],
      'pwd' => [
        'label' => tr('New Password')
                . ':<br />(' . tr('Must be at least six characters') . ')',
      ],
      'pwd_confirm' => [ 'label' => 'Confirm Password' ],
    ];

    if ($this->page->user['privs'] & $RIGHTS_ADMIN) {
      $privs = $this->form->field('privs');
      $rights_mask = $RIGHTS_EDITOR | $RIGHTS_ADMIN | $RIGHTS_REFEREE | $RIGHTS_TRANSLATOR;
      if ($edit_self) {
        // if i'm admin, i'm at least EDITOR
        $rights_mask &= ~$RIGHTS_EDITOR;
        $fields[] = '<input type="hidden" name="privs[]" value="' . $RIGHTS_EDITOR . '" />'
                  . $privs->show($rights_mask);
      }
      else {
        $fields['privs'] = [ 'label' => 'Access rights', 'value' => $privs->show($rights_mask) ];
      }
    }

    $fields[] = $this->form->show_submit(tr('Store'));

    return $fields;
  }

  function buildListingCell (&$row, $col_index, $val = null) {
    global $RIGHTS_ADMIN, $RIGHTS_EDITOR;

    $val = $row[$col_index];
    $name = $this->fields_listing[$col_index];

    switch ($col_index) {
      case 1:
        $val = sprintf('<a href="%s">%s</a>',
                        htmlspecialchars($this->page->buildLink([ 'pn' => $this->page->name, 'edit' => $row['id'] ])),
                        $this->formatText($row['email']));
        break;

      case 2: // lastname
        $val = $this->formatText($row['lastname']
                                 . (isset($row['firstname'])
                                    ? ' ' . $row['firstname']: ''));
        break;

      case 3:
        return false; // skip firstname
        break;

      case 5:
        $val = $this->formatTimestamp($row['created'], 'd.m.y');
        break;

      case count($this->fields_listing) - 3:
        $parts = [];
        foreach ([
            $GLOBALS['RIGHTS_REFEREE'] => 'Hrsg',
            $GLOBALS['RIGHTS_TRANSLATOR'] => 'Über',
            $GLOBALS['RIGHTS_EDITOR'] => 'Ed',
            $GLOBALS['RIGHTS_ADMIN'] => 'Ad'
          ] as $right => $short)
        {
          if (0 != ($val & $right)) {
            $parts[] = $short;
          }
        }

        $val = '';
        if (count($parts) > 0) {
          $val = implode('|', $parts);
        }

        break;

      case count($this->fields_listing) - 1:
        if (0 == ($val & $GLOBALS['RIGHTS_ADMIN'])) {
          $val = sprintf('[<a href="%s" style="white-space: nowrap">%s</a>]',
                         htmlspecialchars($this->page->buildLink([ 'pn' => $this->page->name, 'action' => 'su', 'id' => $row['id'] ])),
                         $this->htmlSpecialchars(tr('switch to')));
        }
        else {
          $val = '';
        }

        break;
    }

    return parent::buildListingCell($row, $col_index, $val);
  }

  function buildContent () {
    switch (array_key_exists('action', $this->page->parameters)
           ? $this->page->parameters['action'] : null) {
      default:
        $ret = parent::buildContent();
    }

    return $ret;
  }
}

$display = new DisplayAccount($page, new AdminOnlyFlow($page));
if (false === $display->init()) {
  $page->redirect([ 'pn' => '' ]);
}
$page->setDisplay($display);
