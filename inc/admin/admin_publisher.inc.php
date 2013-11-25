<?php
/*
 * admin_publisher.inc.php
 *
 * Class for managing publishers
 *
 * (c) 2008-2013 daniel.burckhardt@sur-gmbh.ch
 *
 * Version: 2013-11-25 dbu
 *
 * Changes:
 *
 */

require_once INC_PATH . 'common/tablemanager.inc.php';
require_once INC_PATH . 'admin/common.inc.php';

class PublisherFlow extends TableManagerFlow
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

class PublisherRecord extends TableManagerRecord {

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

class DisplayPublisher extends DisplayTable
{
  var $page_size = 30;
  var $show_xls_export = TRUE;
  var $table = 'Publisher';
  var $fields_listing = array('id', 'name', 'domicile'); // , 'status');
  var $listing_default_action = TABLEMANAGER_VIEW;

  var $condition = array(
      array('name' => 'search', 'method' => 'buildLikeCondition', 'args' => 'name,domicile'),
      'Publisher.status>=0',
      // alternative: buildFulltextCondition
  );
  var $order = array(array('name'));
  var $view_after_edit = TRUE;
  var $cols_listing = array('name' => 'Name', 'place' => 'Place');

  function __construct (&$page, $workflow = '') {
    parent::__construct($page, $workflow);

    if ('xls' == $this->page->display)
      $this->page_size = -1;
  }

  function getCountries () {
    return Countries::getAll();
  }

  function instantiateRecord ($table = '', $dbconn = '') {
    return new PublisherRecord(array('tables' => $this->table, 'dbconn' => $this->page->dbconn));
  }

  function buildRecord ($name = '') {
    global $COUNTRIES_FEATURED;

    $record = parent::buildRecord($name);

    if (!isset($record))
      return;

    $countries = $this->getCountries();
    $countries_ordered = array('' => tr('-- not available --'));
    if (isset($COUNTRIES_FEATURED)) {
      for ($i = 0; $i < sizeof($COUNTRIES_FEATURED); $i++) {
        $countries_ordered[$COUNTRIES_FEATURED[$i]] = $countries[$COUNTRIES_FEATURED[$i]];
      }
      $countries_ordered['&#x2500;&#x2500;&#x2500;&#x2500;&#x2500;&#x2500;'] = FALSE; // separator
    }
    foreach ($countries as $cc => $name) {
      if (!isset($countries_ordered[$cc]))
        $countries_ordered[$cc] = $name;
    }

    $record->add_fields(array(
      new Field(array('name'=>'id', 'type'=>'hidden', 'datatype'=>'int', 'primarykey'=>1)),
      new Field(array('name'=>'created', 'type'=>'hidden', 'datatype'=>'function', 'value'=>'NOW()', 'noupdate' => TRUE)),
      new Field(array('name'=>'created_by', 'type'=>'hidden', 'datatype'=>'int', 'value' => $this->page->user['id'], 'null'=>1, 'noupdate' => TRUE)),
      new Field(array('name'=>'changed', 'type'=>'hidden', 'datatype'=>'function', 'value'=>'NOW()')),
      new Field(array('name'=>'changed_by', 'type'=>'hidden', 'datatype'=>'int', 'value' => $this->page->user['id'], 'null'=>1)),
      new Field(array('name'=>'name', 'type'=>'text', 'size'=>40, 'datatype'=>'char', 'maxlength'=>127)),
      new Field(array('name'=>'domicile', 'type'=>'text', 'size'=>40, 'datatype'=>'char', 'maxlength'=>127, 'null'=>1)),
      new Field(array('name'=>'isbn', 'type'=>'text', 'size'=>40, 'datatype'=>'char', 'maxlength'=>127, 'null'=>1)),

      new Field(array('name'=>'address', 'type'=>'textarea', 'datatype'=>'char', 'cols'=>50, 'rows' => 2, 'null' => TRUE)),
      new Field(array('name'=>'place', 'type'=>'text', 'size' => 30, 'datatype'=>'char', 'maxlength' => 80, 'null'=>TRUE)),
      new Field(array('name'=>'zip', 'type'=>'text', 'datatype'=>'char', 'size'=>8, 'maxlength'=>8, 'null'=>TRUE)),
      new Field(array('name'=>'country', 'type'=>'select', 'datatype'=>'char', 'null' => TRUE,
                      'options'=>array_keys($countries_ordered), 'labels'=>array_values($countries_ordered), 'default' => 'DE', 'null' => TRUE)),

      new Field(array('name'=>'phone', 'type'=>'text', 'size'=>40, 'datatype'=>'char', 'maxlength'=>40, 'null'=>TRUE)),
      new Field(array('name'=>'fax', 'type'=>'text', 'size'=>40, 'datatype'=>'char', 'maxlength'=>40, 'null'=>TRUE)),
      new Field(array('name'=>'url', 'type'=>'text', 'size'=>40, 'datatype'=>'char', 'maxlength'=>255, 'null'=>TRUE)),
      new Field(array('name'=>'email', 'type'=>'email', 'size'=>40, 'datatype'=>'char', 'maxlength'=>80, 'null'=>1)),
      new Field(array('name'=>'url', 'type'=>'text', 'size'=>40, 'datatype'=>'char', 'maxlength'=>255, 'null'=>1)),
      new Field(array('name'=>'name_contact', 'type'=>'text', 'size'=>40, 'datatype'=>'char', 'maxlength'=>127, 'null'=>1)),
      new Field(array('name'=>'phone_contact', 'type'=>'text', 'size'=>40, 'datatype'=>'char', 'maxlength'=>80, 'null'=>1)),
      new Field(array('name'=>'fax_contact', 'type'=>'text', 'size'=>40, 'datatype'=>'char', 'maxlength'=>40, 'null'=>TRUE)),
      new Field(array('name'=>'email_contact', 'type'=>'email', 'size'=>40, 'datatype'=>'char', 'maxlength'=>80, 'null'=>1)),
      new Field(array('name'=>'comments_internal', 'type'=>'textarea', 'datatype'=>'char', 'cols'=>50, 'rows' => 4, 'null' => TRUE)),

    ));

    return $record;
  }

