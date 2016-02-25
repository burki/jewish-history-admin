<?php
/*
 * admin_publication.inc.php
 *
 * Class for managing publications (sources)
 *
 * (c) 2007-2016 daniel.burckhardt@sur-gmbh.ch
 *
 * Version: 2016-02-25 dbu
 *
 * Changes:
 *
 */

require_once INC_PATH . 'admin/displaybackend.inc.php';

require_once INC_PATH . 'common/biblioservice.inc.php';

class PublicationRecord extends TableManagerRecord
{
  var $languages = array('de', 'en');

  function store ($args = '') {
    // remove dashes and convert x to upper from isbn
    $isbn = $this->get_value('isbn');
    if (!empty($isbn)) {
      $this->set_value('isbn', BiblioService::normalizeIsbn($isbn));
    }

    $attribution = array();
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
        $folder = ImageUploadHandler::directory($id, $TYPE_PUBLICATION, TRUE);
        $fname = 'cover00';
        // get the extension from the url - TODO: check mime-type
        if (preg_match('/(\.[^\.]+)$/', $image_url, $matches)) {
          $ext = $matches[1];
        }

        $fullname = $folder . $fname . '_large' . $ext;

        if (ImageUploadHandler::checkDirectory($folder, TRUE)) {
          $handle = fopen($image_url, 'rb');
          $contents = stream_get_contents($handle);
          fclose($handle);

          $handle = fopen($fullname, "wb");
          if (fwrite($handle, $contents) === FALSE) {
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
      $attribution = json_decode($this->get_value('attribution'), TRUE);
      foreach ($this->languages as $language) {
        if (isset($attribution) && FALSE !== $attribution && array_key_exists($language, $attribution)) {
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
    return FALSE;
  }
}

class DisplayPublication extends DisplayBackend
{
  var $page_size = 30;
  var $table = 'Publication';
  var $fields_listing = array('id', 'IFNULL(author,editor)', 'title',
                              'YEAR(publication_date) AS year',
                              'status');

  var $status_options;
  var $status_default = '-99';
  var $status_deleted = '-1';

  var $condition = array(
      array('name' => 'search', 'method' => 'buildLikeCondition', 'args' => 'title,author,editor'),
      'Publication.status <> -1'
      // alternative: buildFulltextCondition
  );
  var $order = array(
                     'author' => array('IFNULL(author,editor)', 'IFNULL(author,editor) DESC'),
                     'title' => array('title', 'title DESC'),
                     'year' => array('YEAR(publication_date) DESC', 'YEAR(publication_date)'),
                     'status' => array('Publication.status', 'Publication.status DESC'),
                );
  var $cols_listing = array(
                            'author' => 'Author/Editor',
                            'title' => 'Title',
                            'year' => 'Year',
                            'status' => 'Status',
                            );
  var $view_after_edit = TRUE;

  function __construct (&$page) {
    global $STATUS_SOURCE_OPTIONS;

    $this->status_options = $this->view_options['status'] = $STATUS_SOURCE_OPTIONS;
    parent::__construct($page);

    $this->condition[] = array('name' => 'status',
                               'method' => 'buildEqualCondition',
                               'args' => $this->table . '.status',
                               'persist' => 'session');

    $this->condition[] = array('name' => 'status_translation',
                               'method' => 'buildEqualCondition',
                               'args' => $this->table . '.status_translation',
                               'persist' => 'session');

  }

  function init () {
    $ret = parent::init();
    if (FALSE === $ret) {
      return $ret;
    }

    if (!$this->checkAction(TABLEMANAGER_EDIT)) {
      $this->listing_default_action = TABLEMANAGER_VIEW;
    }

    return $ret;
  }

  function instantiateRecord ($table = '', $dbconn = '') {
    return new PublicationRecord(array('tables' => $this->table, 'dbconn' => $this->page->dbconn));
  }

  function buildStatusOptions ($options = NULL) {
    if (!isset($options)) {
      $options = & $this->status_options;
    }

    $status_options = array('<option value="">' . tr('-- all --') . '</option>');
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
          $languages_ordered['&#x2500;&#x2500;&#x2500;&#x2500;&#x2500;&#x2500;'] = FALSE; // separator
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
        $licenses = array();
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
          global $RIGHTS_REFEREE, $RIGHTS_TRANSLATOR;
          $querystr = "SELECT id, lastname, firstname FROM User";
          $querystr .= sprintf(" WHERE 0 <> (privs & %d) AND status <> %d",
                               'translator' == $type ? $RIGHTS_TRANSLATOR : $RIGHTS_REFEREE,
                               STATUS_USER_DELETED);
          $querystr .= " ORDER BY lastname, firstname";
          break;
    }

    if (isset($querystr)) {
      $dbconn->query($querystr);
      $options = array();
      while ($dbconn->next_record()) {
        $options[$dbconn->Record['id']] = in_array($type, array('sourcetype', 'publisher'))
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
    $this->view_options['translator'] = $this->translator_options = $this->buildOptions('translator');
    $this->view_options['lang'] = $this->buildOptions('lang');
    $this->view_options['status_translation'] = $this->status_translation_options
      = array('' => tr('-- please select --')) + $this->buildOptions('status_translation');
    $this->view_options['license'] = $license_options = $this->buildOptions('license');
    $languages_ordered = array('' => tr('-- please select --')) + $this->view_options['lang'];

    $publisher_options = $this->buildOptions('publisher');
    $record->add_fields(array(
        new Field(array('name' => 'id', 'type' => 'hidden', 'datatype' => 'int', 'primarykey' => TRUE)),
//        new Field(array('name' => 'status', 'type' => 'hidden', 'datatype' => 'int', 'default' => 0)),
        new Field(array('name' => 'status', 'type' => 'select',
                        'options' => array_keys($this->status_options),
                        'labels' => array_values($this->status_options),
                        'datatype' => 'int', 'default' => $this->status_default)),
        new Field(array('name' => 'created', 'type' => 'hidden', 'datatype' => 'function', 'value' => 'NOW()', 'noupdate' => TRUE)),
        new Field(array('name' => 'created_by', 'type' => 'hidden', 'datatype' => 'int', 'value' => $this->page->user['id'], 'null' => TRUE, 'noupdate' => TRUE)),
        new Field(array('name' => 'changed', 'type' => 'hidden', 'datatype' => 'function', 'value' => 'NOW()')),
        new Field(array('name' => 'changed_by', 'type' => 'hidden', 'datatype' => 'int', 'value' => $this->page->user['id'], 'null' => TRUE)),
        new Field(array('name' => 'type', 'id' => 'type', 'type' => 'select', 'datatype' => 'char', 'options' => array_keys($type_options), 'labels' => array_values($type_options))),

        new Field(array('name' => 'status_flags', 'type' => 'checkbox', 'datatype' => 'bitmap', 'null' => TRUE, 'default' => 0,
                              'labels' => array(
                                                0x01 => tr('Digitization') . ' ' . tr('finalized'),
                                                0x02 => tr('Transcript and Markup') . ' ' . tr('finalized'),
                                                0x04 => tr('Bibliography') . ' ' . tr('finalized'),
                                                0x08 => tr('Translation') . ' ' . tr('finalized'),
                                                0x10 => tr('Translation Markup') . ' ' . tr('finalized'),
                                                0x20 => tr('ready for publishing'),
                                                ),
                             )
                       ),

       // new Field(array('name' => 'isbn', 'id' => 'isbn', 'type' => 'text', 'size' => 20, 'datatype' => 'char', 'maxlength' => 17, 'null' => TRUE)),
        new Field(array('name' => 'author', 'id' => 'author', 'type' => 'text', 'size' => 60, 'datatype' => 'char', 'maxlength' => 80, 'null' => TRUE)),
        new Field(array('name' => 'editor', 'id' => 'editor', 'type' => 'text', 'size' => 60, 'datatype' => 'char', 'maxlength' => 80, 'null' => TRUE)),
        new Field(array('name' => 'title', 'id' => 'title', 'type' => 'text', 'size' => 60, 'datatype' => 'char', 'maxlength' => 127)),
        new Field(array('name' => 'subtitle', 'id' => 'subtitle', 'type' => 'text', 'size' => 60, 'datatype' => 'char', 'maxlength' => 127, 'null' => TRUE)),
        new Field(array('name' => 'series', 'id' => 'series', 'type' => 'text', 'size' => 60, 'datatype' => 'char', 'maxlength' => 127, 'null' => TRUE)),
        new Field(array('name' => 'place', 'id' => 'place', 'type' => 'text', 'size' => 60, 'datatype' => 'char', 'maxlength' => 127, 'null' => TRUE)),
        new Field(array('name' => 'publisher_id', 'id' => 'publisher_id', 'type' => 'select',
                        'options' => array_merge(array(''), array_keys($publisher_options)),
                        'labels' => array_merge(array('-- select a holding institution --'), array_values($publisher_options)), 'datatype' => 'int')),
        // new Field(array('name' => 'publisher', 'id' => 'publisher', 'type' => 'text', 'size' => 60, 'datatype' => 'char', 'maxlength' => 127, 'null' => TRUE)),
        new Field(array('name' => 'archive_location', 'id' => 'archive_location', 'type' => 'text', 'size' => 60, 'datatype' => 'char', 'maxlength' => 80, 'null' => TRUE)),
        new Field(array('name' => 'publication_date', 'id' => 'publication_date', 'type' => 'date', 'incomplete' => TRUE, 'datatype' => 'date', 'null' => 1)),
        new Field(array('name' => 'binding', 'id' => 'binding', 'type' => 'text', 'size' => 60, 'datatype' => 'char', 'maxlength' => 50, 'null' => TRUE)),
        new Field(array('name' => 'pages', 'id' => 'pages', 'type' => 'text', 'size' => 60, 'datatype' => 'char', 'maxlength' => 50, 'null' => TRUE)),
        new Field(array('name' => 'listprice', 'id' => 'listprice', 'type' => 'text', 'size' =>60, 'datatype' => 'char', 'maxlength' => 50, 'null' => TRUE)),
        new Field(array('name' => 'image_url', 'id' => 'image', 'type' => 'hidden', 'datatype' => 'char', 'null' => TRUE, 'nodbfield' => TRUE)),
        new Field(array('name' => 'url', 'id' => 'toc_url', 'type' => 'text', 'size' => 60, 'datatype' => 'char', 'maxlenght' => 255, 'null' => TRUE)),

        new Field(array('name' => 'lang', 'type' => 'select', 'datatype' => 'char', 'options' => array_keys($languages_ordered), 'labels' => array_values($languages_ordered), 'null' => TRUE)),
        new Field(array('name' => 'translator', 'type' => 'select',
                        'options' => array_merge(array(''), array_keys($this->translator_options)),
                        'labels' => array_merge(array(tr('-- none --')), array_values($this->translator_options)),
                        'datatype' => 'int', 'null' => TRUE)),
        new Field(array('name' => 'status_translation', 'type' => 'select', 'datatype' => 'char', 'options' => array_keys($this->status_translation_options), 'labels' => array_values($this->status_translation_options), 'null' => TRUE)),
        new Field(array('name' => 'place_identifier', 'id' => 'place_identifier', 'type' => 'text', 'size' => 60, 'datatype' => 'char', 'maxlenght' => 255, 'null' => TRUE)),
        new Field(array('name' => 'indexingdate', 'type' => 'date', 'incomplete' => TRUE, 'datatype' => 'date', 'null' => TRUE)),
        new Field(array('name' => 'displaydate', 'type' => 'text', 'size' => 40, 'datatype' => 'char', 'maxlength' => 80, 'null' => TRUE)),

        new Field(array('name' => 'license', 'id' => 'license', 'type' => 'select',
                        'options' => array_keys($license_options),
                        'labels' => array_values($license_options), 'datatype' => 'char', 'null' => TRUE)),
        new Field(array('name' => 'attribution', 'type' => 'hidden', 'datatype' => 'char', 'null' => TRUE)),
        new Field(array('name' => 'attribution_de', 'type' => 'textarea', 'datatype' => 'char', 'cols' => 65, 'rows' => 3, 'null' => TRUE, 'nodbfield' => TRUE)),
        new Field(array('name' => 'attribution_en', 'type' => 'textarea', 'datatype' => 'char', 'cols' => 65, 'rows' => 3, 'null' => TRUE, 'nodbfield' => TRUE)),

        new Field(array('name' => 'comment_digitization', 'type' => 'textarea', 'datatype' => 'char', 'cols' => 65, 'rows' => 8, 'null' => TRUE)),
        new Field(array('name' => 'comment_markup', 'type' => 'textarea', 'datatype' => 'char', 'cols' => 65, 'rows' => 8, 'null' => TRUE)),
        new Field(array('name' => 'comment_bibliography', 'type' => 'textarea', 'datatype' => 'char', 'cols' => 65, 'rows' => 8, 'null' => TRUE)),
        new Field(array('name' => 'comment_translation', 'type' => 'textarea', 'datatype' => 'char', 'cols' => 65, 'rows' => 8, 'null' => TRUE)),
        new Field(array('name' => 'comment_translation_markup', 'type' => 'textarea', 'datatype' => 'char', 'cols' => 65, 'rows' => 8, 'null' => TRUE)),

        new Field(array('name' => 'comment', 'type' => 'textarea', 'datatype' => 'char', 'cols' => 65, 'rows' => 15, 'null' => TRUE)),
      ));

    return $record;
  }

  function getEditRows ($mode = 'edit') {
    $add_publisher_button = sprintf('<input type="button" value="%s" onclick="window.open(\'%s\')" />',
                                    tr('add new Holding Institution'), htmlspecialchars($this->page->buildLink(array('pn' => 'publisher', 'edit' => -1))));

    $rows = array(
      'id' => FALSE, // 'status' => FALSE, // hidden fields

      'status' => array('label' => 'Status'),
      'type' => array('label' => 'Source Type'),

      /* 'isbn' => array('label' => 'ISBN'),
      '<input type="button" value="' . tr('Get Info') . '" onclick="fetchPublicationByIsbn()" />',
      */
      'author' => array('label' => 'Author(s)'),
      'editor' => array('label' => 'Editor(s)'),
      'title' => array('label' => 'Title'),
      'subtitle' => array('label' => 'Subtitle'),
      'series' => array('label' => 'Series'),
      'place' => array('label' => 'Place of publication'),
      'publisher_id' => array('label' => 'Holding Institution',
                              'value' => isset($this->form)
                              ? $this->getFormField('publisher_id') . $add_publisher_button
                              : ''),
      'archive_location' => array('label' => 'Archive location'),
      'publication_date' => array('label' => 'Publication date'),
      'binding' => array('label' => 'Binding'),
      'pages' => array('label' => 'Pages/Ills.'),
      // 'listprice' => array('label' => 'List price'),
      'url' => array('label' => 'URL'),
      'image_url' => FALSE, // hidden field

      'lang' => array('label' => 'Quellsprache'),
      'translator' => array('label' => 'Translator'),
      'status_translation' => array('label' => 'Translation Status'),

      'place_identifier' => array('label' => 'Primärort (Getty-Identifier)'),
      'indexingdate' => array('label' => 'Primärdatum (JJJJ oder TT.MM.JJJJ)'),
      'displaydate' => array('label' => 'Übersteuerung Primärdatum (z.B. "um 1600")'),

      '<hr noshade="noshade" />',
    );

    $additional = array(
        'license' => array('label' => 'License'),
        'attribution_de' => array('label' => 'Attribution (German)'),
        'attribution_en' => array('label' => 'Attribution (English)'),
    );

    if ('edit' == $mode) {
      $status_flags = $this->form->field('status_flags');
    }
    else {
      $status_flags_value = $this->record->get_value('status_flags');
    }

    foreach (array(
                   'digitization' => array('label' => 'Digitization', 'mask' => 0x1),
                   'markup' => array('label' => 'Transcript and Markup', 'mask' => 0x02),
                   'bibliography' => array('label' => 'Bibliography', 'mask' => 0x04),
                   'translation' => array('label' => 'Translation', 'mask' => 0x08),
                   'translation_markup' => array('label' => 'Translation Markup', 'mask' => 0x10),
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
          . ('edit' == $mode
                                  ? $this->getFormField('comment_' . $key)
                                  : $this->record->get_value('comment_' . $key))
      );
    }

    $rows = array_merge($rows, $additional);

    $rows = array_merge($rows,
      array(

        '<hr noshade="noshade" />',

        'comment' => array('label' => 'Internal notes and comments'),

        isset($this->form) ? $this->form->show_submit(ucfirst(tr('save'))) : 'FALSE',
      ));

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

    $images = array(
          'source' => array(
                        'title' => tr('Digitized Media / Transcript'),
                        'multiple' => TRUE,
                        'imgparams' => array('height' => 164,
                                             'scale' => 'down',
                                             'keep' => 'large',
                                             'keep_orig' => TRUE,
                                             'title' => 'File',
                                             'pdf' => TRUE,
                                             'audio' => TRUE,
                                             'video' => TRUE,
                                             'office' => TRUE,
                                             'xml' => TRUE,
                                             ),
                        ));

    return array($TYPE_PUBLICATION, $images);
  }

  function getViewFormats () {
    // return array('body' => array('format' => 'p'));
  }

  function buildViewRows () {
    $resolve_options = array(
                             'type' => 'type',
                             'publisher_id' => 'publisher',
                             'lang' => 'lang',
                             'translator' => 'translator',
                             );

    $rows = $this->getEditRows('view');
    if (isset($rows['title'])) {
      unset($rows['title']);
    }
    unset($rows['publisher_id']['value']); // remove custom-edit value

    $formats = $this->getViewFormats();

    $view_rows = array();

    foreach ($rows as $key => $descr) {
      if ($descr !== FALSE && gettype($key) == 'string') {
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
    $fields = array();
    if ('array' == gettype($rows)) {
      foreach ($rows as $key => $row_descr) {
        if ('string' == gettype($row_descr)) {
          $fields[] = array('&nbsp;', $row_descr);
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

          $fields[] = array($label, $value);
        }
      }
    }
    if (count($fields) > 0) {
      $ret .= $this->buildContentLineMultiple($fields);
    }

    return $ret;
  }

  function buildReviewSubject (&$record) {
    $editor = FALSE;
    $authors = $record->get_value('author');
    if (empty($authors)) {
      $authors = $record->get_value('editor');
      $editor = TRUE;
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
    if ($found = $record->fetch($this->id)) {
      $this->record = &$record;
      $uploadHandler = $this->instantiateUploadHandler();
      if (isset($uploadHandler)) {
        $this->processUpload($uploadHandler);
      }

      $rows = $this->buildViewRows();
      $edit = $this->buildEditButton();

      $ret = '<h2>' . $this->formatText($record->get_value('title')) . ' ' . $edit . '</h2>';

      $ret .= $this->renderView($record, $rows);

      $reviews_found = FALSE; $reviews = '';

      // show all articles related to this source
      $querystr = sprintf("SELECT Message.id AS id, subject, status"
                          ." FROM Message, MessagePublication"
                          ." WHERE MessagePublication.publication_id=%d AND MessagePublication.message_id=Message.id AND Message.status <> %d"
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
          .sprintf('<a href="%s">' . $this->formatText($dbconn->Record['subject']) . '</a>',
                   htmlspecialchars($this->page->buildLink($params_view)))
          .'</li>';
      }
      if ($reviews_found) {
        $reviews .= '</ul>';
      }
      else {
        global $JAVASCRIPT_CONFIRMDELETE;

        $this->script_code .= $JAVASCRIPT_CONFIRMDELETE;
        $url_delete = $this->page->buildLink(array('pn' => $this->page->name, $this->workflow->name(TABLEMANAGER_DELETE) => $this->id));
        $ret .= sprintf("<p>[<a href=\"javascript:confirmDelete('%s', '%s')\">%s</a>]</p>",
                        'Wollen Sie diese Quelle wirklich l&ouml;schen?\n(kein UNDO)',
                        htmlspecialchars($url_delete),
                        tr('delete Source'));
      }
      $url_add = $this->page->buildLink(array('pn' => 'article', 'edit' => -1,
                                              'subject' => $this->buildReviewSubject($record), 'publication' => $this->id));
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

  function buildSearchFields ($options = array()) {
    $options['status_translation'] = '';

    $search = sprintf('<input type="text" name="search" value="%s" size="40" />',
                      $this->htmlSpecialchars(array_key_exists('search', $this->search) ?  $this->search['search'] : ''));
    /*
    $search .= sprintf('<label><input type="hidden" name="fulltext" value="0" /><input type="checkbox" name="fulltext" value="1"%s /> %s</label>',
                       $this->search_fulltext ? ' checked="checked"' : '',
                       tr('Fulltext'));
    */

    $search .=  '<br />' . $this->buildStatusOptions();

    $select_fields = array('status');

    if (method_exists($this, 'buildOptions')) {
      foreach ($options as $name => $option_label) {
         $select_fields[] = $name;
        // Betreuer - TODO: make a bit more generic
        $select_options = array('<option value="">' . tr('-- all --') . '</option>');
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

  function buildListingCell (&$row, $col_index, $val = NULL) {
    $val = NULL;
    if ($col_index == count($this->fields_listing) - 1) {
      $val = (isset($row[$col_index]) && array_key_exists($row[$col_index], $this->status_options))
              ? $this->status_options[$row[$col_index]] . '&nbsp;' : '';

      $url_preview = $this->page->buildLink(array('pn' => $this->page->name, $this->workflow->name(TABLEMANAGER_VIEW) => $row[0]));
      $val = sprintf('<div style="text-align:right; white-space:nowrap">%s[<a href="%s">%s</a>]</div>',
                     $val,
                     htmlspecialchars($url_preview),
                     tr('view'));
    }

    return parent::buildListingCell($row, $col_index, $val);
  }

}

$display = new DisplayPublication($page);
if (FALSE === $display->init()) {
  $page->redirect(array('pn' => ''));
}

$page->setDisplay($display);
