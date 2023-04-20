<?php
/*
 * admin_term.inc.php
 *
 * Class for managing Thesauri-Terms
 *
 * (c) 2013-2023 daniel.burckhardt@sur-gmbh.ch
 *
 * Version: 2023-04-20 dbu
 *
 * Changes:
 *
 */

require_once INC_PATH . 'common/tablemanager.inc.php';

class DisplayTerm
extends DisplayTable
{
  var $page_size = 30;
  var $table = 'Term';
  var $fields_listing = [ 'id', 'name', 'created', "NULL AS status" ];
  var $cols_listing = [
    'name' => 'Term',
    'created' => 'Created',
    'status' => '',
  ];

  var $condition = [
    [ 'name' => 'search', 'method' => 'buildLikeCondition', 'args' => 'name' ],
    [ 'name' => 'category', 'method' => 'buildEqualCondition', 'args' => 'category', 'persist' => 'session' ],
    'Term.status>=0'
  ];
  var $order = [ [ 'ord,name' ] ];
  var $view_after_edit = false;

  function buildRecord ($name = '') {
    global $THESAURI;

    $record = parent::buildRecord($name);

    if (!isset($record)) {
      return;
    }

    if (isset($_SESSION['_term']) && isset($_SESSION['_term']['category'])) {
      $category = $_SESSION['_term']['category'];
    }
    else {
      reset($THESAURI);
      $category = key($THESAURI);
    }

    $record->add_fields([
      new Field([ 'name' => 'id', 'type' => 'hidden', 'datatype' => 'int', 'primarykey' => true ]),
      new Field([ 'name' => 'status', 'type' => 'hidden', 'datatype' => 'int', 'default' => 0 ]),
      new Field([ 'name' => 'created', 'type' => 'hidden', 'datatype' => 'function', 'value' => 'NOW()', 'noupdate' => true ]),
      new Field([ 'name' => 'created_by', 'type' => 'hidden', 'datatype' => 'int', 'value' => $this->page->user['id'], 'noupdate' => true ]),
      new Field([ 'name' => 'changed', 'type' => 'hidden', 'datatype' => 'function', 'value' => 'NOW()' ]),
      new Field([ 'name' => 'changed_by', 'type' => 'hidden', 'datatype' => 'int', 'value' => $this->page->user['id'] ]),
      new Field([ 'name' => 'category', 'type' => 'hidden', 'value' => $category, 'datatype' => 'char' ]),
      new Field([ 'name' => 'name', 'type' => 'text', 'size' => 40, 'datatype' => 'char', 'maxlength' => 255 ]),
    ]);

    return $record;
  }

  function getEditRows ($mode = 'edit') {
    global $THESAURI;

    $category = 'edit' == $mode
      ? $this->form->get_value('category')
      : $this->record->get_value('category');
    // var_dump($this->record);

    return [
      'id' => false, 'status' => false, // hidden fields
      'category' => [
        'label' => 'Thesaurus',
        'value' => sprintf('<input type="hidden" name="category" value="%s" />%s',
                           $this->htmlSpecialchars($category),
                           $this->htmlSpecialchars(tr($THESAURI[$category])))
      ],
      'name' => [ 'label' => 'Term' ],

      isset($this->form) ? $this->form->show_submit(tr('Save')) : false
    ];
  }

  function buildListingWhere () {
    if (empty($_REQUEST['category'])) {
      if (isset($_SESSION['_term']) && !empty($_SESSION['_term']['category'])) {
        $_REQUEST['category'] = $_SESSION['_term']['category'];
      }
      else {
        // preset the first one
        global $THESAURI;

        $_REQUEST['category'] = array_key_first($THESAURI);
      }
    }

    // var_dump($_REQUEST);
    return parent::buildListingWhere();
  }

  function buildSearchBar () {
    global $THESAURI;

    $ret = sprintf('<form action="%s" method="post" name="search">',
                   htmlspecialchars($this->page->buildLink(['pn' => $this->page->name, 'page_id' => 0])));

    $search = sprintf('<input type="text" name="search" value="%s" size="40" />',
                      $this->htmlSpecialchars(array_key_exists('search', $this->search) ?  $this->search['search'] : ''));

    $category_options = [];
    foreach ($THESAURI as $status => $label) {
      $selected = isset($this->search['category'])
        && $this->search['category'] == $status
          ? ' selected="selected"' : '';
      $category_options[] = sprintf('<option value="%s"%s>%s</option>',
                                    $status, $selected,
                                    $this->htmlSpecialchars(tr($label)));
    }
    $search .= ' <select name="category">' . implode($category_options) . '</select>';

    $search .= sprintf(' <input class="submit" type="submit" value="%s" />',
                       $this->htmlSpecialchars(tr('Search')));

    $ret .= sprintf('<tr><td colspan="%d" nowrap="nowrap">%s</td></tr>',
                    $this->cols_listing_count + 1,
                    $search);

    $ret .= '</form>';

    return $ret;
  } // buildSearchBar
}

$display = new DisplayTerm($page);
if (false === $display->init()) {
  $page->redirect(['pn' => '']);
}
$page->setDisplay($display);
