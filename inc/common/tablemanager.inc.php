<?php
/*
 * tablemanager.inc.php
 *
 * Base class to administrate Database items
 *
 * (c) 2006-2018 daniel.burckhardt@sur-gmbh.ch
 *
 * Version: 2018-05-22 dbu
 *
 * TODO:
 *       the models TableManagerRecord->fetch()-method shouldn't need a style
 *       only presentation FormHTML
 *
 * Changes:
 *
 */

require_once LIB_PATH . 'db_forms.php';

// major modes
define('TABLEMANAGER_NOACCESS', -1);
define('TABLEMANAGER_LIST', 0);
define('TABLEMANAGER_VIEW', 10);
define('TABLEMANAGER_EDIT', 20);
define('TABLEMANAGER_DELETE', 30);

// minor modes
define('TABLEMANAGER_EDIT_SUBMITTED', 21);
define('TABLEMANAGER_EDIT_VALIDATED', 22);
define('TABLEMANAGER_EDIT_STORED', 23);

class TableManagerFlow
{
  var $id;
  var $view_after_edit;

  function __construct ($view_after_edit = false) {
    $this->view_after_edit = $view_after_edit;
  }

  function TableManagerFlow ($view_after_edit = false) {
    self::__construct($view_after_edit);
  }

  function init ($page) {
    if (isset($page->parameters['edit']) && ($id = intval($page->parameters['edit'])) != 0) {
      if ($id > 0) {
        $this->id = $id;
      }

      return TABLEMANAGER_EDIT;
    }

    if (isset($page->parameters['view']) && ($id = intval($page->parameters['view'])) > 0) {
      $this->id = $id;

      return TABLEMANAGER_VIEW;
    }

    if (isset($page->parameters['delete']) && ($id = intval($page->parameters['delete'])) > 0) {
      $this->id = $id;

      return TABLEMANAGER_DELETE;
    }

    return TABLEMANAGER_LIST;
  }

  function advance ($step) {
    if ($this->view_after_edit && TABLEMANAGER_EDIT == $step) {
      return TABLEMANAGER_VIEW;
    }

    return TABLEMANAGER_LIST;
  }

  function primaryKey ($id = '') {
    if (!empty($id)) {
      $this->id = $id;
    }

    return $this->id;
  }

  function name ($step) {
    switch ($step) {
      case TABLEMANAGER_VIEW:
        return 'view';
        break;

      case TABLEMANAGER_EDIT:
        return 'edit';
        break;

      case TABLEMANAGER_DELETE:
        return 'delete';
        break;

      default:
        return 'list';
        break;
    }
  }
}

class TableManagerQueryConditionBuilder
{
  var $term;

  function __construct ($term) {
    $this->term = $term;
  }

  function TableManagerQueryConditionBuilder ($term) {
    self::__construct($term);
  }

  static function mysqlParseFulltextBoolean ($search) {
    include_once LIB_PATH . 'simpletest_parser.php';

    if (preg_match("/[\+\-][\b\"]/", $search)) {
      return $search;
    }

    $parser = new MysqlFulltextSimpleParser();
    $lexer = new SimpleLexer($parser);
    $lexer->addPattern("\\s+");
    $lexer->addEntryPattern('"', 'accept', 'writeQuoted');
    $lexer->addPattern("\\s+", 'writeQuoted');
    $lexer->addExitPattern('"', 'writeQuoted');

    // do it
    $lexer->parse($search);

    return isset($parser->output) ? $parser->output : '';
  }

  function buildLikeCondition() {
    if (empty($this->term)) {
      return;
    }

    $num_args = func_num_args();
    if ($num_args <= 0) {
      return;
    }
    $fields = func_get_args();

    $parts = preg_split('/\s+/', $this->term);
    if (count($parts) == 0) {
      return;
    }
    $and_parts = [];
    for ($i = 0; $i < count($parts); $i++) {
      $or_parts = [];
      for ($j = 0; $j < $num_args; $j++) {
        $or_parts[] = $fields[$j] . " LIKE '%" . addslashes($parts[$i]) . "%'";
      }
      $and_parts[] = '(' . implode(' OR ', $or_parts) . ')';
    }
    return implode(' AND ', $and_parts);
  }

