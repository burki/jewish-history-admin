<?php
/*
 * admin_author.inc.php
 *
 * Manage the authors
 *
 * (c) 2006-2017 daniel.burckhardt@sur-gmbh.ch
 *
 * Version: 2017-03-20 dbu
 *
 * Changes:
 *
 */

require_once INC_PATH . 'common/tablemanager.inc.php';
require_once INC_PATH . 'admin/common.inc.php';

class AuthorFlow extends TableManagerFlow
{
  const MERGE    = 1010;

  static $TABLES_RELATED = array(
    'editor' => array('Message'),
    'referee' => array('Message'),
    'translator' => array('Message', 'Publication'),
    'user_id' => array('MessageUser'),
  );

  var $user;
  var $is_internal = FALSE;

  function __construct ($page) {
    global $RIGHTS_EDITOR;

    $this->user = $page->user;
    $this->is_internal = 0 != ($this->user['privs'] & $RIGHTS_EDITOR);

    parent::TableManagerFlow($this->is_internal);
  }

  function init ($page) {
    // die('AuthorFlow::init()');
    if ($this->is_internal) {
      if (isset($page->parameters['merge']) && ($id = intval($page->parameters['merge'])) > 0) {
        $this->id = $id;
        return self::MERGE;
      }
      return parent::init($page);
    }
    else {
      // only view
      if (isset($page->parameters['view']) && ($id = intval($page->parameters['view'])) > 0) {
        $this->id = $id;
        return TABLEMANAGER_VIEW;
      }
      return FALSE;
    }
  }

  function primaryKey ($id = '') {
    if ($this->is_internal) {
      return parent::primaryKey($id);
    }

    // just handle own stuff
    return parent::primaryKey($id);
  }

  function advance ($step) {
    if ($this->is_internal) {
      return parent::advance($step);
    }

    // there is no listing for regular users
    return FALSE;
  }
}

class AuthorRecord extends TableManagerRecord
{
  function store ($args = '') {
    $name_parts = [];

    $lastname = $this->get_value('lastname');
    if (!empty($lastname)) {
      $name_parts[] = $lastname;
    }
    $firstname = $this->get_value('firstname');
    if (!empty($firstname)) {
      $name_parts[] = $firstname;
    }

    $slugify = new \Cocur\Slugify\Slugify();
    $this->set_value('slug', $slugify->slugify(join(', ', $name_parts), '-'));

    return parent::store($args);
  }

  function delete ($id) {
    $dbconn = $this->params['dbconn'];
    $querystr = sprintf("UPDATE %s SET status=%d WHERE id=%d",
                        $this->params['tables'],
                        AuthorListing::$status_deleted,
                        $id);
    $dbconn->query($querystr);

    return $dbconn->affected_rows() > 0;
  }
}

class AuthorQueryConditionBuilder extends TableManagerQueryConditionBuilder
{

  function buildStatusCondition () {
    $num_args = func_num_args();
    if ($num_args <= 0) {
      return;
    }

    $fields = func_get_args();

    if (isset($this->term) && '' !== $this->term) {
      $ret = $fields[0] . '=' . intval($this->term);
      if (0 == intval($this->term)) {
        // also show expired on holds
        $ret = "($ret "
          // ." OR ".$fields[0]. " = -3" // uncomment for open requests
             . " OR (" . $fields[0] . " = 2 AND hold <= CURRENT_DATE()))";
      }
      return $ret;
    }
  }
}

