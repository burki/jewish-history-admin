<?php
/*
 * admin_organization.inc.php
 *
 * Manage the organization-table
 *
 * (c) 2016 daniel.burckhardt@sur-gmbh.ch
 *
 * Version: 2016-08-23 dbu
 *
 * TODO:
 *
 * Changes:
 *
 */

require_once INC_PATH . 'admin/displaybackend.inc.php';

class OrganizationFlow extends TableManagerFlow
{
  const MERGE = 1010;
  const IMPORT = 1100;

  static $TABLES_RELATED = array("MediaEntity JOIN organization ON CONCAT('http://d-nb.info/gnd/', organization.gnd) = MediaEntity.uri AND organization.id=?");

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

class OrganizationRecord extends TableManagerRecord
{
  var $languages = array('de', 'en');

  function store ($args = '') {
    $alternateName = array();
    foreach ($this->languages as $language) {
      $value = $this->get_value('name_variant_' . $language);
      if (!empty($value)) {
        $alternateName[$language] = trim($value);
      }
    }
    $this->set_value('alternateName', json_encode($alternateName));
    return parent::store($args);
  }

  function fetch ($args, $datetime_style = '') {
    $fetched = parent::fetch($args, $datetime_style);
    if ($fetched) {
      $alternateName = json_decode($this->get_value('alternateName'), TRUE);
      foreach ($this->languages as $language) {
        if (isset($alternateName) && FALSE !== $alternateName && array_key_exists($language, $alternateName)) {
          $this->set_value('name_variant_' . $language, $alternateName[$language]);
        }
      }
    }
    return $fetched;
  }

}

class DisplayOrganization extends DisplayBackend
{
  var $table = 'organization';
  var $fields_listing = array('organization.id AS id',
                              "organization.name AS name",
                              'organization.gnd AS gnd',
                              /* 'COUNT(DISTINCT Item.id) AS count',
                              'COUNT(DISTINCT Media.id) AS how_many_media',
                              */
                              'organization.created_at AS created',
                              'organization.status AS status',
                              );
  var $joins_listing = array(
                             // ' LEFT OUTER JOIN ItemPlace ON ItemOrganization.id_place=Organization.id LEFT OUTER JOIN Item ON ItemOrganization.id_item=Item.id AND Item.status >= 0' /* AND Item.collection <> 33' */,
                             // " LEFT OUTER JOIN Media ON Media.item_id=Item.id AND Media.type = 0 AND Media.name='preview00'"
                             );
  var $group_by_listing = 'organization.id';
  var $distinct_listing = TRUE;
  var $order = array('name' => array('name', 'name DESC'),
                     // 'count' => array('count DESC', 'count'),
                     // 'how_many_media' => array('how_many_media DESC', 'how_many_media'),
                     'created' => array('created_at DESC, organization.id desc', 'created_at, organization.id'),
                    );
  var $cols_listing = array('name' => 'Name',
                            // 'parent_path' => 'Uebergeordnet',
                            'gnd' => 'GND',
                            // 'count' => 'Erfasste Werke',
                            // 'how_many_media' => 'Erfasste Bilder',
                            'created' => 'Created',
                            'status' => ''
                            );
  var $page_size = 50;
  var $search_fulltext = NULL;
  var $view_after_edit = TRUE;
  var $show_xls_export = TRUE;
  var $xls_name = 'orte';

  function __construct (&$page, $workflow = NULL) {
    $workflow = new OrganizationFlow($page); // deleting may be merging
    parent::__construct($page, $workflow);

    if ('xls' == $page->display) {
      $this->cols_listing_count = count($this->fields_listing) - 1;
    }

    if ($page->lang() != 'en_US') {
      $this->datetime_style = 'DD.MM.YYYY';
    }

    $this->messages['item_new'] = tr('New Organization');
    $this->search_fulltext = $this->page->getPostValue('fulltext');
    if (!isset($this->search_fulltext)) {
      $this->search_fulltext = $this->page->getSessionValue('fulltext');
    }
    $this->page->setSessionValue('fulltext', $this->search_fulltext);

    if ($this->search_fulltext) {
      $search_condition = array('name' => 'search', 'method' => 'buildFulltextCondition', 'args' => 'name,gnd', 'persist' => 'session');
    }
    else {
      $search_condition = array('name' => 'search', 'method' => 'buildLikeCondition', 'args' => 'name,gnd', 'persist' => 'session');
    }

    $this->condition = array(
      sprintf('organization.status <> %d', $this->status_deleted),
      array('name' => 'status', 'method' => 'buildStatusCondition', 'args' => 'status', 'persist' => 'session'),
      $search_condition,
    );
  }