  function buildEqualCondition () {
    if (empty($this->term)) {
      return;
    }

    $num_args = func_num_args();
    if ($num_args <= 0) {
      return;
    }

    $fields = func_get_args();

    $or_parts = [];
    for ($j = 0; $j < $num_args; $j++) {
      $or_parts[] = $fields[$j] . " = '" . addslashes($this->term) . "'";
    }
    return count($or_parts) > 1
      ? '(' . implode(' OR ', $or_parts) . ')'
      : $or_parts[0];
  }

  function buildFulltextCondition () {
    if (empty($this->term)) {
      return;
    }

    $num_args = func_num_args();
    if ($num_args <= 0) {
      return;
    }
    $fields = func_get_args();

    $fulltext_sql = addslashes(self::mysqlParseFulltextBoolean($this->term));

    return 'MATCH (' . implode(', ', $fields) . ") AGAINST ('" . $fulltext_sql . "' IN BOOLEAN MODE)";
  }
} // class TableManagerQueryConditionBuilder

class TableManagerRecord extends RecordSQL
{
  function fetch ($args, $datetime_style = '') {
    return parent::fetch($args, $datetime_style);
  }
}

class DisplayTable extends PageDisplay
{
  var $step;
  var $modeminor;  // if $mode is TABLEMANAGER_EDIT, we can have several minor modes (edited, validated, ...)
  var $postback = false; // set to true if we post a form from this screen
  var $clear_postback = false; // $this->isPostback() -> false if $clear_postback == true

  var $active_conn;

  var $table;
  var $primary_key = 'id';
  var $datetime_style = 'MM/DD/YYYY';
  var $sql_calc_found_rows = true;

  var $messages = ['item_new' => 'New Item'];

  var $page_size = -1;
  var $show_xls_export = false;
  var $show_record_count = false;

  var $fields;
  var $distinct_listing = false;
  var $joins_listing;
  var $group_by_listing;
  var $fields_listing;
  var $cols_listing; // e.g. array('name' => 'Name')
  var $cols_listing_count;
  var $idcol_listing = false;

  var $record;
  var $form;

  var $id;                // value of the primary key field
  var $search;            // search-fields
  var $paging;
  var $order;             // the different orderings of the listing
  var $order_index;
  var $condition;

  function __construct (&$page, $workflow = '') {
    if (defined('DATETIME_STYLE')) {
      $this->datetime_style = tr(DATETIME_STYLE);
    }

    parent::__construct($page);
    $this->workflow = gettype($workflow) == 'object'
      ? $workflow
      : new TableManagerFlow($this->is_internal && (isset($this->view_after_edit) && $this->view_after_edit));
  }

  function init () {
    $this->step = $this->workflow->init($this->page);

    if ($this->step == TABLEMANAGER_NOACCESS) {
      return false;
    }

    if ($this->isPostback()) {
      $this->step = TABLEMANAGER_EDIT;
    }

    $this->record = $this->buildRecord($this->workflow->name($this->step));
    list($advance, $this->modeminor) = $this->process();
    if ($advance) {
// echo $this->step.'->'.$this->workflow->advance($this->step);
      $this->clear_postback = true; // force refetch after store
      $this->step = $this->workflow->advance($this->step);
      if (false !== $this->step) {
        $this->record = $this->buildRecord($this->workflow->name($this->step));
      }
    }

    return $this->step;
  }

  function isPostback ($name = '') {
    if ($_SERVER['REQUEST_METHOD'] != 'POST' || $this->clear_postback) {
      return false;
    }
    return isset($_POST['_postback']) && !empty($_POST['_postback'])
      ? (!empty($name) ? $name == $_POST['_postback'] : true)
      : false;
  }

  function buildRecord ($name = '') {
    if ('list' == $name) {
      return;
    }

    return $this->instantiateRecord($this->table);
  }

  function instantiateRecord ($table = '', $dbconn = '') {
    return new TableManagerRecord(
      ['tables' => !empty($table) ? $table : $this->table,
            'dbconn'=> !empty($dbconn) ? $dbconn : $this->page->dbconn]
    );
  }

  function instantiateHtmlForm ($name = 'detail', $action = '', $method = 'post') {
    $params = ['method' => 'post', 'name' => $name];
    if (!empty($action)) {
      $params['action'] = $action;
    }
    $params['datetime_style'] = $this->datetime_style;

    return new FormHTML($params, $this->record);
  }

  function setInput ($values = null) {
    if (null === $values) {
      $values = & $_POST;
    }
    $this->form->set_values($values);
  }

  function validateInput () {
    return $this->form->validate();
  }

