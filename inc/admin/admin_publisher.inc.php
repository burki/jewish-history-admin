<?php
/*
 * admin_publisher.inc.php
 *
 * Class for managing holding institutions
 *
 * (c) 2008-2018 daniel.burckhardt@sur-gmbh.ch
 *
 * Version: 2018-09-14 dbu
 *
 * Changes:
 *
 */

require_once INC_PATH . 'common/tablemanager.inc.php';
require_once INC_PATH . 'admin/common.inc.php';

class PublisherFlow
extends TableManagerFlow
{
  const MERGE = 1010;

  function init ($page) {
    $ret = parent::init($page);
    if (TABLEMANAGER_DELETE == $ret) {
      $dbconn = & $page->dbconn;
      $querystr = sprintf("SELECT COUNT(*) AS count FROM Publication WHERE publisher_id=%d AND status >= 0",
                          $this->id);
      $dbconn->query($querystr);
      if ($dbconn->next_record() && $dbconn->Record['count'] > 0) {
        return self::MERGE;
      }
    }

    return $ret;
  }
}

class PublisherRecord
extends TableManagerRecord
{

  function delete ($id) {
    global $STATUS_REMOVED;

    $dbconn = $this->params['dbconn'];
    $querystr = sprintf("UPDATE %s SET status=%d WHERE id=%d",
                        $this->params['tables'],
                        $STATUS_REMOVED,
                        $id);
    $dbconn->query($querystr);

    return $dbconn->affected_rows() > 0;
  }
}