  function setRecordInternal (&$record) {
  }

  function instantiateRecord ($table = '', $dbconn = '') {
    $record = new OrganizationRecord(array('tables' => $this->table, 'dbconn' => new DB_Presentation()));

    $record->add_fields(
      array(
        new Field(array('name' => 'id', 'type' => 'hidden', 'datatype' => 'int', 'primarykey' => TRUE)),

        new Field(array('name' => 'created_at', 'type' => 'hidden', 'datatype' => 'function', 'value' => 'UTC_TIMESTAMP()', 'noupdate' => TRUE)),
        // new Field(array('name' => 'created_by', 'type' => 'hidden', 'datatype' => 'int', 'value' => $this->page->user['id'], 'noupdate' => TRUE)),
        new Field(array('name' => 'changed_at', 'type' => 'hidden', 'datatype' => 'function', 'value' => 'UTC_TIMESTAMP()')),
        // new Field(array('name' => 'changed_by', 'type' => 'hidden', 'datatype' => 'int', 'value' => $this->page->user['id'])),

        new Field(array('name' => 'name', 'id' => 'name', 'type' => 'text', 'size' => 40, 'datatype' => 'char', 'maxlength' => 80)),

        new Field(array('name' => 'alternateName', 'type' => 'hidden', 'datatype' => 'char', 'null' => TRUE)),
        new Field(array('name' => 'name_variant_de', 'type' => 'text', 'datatype' => 'char', 'size' => 40, 'null' => TRUE, 'nodbfield' => true)),
        new Field(array('name' => 'name_variant_en', 'type' => 'text', 'datatype' => 'char', 'size' => 40, 'null' => TRUE, 'nodbfield' => true)),

        new Field(array('name' => 'gnd', 'id' => 'gnd', 'type' => 'text', 'datatype' => 'char', 'size' => 15, 'maxlength' => 11, 'null' => TRUE)),
        // new Field(array('name' => 'tgn_parent', 'id' => 'tgn_parent', 'type' => 'hidden', 'datatype' => 'char', 'size' => 15, 'maxlength' => 11, 'null' => TRUE)),
        // new Field(array('name' => 'parent_path', 'id' => 'parent_path', 'type' => 'hidden', 'datatype' => 'char', 'size' => 40, 'null' => TRUE)),

        /*
        new Field(array('name' => 'latitude', 'id' => 'latitude', 'type' => 'hidden', 'datatype' => 'char', 'size' => 15, 'null' => TRUE)),
        new Field(array('name' => 'longitude', 'id' => 'longitude', 'type' => 'hidden', 'datatype' => 'char', 'size' => 15, 'null' => TRUE)),
        */

        // new Field(array('name' => 'comment_internal', 'type' => 'textarea', 'datatype' => 'char', 'cols' => 50, 'rows' => 4, 'null' => TRUE)),
    ));

    if ($this->page->isAdminUser()) {
      // admins may publish organization
      $record->add_fields(
        array(
          new Field(array('name' => 'status', 'type' => 'hidden', 'value' => 0, 'noupdate' => !$this->is_internal, 'null' => TRUE)),
          )
        );
    }

    return $record;
  }