  function store () {
    $minor = TABLEMANAGER_EDIT_VALIDATED;

    $form = &$this->form; // save some typing
    if ($form->store() > 0) {
      $this->id = $form->get_value($this->primary_key);
      if (isset($this->workflow)) {
        $this->workflow->primaryKey($this->id);
      }
      $minor = TABLEMANAGER_EDIT_STORED;
    }
    else {
      $this->id = $form->get_value($this->primary_key);
    }

    return $minor;
  }

  function process () {
    // now check if it was submitted
    if (!$this->isPostback()) {
      return [false, 0];
    }

    if (!isset($this->record)) {
      die('TableManagerRecord::record is not set');
    }

    $minor = TABLEMANAGER_EDIT_SUBMITTED;

    $this->form = $this->instantiateHtmlForm();

    $this->setInput();

    $this->invalid = [];
    if ($this->validateInput()) {
      $minor = $this->store(); // try to store it
    }
    else {
      $this->id = $this->form->get_value($this->primary_key);
      $this->invalid = array_merge($this->form->invalid(), $this->invalid);
    }

    return $minor == TABLEMANAGER_EDIT_STORED
      ? [true, $this->workflow->advance(TABLEMANAGER_EDIT)]
      : [false, $minor];
  } // process

  function message ($msg_name, $lang = null) {
    $ret = isset($this->messages[$msg_name]) ? $this->messages[$msg_name] : $msg_name;
    return is_array($ret) ? array_map('tr', $ret) : tr($ret);
  }

  function renderEditFormHiddenFields ($name) {
    return '<input type="hidden" name="_postback" value="' . $this->htmlSpecialchars($name) . '" />';
  }

  function renderEditForm ($rows, $name = 'detail') {
    $ret = '';
    if (!empty($this->page->msg)) {
      $ret .= '<p class="message">' . $this->page->msg . '</p>';
    }
    $ret .= $this->form->show_start()
          . $this->renderEditFormHiddenFields($name)
          ;
    $fields = [];
    if ('array' == gettype($rows)) {
      foreach ($rows as $key => $row_descr) {
        if (isset($this->invalid[$key])) {
          $error = $this->invalid[$key];
          $msg = $this->form->error_fulltext($error, $this->page->lang());
        }
        if ('boolean' == gettype($row_descr)) {
          $value = $this->getFormField($key);
          if ($row_descr) {
            $fields[] = ['', $value];
          }
          else {
            $ret .= $value;
          }
        }
        else if ('string' == gettype($row_descr)) {
          $fields[] = ['&nbsp;', $row_descr];
        }
        else {
          $label = isset($row_descr['label']) ? tr($row_descr['label']).':' : '';
          if (!empty($label)) {
            $required = false;
            if (isset($row_descr['required'])) {
              $required = $row_descr['required'];
            }
            else {
              // query field to find if it is required
              $field = $this->form->field($key);
              if (isset($field)) {
                $null = $field->get('null');
                $required = !isset($null) || !$null;
              }
            }
            if ($required) {
              $label = $this->buildRequired($label);
            }
            if (isset($row_descr['show_datetimestyle']) && $row_descr['show_datetimestyle']) {
              $label .= '<div class="leftSmaller">(' . tr($this->formatText($this->datetime_style)) . ')</div>';
            }
          }
          if (isset($row_descr['fields'])) {
            $value = '';
            foreach ($row_descr['fields'] as $field) {
              $value .= (!empty($value) ? ' ' : '').$this->getFormField($field);
            }
          }
          else if (isset($row_descr['value'])) {
            $value = $row_descr['value'];
          }
          else {
            $value = $this->getFormField($key);
          }
          $fields[] = [$label, $value];
        }
      }
    }
    if (count($fields) > 0) {
      $ret .= $this->buildContentLineMultiple($fields);
    }

    $ret .= $this->form->show_end();

    return $ret;
  }

  function getEditRows () {
    return ['id' => true, '' => $this->form->show_submit('Store')];
  }

  function buildFormAction () {
    return $this->page->buildLink(['pn' => $this->page->name,
                                        $this->workflow->name(TABLEMANAGER_EDIT) => isset($this->id) ? $this->id : -1]);
  }

