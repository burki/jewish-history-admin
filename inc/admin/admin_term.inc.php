<?php
/*
 * admin_term.inc.php
 *
 * Class for managing Thesauri-Terms
 *
 * (c) 2013 daniel.burckhardt@sur-gmbh.ch
 *
 * Version: 2013-07-29 dbu
 *
 * Changes:
 *
 */

require_once INC_PATH . 'common/tablemanager.inc.php';

class DisplayTerm extends DisplayTable
{
  var $page_size = 30;
  var $table = 'Term';
  var $fields_listing = array('id', 'name', 'created', "NULL AS status");
  var $cols_listing = array(
                            'name' => 'Term',
                            'created' => 'Created',
                            'status' => '',
                            );

  var $condition = array(
      array('name' => 'search', 'method' => 'buildLikeCondition', 'args' => 'name'),
      array('name' => 'category', 'method' => 'buildEqualCondition', 'args' => 'category', 'persist' => 'session'),
      'Term.status>=0'
  );
  var $order = array(array('ord,name'));
  var $view_after_edit = FALSE;

  function buildRecord ($name = '') {
    global $THESAURI;

    $record = parent::buildRecord($name);

    if (!isset($record))
      return;

    if (isset($_SESSION['_term']) && isset($_SESSION['_term']['category'])) {
      $category = $_SESSION['_term']['category'];
    }
    else {
      reset($THESAURI);
      list($category, $dummy) = each($THESAURI);
    }

    $record->add_fields(
      array(
        new Field(array('name' => 'id', 'type' => 'hidden', 'datatype' => 'int', 'primarykey' => TRUE)),
        new Field(array('name' => 'status', 'type' => 'hidden', 'datatype' => 'int', 'default' => 0)),
        new Field(array('name' => 'created', 'type' => 'hidden', 'datatype' => 'function', 'value' => 'NOW()', 'noupdate' => 1)),
        new Field(array('name' => 'created_by', 'type' => 'hidden', 'datatype' => 'int', 'value' => $this->page->user['id'], 'noupdate' => 1)),
        new Field(array('name' => 'changed', 'type' => 'hidden', 'datatype' => 'function', 'value' => 'NOW()')),
        new Field(array('name' => 'changed_by', 'type' => 'hidden', 'datatype' => 'int', 'value' => $this->page->user['id'])),
        new Field(array('name' => 'category', 'type' => 'hidden', 'value' => $category, 'datatype' => 'char')),
        new Field(array('name' => 'name', 'type' => 'text', 'size' => 40, 'datatype' => 'char', 'maxlength' => 255)),
      ));

    return $record;
  }

  function getEditRows ($mode = 'edit') {
    global $THESAURI;

    $category = 'edit' == $mode
      ? $this->form->get_value('category')
      : $this->record->get_value('category');
    // var_dump($this->record);
    return array(
      'id' => FALSE, 'status' => FALSE, // hidden fields
      'category' => array('label' => 'Thesaurus',
                          'value' => sprintf('<input type="hidden" name="category" value="%s" />%s',
                                             $this->htmlSpecialchars($category),
                                             $this->htmlSpecialchars(tr($THESAURI[$category])))
                          ),
      'name' => array('label' => 'Term'),

      isset($this->form) ? $this->form->show_submit(tr('Save')) : FALSE
    );
  }

  function buildListingWhere () {
    if (empty($_REQUEST['category'])) {
      if (isset($_SESSION['_term']) && !empty($_SESSION['_term']['category'])) {
        $_REQUEST['category'] = $_SESSION['_term']['category'];
      }
      else {
        // preset the first one
        global $THESAURI;
        reset($THESAURI);
        list($_REQUEST['category'], $dummy) = each($THESAURI);
      }
    }
    // var_dump($_REQUEST);
    return parent::buildListingWhere();
  }

  function buildSearchBar () {
    global $THESAURI;

    $ret = sprintf('<form action="%s" method="post" name="search">',
                   htmlspecialchars($this->page->buildLink(array('pn' => $this->page->name, 'page_id' => 0))));

    $search = sprintf('<input type="text" name="search" value="%s" size="40" />',
                      $this->htmlSpecialchars(array_key_exists('search', $this->search) ?  $this->search['search'] : ''));

    $category_options = array();
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
if (FALSE === $display->init()) {
  $page->redirect(array('pn' => ''));
}
$page->setDisplay($display);
