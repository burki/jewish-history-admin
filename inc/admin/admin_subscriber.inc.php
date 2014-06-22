<?php
/*
 * admin_subscriber.inc.php
 *
 * Manage the subscribers
 *
 * (c) 2006-2013 daniel.burckhardt@sur-gmbh.ch
 *
 * Version: 2013-11-25 dbu
 *
 * TODO: validity of Subscription Status/Hold-Date
 *
 * Changes:
 *
 */

require_once INC_PATH . 'common/tablemanager.inc.php';
require_once INC_PATH . 'admin/common.inc.php';

class SubscriberFlow extends TableManagerFlow {
  const LISTSERV = 1000;
  const MERGE    = 1010;

  var $user;
  var $is_internal = FALSE;

  function __construct (&$page) {
    global $RIGHTS_EDITOR;

    $this->user = &$page->user;
    $this->is_internal = 0 != ($this->user['privs'] & $RIGHTS_EDITOR);

    parent::TableManagerFlow($this->is_internal);
  }

  function init ($page) {
    // die('SubscriberFlow::init()');
    if ($this->is_internal) {
      if (isset($page->parameters['listserv']) && ($id = intval($page->parameters['listserv'])) > 0) {
        $this->id = $id;
        return self::LISTSERV;
      }
      if (isset($page->parameters['merge']) && ($id = intval($page->parameters['merge'])) > 0) {
        $this->id = $id;
        return self::MERGE;
      }
      return parent::init($page);
    }
    else {
      if (isset($page->parameters['listserv'])) {
        $this->id = $this->user['id'];
        return self::LISTSERV;
      }
      return isset($page->parameters['edit']) ? TABLEMANAGER_EDIT : FALSE;
    }
  }

  function primaryKey ($id = '') {
    if ($this->is_internal)
      return parent::primaryKey($id);

    // just handle own stuff
    return $this->user['id'];
  }

  function advance ($step) {
    if ($this->is_internal)
      return parent::advance($step);

    // there is no listing for regular users
    return FALSE;
  }
}

class SubscriberRecord extends TableManagerRecord {

  function delete ($id) {
    $dbconn = $this->params['dbconn'];
    $querystr = sprintf("UPDATE %s SET status=%d WHERE id=%d",
                        $this->params['tables'],
                        SubscriberListing::$status_deleted,
                        $id);
    $dbconn->query($querystr);

    return $dbconn->affected_rows() > 0;
  }

}

class SubscriberQueryConditionBuilder extends TableManagerQueryConditionBuilder {

  function buildStatusCondition () {
    $num_args = func_num_args();
    if ($num_args <= 0)
      return;
    $fields = func_get_args();

    if (isset($this->term) && '' !== $this->term) {
      $ret = $fields[0].'='.intval($this->term);
      if (0 == intval($this->term)) {
        // also show expired on holds
        $ret = "($ret "
          // ." OR ".$fields[0]. " = -3" // uncomment for open requests
          ." OR (".$fields[0]." = 2 AND hold <= CURRENT_DATE()))";
      }
      return $ret;
    }
  }

}

class DisplaySubscriber extends DisplayTable
{
  var $table = 'User';
  var $fields_listing = array('User.id AS id', 'lastname', 'firstname', 'email',
    'status', 'UNIX_TIMESTAMP(User.created) AS created', 'comment');
  var $joins_listing;
  var $order = array('name' => array('lastname, firstname', 'lastname DESC, firstname DESC'),
                     'created' => array('created DESC, User.id desc', 'created, User.id'),
                    );
  var $cols_listing = array('name' => 'Name', 'email' => 'E-Mail', 'status' => '', 'created' => 'Created');
  var $page_size = 50;
  var $status_deleted;
  var $search_fulltext = NULL;

  function __construct (&$page) {
    parent::__construct($page, new SubscriberFlow($page));

    $this->status_deleted = SubscriberListing::$status_deleted;

    if ($page->lang() != 'en_US')
      $this->datetime_style = 'DD.MM.YYYY';
    $this->messages['item_new'] = 'New Author';
    $this->search_fulltext = $this->page->getPostValue('fulltext');
    if (!isset($this->search_fulltext))
      $this->search_fulltext = $this->page->getSessionValue('fulltext');
    $this->page->setSessionValue('fulltext', $this->search_fulltext);

    $review = $this->page->getSessionValue('review');
    if (NULL === $review && !array_key_exists('review', $_REQUEST)) { // default to reviewers only
      $this->page->getSessionValue('review', $_REQUEST['review'] = 'Y');
    }

    if ($this->search_fulltext) {
      $search_condition = array('name' => 'search', 'method' => 'buildFulltextCondition', 'args' => 'lastname,firstname,email,institution,supervisor,address,areas,description,review_areas', 'persist' => 'session');
    }
    else {
      $search_condition = array('name' => 'search', 'method' => 'buildLikeCondition', 'args' => 'lastname,firstname,email', 'persist' => 'session');
    }

    $this->condition = array(
      "User.status <> ".$this->status_deleted,
      array('name' => 'status', 'method' => 'buildStatusCondition', 'args' => 'status', 'persist' => 'session'),
      $search_condition,
      array('name' => 'review', 'method' => 'buildLikeCondition', 'args' => 'review', 'persist' => 'session'),
    );
  }