  function buildEdit ($name = 'detail') {
    if (!isset($this->record)) {
      return false;
    }

    // check if we need to fetch the data from the DB
    $fetch = $this->workflow->primaryKey() > 0 && !$this->isPostback($name);

    if ($fetch && ($res = $this->record->fetch($this->workflow->primaryKey(), $this->datetime_style)) <= 0) {
      return false;
    }

    if ($fetch) {
      $this->id = $this->workflow->primaryKey();
    }

    $this->postback = $name;

    // here we can start to build-up the HTML-Form
    $action = $this->buildFormAction();
    if (!isset($this->form)) {
      $this->form = $this->instantiateHtmlForm($name, $action);
    }
    else {
      $this->form->params['action'] = $action;
    }

    return $this->renderEditForm($this->getEditRows(), $name);
  }

  // functions to build up a query:
  // SELECT *Fields* FROM *Tables* WHERE *Where* ORDER BY *Order*
  function buildListingFields () {
    if (isset($this->record)) {
      $fieldnames = $this->record->get_fieldnames();

      for ($i = 0; $i < count($fieldnames); $i++) {
        $field = $this->record->get_field($fieldnames[$i]);
        switch ($field->get('type')) {
          case 'date':
          case 'datetime':
            $format = $field->get('format');
            if (isset($format)) {
              $fieldnames[$i] = "DATE_FORMAT(" . $fieldnames[$i] . ", '$format') AS " . $fieldnames[$i];
            }
            break;
          default:
            $expression = $field->get('expression');
            if (!empty($expression)) {
              $fieldnames[$i] = $field->get('expression') . ' AS ' . $fieldnames[$i];
            }
        }
      }
      $this->fields_listing = $fieldnames;
    }
    else if (false !== $this->fields_listing) {
      if (isset($this->fields_listing)) {
        $fieldnames = $this->fields_listing;
      }
      else if (!empty($this->table)) {
        // query database for fields
        $res = $this->page->dbconn->metadata($this->table);
        $fieldnames = [];
        for ($i = 0; $i < count($res); $i++) {
          $field = &$res[$i];
          $fieldname = $field['name'];
          switch ($field['type']) {
            case 'date':
            case 'datetime':
            case 'timestamp':
              $fieldnames[$i] = "DATE_FORMAT($fieldname, '%Y-%m-%d %H:%i:%s') AS $fieldname";
              break;
            default:
              $fieldnames[$i] = $fieldname;
          }
        }
        $this->fields_listing = $fieldnames;
      }
    }
    if (!isset($fieldnames)) {
      $fieldnames = ['*']; // get all if nothing else is specified
    }

    return $fieldnames;
  }

  function buildListingTables () {
    if (!isset($this->record)) {
      return [$this->table];
    }

    $tables = $this->record->params['tables'];
    if (!is_array($tables)) {
      return [$tables];
    }
    return $tables;
  }

  function buildListingJoins () {
    return $this->joins_listing;
  }

  function instantiateQueryConditionBuilder ($term) {
    return new TableManagerQueryConditionBuilder($term);
  }

  function buildListingWhere () {
    $where = '';
    $search_terms = [];
    if (isset($this->condition)) {
      $conditions = [];
      for ($i = 0; $i < count($this->condition); $i++) {
        if ('string' == gettype($this->condition[$i])) {
          $conditions[] = $this->condition[$i];
        }
        else if ('array' == gettype($this->condition[$i])) {
          $condition = & $this->condition[$i];
          if (isset($condition['name'])) {
            $name = $condition['name'];
            $value = array_key_exists($name, $_REQUEST) ? $_REQUEST[$name] : '';

            if (array_key_exists('persist', $condition)) {
              if (!array_key_exists($name, $_REQUEST)) {
                $value = $this->page->getSessionValue($name);
              }
              if (!isset($value)) {
                $value = '';
              }

              $this->page->setSessionValue($name, $value);
            }

            $search_terms[$name] = $value;
            if (isset($condition['method'])) {
              $conditionBuilder = $this->instantiateQueryConditionBuilder($value);
              if (method_exists($conditionBuilder, $condition['method'])) {
                $condition = call_user_func_array([&$conditionBuilder, $condition['method']],
                                                     preg_split('/\\s*\\,\\s*/', $condition['args']));
                if (isset($condition)) {
                  $conditions[] = $condition;
                }
              }
            }
          }
        }
      }
      $where = join(' AND ', $conditions);
    }
    return [$where, $search_terms];
  }

  function buildListingGroupBy () {
    return $this->group_by_listing;
  }

