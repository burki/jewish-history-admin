<?php
/*
 * admin_publication.inc.php
 *
 * Class for managing publications (sources)
 *
 * (c) 2007-2018 daniel.burckhardt@sur-gmbh.ch
 *
 * Version: 2018-12-05 dbu
 *
 * Changes:
 *
 */

require_once INC_PATH . 'admin/displaybackend.inc.php';

require_once INC_PATH . 'common/biblioservice.inc.php';

class PublicationRecord
extends TableManagerRecord
{
  var $languages = [ 'de', 'en' ];

  function store ($args = '') {
    // remove dashes and convert x to upper from isbn
    $isbn = $this->get_value('isbn');
    if (!empty($isbn)) {
      $this->set_value('isbn', BiblioService::normalizeIsbn($isbn));
    }

    $attribution = [];
    foreach ($this->languages as $language) {
      $value = $this->get_value('attribution_' . $language);
      if (!empty($value)) {
        $attribution[$language] = trim($value);
      }
    }
    $this->set_value('attribution', json_encode($attribution));

    $stored = parent::store();

    if ($stored) {
      // currently don't fetch covers
      // here we are assured to have an valid id
      $image_url = $this->get_value('image_url');
      if (!empty($image_url)) {
        global $TYPE_PUBLICATION;

        // create local directory and write it
        $id = $this->get_value('id');
        $folder = ImageUploadHandler::directory($id, $TYPE_PUBLICATION, true);
        $fname = 'cover00';
        // get the extension from the url - TODO: check mime-type
        if (preg_match('/(\.[^\.]+)$/', $image_url, $matches)) {
          $ext = $matches[1];
        }

        $fullname = $folder . $fname . '_large' . $ext;

        if (ImageUploadHandler::checkDirectory($folder, true)) {
          $handle = fopen($image_url, 'rb');
          $contents = stream_get_contents($handle);
          fclose($handle);

          $handle = fopen($fullname, "wb");
          if (fwrite($handle, $contents) === false) {
            $ret .= "<p>Error writing $fullname.</p>";
          }
          fclose($handle);
          if (file_exists($fullname)) {
            $fname_store = $fullname;

            $fullname_scaled = $folder . $fname . '.jpg';

            if (defined('UPLOAD_PATH2MAGICK')) {
              $cmd = UPLOAD_PATH2MAGICK . 'convert'
                   . ' -geometry x164'
                   . ' ' . escapeshellarg($fullname)
                   . ' ' . escapeshellarg($fullname_scaled);
              // var_dump($cmd);
              $ret = exec($cmd, $lines, $retval);
              if (file_exists($fullname_scaled)) {
                $fname_store = $fullname_scaled;
              }
            }
            else {
              copy($fullname, $fullname_scaled);
            }

            $size = @getimagesize($fname_store);
            if (isset($size)) {

              // insert/update the Media-record
              $dbconn = new DB;

              $handler = new ImageUploadHandler($id, $TYPE_PUBLICATION);
              $record = $handler->instantiateUploadRecord($dbconn);

              $record->set_value('item_id', $id);
              $record->set_value('ord', 0);
              $record->set_value('name', $fname);
              $record->set_value('width', $size[0]);
              $record->set_value('height', $size[1]);
              $record->set_value('mimetype', $size['mime']);
// var_dump($size);
              // find out if we already have an item
              $querystr = sprintf("SELECT id FROM Media WHERE item_id=%d AND type=%d AND name='%s' ORDER BY ord DESC LIMIT 1",
                                  $id, $TYPE_PUBLICATION, $fname);
              $dbconn->query($querystr);
              if ($dbconn->next_record()) {
                $record->set_value('id', $dbconn->Record['id']);
              }

              $record->store();
            }
          }
        }
      }
    }

    return $stored;
  }

  function fetch ($args, $datetime_style = '') {
    $fetched = parent::fetch($args, $datetime_style);
    if ($fetched) {
      $attribution = json_decode($this->get_value('attribution'), true);
      foreach ($this->languages as $language) {
        if (isset($attribution) && false !== $attribution && array_key_exists($language, $attribution)) {
          $this->set_value('attribution_' . $language, $attribution[$language]);
        }
      }
    }

    return $fetched;
  }

  function delete ($id) {
    global $STATUS_REMOVED;

    $dbconn = $this->params['dbconn'];
    // check if there are Reviews tied to this book
    $querystr = sprintf("SELECT COUNT(*) AS has_related FROM Message, MessagePublication"
                        . " WHERE Message.id=message_id AND publication_id=%d AND Message.status <> %s",
                        $id, $STATUS_REMOVED);
    $dbconn->query($querystr);
    if ($dbconn->next_record() && 0 == $dbconn->Record['has_related']) {
      // break relation
      $querystr = sprintf("DELETE FROM MessagePublication WHERE publication_id=%d",
                          $id);
      $dbconn->query($querystr);
      // remove publication
      $querystr = sprintf("UPDATE Publication SET status=%s WHERE id=%d",
                          $STATUS_REMOVED, $id);
      $dbconn->query($querystr);
      return $dbconn->affected_rows() > 0;
    }

    return false;
  }
}