  function renderEditForm ($rows, $name = 'detail') {
    $changed = isset($this->id) ? $this->buildChangedBy() : '';

    return $changed . parent::renderEditForm($rows, $name);
  }

  function getEditRows () {
    return array(
      'id' => FALSE, 'status' => FALSE, // hidden fields

      'name' => array('label' => 'Name'),
      'domicile' => array('label' => 'Domicile of the publisher'),
      'isbn' => array('label' => 'ISBN prefix(es)'),

      '<hr noshade="noshade" />',

      'address' => array('label' => 'Address'),
      array('label' => 'Postcode / Place', 'fields' => array('zip', 'place')),
      'country' => array('label' => 'Country'),
      'email' => array('label' => 'E-Mail (general)'),
      'phone' => array('label' => 'Telephone (general)'),
      'fax' => array('label' => 'Fax (general)'),
      'url' => array('label' => 'Homepage'),

      '<hr noshade="noshade" /><b>'.tr('Review department').'</b>',
      'name_contact' => array('label' => 'Contact person(s)'),
      'email_contact' => array('label' => 'E-Mail'),
      'phone_contact' => array('label' => 'Telephone'),
      'fax_contact' => array('label' => 'Fax'),

      '<hr noshade="noshade" />',
      'comments_internal' => array('label' => 'Internal notes and comments'),

      isset($this->form) ? $this->form->show_submit(ucfirst(tr('save'))) : 'FALSE'
    );
  }

  function getViewFormats () {
    // return array('body' => array('format' => 'p'));
  }

  function buildViewRows () {
    $rows = $this->getEditRows();
    if (isset($rows['name']))
      unset($rows['name']);

    $formats = $this->getViewFormats();

    $view_rows = array();

    foreach ($rows as $key => $descr) {
      if ($descr !== FALSE && gettype($key) == 'string') {
        if (isset($formats[$key]))
          $descr = array_merge($descr, $formats[$key]);
        $view_rows[$key] = $descr;
      }
    }

    return $view_rows;
  }