class DisplayPublisher
extends DisplayTable
{
  var $page_size = 30;
  var $show_xls_export = true;
  var $table = 'Publisher';
  var $fields_listing = [ 'id', 'name', 'place', /* 'status' */ ];
  var $listing_default_action = TABLEMANAGER_VIEW;

  var $condition = [
    [ 'name' => 'search', 'method' => 'buildLikeCondition', 'args' => 'name,place' ],
    'Publisher.status>=0',
    // alternative: buildFulltextCondition
  ];
  var $order = [ [ 'name' ] ];
  var $view_after_edit = true;
  var $cols_listing = [ 'name' => 'Name', 'place' => 'Place' ];

  function __construct (&$page, $workflow = '') {
    parent::__construct($page, $workflow);

    if ('xls' == $this->page->display) {
      $this->page_size = -1;
    }
  }

  function init () {
    global $RIGHTS_EDITOR, $RIGHTS_ADMIN;

    if (empty($this->page->user)
        || 0 == ($this->page->user['privs'] & ($RIGHTS_ADMIN | $RIGHTS_EDITOR)))
    {
      return false;
    }

    return parent::init();
  }

  function getCountries () {
    return Countries::getAll();
  }

  function instantiateRecord ($table = '', $dbconn = '') {
    return new PublisherRecord([ 'tables' => $this->table, 'dbconn' => $this->page->dbconn ]);
  }

  function buildRecord ($name = '') {
    global $COUNTRIES_FEATURED;

    $record = parent::buildRecord($name);

    if (!isset($record)) {
      return;
    }

    $countries = $this->getCountries();
    $countries_ordered = ['' => tr('-- not available --')];
    if (isset($COUNTRIES_FEATURED)) {
      for ($i = 0; $i < count($COUNTRIES_FEATURED); $i++) {
        $countries_ordered[$COUNTRIES_FEATURED[$i]] = $countries[$COUNTRIES_FEATURED[$i]];
      }
      $countries_ordered['&#x2500;&#x2500;&#x2500;&#x2500;&#x2500;&#x2500;'] = false; // separator
    }
    foreach ($countries as $cc => $name) {
      if (!isset($countries_ordered[$cc])) {
        $countries_ordered[$cc] = $name;
      }
    }

    $record->add_fields([
      new Field([ 'name' => 'id', 'type' => 'hidden', 'datatype' => 'int', 'primarykey' => true ]),
      new Field([ 'name' => 'created', 'type' => 'hidden', 'datatype' => 'function', 'value' => 'NOW()', 'noupdate' => true ]),
      new Field([ 'name' => 'created_by', 'type' => 'hidden', 'datatype' => 'int', 'value' => $this->page->user['id'], 'null' => true, 'noupdate' => true ]),
      new Field([ 'name' => 'changed', 'type' => 'hidden', 'datatype' => 'function', 'value' => 'NOW()' ]),
      new Field([ 'name' => 'changed_by', 'type' => 'hidden', 'datatype' => 'int', 'value' => $this->page->user['id'], 'null' => true ]),
      new Field([ 'name' => 'name', 'type' => 'text', 'size' => 40, 'datatype' => 'char', 'maxlength' => 127 ]),
      // new Field([ 'name' => 'domicile', 'type' => 'text', 'size' => 40, 'datatype' => 'char', 'maxlength' => 127, 'null' => true ]),
      // new Field([ 'name' => 'isbn', 'type' => 'text', 'size' => 40, 'datatype' => 'char', 'maxlength' => 127, 'null' => true ]),

      new Field([ 'name' => 'address', 'type' => 'textarea', 'datatype' => 'char', 'cols' => 50, 'rows' => 2, 'null' => true ]),
      new Field([ 'name' => 'place', 'type' => 'text', 'size' => 30, 'datatype' => 'char', 'maxlength' => 80, 'null' => true ]),
      new Field([ 'name' => 'zip', 'type' => 'text', 'datatype' => 'char', 'size' => 8, 'maxlength' => 8, 'null' => true ]),
      new Field([ 'name' => 'country', 'type' => 'select', 'datatype' => 'char', 'null' => true,
                  'options' => array_keys($countries_ordered),
                  'labels' => array_values($countries_ordered), 'default' => 'DE', 'null' => true ]),

      new Field(['name' => 'phone', 'type' => 'text', 'size' => 40, 'datatype' => 'char', 'maxlength' => 40, 'null' => true ]),
      new Field(['name' => 'fax', 'type' => 'text', 'size' => 40, 'datatype' => 'char', 'maxlength' => 40, 'null' => true ]),
      new Field(['name' => 'url', 'type' => 'text', 'size' => 40, 'datatype' => 'char', 'maxlength' => 255, 'null' => true ]),
      new Field(['name' => 'email', 'type' => 'email', 'size' => 40, 'datatype' => 'char', 'maxlength' => 80, 'null' => true ]),
      new Field(['name' => 'url', 'type' => 'text', 'size' => 40, 'datatype' => 'char', 'maxlength' => 255, 'null' => true ]),
      new Field(['name' => 'gnd', 'id' => 'gnd', 'type' => 'text', 'datatype' => 'char', 'size' => 15, 'maxlength' => 11, 'null' => true ]),
      new Field(['name' => 'name_contact', 'type' => 'text', 'size' => 40, 'datatype' => 'char', 'maxlength' => 127, 'null' => true ]),
      new Field(['name' => 'phone_contact', 'type' => 'text', 'size' => 40, 'datatype' => 'char', 'maxlength' => 80, 'null' => true ]),
      new Field(['name' => 'fax_contact', 'type' => 'text', 'size' => 40, 'datatype' => 'char', 'maxlength' => 40, 'null' => true ]),
      new Field(['name' => 'email_contact', 'type' => 'email', 'size' => 40, 'datatype' => 'char', 'maxlength' => 80, 'null' => true ]),
      new Field(['name' => 'comments_internal', 'type' => 'textarea', 'datatype' => 'char', 'cols' => 50, 'rows' => 4, 'null' => true ]),
    ]);

    return $record;
  }

  function renderEditForm ($rows, $name = 'detail') {
    $changed = isset($this->id) ? $this->buildChangedBy() : '';

    return $changed . parent::renderEditForm($rows, $name);
  }

  function getEditRows ($mode = 'edit') {
    $rows = [
      'id' => false, 'status' => false, // hidden fields

      'name' => [ 'label' => 'Name' ],
      // 'domicile' => [ 'label' => 'Domicile of the publisher' ],
      // 'isbn' => [ 'label' => 'ISBN prefix(es)' ],

      // '<hr noshade="noshade" />',

      'address' => [ 'label' => 'Address' ],
      [ 'label' => 'Postcode / Place', 'fields' => [ 'zip', 'place' ] ],
      'country' => [ 'label' => 'Country' ],
      'email' => [ 'label' => 'E-Mail (general)' ],
      'phone' => [ 'label' => 'Telephone (general)' ],
      'fax' => [ 'label' => 'Fax (general)' ],
      'url' => [ 'label' => 'Homepage' ],
      'gnd' => [
        'label' => 'GND-Nr',
        'description' => 'Identifikator der Gemeinsamen Normdatei, vgl. http://de.wikipedia.org/wiki/Hilfe:GND',
      ],

      '<hr noshade="noshade" />'
      // . '<b>' . tr('Review department') . '</b>'
      ,
      'name_contact' => [ 'label' => 'Contact person(s)' ],
      'email_contact' => [ 'label' => 'E-Mail' ],
      'phone_contact' => [ 'label' => 'Telephone' ],
      'fax_contact' => [ 'label' => 'Fax' ],

      '<hr noshade="noshade" />',
      'comments_internal' => [ 'label' => 'Internal notes and comments' ],

      isset($this->form) ? $this->form->show_submit(ucfirst(tr('save'))) : false
    ];

    if ('view' == $mode) {
      $gnd = $this->record->get_value('gnd');
      if (!empty($gnd)) {
        $rows['gnd']['value'] = sprintf('<a href="http://d-nb.info/gnd/%s" target="_blank">%s</a>',
                                        $gnd, $gnd);
      }
    }

    return $rows;
  }

  function getViewFormats () {
    // return [ 'body' => [ 'format' => 'p' ] ];
  }

  function buildViewRows () {
    $rows = $this->getEditRows('view');
    if (isset($rows['name'])) {
      unset($rows['name']);
    }

    $formats = $this->getViewFormats();

    $view_rows = [];

    foreach ($rows as $key => $descr) {
      if ($descr !== false && gettype($key) == 'string') {
        if (isset($formats[$key])) {
          $descr = array_merge($descr, $formats[$key]);
        }
        $view_rows[$key] = $descr;
      }
    }

    return $view_rows;
  }

  function renderView ($record, $rows) {
    $ret = '';
    if (!empty($this->page->msg)) {
      $ret .= '<p class="message">' . $this->page->msg . '</p>';
    }

    $fields = [];
    if (is_array($rows)) {
      foreach ($rows as $key => $row_descr) {
        if ('string' == gettype($row_descr)) {
          $fields[] = ['&nbsp;', $row_descr];
        }
        else {
          $label = isset($row_descr['label']) ? tr($row_descr['label']).':' : '';
          // var_dump($row_descr);
          if (isset($row_descr['fields'])) {
            $value = '';
            foreach ($row_descr['fields'] as $field) {
              $field_value = $record->get_value($field);
              $field_value = 'p' == $row_descr['format']
                ? $this->formatParagraphs($field_value) : $this->formatText($field_value);
              $value .= (!empty($value) ? ' ' : '').$field_value;
            }
          }
          else if (isset($row_descr['value'])) {
            $value = $row_descr['value'];
          }
          else {
            $field_value = $record->get_value($key);
            $value = isset($row_descr['format']) && 'p' == $row_descr['format']
                ? $this->formatParagraphs($field_value) : $this->formatText($field_value);
          }

          $fields[] = [$label, $value];
        }
      }
    }

    if (count($fields) > 0) {
      $ret .= $this->buildContentLineMultiple($fields);
    }

    return $ret;
  }

  function buildEditButton () {
    $this->script_code .= <<<EOT
  function setPublisher (form, id, name) {
    var option = new Option();
    option.value = id;
    option.text = name;
    option.selected = true;
    form.publisher_id.options[0] = option;
    window.close();
  }
EOT;

    return
      sprintf(' <span class="regular">[<a href="%s">%s</a>]</span>',
              htmlspecialchars($this->page->buildLink(['pn' => $this->page->name, $this->workflow->name(TABLEMANAGER_EDIT) => $this->id])),
              tr($this->workflow->name(TABLEMANAGER_EDIT)))
      . sprintf(' <span class="regular">[<a href="%s">%s</a>]</span>',
              htmlspecialchars($this->page->buildLink(['pn' => $this->page->name, 'delete' => $this->id])),
              tr($this->workflow->name(TABLEMANAGER_DELETE)))
      . sprintf('<script>document.write(null != window.opener && null != window.opener.document.detail ? " <span class=\"regular\">[<a href=\"#\" onclick=\"setPublisher(window.opener.document.detail, %d, \'%s\')\">set holding institution</a>]</span>" : "")</script>',
                $this->record->get_value('id'),
                htmlspecialchars(preg_replace('/\\\\/', "\\\\\\\\", addslashes($this->record->get_value('name')))));

  }

  function buildView () {
    $this->id = $this->workflow->primaryKey();
    $record = $this->buildRecord();
    if ($found = $record->fetch($this->id, $this->datetime_style)) {
      $this->record = &$record;
      $uploadHandler = $this->instantiateUploadHandler();
      if (isset($uploadHandler)) {
        $this->processUpload($uploadHandler);
      }

      $rows = $this->buildViewRows();
      $edit = $this->buildEditButton();

      $ret = '<h2>'
           . $this->formatText($record->get_value('name'))
           . ' ' . $edit
           . '</h2>';

      $ret .= $this->renderView($record, $rows);

      if (isset($uploadHandler)) {
        $ret .= $this->renderUpload($uploadHandler);
      }

    }

    return $ret;
  }

  function buildSearchBar () {
    $ret = sprintf('<form action="%s" method="post">',
                   htmlspecialchars($this->page->buildLink(['pn' => $this->page->name, 'page_id' => 0])));
    if ($this->cols_listing_count > 0) {
      $ret .= sprintf('<tr><td colspan="%d" nowrap="nowrap"><input type="text" name="search" value="%s" size="40" /><input class="submit" type="submit" value="%s" /></td></tr>',
                      $this->cols_listing_count,
                      $this->htmlSpecialchars($this->search['search']),
                      tr('Search'));
    }

    $ret .= '</form>';

    return $ret;
  } // buildSearchBar

  function buildListingRow (&$row) {
    if ('xls' == $this->page->display) {
      $xls_row = [];
      for ($i = 0; $i < $this->cols_listing_count; $i++) {
        $xls_row[] = $row[$i];
      }
      $this->xls_data[] = $xls_row;
    }
    else {
      return parent::buildListingRow($row);
    }
  }

  function buildMerge () {
    global $STATUS_REMOVED;

    $name = 'merge';

    // fetch the record that is to be removed
    $id = $this->workflow->primaryKey();
    $record = $this->buildRecord();
    // created is default of type function
    // $record->get_field('created')->set('datatype', 'date');
    if (!$record->fetch($id)) {
      return false;
    }

    $action = null;
    if (array_key_exists('with', $_POST)
        && intval($_POST['with']) > 0)
    {
      $action = 'merge';
      $id_new = intval($_POST['with']);
    }
    $ret = false;

    switch ($action) {
      case 'merge':
        $record_new = $this->buildRecord();
        if (!$record_new->fetch($id_new)) {
          return false;
        }

        $querystr = sprintf("UPDATE Publication SET publisher_id=%d WHERE publisher_id=%d",
                            $id_new, $id);
        $this->page->dbconn->query($querystr);
        $this->page->redirect(['pn' => $this->page->name, 'delete' => $id]);
        break;

      default:
        $orig = sprintf('%s%s',
                  $record->get_value('name'),
                  '' != $record->get_value('place')
                  ? ' (' .$record->get_value('place') . ')'
                  : '');

        // show replacements
        $dbconn = &$this->page->dbconn;
        $querystr = sprintf("SELECT id, name, place, status, UNIX_TIMESTAMP(created) AS created_timestamp FROM Publisher WHERE id<>%d AND status <> %d ORDER BY name, status DESC, created DESC",
                            $id, $STATUS_REMOVED);
        $dbconn->query($querystr);
        $replace = '';
        $params_replace = ['pn' => $this->page->name, 'delete' => $id];
        while ($dbconn->next_record()) {
          $replace .= sprintf('<option value="%d">%s</option>',
                              $dbconn->Record['id'],
                              $this->htmlSpecialchars($dbconn->Record['name']
                                  . (!empty($dbconn->Record['place']) ? ' (' . $dbconn->Record['place'] . ')': '')));
        }
        if (!empty($replace)) {
          $ret = '<form method="post" action="'.htmlspecialchars($this->page->buildLink($params_replace)).'"><p>' . sprintf(tr('Assign publications belonging to %s to'), $this->formatText($orig))
               . ': <select name="with">' . $replace . '</select><input type="submit" value="'. tr('replace') . '" /></p></form>';
        }
        // $ret .= '<p>TODO: search field</p>';
    }

    return $ret;
  }

  function buildContent () {
    if (PublisherFlow::MERGE == $this->step) {
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

$display = new DisplayPublisher($page, new PublisherFlow($page));
if (false === $display->init()) {
  $page->redirect(['pn' => '']);
}

$page->setDisplay($display);