  function init () {
    $this->step = $this->workflow->init($this->page);
    if (in_array($this->step, array(SubscriberFlow::LISTSERV, SubscriberFlow::MERGE)))
      return $this->step;

    return parent::init();
  }

  function getCountries () {
    return Countries::getAll();
  }

  function setRecordInternal (&$record) {
  }

  function instantiateRecord ($table = '', $dbconn = '') {
    global $COUNTRIES_FEATURED;

    $record =  new SubscriberRecord(array('tables' => $this->table, 'dbconn' => $this->page->dbconn));

    $countries = $this->getCountries();
    $countries_ordered = array('' => tr('-- not available --'));
    if (isset($COUNTRIES_FEATURED)) {
      for($i = 0; $i < sizeof($COUNTRIES_FEATURED); $i++) {
        $countries_ordered[$COUNTRIES_FEATURED[$i]] = $countries[$COUNTRIES_FEATURED[$i]];
      }
      $countries_ordered['&#x2500;&#x2500;&#x2500;&#x2500;&#x2500;&#x2500;'] = FALSE; // separator
    }
    foreach ($countries as $cc => $name) {
      if (!isset($countries_ordered[$cc]))
        $countries_ordered[$cc] = $name;
    }

    $status_options = array();
    foreach (SubscriberListing::$status_list as $val => $label)
      $status_options[$val] = tr($label);

    $sex_options = array('' => '--', 'F' => tr('Mrs.'), 'M' => tr('Mr.'));
    $review_options = array('Y' => tr('yes'), 'N' => tr('no'));

    $record->add_fields(
      array(
        new Field(array('name'=>'id', 'type'=>'hidden', 'datatype'=>'int', 'primarykey'=>1)),

//        new Field(array('name'=>'creator', 'type'=>'hidden', 'datatype'=>'int', 'null'=>TRUE, 'noupdate' => 1)),
        new Field(array('name'=>'created', 'type'=>'hidden', 'datatype'=>'function', 'value'=>'NOW()', 'noupdate' => 1)),
/*        new Field(array('name'=>'editor', 'type'=>'hidden', 'datatype'=>'int', 'null'=>TRUE)),*/
        new Field(array('name'=>'changed', 'type'=>'hidden', 'datatype'=>'function', 'value'=>'NOW()')),

        new Field(array('name'=>'email', 'type'=>'email', 'size'=>40, 'datatype'=>'char', 'maxlength'=>80, 'null'=>TRUE, 'noupdate' => !$this->is_internal)),
        new Field(array('name'=>'status', 'type'=>'select', 'options' => array_keys($status_options), 'labels' => array_values($status_options), 'datatype'=>'int', 'default' => -10, 'noupdate' => !$this->is_internal, 'null' => !$this->is_internal)),
//        new Field(array('name'=>'status', 'type'=>'hidden', 'value' => 0, 'noupdate' => !$this->is_internal, 'null' => TRUE)),
        new Field(array('name'=>'subscribed', 'type'=>'date', 'datatype'=>'date', 'null'=>TRUE, 'noupdate' => !$this->is_internal)),
        new Field(array('name'=>'unsubscribed', 'type'=>'date', 'datatype'=>'date', 'null'=>TRUE, 'noupdate' => !$this->is_internal)),
        /* new Field(array('name'=>'hold', 'type'=>'date', 'datatype'=>'date', 'null'=>TRUE, 'noupdate' => !$this->is_internal)), */

        new Field(array('name'=>'sex', 'type'=>'select', 'datatype'=>'char', 'options' => array_keys($sex_options), 'labels' => array_values($sex_options))),
        new Field(array('name'=>'title', 'type'=>'text', 'datatype'=>'char', 'size'=>8, 'maxlength'=>20, 'null'=>TRUE)),
        new Field(array('name'=>'lastname', 'type'=>'text', 'size'=>40, 'datatype'=>'char', 'maxlength'=>80)),
        new Field(array('name'=>'firstname', 'type'=>'text', 'size'=>40, 'datatype'=>'char', 'maxlength'=>80, 'null' => TRUE)),

        new Field(array('name'=>'position', 'type'=>'text', 'size'=>40, 'datatype'=>'char', 'maxlength'=>80, 'null' => TRUE)),
        new Field(array('name'=>'email_work', 'type'=>'email', 'size'=>40, 'datatype'=>'char', 'maxlength'=>80, 'null'=>TRUE)),
        new Field(array('name'=>'institution', 'type'=>'text', 'size'=>40, 'datatype'=>'char', 'maxlength'=>80, 'null' => TRUE)),

        new Field(array('name'=>'address', 'type'=>'textarea', 'datatype'=>'char', 'cols'=>50, 'rows' => 5, 'null' => TRUE)),
        new Field(array('name'=>'place', 'type'=>'text', 'size' => 30, 'datatype'=>'char', 'maxlength' => 80, 'null'=>TRUE)),
        new Field(array('name'=>'zip', 'type'=>'text', 'datatype'=>'char', 'size'=>8, 'maxlength'=>8, 'null'=>TRUE)),
        new Field(array('name'=>'country', 'type'=>'select', 'datatype'=>'char', 'null'=>TRUE, 'options'=>array_keys($countries_ordered), 'labels'=>array_values($countries_ordered), 'default' => 'DE', 'null' => TRUE)),

        new Field(array('name'=>'phone', 'type'=>'text', 'size'=>40, 'datatype'=>'char', 'maxlength'=>40, 'null'=>TRUE)),
        new Field(array('name'=>'fax', 'type'=>'text', 'size'=>40, 'datatype'=>'char', 'maxlength'=>40, 'null'=>TRUE)),
        new Field(array('name'=>'url', 'type'=>'text', 'size'=>40, 'datatype'=>'char', 'maxlength'=>255, 'null'=>TRUE)),
        new Field(array('name'=>'supervisor', 'type'=>'text', 'size'=>40, 'datatype'=>'char', 'maxlength'=>255, 'null'=>TRUE)),
//        new Field(array('name'=>'description', 'type'=>'textarea', 'datatype'=>'char', 'cols'=>50, 'rows' => 4, 'null' => TRUE)),
        new Field(array('name'=>'areas', 'type'=>'textarea', 'datatype'=>'char', 'cols'=>50, 'rows' => 4, 'null' => TRUE)),
        new Field(array('name'=>'ip', 'type'=>'hidden', 'datatype'=>'char', 'null' => TRUE, 'noupdate'=>TRUE, 'value' => $_SERVER['REMOTE_ADDR'])),

    ));

    if ($this->is_internal) {
      $record->add_fields(
        array(
          new Field(array('name'=>'knownthrough', 'type'=>'text', 'size'=>40, 'datatype'=>'char', 'maxlength'=>255, 'null'=>TRUE)),
          new Field(array('name'=>'expectations', 'type'=>'textarea', 'datatype'=>'char', 'cols'=>50, 'rows' => 4, 'null' => TRUE)),
          new Field(array('name'=>'forum', 'type'=>'text', 'size'=>40, 'datatype'=>'char', 'maxlength'=>255, 'null'=>TRUE)),

          new Field(array('name'=>'review', 'type'=>'select', 'datatype'=>'char', 'options' => array_keys($review_options), 'labels' => array_values($review_options))),
          new Field(array('name'=>'review_areas', 'type'=>'textarea', 'datatype'=>'char', 'cols'=>50, 'rows' => 4, 'null' => TRUE)),
          new Field(array('name'=>'review_suggest', 'type'=>'textarea', 'datatype'=>'char', 'cols'=>50, 'rows' => 4, 'null' => TRUE)),

          new Field(array('name'=>'comment', 'type'=>'textarea', 'datatype'=>'char', 'cols'=>50, 'rows' => 4, 'null' => TRUE, 'noupdate' => !$this->is_internal)),
        ));
    }

    return $record;
  }