  function buildListingOrder () {
    if (!isset($this->order)) {
      return;
    }

    $order = & $this->order;

    $orders = array_keys($order);
    if (count($orders) == 0) {
      return;
    }

    $current_order = $this->page->getSessionValue('order');
    if (!empty($current_order)) {
      $order_index = $this->page->getSessionValue('order_index');
    }
    else {
      $order_index = 0;
    }
// var_dump($current_order);
// var_dump($order_index);

    if (isset($_REQUEST['sort']) && isset($order[$_REQUEST['sort']])) {
      // wish for new sort
      $new_order = $_REQUEST['sort'];
    }
    else if (!empty($current_order) && isset($order[$current_order])) {
      // change order of existing sort
      $new_order = $current_order;
    }
    else {
      // default sort order
      $new_order = $orders[0];
    }

    if (isset($_REQUEST['sort']) && isset($order[$_REQUEST['sort']])) {
      // wish for new sort
      if ($current_order == $_REQUEST['sort']
          && $order_index >= 0 && $order_index + 1 < count($order[$new_order]))
      {
        ++$order_index;
      }
      else {
        $order_index = 0;
      }
    }

    $this->page->setSessionValue('order', $new_order);
    $this->page->setSessionValue('order_index', $order_index);

    return [$order[$new_order][$order_index], $new_order, $order_index];
  }

  function buildListingQuery () {
    list($where, $search_terms) = $this->buildListingWhere();
    list($order_by, $order, $order_index) = $this->buildListingOrder();

    $group_by = $this->buildListingGroupBy();

    $fields = $this->buildListingFields();

    $querystr = "SELECT "
              . ($this->distinct_listing ? 'DISTINCT ' : '')
              . implode(', ', $fields)
              . ' FROM ' . implode(', ', $this->buildListingTables());
    $joins = $this->buildListingJoins();
    if (isset($joins) && count($joins) > 0) {
      $querystr .= ' ' . implode(' ', $joins);
    }
    if (!empty($where)) {
      $querystr .= ' WHERE ' . $where;
    }
    if (!empty($group_by)) {
      $querystr .= ' GROUP BY ' . $group_by;
    }
    if (!empty($order_by)) {
      $querystr .= ' ORDER BY ' . $order_by;
    }

// var_dump($querystr);

    return [$querystr, $search_terms, $order, $order_index];
  }

  function doListingQuery ($page_size = 0, $page_id = 0) {
    list($querystr, $this->search, $this->order_active, $this->order_index) = $this->buildListingQuery();

    if ($page_size > 0) {
      $this->paging = ['page_id' => 0];
      if ($this->sql_calc_found_rows) {
        // Replace "SELECT" by "SELECT SQL_CALC_FOUND_ROWS"
        if (!preg_match('/\\bSQL_CALC_FOUND_ROWS\\b/', $querystr)) {
          $querystr = preg_replace('/SELECT\\b/i', 'SELECT SQL_CALC_FOUND_ROWS', $querystr);
        }
      }
      $querystr .= ' LIMIT ' . ($page_id * $page_size) . ', ' . $page_size;
      // fetch those rows
      $rows = [];
      $dbconn = &$this->page->dbconn;
      $dbconn->query($querystr);
      while ($dbconn->next_record()) {
        $rows[] = $dbconn->Record;
      }

      if ($this->sql_calc_found_rows) {
        $querystr = "SELECT FOUND_ROWS() AS found_rows";
        $result = $dbconn->query($querystr);
        if ($dbconn->next_record()) {
          $count = $dbconn->Record['found_rows'];
        }
      }
      $dbconn->free();

      // calc some stuff for paged resultsets
      $page_count = $count >= 0 ? intval($count / $page_size) + ($count % $page_size > 0 ? 1 : 0) : -1;
      if ($page_id < $page_count) {
        $this->paging['page_id'] = $page_id;
      }
      $this->paging['page_count'] = $page_count;

      if ($count >= 0 && $page_id + 1 <= $page_count) {
        $current_end = ($page_id + 1) * $page_size;
        if ($current_end > $count) {
          $current_end = $count;
        }
        $this->paging['record_start'] = ($page_id * $page_size) + 1;
        $this->paging['record_end'] = $current_end;
        $this->paging['record_count'] = $count;
      }
      return $rows;
    }
    else {
      // just query - the calling method will fetch the rows
      $this->active_conn = new DB();
      $this->active_conn->query($querystr);
    }
  }

  // the render functions