class DisplayPublication
extends DisplayBackend
{
  var $page_size = 30;
  var $table = 'Publication';
  var $fields_listing = [
    'id', 'IFNULL(author,editor)', 'title',
    'YEAR(publication_date) AS year',
    'status', 'status_flags',
  ];

  var $status_options;
  var $status_default = '-99';
  var $status_deleted = '-1';

  var $condition = [
    [ 'name' => 'search', 'method' => 'buildLikeCondition', 'args' => 'title,author,editor' ],
    'Publication.status <> -1',
    // alternative: buildFulltextCondition
  ];
  var $order = [
    'id' => [ 'id DESC', 'id' ],
    'author' => [ 'IFNULL(author,editor)', 'IFNULL(author,editor) DESC' ],
    'title' => [ 'title', 'title DESC' ],
    'year' => [ 'YEAR(publication_date) DESC', 'YEAR(publication_date)' ],
    'status' => [ 'Publication.status', 'Publication.status DESC' ],
  ];
  var $cols_listing = [
    'id' => 'ID',
    'author' => 'Author/Editor',
    'title' => 'Title',
    'year' => 'Year',
    'status' => 'Status',
    '' => '',
  ];
  var $idcol_listing = true;
  var $view_after_edit = true;

  function __construct (&$page) {
    global $STATUS_SOURCE_OPTIONS;

    $this->status_options = $this->view_options['status'] = $STATUS_SOURCE_OPTIONS;
    parent::__construct($page);

    $this->condition[] = [
      'name' => 'status',
      'method' => 'buildEqualCondition',
      'args' => $this->table . '.status',
      'persist' => 'session',
    ];

    $this->condition[] = [
      'name' => 'status_translation',
      'method' => 'buildEqualCondition',
      'args' => $this->table . '.status_translation',
      'persist' => 'session',
    ];
  }

  function init () {
    $ret = parent::init();
    if (false === $ret) {
      return $ret;
    }

    if (!$this->checkAction(TABLEMANAGER_EDIT)) {
      $this->listing_default_action = TABLEMANAGER_VIEW;
    }

    return $ret;
  }

  function instantiateRecord ($table = '', $dbconn = '') {
    return new PublicationRecord([ 'tables' => $this->table, 'dbconn' => $this->page->dbconn ]);
  }

  function buildStatusOptions ($options = null) {
    if (!isset($options)) {
      $options = & $this->status_options;
    }

    $status_options = [ '<option value="">' . tr('-- all --') . '</option>' ];
    foreach ($options as $status => $label) {
      if ($this->status_deleted != $status) {
        $selected = isset($this->search['status']) && $this->search['status'] !== ''
            && $this->search['status'] == $status
            ? ' selected="selected"' : '';
        $style = '';
        if (preg_match('/^_(.*)_$/', $label, $matches)) {
          $label = $matches[1];
          $style = ' style="font-style: italic;"';
        }

        $status_options[] = sprintf('<option value="%d"%s%s>%s</option>',
                                    $status,
                                    $selected,
                                    $style,
                                    $this->htmlSpecialchars(tr($label)));
      }
    }

    return tr('Status')
         . ': <select name="status">' . implode($status_options) . '</select>';
  }

  function buildOptions ($type) {
    $dbconn = & $this->page->dbconn;
    switch ($type) {
      case 'lang':
        global $LANGUAGES_FEATURED;
        $languages = $this->getLanguages($this->page->lang());
        if (isset($LANGUAGES_FEATURED)) {
          for ($i = 0; $i < count($LANGUAGES_FEATURED); $i++) {
            $languages_ordered[$LANGUAGES_FEATURED[$i]] = $languages[$LANGUAGES_FEATURED[$i]];
          }
          $languages_ordered['&#x2500;&#x2500;&#x2500;&#x2500;&#x2500;&#x2500;'] = false; // separator
        }

        foreach ($languages as $iso639_2 => $name) {
          if (!isset($languages_ordered[$iso639_2])) {
            $languages_ordered[$iso639_2] = $name;
          }
        }

        return $languages_ordered;
        break;

      case 'license':
        global $LICENSE_OPTIONS;
        $licenses = [];
        foreach ($LICENSE_OPTIONS as $key => $label) {
          $licenses[$key] = tr($label);
        }

        return $licenses;
        break;

      case 'status_translation':
        global $STATUS_TRANSLATION_OPTIONS;
        return $STATUS_TRANSLATION_OPTIONS;
        break;

      case 'type':
        $type = 'sourcetype';
        $querystr = sprintf("SELECT id, name FROM Term WHERE category='%s' AND status >= 0 ORDER BY ord, name",
                            addslashes($type));
        break;

      case 'publisher':
        $querystr = sprintf("SELECT id, name FROM %s WHERE status >= 0 ORDER BY name",
                          $dbconn->escape_string(ucfirst($type)));
        break;

      case 'referee':
      case 'translator':
      case 'translator_de':
        global $RIGHTS_REFEREE, $RIGHTS_TRANSLATOR;
        $querystr = "SELECT id, lastname, firstname FROM User";
        $querystr .= sprintf(" WHERE 0 <> (privs & %d) AND status <> %d",
                             'referee' == $type ? $RIGHTS_REFEREE : $RIGHTS_TRANSLATOR,
                             STATUS_USER_DELETED);
        $querystr .= " ORDER BY lastname, firstname";
        break;
    }

    if (isset($querystr)) {
      $dbconn->query($querystr);
      $options = [];
      while ($dbconn->next_record()) {
        $options[$dbconn->Record['id']] = in_array( $type, [ 'sourcetype', 'publisher' ])
          ? $dbconn->Record['name']
          : $dbconn->Record['lastname'] . ', ' . $dbconn->Record['firstname'];
      }

      return $options;
    }
  }

  function buildRecord ($name = '') {
    $record = parent::buildRecord($name);

    if (!isset($record)) {
      return;
    }

    $this->view_options['type'] = $type_options = $this->buildOptions('type');
    $this->view_options['translator'] = $this->view_options['translator_de']
      = $this->translator_options = $this->buildOptions('translator');
    $this->view_options['lang'] = $this->buildOptions('lang');
    $this->view_options['status_translation'] = $this->status_translation_options
      = [ '' => tr('-- please select --') ] + $this->buildOptions('status_translation');
    $this->view_options['license'] = $license_options = $this->buildOptions('license');
    $languages_ordered = [ '' => tr('-- please select --') ] + $this->view_options['lang'];

    $publisher_options = $this->buildOptions('publisher');
    $record->add_fields([
      new Field([ 'name' => 'id', 'type' => 'hidden', 'datatype' => 'int', 'primarykey' => true ]),
      new Field([
        'name' => 'status', 'type' => 'select',
        'options' => array_keys($this->status_options),
        'labels' => array_values($this->status_options),
        'datatype' => 'int', 'default' => $this->status_default,
      ]),
      new Field([ 'name' => 'created', 'type' => 'hidden', 'datatype' => 'function', 'value' => 'NOW()', 'noupdate' => true ]),
      new Field([ 'name' => 'created_by', 'type' => 'hidden', 'datatype' => 'int', 'value' => $this->page->user['id'], 'null' => true, 'noupdate' => true ]),
      new Field([ 'name' => 'changed', 'type' => 'hidden', 'datatype' => 'function', 'value' => 'NOW()' ]),
      new Field([ 'name' => 'changed_by', 'type' => 'hidden', 'datatype' => 'int', 'value' => $this->page->user['id'], 'null' => true ]),
      new Field([ 'name' => 'type', 'id' => 'type', 'type' => 'select', 'datatype' => 'char', 'options' => array_keys($type_options), 'labels' => array_values($type_options) ]),

      new Field([
        'name' => 'status_flags', 'type' => 'checkbox', 'datatype' => 'bitmap', 'null' => true, 'default' => 0,
        'labels' => [
          0x01 => tr('Digitization') . ' ' . tr('finalized'),
          0x02 => tr('Transcript and Markup') . ' ' . tr('finalized'),
          0x04 => tr('Bibliography') . ' ' . tr('finalized'),
          0x08 => tr('Translation') . ' ' . tr('finalized'),
          0x10 => tr('Translation Markup') . ' ' . tr('finalized'),
          0x20 => tr('ready for publishing'),
        ],
      ]),

      // new Field([ 'name' => 'isbn', 'id' => 'isbn', 'type' => 'text', 'size' => 20, 'datatype' => 'char', 'maxlength' => 17, 'null' => true ]),
      new Field([ 'name' => 'author', 'id' => 'author', 'type' => 'text', 'size' => 60, 'datatype' => 'char', 'maxlength' => 80, 'null' => true ]),
      new Field([ 'name' => 'editor', 'id' => 'editor', 'type' => 'text', 'size' => 60, 'datatype' => 'char', 'maxlength' => 80, 'null' => true ]),
      new Field([ 'name' => 'title', 'id' => 'title', 'type' => 'text', 'size' => 60, 'datatype' => 'char', 'maxlength' => 127 ]),
      new Field([ 'name' => 'subtitle', 'id' => 'subtitle', 'type' => 'text', 'size' => 60, 'datatype' => 'char', 'maxlength' => 127, 'null' => true ]),
      new Field([ 'name' => 'series', 'id' => 'series', 'type' => 'text', 'size' => 60, 'datatype' => 'char', 'maxlength' => 127, 'null' => true ]),
      new Field([ 'name' => 'place', 'id' => 'place', 'type' => 'text', 'size' => 60, 'datatype' => 'char', 'maxlength' => 127, 'null' => true ]),
      new Field([ 'name' => 'publisher_id', 'id' => 'publisher_id', 'type' => 'select',
                  'options' => array_merge([ '' ], array_keys($publisher_options)),
                  'labels' => array_merge([ '-- select a holding institution --' ], array_values($publisher_options)), 'datatype' => 'int' ]),
      // new Field([ 'name' => 'publisher', 'id' => 'publisher', 'type' => 'text', 'size' => 60, 'datatype' => 'char', 'maxlength' => 127, 'null' => true ]),
      new Field([ 'name' => 'archive_location', 'id' => 'archive_location', 'type' => 'text', 'size' => 60, 'datatype' => 'char', 'maxlength' => 383, 'null' => true ]),
      new Field([ 'name' => 'publication_date', 'id' => 'publication_date', 'type' => 'date', 'incomplete' => true, 'datatype' => 'date', 'null' => true ]),
      new Field([ 'name' => 'binding', 'id' => 'binding', 'type' => 'text', 'size' => 60, 'datatype' => 'char', 'maxlength' => 50, 'null' => true ]),
      new Field([ 'name' => 'pages', 'id' => 'pages', 'type' => 'text', 'size' => 60, 'datatype' => 'char', 'maxlength' => 50, 'null' => true ]),
      new Field([ 'name' => 'listprice', 'id' => 'listprice', 'type' => 'text', 'size' =>60, 'datatype' => 'char', 'maxlength' => 50, 'null' => true ]),
      new Field([ 'name' => 'image_url', 'id' => 'image', 'type' => 'hidden', 'datatype' => 'char', 'null' => true, 'nodbfield' => true ]),
      new Field([ 'name' => 'url', 'id' => 'toc_url', 'type' => 'text', 'size' => 60, 'datatype' => 'char', 'maxlength' => 255, 'null' => true ]),

      new Field([ 'name' => 'lang', 'type' => 'select', 'datatype' => 'char', 'options' => array_keys($languages_ordered), 'labels' => array_values($languages_ordered), 'null' => true ]),
      new Field([ 'name' => 'translator', 'type' => 'select',
                  'options' => array_merge([ '' ], array_keys($this->translator_options)),
                  'labels' => array_merge([ tr('-- none --') ], array_values($this->translator_options)),
                  'datatype' => 'int', 'null' => true ]),
      new Field([ 'name' => 'translator_de', 'type' => 'select',
                  'options' => array_merge([ '' ], array_keys($this->translator_options)),
                  'labels' => array_merge([ tr('-- none --') ], array_values($this->translator_options)),
                  'datatype' => 'int', 'null' => true ]),
      new Field([ 'name' => 'status_translation', 'type' => 'select', 'datatype' => 'char', 'options' => array_keys($this->status_translation_options), 'labels' => array_values($this->status_translation_options), 'null' => true ]),
      new Field([ 'name' => 'place_identifier', 'id' => 'place_identifier', 'type' => 'text', 'size' => 60, 'datatype' => 'char', 'maxlength' => 255, 'null' => true ]),
      new Field([ 'name' => 'place_geo', 'id' => 'place_geo', 'type' => 'text', 'size' => 60, 'datatype' => 'char', 'maxlength' => 255, 'null' => true ]),
      new Field([ 'name' => 'indexingdate', 'type' => 'date', 'incomplete' => true, 'datatype' => 'date', 'null' => true ]),
      new Field([ 'name' => 'displaydate', 'type' => 'text', 'size' => 40, 'datatype' => 'char', 'maxlength' => 80, 'null' => true ]),

      new Field([ 'name' => 'license', 'id' => 'license', 'type' => 'select',
                  'options' => array_keys($license_options),
                  'labels' => array_values($license_options), 'datatype' => 'char', 'null' => true ]),
      new Field([ 'name' => 'attribution', 'type' => 'hidden', 'datatype' => 'char', 'null' => true ]),
      new Field([ 'name' => 'attribution_de', 'type' => 'textarea', 'datatype' => 'char', 'cols' => 65, 'rows' => 3, 'null' => true, 'nodbfield' => true ]),
      new Field([ 'name' => 'attribution_en', 'type' => 'textarea', 'datatype' => 'char', 'cols' => 65, 'rows' => 3, 'null' => true, 'nodbfield' => true ]),

      new Field([ 'name' => 'comment_digitization', 'type' => 'textarea', 'datatype' => 'char', 'cols' => 65, 'rows' => 8, 'null' => true ]),
      new Field([ 'name' => 'comment_markup', 'type' => 'textarea', 'datatype' => 'char', 'cols' => 65, 'rows' => 8, 'null' => true ]),
      new Field([ 'name' => 'comment_bibliography', 'type' => 'textarea', 'datatype' => 'char', 'cols' => 65, 'rows' => 8, 'null' => true ]),
      new Field([ 'name' => 'comment_translation', 'type' => 'textarea', 'datatype' => 'char', 'cols' => 65, 'rows' => 8, 'null' => true ]),
      new Field([ 'name' => 'comment_translation_markup', 'type' => 'textarea', 'datatype' => 'char', 'cols' => 65, 'rows' => 8, 'null' => true ]),

      new Field([ 'name' => 'comment', 'type' => 'textarea', 'datatype' => 'char', 'cols' => 65, 'rows' => 15, 'null' => true ]),
    ]);

    return $record;
  }

  function getEditRows ($mode = 'edit') {
    $add_publisher_button = sprintf('<input type="button" value="%s" onclick="window.open(\'%s\')" />',
                                    tr('add new Holding Institution'), htmlspecialchars($this->page->buildLink([ 'pn' => 'publisher', 'edit' => -1 ])));

    $rows = [
      'id' => false, // 'status' => false, // hidden fields

      'status' => [ 'label' => 'Status' ],
      'type' => [ 'label' => 'Source Type' ],

      /* 'isbn' => [ 'label' => 'ISBN' ],
      '<input type="button" value="' . tr('Get Info') . '" onclick="fetchPublicationByIsbn()" />',
      */
      'author' => [ 'label' => 'Author(s)' ],
      'editor' => [ 'label' => 'Editor(s)' ],
      'title' => [ 'label' => 'Title' ],
      'subtitle' => [ 'label' => 'Subtitle' ],
      'series' => [ 'label' => 'Series' ],
      'place' => [ 'label' => 'Place of publication' ],
      'publisher_id' => [
        'label' => 'Holding Institution',
        'value' => isset($this->form)
                    ? $this->getFormField('publisher_id') . $add_publisher_button
                    : '',
      ],
      'archive_location' => [ 'label' => 'Archive location' ],
      'publication_date' => [ 'label' => 'Publication date' ],
      'binding' => [ 'label' => 'Binding' ],
      'pages' => [ 'label' => 'Pages/Ills.' ],
      // 'listprice' => [ 'label' => 'List price' ],
      'url' => [ 'label' => 'URL' ],
      'image_url' => false, // hidden field

      'lang' => [ 'label' => 'Source Language' ],
      'translator' => [ 'label' => 'Translator (into English)' ],
      'translator_de' => [ 'label' => 'Translator (into German)' ],
      'status_translation' => [ 'label' => 'Translation Status' ],

      'place_identifier' => [ 'label' => 'Primary Place (Getty-Identifier)' ],
      'place_geo' => [ 'label' => 'Primary Place Coordinate Override (Latitude,Longitude, e.g "52.516667,13.4")' ],
      'indexingdate' => [ 'label' => 'Primary Date (YYYY or DD.MM.YYY)' ],
      'displaydate' => [ 'label' => 'Primary Date Override (e.g. "around 1600")' ],

      '<hr noshade="noshade" />',
    ];

    $additional = [
      'license' => [ 'label' => 'License' ],
      'attribution_de' => [ 'label' => 'Attribution (German)' ],
      'attribution_en' => [ 'label' => 'Attribution (English)' ],
    ];

    if ('edit' == $mode) {
      $status_flags = $this->form->field('status_flags');
    }
    else {
      $status_flags_value = $this->record->get_value('status_flags');
    }

    foreach ([
        'digitization' => [ 'label' => 'Digitization', 'mask' => 0x1 ],
        'markup' => [ 'label' => 'Transcript and Markup', 'mask' => 0x02 ],
        'bibliography' => [ 'label' => 'Bibliography', 'mask' => 0x04 ],
        'translation' => [ 'label' => 'Translation', 'mask' => 0x08 ],
        'translation_markup' => [ 'label' => 'Translation Markup', 'mask' => 0x10 ],
      ] as $key => $options)
    {
      if ('edit' == $mode) {
        $finalized = $status_flags->show($options['mask']) . '<br />';
      }
      else {
        $finalized = (0 != ($status_flags_value & $options['mask']) ? tr('finalized') . '<br />' : '');
      }

      $additional['comment_' . $key] = [
        'label' => $options['label'],
        'value' => $finalized
          . ('edit' == $mode
              ? $this->getFormField('comment_' . $key)
              : $this->record->get_value('comment_' . $key))
      ];
    }

    $rows = array_merge($rows, $additional);

    $rows = array_merge($rows, [
      '<hr noshade="noshade" />',

      'comment' => [ 'label' => 'Internal notes and comments' ],

      isset($this->form) ? $this->form->show_submit(ucfirst(tr('save'))) : 'false',
    ]);

    return $rows;
  }

  function renderEditForm ($rows, $name = 'detail') {
    $this->script_url[] = 'script/scriptaculous/prototype.js';

    $url_ws = $this->page->BASE_PATH . 'admin/admin_ws.php';
    $url_params = isset($this->id) ? '&id_publication=' . $this->id : '';

    $this->script_code .= <<<EOT
    function fetchPublicationByIsbn() {
      var isbn = \$F('isbn');
      var url = '{$url_ws}';
      var pars = 'pn=publication&action=fetchPublicationByIsbn{$url_params}&isbn=' + escape(isbn);
      var myAjax = new Ajax.Request(
            url,
            {
                method: 'get',
                parameters: pars,
                onComplete: setPublication
            });
    }

    function setPublication (originalRequest, obj) {
      if (obj.status > 0) {
        if (obj.status == 2) {
          var msg = 'Es besteht bereits ein Datensatz zu dieser Publikation';
          if (obj['isbn'] != null) {
            msg += ' unter der ISBN: ' + obj['isbn'];
          }
          alert(msg);
        }
        else {
          var fields = ['title', 'subtitle', 'author', 'editor', 'series', 'binding', 'pages', 'publication_date', 'place', 'listprice', 'image'];
          for (var i = 0; i < fields.length; i++) {
            var name = fields[i];
            if (null != obj[name]) {
              var field = \$(fields[i]);
              if (null != field) {
                field.value = obj[name];
              }
            }
          }
          if (null != obj.publisher) {
            // try to set the publisher
            var field = \$('publisher_id');
            var found = false;
            if (null != field && null != field.options) {
              for (var i = 0; i < field.options.length; i++) {
                if (obj.publisher == field.options[i].text) {
                  field.options[i].selected = true;
                  found = true;
                  break;
                }
              }
            }
            if (!found) {
              alert('Der Verlag\\n' + obj.publisher + '\\nkonnte nicht automatisch zugewiesen werden. Bitte w\\u00e4hlen Sie einen passenden Eintrag aus der Liste der Verlage oder erfassen ihn neu.')
            }
          }

        }
      }
      else
        alert('ret: ' + obj.msg + obj.status);
    }

EOT;

    $changed = isset($this->id) ? $this->buildChangedBy() : '';

    return $changed . parent::renderEditForm($rows, $name);
  }

  function getImageDescriptions () {
    global $TYPE_PUBLICATION;

    $images = [
      'source' => [
        'title' => tr('Digitized Media / Transcript'),
        'multiple' => true,
        'imgparams' => [
          'height' => 164,
          'scale' => 'down',
          'keep' => 'large',
          'keep_orig' => true,
          'title' => 'File',
          'pdf' => true,
          'audio' => true,
          'video' => true,
          'office' => true,
          'xml' => true,
        ],
      ],
    ];

    return [ $TYPE_PUBLICATION, $images ];
  }

  function getViewFormats () {
    // return [ 'body' => [ 'format' => 'p'));
  }

  function buildViewRows () {
    $resolve_options = [
      'type' => 'type',
      'publisher_id' => 'publisher',
      'lang' => 'lang',
      'translator' => 'translator',
    ];

    $rows = $this->getEditRows('view');
    if (isset($rows['title'])) {
      unset($rows['title']);
    }
    unset($rows['publisher_id']['value']); // remove custom-edit value

    $formats = $this->getViewFormats();

    $view_rows = [];

    foreach ($rows as $key => $descr) {
      if ($descr !== false && gettype($key) == 'string') {
        if (isset($formats[$key])) {
          $descr = array_merge($descr, $formats[$key]);
        }
        $view_rows[$key] = $descr;
        if (array_key_exists($key, $resolve_options)) {
          // var_dump($key);
          $view_rows[$key]['options'] = $this->buildOptions($resolve_options[$key]);
        }
        else if (isset($this->view_options[$key])) {
          $view_rows[$key]['options'] = $this->view_options[$key];
        }
      }
    }

    return $view_rows;
  }

  function renderView ($record, $rows) {
    $ret = '<div id="previewOverlay"><iframe id="previewOverlayFrame" src="" frameBorder="0" width="100%" height="100%"></iframe></div>';
    $this->script_ready[] = <<<EOT
jQuery("#previewOverlay").dialog({
    autoOpen: false,
    modal: true,
    open: function(ev, ui) {
    },
    close: function(ev, ui) {
      jQuery('#previewOverlayFrame').attr('src', '');
    },
    width: 860,
    height: 600,
    buttons: {
      "Abbrechen": function() {
        jQuery(this).dialog("close");
      }
    }
});

function previewOverlayClose() {
  jQuery('#previewOverlay').dialog('close');
  return false;
}

jQuery(".previewOverlayTrigger").on("click", function(e) {
    var browserWindow = jQuery(window);
    var dWidth = browserWindow.width() * 0.8;
    var dHeight = browserWindow.height() * 0.8;
    jQuery('#previewOverlayFrame').attr('src', this.href);
    // jQuery('#previewOverlay').dialog( "option", "width", dWidth);
    jQuery('#previewOverlay').dialog( "option", "height", dHeight);
    jQuery('#previewOverlay').dialog('open');
    return false;
});
EOT;

    if (!empty($this->page->msg)) {
      $ret .= '<p class="message">' . $this->page->msg . '</p>';
    }
    $fields = [];
    if (is_array($rows)) {
      foreach ($rows as $key => $row_descr) {
        if (is_string($row_descr)) {
          $fields[] = [ '&nbsp;', $row_descr ];
        }
        else {
          $label = isset($row_descr['label']) ? tr($row_descr['label']).':' : '';
          // var_dump($row_descr);
          if (isset($row_descr['fields'])) {
            $value = '';
            foreach ($row_descr['fields'] as $field) {
              $field_value = $record->get_value($field);
              $field_value = array_key_exists('format', $row_descr) && 'p' == $row_descr['format']
                ? $this->formatParagraphs($field_value) : $this->formatText($field_value);
              $value .= (!empty($value) ? ' ' : '').$field_value;
            }
          }
          else if (isset($row_descr['value'])) {
            $value = $row_descr['value'];
          }
          else {
            $field_value = $record->get_value($key);
            if (isset($row_descr['options']) && isset($field_value) && '' !== $field_value) {
              $values = preg_split('/,\s*/', $field_value);
              for ($i = 0; $i < count($values); $i++) {
                if (isset($row_descr['options'][$values[$i]])) {
                  $values[$i] = $row_descr['options'][$values[$i]];
                }
              }
              $field_value = implode(', ', $values);
            }
            $value = isset($row_descr['format']) && 'p' == $row_descr['format']
              ? $this->formatParagraphs($field_value) : $this->formatText($field_value);
          }

          $fields[] = [ $label, $value ];
        }
      }
    }

    if (count($fields) > 0) {
      $ret .= $this->buildContentLineMultiple($fields);
    }

    return $ret;
  }

  function buildReviewSubject (&$record) {
    $editor = false;
    $authors = $record->get_value('author');
    if (empty($authors)) {
      $authors = $record->get_value('editor');
      $editor = true;
    }

    $author_short = '';
    if (!empty($authors)) {
      // $parts = preg_split('/\s*\;\s/', $authors);
      // list($lastname, $firstname) = preg_split('/\s*,\s*/', $parts[0]);
      // $author_short = (!empty($firstname) ? $firstname[0] . '. ' : '') . $lastname;
      $author_short = $authors;
      if ($editor) {
        $author_short .= ' (Hrsg.)';
      }
      $author_short .= ': ';
    }

    $subject = $author_short . $this->formatText($record->get_value('title'));

    return htmlspecialchars_decode(strip_tags($subject));
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

      $ret = '<h2>' . $this->formatText($record->get_value('title')) . ' ' . $edit . '</h2>';

      $ret .= $this->renderView($record, $rows);

      $reviews_found = false; $reviews = '';

      // show all articles related to this source
      $querystr = sprintf("SELECT Message.id AS id, subject, status"
                          ." FROM Message, MessagePublication"
                          ." WHERE MessagePublication.publication_id=%d AND MessagePublication.message_id=Message.id AND Message.status <> %d"
                          ." ORDER BY Message.id DESC",
                          $this->id, STATUS_DELETED);
      $dbconn = & $this->page->dbconn;
      $dbconn->query($querystr);
      $reviews = '';
      $params_view = [ 'pn' => 'article' ];
      $reviews_found = false;
      while ($dbconn->next_record()) {
        if (!$reviews_found) {
          $reviews = '<ul>';
          $reviews_found = true;
        }
        $params_view['view'] = $dbconn->Record['id'];
        $reviews .= sprintf('<li id="item_%d">', $dbconn->Record['id'])
          . sprintf('<a href="%s">' . $this->formatText($dbconn->Record['subject']) . '</a>',
                   htmlspecialchars($this->page->buildLink($params_view)))
          . '</li>';
      }

      if ($reviews_found) {
        $reviews .= '</ul>';
      }
      else {
        global $JAVASCRIPT_CONFIRMDELETE;

        $this->script_code .= $JAVASCRIPT_CONFIRMDELETE;
        $url_delete = $this->page->buildLink([ 'pn' => $this->page->name, $this->workflow->name(TABLEMANAGER_DELETE) => $this->id ]);
        $ret .= sprintf("<p>[<a href=\"javascript:confirmDelete('%s', '%s')\">%s</a>]</p>",
                        'Wollen Sie diese Quelle wirklich l&ouml;schen?\n(kein UNDO)',
                        htmlspecialchars($url_delete),
                        tr('delete Source'));
      }

      $url_add = $this->page->buildLink([
        'pn' => 'article', 'edit' => -1,
        'subject' => $this->buildReviewSubject($record),
        'publication' => $this->id,
      ]);

      $ret .= '<h2>' . tr('Articles')
            . ($this->checkAction(TABLEMANAGER_EDIT)
                ? ' <span class="regular">[<a href="' . htmlspecialchars($url_add).'">' . tr('add new') . '</a>]</span>'
                : '')
            . '</h2>'
            . $reviews;

      // $ret .= '<tt><pre>' . $this->buildLiteraturTemplate() . '</tt></pre>';

      if (isset($uploadHandler)) {
        $ret .= $this->renderUpload($uploadHandler, 'File Upload');
      }
    }

    return $ret;
  }

  function buildSearchFields ($options = []) {
    $options['status_translation'] = '';

    $search = sprintf('<input type="text" name="search" value="%s" size="40" />',
                      $this->htmlSpecialchars(array_key_exists('search', $this->search) ?  $this->search['search'] : ''));
    /*
    $search .= sprintf('<label><input type="hidden" name="fulltext" value="0" /><input type="checkbox" name="fulltext" value="1"%s /> %s</label>',
                       $this->search_fulltext ? ' checked="checked"' : '',
                       tr('Fulltext'));
    */

    $search .=  '<br />' . $this->buildStatusOptions();

    $select_fields = [ 'status' ];

    if (method_exists($this, 'buildOptions')) {
      foreach ($options as $name => $option_label) {
         $select_fields[] = $name;
        // Betreuer - TODO: make a bit more generic
        $select_options = [ '<option value="">' . tr('-- all --') . '</option>' ];
        foreach ($this->buildOptions($name) as $id => $label) {
          $selected = isset($this->search[$name])
              && $this->search[$name] !== ''
              && $this->search[$name] == $id
          ? ' selected="selected"' : '';
          $select_options[] = sprintf('<option value="%s"%s>%s</option>',
                                      $id, $selected,
                                      htmlspecialchars(tr($label)));
        }
        $search .= ' ' . tr($option_label)
                 . sprintf(': <select name="%s">%s</select>',
                           $name, implode($select_options));
      }
    }

    // clear the search
    $select_fields_json = json_encode($select_fields);
    $url_clear = $this->page->BASE_PATH . 'media/clear.gif';
    $search .= <<<EOT
      <script>
      function clear_search() {
        var form = document.forms['search'];
        if (null != form) {
          var textfields = ['search'];
          for (var i = 0; i < textfields.length; i++) {
            if (null != form.elements[textfields[i]]) {
              form.elements[textfields[i]].value = '';
            }
          }
          var selectfields = ${select_fields_json};
          for (var i = 0; i < selectfields.length; i++) {
            if (null != form.elements[selectfields[i]]) {
              form.elements[selectfields[i]].selectedIndex = 0;
            }
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

    return $search;
  }

  function buildListingCell (&$row, $col_index, $val = null) {
    $val = null;
    if ($col_index == count($this->fields_listing) - 2) {
      $val = (isset($row[$col_index]) && array_key_exists($row['status'], $this->status_options))
              ? $this->status_options[$row['status']] . '&nbsp;' : '';
      if (isset($row['status_flags'])) {
        $status_labels = [];
        $status_flag_labels = [
          0x01 => tr('Digitization') . ' ' . tr('finalized'),
          0x02 => tr('Transcript and Markup') . ' ' . tr('finalized'),
          0x04 => tr('Bibliography') . ' ' . tr('finalized'),
          0x08 => tr('Translation') . ' ' . tr('finalized'),
          0x10 => tr('Translation Markup') . ' ' . tr('finalized'),
          0x20 => tr('ready for publishing'),
        ];

        foreach ($status_flag_labels as $mask => $label) {
          $status_labels[] = sprintf('<li class="%s"><a href="#" title="%s">%s</a></li>',
                                     0 != ($row['status_flags'] & $mask) ? 'active' : 'inactive',
                                     $label,
                                     mb_substr($label, 0, 1, $this->charset));
        }
        $val .= '<ul class="status">' . implode('', $status_labels) . '</ul>';
      }
    }
    else if ($col_index == count($this->fields_listing) - 1) {
      $url_preview = $this->page->buildLink([ 'pn' => $this->page->name, $this->workflow->name(TABLEMANAGER_VIEW) => $row[0] ]);
      $val = sprintf('[<a href="%s">%s</a>]',
                     htmlspecialchars($url_preview),
                     tr('view'));
    }

    return parent::buildListingCell($row, $col_index, $val);
  }
}

$display = new DisplayPublication($page);
if (false === $display->init()) {
  $page->redirect([ 'pn' => '' ]);
}

$page->setDisplay($display);