  function instantiateQueryConditionBuilder ($term) {
    return new SubscriberQueryConditionBuilder($term);
  }

  function getEditRows () {
    $rows = array(
        'id' => FALSE,
        'flags' => FALSE,
        'email' => array('label' => 'E-mail'),
        'status' => array('label' => 'Newsletter'),
    );

    if (FALSE && $this->is_internal) {
      $rows = array_merge($rows,
        array(
          array('label' => 'Subscribed / Unsubscribed', 'fields' => array('subscribed', 'unsubscribed'), 'show_datetimestyle' => true),
          'hold' => array('label' => 'Hold until', 'show_datetimestyle' => true),
          $this->form->show_submit(tr('Store')).'<hr noshade="noshade" />',
        ));
    }

    $rows = array_merge($rows, array(
        array('label' => 'Salutation / Academic Title', 'fields' => array('sex', 'title')),
        'lastname' => array('label' => 'Last Name'),
        'firstname' => array('label' => 'First Name'),
        'position' => array('label' => 'Position'),
        $this->form->show_submit(tr('Store')).'<hr noshade="noshade" />',
        'email_work' => array('label' => 'Institutional E-Mail'),
        'institution' => array('label' => 'Institution'),
        'address' => array('label' => 'Address'),
        array('label' => 'Postcode / Place', 'fields' => array('zip', 'place')),
        'country' => array('label' => 'Country'),
        'phone' => array('label' => 'Telephone'),
        'fax' => array('label' => 'Fax'),
        '<hr noshade="noshade" />',
        'url' => array('label' => 'Homepage'),
        'supervisor' => array('label' => 'Supervisor'),
        // 'description' => array('label' => 'Profile'),
        'areas' => array('label' => 'Areas of interest'),
    ));
    if ($this->is_internal) {
      $rows = array_merge($rows,
        array(
        /* 'expectations' => array('label' => 'Expectations'),
        'knownthrough' => array('label' => 'How did you get to know us'),
        'forum' => array('label' => 'Other lists and fora'),
        '<hr noshade="noshade" />',  */
        'review' => array('label' => 'Willing to contribute'),
        'review_areas' => array('label' => 'Contribution areas'),
        'review_suggest' => array('label' => 'Article suggestion'),
        '<hr noshade="noshade" />',
        'comment' => array('label' => 'Internal notes and comment'),
        ));
    }
    else {
      $rows['email']['value'] = $this->record->get_value('email');
      $rows['status']['value'] = tr(SubscriberListing::$status_list[$this->record->get_value('status')]);
    }


    return array_merge(
      $rows,
      array(
        $this->form->show_submit(tr('Store')),
      )
    );
  }