  function renderView ($record, $rows) {
    $ret = '';
    if (!empty($this->page->msg))
      $ret .= '<p class="message">' . $this->page->msg . '</p>';

    $fields = array();
    if ('array' == gettype($rows)) {
      foreach ($rows as $key => $row_descr) {
        if ('string' == gettype($row_descr))
          $fields[] = array('&nbsp;', $row_descr);
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
          else if (isset($row_descr['value']))
            $value = $row_descr['value'];
          else {
            $field_value = $record->get_value($key);
            $value = isset($row_descr['format']) && 'p' == $row_descr['format']
                ? $this->formatParagraphs($field_value) : $this->formatText($field_value);
          }

          $fields[] = array($label, $value);
        }
      }
    }
    if (sizeof($fields) > 0)
      $ret .= $this->buildContentLineMultiple($fields);

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
              htmlspecialchars($this->page->buildLink(array('pn' => $this->page->name, $this->workflow->name(TABLEMANAGER_EDIT) => $this->id))),
              tr($this->workflow->name(TABLEMANAGER_EDIT)))
      . sprintf(' <span class="regular">[<a href="%s">%s</a>]</span>',
              htmlspecialchars($this->page->buildLink(array('pn' => $this->page->name, 'delete' => $this->id))),
              tr($this->workflow->name(TABLEMANAGER_DELETE)))
      . sprintf('<script>document.write(null != window.opener && null != window.opener.document.detail ? " <span class=\"regular\">[<a href=\"#\" onclick=\"setPublisher(window.opener.document.detail, %d, \'%s\')\">set publisher</a>]</span>" : "")</script>',
                $this->record->get_value('id'),
                htmlspecialchars(preg_replace('/\\\\/', "\\\\\\\\", addslashes($this->record->get_value('name')))));

  }

  function buildView () {
    $this->id = $this->workflow->primaryKey();
    $record = $this->buildRecord();
    if ($found = $record->fetch($this->id)) {
      $this->record = &$record;
      $uploadHandler = $this->instantiateUploadHandler();
      if (isset($uploadHandler))
        $this->processUpload($uploadHandler);

      $rows = $this->buildViewRows();
      $edit = $this->buildEditButton();

      $ret = '<h2>' . $this->formatText($record->get_value('name')).' '.$edit . '</h2>';

      $ret .= $this->renderView($record, $rows);

      if (isset($uploadHandler))
        $ret .= $this->renderUpload($uploadHandler);

    }

    return $ret;
  }

  function buildSearchBar () {
    $ret = sprintf('<form action="%s" method="post">',
                   htmlspecialchars($this->page->buildLink(array('pn' => $this->page->name, 'page_id' => 0))));
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
      $xls_row = array();
      for ($i = 0; $i < $this->cols_listing_count; $i++) {
        $xls_row[] = $row[$i];
      }
      $this->xls_data[] = $xls_row;
    }
    else
      return parent::buildListingRow($row);
  }

  function buildMerge () {
    global $STATUS_REMOVED;

    $name = 'merge';

    // fetch the record that is to be removed
    $id = $this->workflow->primaryKey();
    $record = $this->buildRecord();
    // created is default of type function
    // $record->get_field('created')->set('datatype', 'date');
    if (!$record->fetch($id))
      return FALSE;
    $action = NULL;
    if (array_key_exists('with', $_POST)
        && intval($_POST['with']) > 0) {
      $action = 'merge';
      $id_new = intval($_POST['with']);
    }
    $ret = FALSE;

    switch ($action) {
      case 'merge':
        $record_new = $this->buildRecord();
        if (!$record_new->fetch($id_new))
          return FALSE;

        $querystr = sprintf("UPDATE Publication SET publisher_id=%d WHERE publisher_id=%d",
            $id_new, $id);
        $this->page->dbconn->query($querystr);
        $this->page->redirect(array('pn' => $this->page->name, 'delete' => $id));
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
        $params_replace = array('pn' => $this->page->name, 'delete' => $id);
        while ($dbconn->next_record()) {
          $replace .= sprintf('<option value="%d">%s</option>',
                              $dbconn->Record['id'],
                              $this->htmlSpecialchars($dbconn->Record['name']
                                  . (!empty($dbconn->Record['place']) ? ' (' . $dbconn->Record['place'] . ')': '')));
        }
        if (!empty($replace)) {
          $ret = '<form method="post" action="'.htmlspecialchars($this->page->buildLink($params_replace)).'"><p>' . sprintf(tr('Assign publications belonging to %s to'), $this->formatText($orig))
                .': <select name="with">' . $replace . '</select><input type="submit" value="'. tr('replace') . '" /></p></form>';
        }
        // $ret .= '<p>TODO: search field</p>';
    }

    return $ret;
  }

  function buildContent () {
    if (PublisherFlow::MERGE == $this->step) {
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

$display = new DisplayPublisher($page, new PublisherFlow($page));
if (FALSE === $display->init())
  $page->redirect(array('pn' => ''));

$page->setDisplay($display);
