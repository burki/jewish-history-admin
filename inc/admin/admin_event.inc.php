<?php
/*
 * admin_event.inc.php
 *
 * Manage the event-table
 *
 * (c) 2016-2018 daniel.burckhardt@sur-gmbh.ch
 *
 * Version: 2018-07-23 dbu
 *
 * TODO:
 *
 * Changes:
 *
 */

require_once INC_PATH . 'admin/displaybackend.inc.php';

class EventFlow
extends TableManagerFlow
{
  const MERGE = 1010;
  const IMPORT = 1100;

  static $TABLES_RELATED = [
    "MediaEntity JOIN event ON CONCAT('http://d-nb.info/gnd/', event.gnd) = MediaEntity.uri AND event.id=?",
  ];

  function init ($page) {
    $ret = parent::init($page);
    if (TABLEMANAGER_DELETE == $ret) {
      $dbconn = new DB_Presentation();
      foreach (self::$TABLES_RELATED as $from_where) {
        $querystr = sprintf("SELECT COUNT(*) AS count FROM %s",
                            $from_where);
        $querystr = preg_replace('/\?/', $this->id, $querystr);
        $dbconn->query($querystr);
        if ($dbconn->next_record()
            && ($row = $dbconn->Record)
            && $row['count'] > 0)
        {
          return self::MERGE;
        }
      }
    }
    else if (TABLEMANAGER_LIST == $ret
             && isset($page->parameters['view'])
             && 'import' == $page->parameters['view'])
    {
      return self::IMPORT;
    }

    return $ret;
  }
}

class EventRecord
extends TableManagerRecord
{
  var $languages = [ 'de', 'en' ];
  var $localizedFields = [
    'alternateName' => 'name_variant',
    'description' => 'description',
  ];

  function store ($args = '') {
    foreach ($this->localizedFields as $db_field => $form_field) {
      $values = [];
      foreach ($this->languages as $language) {
        $value = $this->get_value($form_field . '_' . $language);
        if (!empty($value)) {
          $values[$language] = trim($value);
        }
      }
      $this->set_value($db_field, json_encode($values));
    }

    return parent::store($args);
  }

  function fetch ($args, $datetime_style = '') {
    $fetched = parent::fetch($args, $datetime_style);

    if ($fetched) {
      foreach ($this->localizedFields as $db_field => $form_field) {
        $values = json_decode($this->get_value($db_field), true);
        foreach ($this->languages as $language) {
          if (isset($values) && false !== $values && array_key_exists($language, $values)) {
            $this->set_value($form_field . '_' . $language, $values[$language]);
          }
        }
      }
    }

    return $fetched;
  }
}