class DisplayAuthor extends DisplayTable
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
    parent::__construct($page, new AuthorFlow($page));

    $this->script_url[] = 'script/jquery-1.9.1.min.js';
    $this->script_url[] = 'script/jquery-noconflict.js';
    $this->script_url[] = 'script/jquery-ui-1.10.3.custom.min.js';

    $this->status_deleted = AuthorListing::$status_deleted;

    if ($page->lang() != 'en_US') {
      $this->datetime_style = 'DD.MM.YYYY';
    }
    $this->messages['item_new'] = 'New Author';
    $this->search_fulltext = $this->page->getPostValue('fulltext');
    if (!isset($this->search_fulltext)) {
      $this->search_fulltext = $this->page->getSessionValue('fulltext');
    }
    $this->page->setSessionValue('fulltext', $this->search_fulltext);

    $review = $this->page->getSessionValue('review');
    if (NULL === $review && !array_key_exists('review', $_REQUEST)) {
      // default to reviewers only
      $this->page->getSessionValue('review', $_REQUEST['review'] = 'Y');
    }

    if ($this->search_fulltext) {
      $search_condition = array('name' => 'search', 'method' => 'buildFulltextCondition', 'args' => 'lastname,firstname,email,institution,address,areas,description,review_areas', 'persist' => 'session');
    }
    else {
      $search_condition = array('name' => 'search', 'method' => 'buildLikeCondition', 'args' => 'lastname,firstname,email', 'persist' => 'session');
    }

    $this->condition = array(
      "User.status <> " . $this->status_deleted,
      array('name' => 'status', 'method' => 'buildStatusCondition', 'args' => 'status', 'persist' => 'session'),
      $search_condition,
      array('name' => 'review', 'method' => 'buildLikeCondition', 'args' => 'review', 'persist' => 'session'),
    );
  }

  function init () {
    $this->step = $this->workflow->init($this->page);

    return parent::init();
  }

  function getCountries () {
    return Countries::getAll();
  }

  function setRecordInternal (&$record) {
  }

  function instantiateRecord ($table = '', $dbconn = '') {
    global $COUNTRIES_FEATURED;

    $record =  new AuthorRecord(array('tables' => $this->table, 'dbconn' => $this->page->dbconn));

    $countries = $this->getCountries();
    $countries_ordered = array('' => tr('-- not available --'));
    if (isset($COUNTRIES_FEATURED)) {
      for ($i = 0; $i < count($COUNTRIES_FEATURED); $i++) {
        $countries_ordered[$COUNTRIES_FEATURED[$i]] = $countries[$COUNTRIES_FEATURED[$i]];
      }
      $countries_ordered['&#x2500;&#x2500;&#x2500;&#x2500;&#x2500;&#x2500;'] = FALSE; // separator
    }
    foreach ($countries as $cc => $name) {
      if (!isset($countries_ordered[$cc])) {
        $countries_ordered[$cc] = $name;
      }
    }

    $status_options = array();
    foreach (AuthorListing::$status_list as $val => $label) {
      $status_options[$val] = tr($label);
    }

    $sex_options = array('' => '--', 'F' => tr('Mrs.'), 'M' => tr('Mr.'));
    $review_options = array('' => tr('outstanding request'), 'Y' => tr('yes'), 'N' => tr('no'));

    $record->add_fields(
      array(
        new Field(array('name' => 'id', 'type' => 'hidden', 'datatype' => 'int', 'primarykey' => TRUE)),

//        new Field(array('name' => 'creator', 'type' => 'hidden', 'datatype' => 'int', 'null' => TRUE, 'noupdate' => 1)),
        new Field(array('name' => 'created', 'type' => 'hidden', 'datatype' => 'function', 'value' => 'NOW()', 'noupdate' => 1)),
/*        new Field(array('name' => 'editor', 'type' => 'hidden', 'datatype' => 'int', 'null' => TRUE)),*/
        new Field(array('name' => 'changed', 'type' => 'hidden', 'datatype' => 'function', 'value' => 'NOW()')),
        new Field(array('name' => 'changed_by', 'type' => 'hidden', 'datatype' => 'int', 'value' => $this->page->user['id'], 'null' => TRUE)),

        new Field(array('name' => 'email', 'type' => 'email', 'size' => 40, 'datatype' => 'char', 'maxlength' => 80, 'null' => TRUE, 'noupdate' => !$this->is_internal)),
        new Field(array('name' => 'status', 'type' => 'select', 'options' => array_keys($status_options), 'labels' => array_values($status_options), 'datatype' => 'int', 'default' => -10, 'noupdate' => !$this->is_internal, 'null' => !$this->is_internal)),
//        new Field(array('name' => 'status', 'type' => 'hidden', 'value' => 0, 'noupdate' => !$this->is_internal, 'null' => TRUE)),
        new Field(array('name' => 'subscribed', 'type' => 'date', 'datatype' => 'date', 'null' => TRUE, 'noupdate' => !$this->is_internal)),
        new Field(array('name' => 'unsubscribed', 'type' => 'date', 'datatype' => 'date', 'null' => TRUE, 'noupdate' => !$this->is_internal)),
        /* new Field(array('name' => 'hold', 'type' => 'date', 'datatype' => 'date', 'null' => TRUE, 'noupdate' => !$this->is_internal)), */

        new Field(array('name' => 'sex', 'type' => 'select', 'datatype' => 'char', 'options' => array_keys($sex_options), 'labels' => array_values($sex_options))),
        new Field(array('name' => 'title', 'type' => 'text', 'datatype' => 'char', 'size' => 8, 'maxlength' => 20, 'null' => TRUE)),
        new Field(array('name' => 'lastname', 'id' => 'lastname', 'type' => 'text', 'size' => 40, 'datatype' => 'char', 'maxlength' => 80)),
        new Field(array('name' => 'firstname', 'id' => 'firstname', 'type' => 'text', 'size' => 40, 'datatype' => 'char', 'maxlength' => 80, 'null' => TRUE)),
        new Field(array('name' => 'slug', 'type' => 'hidden', 'datatype' => 'char', 'null' => TRUE)),

        new Field(array('name' => 'position', 'type' => 'text', 'size' => 40, 'datatype' => 'char', 'maxlength' => 80, 'null' => TRUE)),
        new Field(array('name' => 'email_work', 'type' => 'email', 'size' => 40, 'datatype' => 'char', 'maxlength' => 80, 'null' => TRUE)),
        new Field(array('name' => 'institution', 'type' => 'text', 'size' => 40, 'datatype' => 'char', 'maxlength' => 80, 'null' => TRUE)),

        new Field(array('name' => 'address', 'type' => 'textarea', 'datatype' => 'char', 'cols' => 50, 'rows' => 5, 'null' => TRUE)),
        new Field(array('name' => 'place', 'type' => 'text', 'size' => 30, 'datatype' => 'char', 'maxlength' => 80, 'null' => TRUE)),
        new Field(array('name' => 'zip', 'type' => 'text', 'datatype' => 'char', 'size' => 8, 'maxlength' => 8, 'null' => TRUE)),
        new Field(array('name' => 'country', 'type' => 'select', 'datatype' => 'char', 'null' => TRUE, 'options' => array_keys($countries_ordered), 'labels' => array_values($countries_ordered), 'default' => 'DE', 'null' => TRUE)),

        new Field(array('name' => 'phone', 'type' => 'text', 'size' => 40, 'datatype' => 'char', 'maxlength' => 40, 'null' => TRUE)),
        new Field(array('name' => 'fax', 'type' => 'text', 'size' => 40, 'datatype' => 'char', 'maxlength' => 40, 'null' => TRUE)),
        new Field(array('name' => 'url', 'type' => 'text', 'size' => 40, 'datatype' => 'char', 'maxlength' => 255, 'null' => TRUE)),
//        new Field(array('name' => 'supervisor', 'type' => 'text', 'size' => 40, 'datatype' => 'char', 'maxlength' => 255, 'null' => TRUE)),
        new Field(array('name' => 'gnd', 'id' => 'gnd', 'type' => 'text', 'datatype' => 'char', 'size' => 15, 'maxlength' => 11, 'null' => TRUE)),
        new Field(array('name' => 'description', 'type' => 'textarea', 'datatype' => 'char', 'cols' => 50, 'rows' => 4, 'null' => TRUE)),
        new Field(array('name' => 'description_de', 'type' => 'textarea', 'datatype' => 'char', 'cols' => 50, 'rows' => 4, 'null' => TRUE)),
        new Field(array('name' => 'areas', 'type' => 'textarea', 'datatype' => 'char', 'cols' => 50, 'rows' => 4, 'null' => TRUE)),
        new Field(array('name' => 'ip', 'type' => 'hidden', 'datatype' => 'char', 'null' => TRUE, 'noupdate' => TRUE, 'value' => $_SERVER['REMOTE_ADDR'])),

    ));

    if ($this->is_internal) {
      $record->add_fields(
        array(
          new Field(array('name' => 'knownthrough', 'type' => 'text', 'size' => 40, 'datatype' => 'char', 'maxlength' => 255, 'null' => TRUE)),
          new Field(array('name' => 'expectations', 'type' => 'textarea', 'datatype' => 'char', 'cols' => 50, 'rows' => 4, 'null' => TRUE)),
          new Field(array('name' => 'forum', 'type' => 'text', 'size' => 40, 'datatype' => 'char', 'maxlength' => 255, 'null' => TRUE)),

          new Field(array('name' => 'review', 'type' => 'select', 'datatype' => 'char', 'options' => array_keys($review_options), 'labels' => array_values($review_options), 'null' => TRUE)),
          new Field(array('name' => 'review_areas', 'type' => 'textarea', 'datatype' => 'char', 'cols' => 50, 'rows' => 4, 'null' => TRUE)),
          new Field(array('name' => 'review_suggest', 'type' => 'textarea', 'datatype' => 'char', 'cols' => 50, 'rows' => 4, 'null' => TRUE)),

          new Field(array('name' => 'comment', 'type' => 'textarea', 'datatype' => 'char', 'cols' => 50, 'rows' => 4, 'null' => TRUE, 'noupdate' => !$this->is_internal)),
          new Field(array('name' => 'status_flags', 'type' => 'checkbox', 'datatype' => 'bitmap', 'null' => TRUE, 'default' => 0,
                              'labels' => array(
                                                0x01 => tr('CV') . ' ' . tr('finalized'),
                                                ),
                             ))
        ));
    }

    return $record;
  }

  function instantiateQueryConditionBuilder ($term) {
    return new AuthorQueryConditionBuilder($term);
  }

  function getEditRows ($mode = 'edit') {
    $gnd_search = '';
    if ('edit' == $mode) {
      $gnd_search = sprintf('<input value="GND Anfrage nach Name, Vorname" type="button" onclick="%s" /><span id="spinner"></span><br />',
                            "jQuery('#gnd').autocomplete('enable');jQuery('#gnd').autocomplete('search', jQuery('#lastname').val() + ', ' + jQuery('#firstname').val())");
      $this->script_ready[] = <<<EOT

    jQuery('#gnd').autocomplete({
      // source: availablePnds,
      type: 'post',
      source: './admin_ws.php?pn=person&action=lookupGnd&_debug=1',
      minChars: 2,
      search: function(event, ui) {
        if (jQuery('#gnd').autocomplete('option', 'disabled'))
          return false;

        var output = jQuery('#spinner');
        if (null != output) {
          output.html('<img src="./media/ajax-loader.gif" alt="running" />');
        }
      },
      response: function(event,ui) { // was open
        var output = jQuery('#spinner');
        if (null != output) {
          output.html('');
        }
      },
      focus: function(event, ui) {
        jQuery('#gnd').val(ui.item.value);
        return false;
      },
      change: function(event, ui) {
        var output = jQuery('#spinner');
        if (null != output) {
          output.html('');
        }
      },
      select: function(event, ui) {
        jQuery('#gnd').val(ui.item.value);
                // try to fetch more info by gnd
                jQuery.ajax({ url: './admin_ws.php?pn=person&action=fetchBiographyByGnd&_debug=1',
                              data: { gnd: ui.item.value },
                              dataType: 'json',
                              success: function (data) {
                                var mapping = {dateOfBirth: 'birthdate',
                                               placeOfBirth: 'birthplace',
                                               placeOfResidence: 'actionplace',
                                               dateOfDeath: 'deathdate',
                                               placeOfDeath: 'deathplace',
                                               academicTitle: 'title',
                                               biographicalInformation: 'occupation'};
                                for (key in mapping) {
                                  if (null != data[key]) {
                                    var field = jQuery('#' + mapping[key]);
                                    if (null != field) {
                                      var val = data[key];
                                      if (val != null && ('dateOfBirth' == key || 'dateOfDeath' == key)) {
                                        var parts = val.split(/\-/);
                                        val = val.split(/\-/).reverse().join('.');
                                      }
                                      field.val(val);
                                    }
                                  }
                                }
                              }});

        return false;
      },
      close: function(event, ui) {
        jQuery('#gnd').autocomplete('disable');
        var output = jQuery('#spinner');
        if (null != output) {
          output.html('');
        }
      }

    })
    .autocomplete('disable');
EOT;
    }

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
          $this->form->show_submit(tr('Store'))
          . '<hr noshade="noshade" />',
        ));
    }

    $rows = array_merge($rows, array(
      array(
        'label' => 'Salutation / Academic Title', 'fields' => array('sex', 'title')),
        'lastname' => array('label' => 'Last Name'),
        'firstname' => array('label' => 'First Name'),
        'position' => array('label' => 'Position'),
        'gnd' => array('label' => 'GND-Nr',
                       'description' => 'Identifikator der Gemeinsamen Normdatei, vgl. http://de.wikipedia.org/wiki/Hilfe:GND',),
        (isset($this->form) ? $gnd_search . $this->form->show_submit(tr('Store')) : '')
        . '<hr noshade="noshade" />',
        'email_work' => array('label' => 'Institutional E-Mail'),
        'institution' => array('label' => 'Institution'),
        'address' => array('label' => 'Address'),
        array('label' => 'Postcode / Place', 'fields' => array('zip', 'place')),
        'country' => array('label' => 'Country'),
        'phone' => array('label' => 'Telephone'),
        'fax' => array('label' => 'Fax'),
        '<hr noshade="noshade" />',
        'url' => array('label' => 'Homepage'),
        'description_de' => array('label' => 'Public CV (de)'),
        'description' => array('label' => 'Public CV (en)'),
        '<hr noshade="noshade" />',
        // 'supervisor' => array('label' => 'Supervisor'),
        'areas' => array('label' => 'Areas of interest'),
    ));

    if ($this->is_internal) {
      $additional = array();
      if ('edit' == $mode) {
        $status_flags = $this->form->field('status_flags');
      }
      else {
        $status_flags_value = $this->record->get_value('status_flags');
      }

      foreach (array(
                     'cv' => array('label' => 'CV', 'mask' => 0x1),
                     )
               as $key => $options)
      {
        if ('edit' == $mode) {
          $finalized = $status_flags->show($options['mask']) . '<br />';
        }
        else {
          $finalized = (0 != ($status_flags_value & $options['mask']) ? tr('finalized') . '<br />' : '');
        }
        $additional['comment_' . $key] = array(
          'label' => $options['label'],
          'value' => $finalized
            /* . ('edit' == $mode
                                    ? $this->getFormField('comment_' . $key)
                                    : $this->record->get_value('comment_' . $key)) */
        );
      }

      $rows = array_merge($rows,
        array(
        /* 'expectations' => array('label' => 'Expectations'),
        'knownthrough' => array('label' => 'How did you get to know us'),
        'forum' => array('label' => 'Other lists and fora'),
        '<hr noshade="noshade" />',  */
        'review' => array('label' => 'Willing to contribute'),
        'review_areas' => array('label' => 'Contribution areas'),
        'review_suggest' => array('label' => 'Article suggestion'),
      ));

      $rows = array_merge($rows, $additional);

      $rows = array_merge($rows,
        array(
        '<hr noshade="noshade" />',
        'comment' => array('label' => 'Internal notes and comment'),
        ));
    }
    else {
      $rows['email']['value'] = $this->record->get_value('email');
      $rows['status']['value'] = tr(AuthorListing::$status_list[$this->record->get_value('status')]);
    }

    return array_merge(
      $rows,
      array(
        $this->form->show_submit(tr('Store')),
      )
    );
  }

  function renderEditForm ($rows, $name = 'detail') {
    $changed = isset($this->id) ? $this->buildChangedBy() : '';

    return $changed . parent::renderEditForm($rows, $name);
  }

  function buildView () {
    $this->id = $this->workflow->primaryKey();

    $author = new AuthorListing($this->id);

    if (!$author->query($this->page->dbconn)) {
      return 'An error occured query-ing your data';
    }

    $ret = $author->build($this, $this->workflow->is_internal
                          ? 'admin' : 'restricted');

    if ($this->is_internal) {
      global $STATUS_OPTIONS;

      $reviews_found = FALSE; $reviews = '';

      // show all articles related to this person
      $querystr = sprintf("SELECT Message.id AS id, subject, Message.status AS status"
                          ." FROM Message INNER JOIN MessageUser ON MessageUser.message_id=Message.id"
                          ." WHERE MessageUser.user_id=%d AND Message.status <> %d"
                          ." ORDER BY Message.id DESC",
                          $this->id, STATUS_DELETED);

      $dbconn = & $this->page->dbconn;
      $dbconn->query($querystr);
      $reviews = '';
      $params_view = array('pn' => 'article');
      $reviews_found = FALSE;
      while ($dbconn->next_record()) {
        if (!$reviews_found) {
          $reviews = '<ul>';
          $reviews_found = TRUE;
        }
        $params_view['view'] = $dbconn->Record['id'];
        $reviews .= sprintf('<li id="item_%d">', $dbconn->Record['id'])
                  . sprintf('<a href="%s">%s</a> (%s)',
                            htmlspecialchars($this->page->buildLink($params_view)),
                            $this->formatText($dbconn->Record['subject']),
                            $STATUS_OPTIONS[$dbconn->Record['status']])
          . '</li>';
      }
      if ($reviews_found) {
        $reviews .= '</ul>';
      }

      if ($reviews_found) {
        $ret .= '<h2>' . tr('Article') . '</h2>'
              . $reviews;
      }
    }
    return $ret;
  }

  function buildSearchBar () {
/*
    $select_options = array('<option value="">' . tr('-- all --') . '</option>');
    foreach (AuthorListing::$status_list as $status => $label)
      if ($this->status_deleted != $status) {
        $selected = $this->search['status'] !== ''
            && $this->search['status'] == $status
            ? ' selected="selected"' : '';
        $select_options[] = sprintf('<option value="%d"%s>%s</option>', $status, $selected, htmlspecialchars(tr($label)));
      }
*/
      $ret = sprintf('<form action="%s" method="post" name="search">',
                     htmlspecialchars($this->page->buildLink(array('pn' => $this->page->name, 'page_id' => 0))));

      $search = '<input type="text" name="search" value="' . $this->htmlSpecialchars(array_key_exists('search', $this->search) ?  $this->search['search'] : '') . '" size="40" />';
      $search .= '<label><input type="hidden" name="fulltext" value="0" />'
               . '<input type="checkbox" name="fulltext" value="1" '
               . ($this->search_fulltext ? ' checked="checked"' : '')
               . '/> '
               . $this->htmlSpecialchars(tr('Fulltext'))
               . '</label>';
      /*
      $search .= '<br />' . tr('Subscription Status') . ': <select name="status">' . implode($select_options) . '</select>';
      */

      foreach (array('' => '-- all --', 'Y' => 'yes', /* 'N' => 'no', */) as $status => $label) {
        $selected = isset($this->search['review']) && $this->search['review'] !== ''
            && $this->search['review'] == $status
            ? ' selected="selected"' : '';
        $review_options[] = sprintf('<option value="%s"%s>%s</option>',
                                    $status, $selected,
                                    htmlspecialchars(tr($label)));
      }

      $search .= ' ' . tr('Willing to contribute')
               . ': <select name="review">' . implode($review_options) . '</select>';
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
    $search .= ' <input class="submit" type="submit" value="' . tr('Search') . '" />';

    $ret .= sprintf('<tr><td colspan="%d" nowrap="nowrap">', $this->cols_listing_count + 1)
            .$search.'</td></tr>';

    $ret .= '</form>';
    return $ret;
  } // buildSearchBar

  function buildListingCell (&$row, $col_index, $val = NULL) {
    $val = NULL;
    switch ($col_index) {
      case 1:
        $val .= '<a href="' . htmlspecialchars($this->page->buildLink(array('pn' => $this->page->name, 'view' => $row['id']))) . '">'
              . $this->formatText($row['lastname'] . (isset($row['firstname']) ? ' ' . $row['firstname'] : ''))
              . '</a>';
        break;

      case 2:
      case 6:
        return FALSE;
        break;

      case 3:
        $val = $row['email'];
        if (array_key_exists('status', $this->search)
           && ('0' === $this->search['status'] || -3 == $this->search['status'])) {
          if (isset($row['comment'])) {
            $val = (isset($val) ? $val . '<br />' : '')
                 . $this->formatText($row['comment']);
          }
        }
        break;

      case 4:
          $val = '&nbsp;';
          if (FALSE && array_key_exists('status', $this->search)
           && ('' === $this->search['status'] || '0' === $this->search['status']))
          {
            $val = tr(AuthorListing::$status_list[$row['status']]) . ' ' . $val;
          }
        break;

      case 5:
        $val = '<div align="right">'
             . $this->formatTimestamp($row['created'], 'd.m.y')
             . '</div>';
        break;
    }

    return parent::buildListingCell($row, $col_index, $val);
  }

  function buildMerge () {
    $name = 'merge';

    // fetch the record that is to be removed
    $id = $this->workflow->primaryKey();
    $record = $this->instantiateRecord();
    // created is default of type function
    $record->get_field('created')->set('datatype', 'date');
    if (!$record->fetch($id)) {
      return FALSE;
    }

    $action = NULL;
    if (array_key_exists('with', $this->page->parameters)
        && intval($this->page->parameters['with']) > 0)
    {
      $action = 'merge';
      $id_new = intval($this->page->parameters['with']);
    }
    if ($this->isPostback($name)) {
      $action = $this->page->getPostValue('action');
    }
    else if (array_key_exists('action', $this->page->parameters)) {
      $action = $this->page->parameters['action'];
    }

    $ret = FALSE;

    switch ($action) {
      case 'merge':
        $record_new = $this->instantiateRecord();
        $record_new->get_field('created')->set('datatype', 'date');
        if (!$record_new->fetch($id_new)) {
          return FALSE;
        }

        $store = FALSE;

        if ($record->get_value('status') > $record_new->get_value('status')) {
          $record_new->set_value('status', $record->get_value('status'));
        }
        // add old fields to new if empty
        foreach(array('email', 'firstname', 'lastname', 'title',
                      'email_work', 'institution', 'position',
                      'address', 'place', 'zip', 'country', 'phone', 'fax',
                      'supervisor',
                      'forum', 'sex') as $fieldname) {
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
        foreach(array('expectations', 'areas', 'description',
                      'knownthrough',
                      'review_areas', 'review_suggest',
                      'comment') as $fieldname) {
          $old = $record->get_value($fieldname);
          if (!empty($old)) {
            $new = $record_new->get_value($fieldname);
            if (empty($new)) {
              $record_new->set_value($fieldname, $old);
            }
            else {
              $record_new->set_value($fieldname,
                                     $new . utf8_encode("\n\n=== aus gelöschtem Eintrag übernommen:\n")
                                     . $old);
            }
            $store = TRUE;
          }
        }

        if ($store) {
          $record_new->set_value('changed', 'NOW()');
          $record_new->store();
        }

        // assign related from old to new
        foreach (AuthorFlow::$TABLES_RELATED as $field => $tables) {
          foreach ($tables as $table) {
            $querystr = sprintf("UPDATE %s SET %s=%d WHERE %s=%d",
                                $table, $field, $id_new, $field, $id);
            $this->page->dbconn->query($querystr);
          }
        }

        $querystr = sprintf("UPDATE User SET status=%d WHERE id=%d",
                           AuthorListing::$status_deleted, $id);
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
                    $id, AuthorListing::$status_deleted,
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
            . $this->formatText(sprintf('%s %s, %s (%s) %s %s',
                                        $dbconn->Record['firstname'],
                                        $dbconn->Record['lastname'],
                                        $dbconn->Record['place'],
                                        $dbconn->Record['email'],
                                        tr(AuthorListing::$status_list[$dbconn->Record['status']]),
                                        $this->formatTimestamp($dbconn->Record['created_timestamp'])
                  ))
            .' <input type="button" name="select" value="select" onClick="if (confirm(' . sprintf("'%s'", htmlspecialchars($confirm_msg)) . ')) window.location.href=' . sprintf("'%s'", htmlspecialchars($this->page->buildLink($params_replace))) . ';" />';
        }
        if (!empty($replace)) {
          $ret = '<p>'
               . sprintf(tr('Replace %s with'), $this->formatText($orig))
               . ':' . $replace
               . '</p>';
        }
        else {
          $ret = '<p>TODO: '
               . tr('Please search for an author replacelemnt')
               . ' ' . $this->formatText($orig)
               . '</p>';
        }
        // $ret .= '<p>TODO: search field</p>';
    }

    return $ret;
  }

  function buildContent () {
    if (AuthorFlow::MERGE == $this->step) {
      $res = $this->buildMerge();
      if (is_bool($res)) {
        if ($res) {
          $this->step = TABLEMANAGER_VIEW;
        }
      }
      else {
        return $res;
      }
    }
    return parent::buildContent();
  }
}

$display = new DisplayAuthor($page);
if (FALSE === $display->init($page)) {
  $page->redirect(array('pn' => ''));
}

$page->setDisplay($display);