  function buildView () {
    $this->id = $this->workflow->primaryKey();

    $subscriber = new SubscriberListing($this->id);

    if (!$subscriber->query($this->page->dbconn))
        return 'An error occured query-ing your data';

    return $subscriber->build($this, $this->is_internal ? 'admin' : 'default');
  }

  function buildSearchBar () {
/*
    $select_options = array('<option value="">'.tr('-- all --').'</option>');
    foreach (SubscriberListing::$status_list as $status => $label)
      if ($this->status_deleted != $status) {
        $selected = $this->search['status'] !== ''
            && $this->search['status'] == $status
            ? ' selected="selected"' : '';
        $select_options[] = sprintf('<option value="%d"%s>%s</option>', $status, $selected, htmlspecialchars(tr($label)));
      }
*/
    $ret = sprintf('<form action="%s" method="post" name="search">',
                   htmlspecialchars($this->page->buildLink(array('pn' => $this->page->name, 'page_id' => 0))));

      $search = '<input type="text" name="search" value="'.$this->htmlSpecialchars(array_key_exists('search', $this->search) ?  $this->search['search'] : '').'" size="40" />';
      $search .= '<label><input type="hidden" name="fulltext" value="0" /><input type="checkbox" name="fulltext" value="1" '.($this->search_fulltext ? ' checked="checked"' : '').'/> '.$this->htmlSpecialchars(tr('Fulltext')).'</label>';
      /*
      $search .= '<br />'.tr('Subscription Status').': <select name="status">'.implode($select_options).'</select>';
      */

      foreach (array('' => '-- all --', 'Y' => 'yes', /* 'N' => 'no', */) as $status => $label) {
        $selected = isset($this->search['review']) && $this->search['review'] !== ''
            && $this->search['review'] == $status
            ? ' selected="selected"' : '';
        $review_options[] = sprintf('<option value="%s"%s>%s</option>', $status, $selected, htmlspecialchars(tr($label)));
      }

      $search .= ' '. tr('Willing to contribute') . ': <select name="review">'.implode($review_options).'</select>';
      // clear the search
      $url_clear = $this->page->BASE_PATH . 'media/clear.gif';
      $search .= <<<EOT
      <script>
      function clear_search() {
        var form = document.forms['search'];
        if (null != form) {
          var textfields = ['search'];
          for (var i = 0; i < textfields.length; i++) {
            if (null != form.elements[textfields[i]])
              form.elements[textfields[i]].value = '';
          }
          var selectfields = ['status', 'review'];
          for (var i = 0; i < selectfields.length; i++) {
            if (null != form.elements[selectfields[i]])
              form.elements[selectfields[i]].selectedIndex = 0;
          }
          var radiofields = ['fulltext'];
          for (var i = 0; i < radiofields.length; i++) {
            if (null != form.elements[radiofields[i]]) {
                form.elements[radiofields[i]][1].checked = false;
            }
          }
        }
      }
      </script>
      <a title="Clear search fields" href="javascript:clear_search();"><img src="$url_clear" border="0" /></a>
EOT;
    $search .= ' <input class="submit" type="submit" value="'.tr('Search').'" />';

    $ret .= sprintf('<tr><td colspan="%d" nowrap="nowrap">', $this->cols_listing_count + 1)
            .$search.'</td></tr>';

    $ret .= '</form>';
    return $ret;
  } // buildSearchBar