  function getEditRows ($mode = 'edit') {
    $gnd_search = '';
    if (false && 'edit' == $mode) {
      $gnd_search = sprintf('<input value="GND Anfrage nach Name, Vorname" type="button" onclick="%s" /><span id="spinner"></span><br />',
                            "jQuery('#gnd').autocomplete('enable');jQuery('#gnd').autocomplete('search', jQuery('#lastname').val() + ', ' + jQuery('#firstname').val())");
    }
    $rows = array(
      'id' => FALSE, 'status' => FALSE,
      'name' => array('label' => 'Name'),
      'name_variant_de' => array('label' => 'Deutscher Name',
                              'description' => "Please enter additional names or spellings"),
      'name_variant_en' => array('label' => 'Englischer Name',
                              'description' => "Please enter additional names or spellings"),
      'gnd' => array('label' => 'GND-Nr',
                     'description' => 'Identifikator der Gemeinsamen Normdatei, vgl. http://de.wikipedia.org/wiki/Hilfe:GND',),
      (isset($this->form) ? $gnd_search . $this->form->show_submit(tr('Store')) : '')
      . '<hr noshade="noshade" />',

      '<hr noshade="noshade" />',
      'comment_internal' => array('label' => 'Internal notes and comments'),
      (isset($this->form) ? $this->form->show_submit(tr('Store')) : ''),
    );

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

/*
    $this->script_ready[] = <<<EOT

    jQuery('#tgn').autocomplete({
      // source: availablePnds,
      type: 'post',
      source: './admin_ws.php?pn=place&action=lookupTgn&_debug=1',
      minChars: 2,
      search: function(event, ui) {
        if (jQuery('#tgn').autocomplete('option', 'disabled'))
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
        jQuery('#tgn').val(ui.item.value);
        return false;
      },
      change: function(event, ui) {
        var output = jQuery('#spinner');
        if (null != output) {
          output.html('');
        }
      },
      select: function(event, ui) {
        jQuery('#tgn').val(ui.item.value);
                // try to fetch more info by tgn
                jQuery.ajax({ url: './admin_ws.php?pn=place&action=fetchPlaceByTgn&_debug=1',
                              data: { tgn: ui.item.value },
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
        jQuery('#tgn').autocomplete('disable');
        var output = jQuery('#spinner');
        if (null != output) {
          output.html('');
        }
      }

    })
    .autocomplete('disable');
EOT;
*/
    }
    else {
      $gnd = $this->record->get_value('gnd');
      if (false && !empty($gnd)) {
        $this->script_url[] = 'script/seealso.js';


        $PND_LINKS = array('http://d-nb.info/tgn/%s' => 'Deutsche Nationalbibliothek',
                           // 'http://www.kubikat.org/mrbh-cgi/kubikat_de.pl?t_idn=x&tgn=%s' => 'KuBiKat',
                             );
        $rows['gnd']['value'] = '<ul><li>';

        $rows['gnd']['value'] .= htmlspecialchars($gnd);

        $rows['gnd']['value'] .= '</li>';

        $external = array();
        foreach ($PND_LINKS as $url => $title) {
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

    // $this->setFieldDescription($rows, 'person'); // for help texts

    return $rows;
  }

  function buildViewTitle (&$record) {
    return $this->formatText($record->get_value('name'));
  }

  function buildViewFooter ($found = TRUE) {
    $gnd = $this->record->get_value('gnd');
    $publications = !empty($gnd)
      ? $this->buildRelatedPublications('http://vocab.getty.edu/gnd/' . $gnd)
      : '';

    return $publications . parent::buildViewFooter($found);
  }

  function buildViewAdditional (&$record, $uploadHandler) {
    return '';
    require_once INC_PATH . '/common/displayhelper.inc.php';

    $ret = '';

    $dbconn = Database::getAdapter();

    $works = '';
    $querystr = "SELECT Item.id, Item.title, creatordate, earliestdate, latestdate, displaydate, Collection.name AS collection"
              . " FROM Item"
              . " LEFT OUTER JOIN Collection ON Collection.id=Item.collection"
              . " LEFT OUTER JOIN ItemPlace ON ItemOrganization.id_item=Item.id"
              . sprintf(" WHERE ItemOrganization.id_person=%d AND Item.status >= 0", $record->get_value('id'))
              . " ORDER BY earliestdate, displaydate, Item.title, Item.id";

    $stmt = $dbconn->query($querystr);
    if (FALSE !== $stmt) {
      $params = array('pn' => 'item');
      while ($row = $stmt->fetch()) {
        if (!empty($works)) {
          $works .= '<br />';
        }
        else {
          $works = '<h3>' . $this->htmlSpecialchars(tr('Works')) . '</h3>';
        }
        $params['view'] = $row['id'];
        $works .= sprintf('<a href="%s">%s</a> %s (%s)',
                          htmlspecialchars($this->page->buildLink($params)),
                          $this->formatText($row['title']),
                          ItemDisplayHelper::buildDisplayDate($this, $row),
                          $this->formatText($row['collection']));
      }
    }
    if (!empty($works)) {
      $ret .= '<br style="clear: both" />' . $works;
    }

    $exhibitions = '';
    $querystr = "SELECT Exhibition.id, Exhibition.title, startdate, enddate, Location.name AS location"
              . " FROM Exhibition"
              . " LEFT OUTER JOIN Location ON Location.id=Exhibition.id_location"
              . " LEFT OUTER JOIN ExhibitionPlace ON ExhibitionOrganization.id_exhibition=Exhibition.id"
              . sprintf(" WHERE ExhibitionOrganization.id_person=%d AND Exhibition.status >= 0",
                        $record->get_value('id'))
              . " ORDER BY startdate, enddate, Exhibition.title, Exhibition.id";

    $stmt = $dbconn->query($querystr);
    if (FALSE !== $stmt) {
      $params = array('pn' => 'exhibition');
      while ($row = $stmt->fetch()) {
        if (!empty($exhibitions)) {
          $exhibitions .= '<br />';
        }
        else {
          $exhibitions = '<h3>' . $this->htmlSpecialchars(tr('Exhibitions')) . '</h3>';
        }
        $params['view'] = $row['id'];
        $exhibitions .= sprintf('<a href="%s">%s</a> %s (%s)',
                                htmlspecialchars($this->page->buildLink($params)),
                                $this->formatText($row['title']),
                                $this->formatDateRange($row['startdate'],
                                                       $row['enddate']),
                                $this->formatText($row['location']));
      }
    }
    if (!empty($exhibitions)) {
      $ret .= '<br style="clear: both" />' . $exhibitions;
    }

    $publications = '';
    $querystr = "SELECT Publication.id"
              . " FROM Publication"
              . " LEFT OUTER JOIN PublicationPlace ON PublicationOrganization.id_publication=Publication.id"
              . sprintf(" WHERE PublicationOrganization.id_person=%d AND Publication.status >= 0",
                        $record->get_value('id'))
              . " ORDER BY IFNULL(author,editor), YEAR(publication_date)";

    $stmt = $dbconn->query($querystr);
    if (FALSE !== $stmt) {
      $params = array('pn' => 'publication');
      require_once INC_PATH . '/common/biblioservice.inc.php';
      $biblio_client = BiblioService::getInstance();

      while ($row = $stmt->fetch()) {
        if (!empty($publications)) {
          $publications .= '<br />';
        }
        else {
          $publications = '<h3>' . $this->htmlSpecialchars(tr('Publications')) . '</h3>';
        }
        $params['view'] = $row['id'];
        $citation = $biblio_client->buildCitation($row['id'],
                                                  array('person_delimiter' => '/',
                                                        'person_suffix' => ',',
                                                        'title_suffix' => ',',
                                                        'publisher_suppress' => TRUE,
                                                        ));
        $publications .= sprintf('<a href="%s">%s</a>',
                                 htmlspecialchars($this->page->buildLink($params)),
                                 $citation);
      }
    }
    if (!empty($publications))
      $ret .= '<br style="clear: both" />' . $publications;

    return $ret . parent::buildViewAdditional($record, $uploadHandler);
  }

  function buildSearchBar () {
    $ret = sprintf('<form action="%s" method="post" name="search">',
                   htmlspecialchars($this->page->buildLink(array('pn' => $this->page->name, 'page_id' => 0))));

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

  function getImageDescriptions () {
    global $TYPE_ORGANIZATION;

    return array($TYPE_ORGANIZATION, array());
  }

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
    if (!$record->fetch($id))
      return FALSE;
    $action = NULL;
    if (array_key_exists('with', $_POST)
        && intval($_POST['with']) > 0)
    {
      $action = 'merge';
      $id_new = intval($_POST['with']);
    }
    $ret = FALSE;

    $dbconn = new DB_Presentation();
    switch ($action) {
      case 'merge':
        $record_new = $this->buildRecord();
        if (!$record_new->fetch($id_new)) {
          return FALSE;
        }

        foreach (OrganizationFlow::$TABLES_RELATED as $table => $key_field) {
          $querystr = sprintf("UPDATE %s SET %s=%d WHERE %s=%d",
                              $table, $key_field, $id_new, $key_field, $id);
          $dbconn->query($querystr);
        }
        $this->page->redirect(array('pn' => $this->page->name, 'delete' => $id));
        break;

      default:
        $orig = sprintf('%s',
                        $record->get_value('name'));
        return sprintf('%s cannot be deleted since there are entries connected to this place',
                       $orig);

        $params_replace = array('pn' => $this->page->name, 'delete' => $id);
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

  function buildListingCell (&$row, $col_index, $val = NULL) {
    $val = NULL;
    if ($col_index == count($this->fields_listing) - 1) {
      $url_preview = $this->page->buildLink(array('pn' => $this->page->name, $this->workflow->name(TABLEMANAGER_VIEW) => $row[0]));
      $val = sprintf('<div style="text-align:right;">[<a href="%s">%s</a>]</div>',
                     htmlspecialchars($url_preview),
                     tr('view'));
    }

    return parent::buildListingCell($row, $col_index, $val);
  }


  function buildContent () {
    if (OrganizationFlow::MERGE == $this->step) {
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
    if (OrganizationFlow::IMPORT == $this->step) {
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

$display = new DisplayOrganization($page);
if (FALSE === $display->init()) {
  $page->redirect(array('pn' => ''));
}

$page->setDisplay($display);