  function buildSearchBar () {
    $ret = sprintf('<form action="%s" method="post">', $this->page->buildLink(['pn' => $this->page->name, 'page_id' => 0]));
    if ($this->cols_listing_count > 0) {
      $ret .= sprintf('<tr><td colspan="%d" nowrap="nowrap"><input type="text" name="search" value="%s" size="40" /><input class="submit" type="submit" value="%s" /></td></tr>',
                      $this->cols_listing_count,
                      $this->htmlSpecialchars(array_key_exists('search', $this->search) ?  $this->search['search'] : ''),
                      $this->htmlSpecialchars(tr('Search')));
    }

    $ret .= '</form>';

    return $ret;
  } // buildSearchBar

  function buildListingExport () {
    $export = '';
    if ($this->show_xls_export) {
      $export = sprintf('[<a href="%s">%s</a>]',
                        htmlspecialchars($this->page->buildLink(['pn' => $this->page->name, 'view' => 'xls'])),
                        $this->htmlSpecialchars(tr('export')));
    }
    return $export;
  }

  function buildListingAdd () {
    return sprintf('[<a href="%s">%s</a>]',
                   htmlspecialchars($this->page->buildLink(['pn' => $this->page->name, $this->workflow->name(TABLEMANAGER_EDIT) => -1])),
                   tr('add'));
  }

  function buildListingAddItem () {
    $ret = '';

    if ($this->cols_listing_count > 0) {
      if ($this->show_record_count || $this->show_xls_export) {
        $total = '';

        if ($this->show_record_count && isset($this->paging['record_count'])) {
          list($one, $many) = $this->message('record_count');
          $total = $this->paging['record_count'] . ' ' . (1 == $this->paging['record_count'] ? $one : $many);
        }

        $export = $this->buildListingExport();

        $ret .= sprintf('<tr><td>%s</td><td colspan="%d" align="right">%s</td></tr>',
                        $total,
                        $this->cols_listing_count - 1,
                        $export);
      }

      $button_add = $this->buildListingAdd();
      $msg_add = !empty($button_add) ? $this->message('item_new') : '';


      $ret .= sprintf('<tr class="Add"><td class="listing" colspan="%d">%s</td>'
                      . '<td class="listing">%s</td></tr>',
                      $this->cols_listing_count - 1,
                      $msg_add, $button_add);
    }

    return $ret;
  }

  function buildListingTopActions () {
    return $this->buildSearchBar();
  }

  function buildPaging () {
    if (!isset($this->paging) || !($this->cols_listing_count > 0)) {
      return '';
    }

    if ($this->paging['page_count'] <= 1 && !$this->show_xls_export) {
      return '';
    }

    // var_dump($this->paging);
    $page_params = ['pn' => $this->page->name];
    if (array_key_exists('search', $this->search)) {
      $page_params['search'] = $this->search['search'];
    }

    $page_select = '<select name="page_id" onChange="this.form.submit()">';
    for ($page_id = 0; $page_id < $this->paging['page_count']; $page_id++) {
      $page_select .= sprintf('<option value="%d"%s>%d</option>',
                              $page_id,
                              $page_id == $this->paging['page_id'] ? ' selected="selected"' : '',
                              $page_id + 1);
    }
    $page_select .= '</select>' . '/' . $this->paging['page_count'];

    $row = sprintf('<form action="%s" method="post">',
                   htmlspecialchars($this->page->buildLink($page_params)))
         . tr('Result Page') . ': '
         . ($this->paging['page_id'] > 0
            ? '<a href="' . htmlspecialchars($this->page->buildLink(array_merge($page_params, ['page_id' => $this->paging['page_id'] - 1]))) . '">&lt; ' . tr('Previous') . '</a> '
            : '')
         . $page_select
         . ($this->paging['page_id'] < $this->paging['page_count'] - 1
           ? ' <a href="' . htmlspecialchars($this->page->buildLink(array_merge($page_params, ['page_id' => $this->paging['page_id'] + 1]))) . '">' . tr('Next') . ' &gt;</a>' : '')
         . '</form>';

    $colspan = $this->cols_listing_count;

    return sprintf('<tr class="listing"><td class="listing" colspan="%d" nowrap="nowrap" style="text-align: center;">%s</td></tr>',
                   $colspan, $row);
  }