  function buildListingCell (&$row, $col_index, $val = NULL) {
    $val = NULL;
    switch ($col_index) {
      case 1:
        $val .= '<a href="'.$this->page->buildLink(array('pn'=>$this->page->name, 'view' => $row['id'])).'">'
            .$this->formatText($row['lastname'].(isset($row['firstname']) ? ' '.$row['firstname']: '')).'</a>';
        break;
      case 2:
      case 6:
        return FALSE;
        break;
      case 3:
        $val = $row['email'];
        if (array_key_exists('status', $this->search)
           && ('0' === $this->search['status'] || -3 == $this->search['status'])) {
          if (isset($row['comment']))
            $val = (isset($val) ? $val.'<br />' : '')
              .$this->formatText($row['comment']);
        }
        break;
      case 4:
          $val = '&nbsp;';
          if (FALSE && array_key_exists('status', $this->search)
           && ('' === $this->search['status'] || '0' === $this->search['status']))
            $val = tr(SubscriberListing::$status_list[$row['status']]).' '.$val;
        break;
      case 5:
        $val = '<div align="right">'
          .$this->formatTimestamp($row['created'], 'd.m.y').'</div>';
        break;

    }

    return parent::buildListingCell($row, $col_index, $val);
  }

  function buildListserv () {
    $name = 'listserv';

    $action = $this->isPostback($name)
      ? $this->page->getPostValue('action')
      : $this->page->parameters['action'];

    $record = &parent::instantiateRecord();
    $record->add_fields(
      array(
        new Field(array('name'=>'email', 'type'=>'email', 'size'=>40, 'datatype'=>'char', 'maxlength'=>255, 'null'=>FALSE)),
        new Field(array('name'=>'action', 'type'=>'select', 'options' => array('add', 'change', 'delete', 'nomail', 'mail'), 'null'=>FALSE, 'default' => $action, 'nodbfield' => TRUE)),
      ));
    if ('add' == $action) {
      if ($this->is_internal) {
        $record->add_fields(
          array(
            new Field(array('name'=>'lastname', 'type'=>'text', 'size'=>40, 'datatype'=>'char', 'null' => TRUE)),
            new Field(array('name'=>'firstname', 'type'=>'text', 'size'=>40, 'datatype'=>'char', 'null' => TRUE)),
            new Field(array('name'=>'place', 'type'=>'text', 'size'=>40, 'datatype'=>'char', 'null' => TRUE)),
            new Field(array('name'=>'country', 'type'=>'text', 'size'=>3, 'datatype'=>'char', 'null' => TRUE)),
            new Field(array('name'=>'name_place', 'type'=>'text', 'size'=>40, 'datatype'=>'char', 'maxlength'=>80, 'null'=>FALSE, 'nodbfield' => TRUE)),
          ));
      }
      else {
        $querystr = "UPDATE ".$this->table
                   .' SET status = 0'
                   .' WHERE id='.$this->workflow->primaryKey().' AND status <= 0';
        $this->page->dbconn->query($querystr);

        switch ($this->page->lang()) {
          case 'de_DE':
            $ret = '<p>Vielen Dank für Ihr erneutes Interesse an Docupedia. Wir werden Ihre Anmeldung in den nächsten Tagen prüfen.</p>';
            break;
          case 'en_US':
          default:
            $ret = '<p>Thank you very much for your renewed interest in Docupedia. We\'ll review your registration form in the next couple days.</p>';
        }

        $ret .= sprintf('<p>[<a href="%s">%s</a>]</p>', $this->page->buildLink(array('pn' => $this->page->name)), tr('continue'));

        return $ret;
      }
    }
    else if ('change' == $action) {
      $record->add_fields(
        array(
                new Field(array('name'=>'email_new', 'type'=>'email', 'size'=>40, 'datatype'=>'char', 'maxlength'=>255, 'null'=>FALSE, 'nodbfield' => TRUE)),
        ));
    }
    else if ('nomail' == $action) {
      $record->add_fields(
        array(
                new Field(array('name'=>'hold', 'type'=>'date', 'datatype'=>'date', 'null'=>TRUE, 'nodbfield' => TRUE)),
        ));
    }
    else if ('mail' == $action || 'delete' == $action) {
    }
    else
      return FALSE; // invalid action

    $this->record = &$record;
    $this->form = &$this->instantiateHtmlForm('command', $this->page->buildLink(array('pn' => $this->page->name, 'listserv' => $this->workflow->primaryKey())), $name);

    $show_form = TRUE;
    $submit = $this->page->getPostValue('_submit');

    if (!empty($submit)) {
      if ($this->is_internal)
        $this->setInput();
      else {
        // avoid any spoofing
        $safe_params = array('action' => $action);
        switch ($action) {
          case 'change':
            if (array_key_exists('email_new', $_POST))
              $safe_params['email_new'] = $_POST['email_new'];
            break;
          case 'nomail':
            if (array_key_exists('hold', $_POST))
              $safe_params['hold'] = $_POST['hold'];
            break;
        }
        $this->form->set_values($safe_params);
      }

      if ('change' == $action || (!$this->is_internal && ('mail' == $action || 'nomail' == $action || 'delete' == $action))) {
        // make sure email isn't spoofed
        $dbconn = &$this->page->dbconn;
        $querystr = sprintf("SELECT email FROM User WHERE id=%d", $this->workflow->primaryKey());
        $dbconn->query($querystr);
        if ($dbconn->next_record())
          $this->form->set_value('email', $dbconn->Record['email']);
        else if ($this->is_internal)
          $this->form->set_value('email', '');
        else
          return FALSE;
      }

      if ($this->form->validate()) {
        $show_form = FALSE;

        if ('add' == $action) {
          $name_place = translit_7bit($this->form->get_value('name_place'));
          if (preg_match('/&#\d+;/', $name_place)) {
            $this->page->msg = 'The name or place may not contain any umlauts';
            $show_form = TRUE;
          }
        }
        else if ('change' == $action) {
        }

        if (!$show_form) {
          global $MAIL_SETTINGS;
          // compose the message
          switch ($action) {
            case 'add':
              $cmd = sprintf('ADD %s %s %s', LIST_NAME, $this->form->get_value('email'), $name_place);
              break;
            case 'change':
              $cmd = sprintf('CHANGE %s %s %s', LIST_NAME, $this->form->get_value('email'), $this->form->get_value('email_new'));
              break;
            case 'delete':
              $cmd = sprintf('DELETE %s %s', LIST_NAME, $this->form->get_value('email'));
              break;
            case 'mail':
              $cmd = sprintf('SET %s MAIL FOR %s', LIST_NAME, $this->form->get_value('email'));
              break;
            case 'nomail':
              $cmd = sprintf('SET %s NOMAIL FOR %s', LIST_NAME, $this->form->get_value('email'));
              break;
          }

          $headers = array('From: '.$MAIL_SETTINGS['from_listserv']);
          if (isset($MAIL_SETTINGS['bcc_listserv']))
            $headers[] = 'Bcc: '.$MAIL_SETTINGS['bcc_listserv'];

          $listserv_msg = array(
                  'to' => $MAIL_SETTINGS['listserv'],
                  'subject' => '',
                  'body' => $cmd,
                  'headers' => implode("\n", $headers)
          );

          if (send_mail($listserv_msg)) {
            $msg = tr('The following message was sent');
            $update = '';
            switch ($action) {
              case 'add':
                $update = 'status=1, subscribed=NOW(), unsubscribed=NULL, hold=NULL';
                break;
              case 'delete':
                $update = 'status=-5, unsubscribed=NOW(), hold=NULL';
                break;
              case 'mail':
                $update = 'status=1, hold=NULL, unsubscribed=NULL';
                break;
              case 'nomail':
                $hold = $this->form->get_value('hold');
                if (!empty($hold)) {
                  $hold = $this->form->field('hold')->value_internal();
                  $hold_sql = sprintf("'%04d-%02d-%02d'", $hold['year'], $hold['month'], $hold['day']);
                }
                else
                  $hold_sql = 'NULL';
                $update = 'status=2, hold='.$hold_sql;
                break;
              case 'change':
                $update = sprintf("email='%s'", $this->page->dbconn->escape_string($this->form->get_value('email_new')));
                break;
            }
            if (!empty($update)) {
              $querystr = "UPDATE ".$this->table
                          .' SET '.$update
                          .' WHERE id='.$this->workflow->primaryKey();
              $this->page->dbconn->query($querystr);
            }

          }
          else
            $msg = 'The following message could not be sent';

          $ret = '<p>'.$msg.':<tt><pre>To: '.$listserv_msg['to']
            ."\n"
            .$cmd.'</p></tt></p>';

          $ret .= sprintf('<p>[<a href="%s">%s</a>]</p>', $this->page->buildLink(array('pn' => $this->page->name)), tr('continue'));
        }
      }
      else {
        $this->invalid = $this->form->invalid();
      }
    }
    else if ($this->form->fetch(array('where' => 'id='.$this->workflow->primaryKey()))) {
      if ('add' == $action) {
        $name_place = translit_7bit(sprintf('%s %s, %s %s',
                              $this->form->get_value('firstname'),
                              $this->form->get_value('lastname'),
                              $this->form->get_value('place'),
                              $this->form->get_value('country')
                              ));

        $this->form->set_value('name_place', $name_place);
      }
    }
    else
      $show_form = FALSE;

    if ($show_form) {
      if ('change' == $action) {
        $rows['email'] = array('label' => 'Current Subscription E-mail',
                              'value' => $this->form->get_value('email'));
        $rows['email_new'] = array('label' => 'New Subscription E-mail');
      }
      else {
        $rows = array(
          'email' => array('label' => 'Subscription E-mail'),
        );
        if (!$this->is_internal)
          $rows['email']['value'] = $this->form->get_value('email');

        if ('add' == $action) {
          // Vorname Nachname, Ort
          $rows['name_place'] = array('label' => 'Firstname Lastname, Place');
        }
        else if ('nomail' == $action) {
          $rows['hold'] = array('label' => 'Reactivate on', 'show_datetimestyle' => true);
        }
      }

      $rows = array_merge(
                $rows,
                array(
                  'action' => array('label' => 'Action'),
                  $this->form->show_submit(tr('Go')),
                )
              );
      if (!$this->is_internal) // fix action
        $rows['action']['value'] = '<input name="action" type="hidden" value="'
          .$action.'" />'.$action;

      return parent::renderEditForm($rows, $name);
    }
    else {
      return !empty($ret) ? $ret : FALSE;
    }
  }