class DisplayEvent
extends DisplayBackend
{
  var $table = 'event';
  var $fields_listing = [
    'event.id AS id',
    "event.name AS name",
    'event.gnd AS gnd',
    'event.created_at AS created',
    'event.status AS status',
  ];
  var $joins_listing = [
  ];
  var $group_by_listing = 'event.id';
  var $distinct_listing = true;
  var $order = [
    'name' => [ 'name, event.id', 'name DESC, event.id DESC' ],
    'created' => [ 'created_at DESC, event.id DESC', 'created_at, event.id' ],
  ];
  var $cols_listing = [
    'name' => 'Name',
    'gnd' => 'GND',
    'created' => 'Created',
    'status' => ''
  ];
  var $page_size = 50;
  var $search_fulltext = null;
  var $view_after_edit = true;
  var $show_xls_export = true;
  var $xls_name = 'orte';

  function __construct (&$page, $workflow = null) {
    $workflow = new EventFlow($page); // deleting may be merging
    parent::__construct($page, $workflow);

    if ('xls' == $page->display) {
      $this->cols_listing_count = count($this->fields_listing) - 1;
    }

    if ($page->lang() != 'en_US') {
      $this->datetime_style = 'DD.MM.YYYY';
    }

    $this->messages['item_new'] = tr('New Event');
    $this->search_fulltext = $this->page->getPostValue('fulltext');
    if (!isset($this->search_fulltext)) {
      $this->search_fulltext = $this->page->getSessionValue('fulltext');
    }
    $this->page->setSessionValue('fulltext', $this->search_fulltext);

    if ($this->search_fulltext) {
      $search_condition = [ 'name' => 'search', 'method' => 'buildFulltextCondition', 'args' => 'name,gnd', 'persist' => 'session' ];
    }
    else {
      $search_condition = [ 'name' => 'search', 'method' => 'buildLikeCondition', 'args' => 'name,gnd', 'persist' => 'session' ];
    }

    $this->condition = [
      sprintf('event.status <> %d', $this->status_deleted),
      [ 'name' => 'status', 'method' => 'buildStatusCondition', 'args' => 'status', 'persist' => 'session' ],
      $search_condition,
    ];
  }

  function setRecordInternal (&$record) {
  }

  function instantiateRecord ($table = '', $dbconn = '') {
    $record = new EventRecord([ 'tables' => $this->table, 'dbconn' => new DB_Presentation() ]);

    $record->add_fields([
      new Field([ 'name' => 'id', 'type' => 'hidden', 'datatype' => 'int', 'primarykey' => true ]),

      new Field([ 'name' => 'created_at', 'type' => 'hidden', 'datatype' => 'function', 'value' => 'UTC_TIMESTAMP()', 'noupdate' => true ]),
      // new Field([ 'name' => 'created_by', 'type' => 'hidden', 'datatype' => 'int', 'value' => $this->page->user['id'], 'noupdate' => true ]),
      new Field([ 'name' => 'changed_at', 'type' => 'hidden', 'datatype' => 'function', 'value' => 'UTC_TIMESTAMP()' ]),
      // new Field([ 'name' => 'changed_by', 'type' => 'hidden', 'datatype' => 'int', 'value' => $this->page->user['id'] ]),

      new Field([ 'name' => 'name', 'id' => 'name', 'type' => 'text', 'size' => 40, 'datatype' => 'char', 'maxlength' => 80 ]),

      new Field([ 'name' => 'alternateName', 'type' => 'hidden', 'datatype' => 'char', 'null' => true ]),
      new Field([ 'name' => 'name_variant_de', 'type' => 'text', 'datatype' => 'char', 'size' => 40, 'null' => true, 'nodbfield' => true ]),
      new Field([ 'name' => 'name_variant_en', 'type' => 'text', 'datatype' => 'char', 'size' => 40, 'null' => true, 'nodbfield' => true ]),

      new Field([ 'name' => 'startDate', 'id' => 'startDate', 'type' => 'text', /* 'incomplete' => true, */ 'datatype' => 'char', 'null' => true ]),
      new Field([ 'name' => 'endDate', 'id' => 'endDate', 'type' => 'text', /* 'incomplete' => true, */ 'datatype' => 'char', 'null' => true ]),

      new Field([ 'name' => 'description', 'type' => 'hidden', 'datatype' => 'char', 'null' => true ]),
      new Field([ 'name' => 'description_de', 'type' => 'textarea', 'datatype' => 'char', 'cols' => 50, 'rows' => 4, 'null' => true, 'nodbfield' => true ]),
      new Field([ 'name' => 'description_en', 'type' => 'textarea', 'datatype' => 'char', 'cols' => 50, 'rows' => 4, 'null' => true, 'nodbfield' => true ]),

      new Field([ 'name' => 'gnd', 'id' => 'gnd', 'type' => 'text', 'datatype' => 'char', 'size' => 15, 'maxlength' => 11, 'null' => true ]),

      // new Field([ 'name' => 'comment_internal', 'type' => 'textarea', 'datatype' => 'char', 'cols' => 50, 'rows' => 4, 'null' => true ]),
    ]);

    if ($this->page->isAdminUser()) {
      // admins may publish organization
      $record->add_fields([
        new Field([ 'name' => 'status', 'type' => 'hidden', 'value' => 0, 'noupdate' => !$this->is_internal, 'null' => true ]),
      ]);
    }

    return $record;
  }

  function getEditRows ($mode = 'edit') {
    $gnd_search = '';
    if (false && 'edit' == $mode) {
      $gnd_search = sprintf('<input value="GND Anfrage nach Name, Vorname" type="button" onclick="%s" /><span id="spinner"></span><br />',
                            "jQuery('#gnd').autocomplete('enable');jQuery('#gnd').autocomplete('search', jQuery('#lastname').val() + ', ' + jQuery('#firstname').val())");
    }
    $rows = [
      'id' => false, 'status' => false,
      'name' => [ 'label' => 'Name' ],
      'name_variant_de' => [
        'label' => 'Deutscher Name',
        'description' => "Please enter additional names or spellings",
      ],
      'name_variant_en' => [
        'label' => 'Englischer Name',
        'description' => "Please enter additional names or spellings",
      ],

      'gnd' => [
        'label' => 'GND-Nr',
        'description' => 'Identifikator der Gemeinsamen Normdatei, vgl. http://de.wikipedia.org/wiki/Hilfe:GND',
      ],
      (isset($this->form) ? $gnd_search . $this->form->show_submit(tr('Store')) : '')
      . '<hr noshade="noshade" />',

      'startDate' => [ 'label' => 'Start Date', 'fields' => [ 'startDate' ] ],
      'dissolutionDate' => [ 'label' => 'End Date', 'fields' => [ 'endDate' ] ],

      'description_de' => [ 'label' => 'Short Description (de)' ],
      'description_en' => [ 'label' => 'Short Description (en)' ],

      '<hr noshade="noshade" />',
      // 'comment_internal' => [ 'label' => 'Internal notes and comments'),
      (isset($this->form) ? $this->form->show_submit(tr('Store')) : ''),
    ];

    if ('edit' == $mode) {
      $this->script_url[] = 'script/moment.min.js';

      // for chosen and TGN-Calls
      $this->script_code .= <<<EOT

    function showHideFields (show, hide) {
      for (var i = 0; i < hide.length; i++) {
        // jQuery('#' + hide[i]).parent().parent().hide();
        jQuery('#' + hide[i]).parents('.container').hide();
      }
      for (var i = 0; i < show.length; i++) {
        // jQuery('#' + show[i]).parent().parent().show();
        jQuery('#' + show[i]).parents('.container').show();
      }
    }

EOT;
    }
    else {
      $gnd = $this->record->get_value('gnd');
      if (false && !empty($gnd)) {
        $this->script_url[] = 'script/seealso.js';


        $GND_LINKS = [
          'http://d-nb.info/gnd/%s' => 'Deutsche Nationalbibliothek',
        ];
        $rows['gnd']['value'] = '<ul><li>';

        $rows['gnd']['value'] .= htmlspecialchars($gnd);

        $rows['gnd']['value'] .= '</li>';

        $external = [];
        foreach ($GND_LINKS as $url => $title) {
          $url_final = sprintf($url, $gnd);
          $external[] = sprintf('<li><a href="%s" target="_blank">%s</a></li>',
                                htmlspecialchars($url_final), $this->formatText($title));

        }
        if (count($external) > 0) {
          $rows['gnd']['value'] .= implode('', $external);
        }
        $rows['gnd']['value'] .= '</ul>';

        $this->script_code .= <<<EOT
          var service = new SeeAlsoCollection();
          service.services = {
            'pndaks' : new SeeAlsoService('http://beacon.findbuch.de/seealso/pnd-aks/')
          };
          service.views = { 'seealso-ul' : new SeeAlsoUL({ /* preHTML : '<h3>Externe Angebote</h3>', */
                                                            linkTarget: '_blank',
                                                            maxItems: 100 }) };
          service.replaceTagsOnLoad();

EOT;
          $rows['gnd']['value'] .= $ret = <<<EOT
  <div title="$gnd" class="pndaks seealso-ul"></div>
EOT;

      }
    }

    // $this->setFieldDescription($rows, 'event'); // for help texts

    return $rows;
  }

  function buildViewTitle (&$record) {
    return $this->formatText($record->get_value('name'));
  }

  function buildViewFooter ($found = true) {
    $gnd = $this->record->get_value('gnd');
    $publications = !empty($gnd)
      ? $this->buildRelatedPublications('http://vocab.getty.edu/gnd/' . $gnd)
      : '';

    return $publications . parent::buildViewFooter($found);
  }

  function buildSearchBar () {
    $ret = sprintf('<form action="%s" method="post" name="search">',
                   htmlspecialchars($this->page->buildLink([ 'pn' => $this->page->name, 'page_id' => 0 ])));

    $search = sprintf('<input type="text" name="search" value="%s" size="40" />',
                      $this->htmlSpecialchars(array_key_exists('search', $this->search) ?  $this->search['search'] : ''));
    $search .= '<label><input type="hidden" name="fulltext" value="0" /><input type="checkbox" name="fulltext" value="1" '.($this->search_fulltext ? ' checked="checked"' : '').'/> '
             . $this->htmlSpecialchars(tr('Fulltext')) . '</label>';

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
    $search .= sprintf(' <input class="submit" type="submit" value="%s" />',
                       $this->htmlSpecialchars(tr('Search')));

    $ret .= sprintf('<tr><td colspan="%d" nowrap="nowrap">%s</td></tr>',
                    $this->cols_listing_count + 1,
                    $search);

    $ret .= '</form>';
    return $ret;
  } // buildSearchBar

  function doListingQuery ($page_size = 0, $page_id = 0) {
    $dbconn_orig = $this->page->dbconn;
    $this->page->dbconn = new DB_Presentation();
    $ret = parent::doListingQuery($page_size, $page_id);
    $this->page->dbconn = $dbconn_orig;
    return $ret;
  }

  function buildMerge () {
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

    $dbconn = new DB_Presentation();
    switch ($action) {
      case 'merge':
        $record_new = $this->buildRecord();
        if (!$record_new->fetch($id_new)) {
          return false;
        }

        foreach (EventFlow::$TABLES_RELATED as $table => $key_field) {
          $querystr = sprintf("UPDATE %s SET %s=%d WHERE %s=%d",
                              $table, $key_field, $id_new, $key_field, $id);
          $dbconn->query($querystr);
        }
        $this->page->redirect([ 'pn' => $this->page->name, 'delete' => $id ]);
        break;

      default:
        $orig = sprintf('%s',
                        $record->get_value('name'));
        return sprintf('%s cannot be deleted since there are entries connected to this place',
                       $orig);

        $params_replace = [ 'pn' => $this->page->name, 'delete' => $id ];
        // show replacements
        $querystr = sprintf("SELECT id, name, UNIX_TIMESTAMP(created) AS created_timestamp FROM Place WHERE id<>%d AND status >= 0 ORDER BY name, status DESC, created DESC",
                            $id);
        $dbconn->query($querystr);
        $replace = '';
        while ($dbconn->next_record()) {
          $replace .= sprintf('<option value="%d">%s</option>',
                              $dbconn->Record['id'],
                              $this->htmlSpecialchars($dbconn->Record['name'])
                             );
        }
        if (!empty($replace)) {
          $ret = sprintf('<form method="post" action="%s">',
                         htmlspecialchars($this->page->buildLink($params_replace)))
               . '<p>'
               . sprintf(tr('Assign objects belonging to %s to'), $this->formatText($orig))
               . ': <select name="with">' . $replace . '</select>'
               . sprintf(' <input type="submit" value="%s" />',
                         $this->htmlSpecialchars(tr('replace')))
               . '</p>'
               . '</form>';
        }
        // $ret .= '<p>TODO: search field</p>';
    }

    return $ret;
  }

  function buildListingCell (&$row, $col_index, $val = null) {
    $val = null;
    if ($col_index == count($this->fields_listing) - 1) {
      $url_preview = $this->page->buildLink([ 'pn' => $this->page->name, $this->workflow->name(TABLEMANAGER_VIEW) => $row[0] ]);
      $val = sprintf('<div style="text-align:right;">[<a href="%s">%s</a>]</div>',
                     htmlspecialchars($url_preview),
                     tr('view'));
    }

    return parent::buildListingCell($row, $col_index, $val);
  }


  function buildContent () {
    if (EventFlow::MERGE == $this->step) {
      $res = $this->buildMerge();
      if ('boolean' == gettype($res)) {
        if ($res) {
          $this->step = TABLEMANAGER_VIEW;
        }
      }
      else {
        return $res;
      }
    }
    if (EventFlow::IMPORT == $this->step) {
      $res = $this->buildImport();
      if ('boolean' == gettype($res)) {
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

$display = new DisplayEvent($page);
if (false === $display->init()) {
  $page->redirect([ 'pn' => '' ]);
}

$page->setDisplay($display);