  function buildListingColHeaders () {
    if (!isset($this->cols_listing)) {
      return '';
    }

    $headers = & $this->cols_listing;
    $ret = '<tr>';
    $col_names = array_keys($headers);
    for ($i = 0; $i < count($col_names); $i++) {
      $col_name = $col_names[$i];
      $header = $this->formatText(tr($headers[$col_name]));
      if (array_key_exists($col_name, $this->order)) {
        $header = sprintf('<a href="%s">%s</a>',
                          htmlspecialchars($this->page->buildLink(['pn' => $this->page->name, 'page_id' => 0, 'sort' => $col_name])),
                          $header);
      }
      $ret .= '<th>' . $header . '</th>';
    }
    $ret .= '</tr>';

    return $ret;
  }

  function buildListingTop ($may_add = true) {
    $ret = '<table class="listing" cellspacing="0">';

    if (!isset($this->cols_listing_count)) {
      if (isset($this->cols_listing)) {
        $this->cols_listing_count = count($this->cols_listing);
      }
      else if (isset($this->fields_listing)) {
        $this->cols_listing_count = count($this->fields_listing) - 1; // subtract one for the primary-key linking
      }
    }

    $ret .= $this->buildListingTopActions()
          . $this->buildPaging()
          . $this->buildListingColHeaders()
          . ($may_add ? $this->buildListingAddItem() : '');

    return $ret;
  }

  function buildListingCell (&$row, $col_index, $val = null) {
    // expect primary-key in first field, "title" in the second and merge those two:
    if (!$this->idcol_listing && 0 == $col_index) {
      return '';
    }

    $cell = isset($val) ? $val : $this->htmlSpecialchars($row[$col_index]);
    if (1 == $col_index) {
      $cell = sprintf('<a href="%s">%s</a>',
                      htmlspecialchars($this->page->buildLink(['pn' => $this->page->name, $this->workflow->name(isset($this->listing_default_action) ? $this->listing_default_action : TABLEMANAGER_EDIT) => $row[0]])), $cell);
    }

    return '<td class="listing">' . $cell . '</td>';
  }

  function buildListingRow (&$row) {
    if (isset($this->fields_listing)) {
      $ret = '<tr class="listing">';
      $count = count($this->fields_listing);
      for ($i = 0; $i < $count; $i++) {
        $ret .= $this->buildListingCell($row, $i);
      }
      $ret .= '</tr>';
    }
    return $ret;
  }

  function buildListingBottomActions () {
    return $this->buildPaging();
  }

  function buildListingBottom () {
    return $this->buildListingBottomActions()
         . '</table>';
  }

  function buildListing ($page_size = 15, $page_id = 0) {
    $content = '';

    if ($page_size > 0) {
      $rows  = $this->doListingQuery($page_size, $page_id);
      if (isset($rows)) {
        $content .= $this->buildListingTop();
        foreach ($rows as $row) {
          $content .= $this->buildListingRow($row);
        }
        $content .= $this->buildListingBottom();
      }
    }
    else {
      $this->doListingQuery();
      if (isset($this->active_conn)) {
        $content .= $this->buildListingTop();
        while ($this->active_conn->next_record()) {
          $content .= $this->buildListingRow($this->active_conn->Record);
        }
        $content .= $this->buildListingBottom();
        unset($this->active_conn);
      }
    }
    return $content;
  }

  function doDelete () {
    if (!isset($this->record)) {
      return false;
    }

    return $this->record->delete($this->workflow->primaryKey());
  }

  function buildContent () {
    if ($this->step == TABLEMANAGER_DELETE) {
      $this->doDelete();
      $this->step = TABLEMANAGER_LIST;
      $this->record = $this->buildRecord($this->workflow->name($this->step));
    }
    else if ($this->step == TABLEMANAGER_EDIT) {
      $ret = $this->buildEdit();
      if (false === $ret) {
        $this->step = TABLEMANAGER_LIST;
        $this->record = $this->buildRecord($this->workflow->name($this->step));
      }
      else {
        return $ret;
      }
    }
    else if ($this->step == TABLEMANAGER_VIEW) {
      $ret = $this->buildView();
      if (false === $ret) {
        $this->step = TABLEMANAGER_LIST;
        $this->record = $this->buildRecord($this->workflow->name($this->step));
      }
      else {
        return $ret;
      }
    }

    if ($this->step != TABLEMANAGER_EDIT && $this->step != TABLEMANAGER_VIEW) {
      return $this->buildListing($this->page_size, $this->page->getRequestValue('page_id', ['persist' => 'session']));
    }
  } // buildContent

} // class DisplayTable