  function buildMerge () {
    $name = 'merge';

    // fetch the record that is to be removed
    $id = $this->workflow->primaryKey();
    $record = $this->instantiateRecord();
    // created is default of type function
    $record->get_field('created')->set('datatype', 'date');
    if (!$record->fetch($id))
      return FALSE;

    $action = NULL;
    if (array_key_exists('with', $this->page->parameters) && intval($this->page->parameters['with']) > 0) {
      $action = 'merge';
      $id_new = intval($this->page->parameters['with']);
    }
    if ($this->isPostback($name))
      $action = $this->page->getPostValue('action');
    else if (array_key_exists('action', $this->page->parameters))
      $action = $this->page->parameters['action'];

    $ret = FALSE;

    switch ($action) {
      case 'merge':
        $record_new = $this->instantiateRecord();
        $record_new->get_field('created')->set('datatype', 'date');
        if (!$record_new->fetch($id_new))
          return FALSE;

        $store = FALSE;

        if ($record->get_value('status') > $record_new->get_value('status')) {
          $record_new->set_value('status', $record->get_value('status'));
        }
        /* foreach(array('created', 'subscribed') as $fieldname) {
          $old = $record->get_field($fieldname)->get('value_internal');
          $new = $record_new->get_field($fieldname)->get('value_internal');

          switch ($fieldname) {
            case 'subscribed':
              $update = FALSE;

              if (isset($old)) {
                if (!isset($new)) {
                  $new = $old;
                  $update = true;
                }
                else {
                  $old_nr = sprintf('%04d%02d%02d', $old['year'], $old['month'], $old['day']);
                  $new_nr = sprintf('%04d%02d%02d', $new['year'], $new['month'], $new['day']);
                  if ($old_nr <= $new_nr) {
                    $new = $old;
                    $update = true;
                  }
                }
                if ($update) {
                  $record_new->set_fieldvalue($fieldname, 'value_internal', $new);
                  $store = TRUE;
                }
              }
              break;
            case 'unsubscribed':
              if ($record_new->get_value('status') > 0) {
                $record_new->set_fieldvalue($fieldname, 'value_internal', NULL);
                $store = TRUE;
              }
              break;
            case 'created':
              if (isset($old)) {
                $old_nr = sprintf('%04d%02d%02d', $old['year'], $old['month'], $old['day']);
                $new_nr = sprintf('%04d%02d%02d', $new['year'], $new['month'], $new['day']);
                $update = false;
                if (!isset($new_nr) || $old_nr <= $new_nr) {
                  $new = $old;
                  $update = true;
                }
                if ($update) {
                  $record_new->set_fieldvalue($fieldname, 'value_internal', $new);
                  $record_new->get_field('created')->set('noupdate', FALSE); // created is normally not updated
                  $store = TRUE;
                }
              }
          }
        } */
        // add old fields to new if empty
        foreach(array('email', 'firstname', 'lastname', 'title', 'email_work', 'institution', 'position', 'address', 'place', 'zip', 'country', 'phone', 'fax', 'supervisor', 'forum', 'sex') as $fieldname) {
          $old = $record->get_value($fieldname);
          if (!empty($old)) {
            $new = $record_new->get_value($fieldname);
            if (empty($new)) {
              $record_new->set_value($fieldname, $old);
              $store = TRUE;
            }
          }
        }

        // add old fields to new if empty
        foreach(array('expectations', 'areas', 'description', 'knownthrough', 'review_areas', 'review_suggest', 'comment') as $fieldname) {
          $old = $record->get_value($fieldname);
          if (!empty($old)) {
            $new = $record_new->get_value($fieldname);
            if (empty($new)) {
              $record_new->set_value($fieldname, $old);
            }
            else {
              $record_new->set_value($fieldname, $new.utf8_encode("\n\n=== aus gelöschtem Eintrag übernommen:\n").$old);
            }
            $store = TRUE;
          }
        }

        if ($store) {
          $record_new->set_value('changed', 'NOW()');
          $record_new->store();
        }
        $querystr = sprintf("UPDATE User SET status=%d WHERE id=%d",
                           SubscriberListing::$status_deleted, $id);
        $this->page->dbconn->query($querystr);
        $this->page->redirect(array('pn' => $this->page->name, 'edit' => intval($this->page->parameters['with'])));
        break;
      default:
        $orig = sprintf('%s %s, %s (%s)',
                  $record->get_value('firstname'),
                  $record->get_value('lastname'),
                  $record->get_value('place'),
                  $record->get_value('email'));

        $orig_confirm = sprintf('%s %s (%s) %s',
                  $record->get_value('firstname'),
                  $record->get_value('lastname'),
                  $record->get_value('email'),
                  $this->formatTimestamp(strtotime($record->get_value('created')))
                  );

        // find similar entries
        $dbconn = &$this->page->dbconn;
        $querystr = sprintf("SELECT id, firstname, lastname, email, place, status, UNIX_TIMESTAMP(created) AS created_timestamp FROM User WHERE (email = '%s' OR (lastname LIKE '%s' AND firstname LIKE '%s')) AND id<>%d AND status <> %d ORDER BY email='%s' DESC, status DESC, created DESC",
                    $dbconn->escape_string($record->get_value('email')),
                    $dbconn->escape_string($record->get_value('lastname')),                    $dbconn->escape_string($record->get_value('firstname')),
                    $id, SubscriberListing::$status_deleted,
                    $dbconn->escape_string($record->get_value('email'))
                    );
        $dbconn->query($querystr);
        $replace = '';
        $params_replace = array('pn' => $this->page->name, 'merge' => $id);
        while ($dbconn->next_record()) {
          $params_replace['with'] = $dbconn->Record['id'];
          $replace_confirm = sprintf('%s %s (%s) %s',
                  $dbconn->Record['firstname'],
                  $dbconn->Record['lastname'],
                  $dbconn->Record['email'],
                  $this->formatTimestamp($dbconn->Record['created_timestamp']));
          $confirm_msg = sprintf(
              tr('Are you sure you want to replace\\n%s\\nwith\\n%s?'),
              $orig_confirm,
              $replace_confirm);
          $replace .= '<br />'
            .$this->formatText(sprintf('%s %s, %s (%s) %s %s',
                  $dbconn->Record['firstname'],
                  $dbconn->Record['lastname'],
                  $dbconn->Record['place'],
                  $dbconn->Record['email'],
                  tr(SubscriberListing::$status_list[$dbconn->Record['status']]),
                  $this->formatTimestamp($dbconn->Record['created_timestamp'])
                  ))
            .' <input type="button" name="select" value="select" onClick="if (confirm('.sprintf("'%s'", htmlspecialchars($confirm_msg)).')) window.location.href='.sprintf("'%s'", htmlspecialchars($this->page->buildLink($params_replace))).';" />';
        }
        if (!empty($replace)) {
          $ret = '<p>'.sprintf(tr('Replace %s with'), $this->formatText($orig))
                .':'.$replace.'</p>';
        }
        else {
          $ret = '<p>TODO: '.tr('Please search for a subscriber replacing')
              .' '.$this->formatText($orig).'</p>';
        }
        // $ret .= '<p>TODO: search field</p>';
    }

    return $ret;
  }

  function buildContent () {
    if (SubscriberFlow::LISTSERV == $this->step) {
      $res = $this->buildListserv();
      if ('boolean' == gettype($res)) {
        if ($res)
          $this->step = TABLEMANAGER_VIEW;
      }
      else
        return $res;
    }
    else if (SubscriberFlow::MERGE == $this->step) {
      $res = $this->buildMerge();
      if ('boolean' == gettype($res)) {
        if ($res)
          $this->step = TABLEMANAGER_VIEW;
      }
      else
        return $res;
    }
    return parent::buildContent();
  }
}

$display = new DisplaySubscriber($page);
if (FALSE === $display->init($page))
  $page->redirect(array('pn' => ''));

$page->setDisplay($display);
