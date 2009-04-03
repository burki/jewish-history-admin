<?php
/*
 * admin_publication.inc.php
 *
 * Class for managing publications (books)
 *
 * (c) 2007-2008 daniel.burckhardt@sur-gmbh.ch
 *
 * Version: 2008-11-19 dbu
 *
 * Changes:
 *
 */

require_once INC_PATH . 'common/image_upload_handler.inc.php';
require_once INC_PATH . 'common/tablemanager.inc.php';

require_once INC_PATH . 'common/biblioservice.inc.php';

class PublicationRecord extends TableManagerRecord
{
  function store () {
    // remove dashes and convert x to upper from isbn
    $isbn = $this->get_value('isbn');
    if (!empty($isbn))
      $this->set_value('isbn', BiblioService::normalizeIsbn($isbn));

    $stored = parent::store();

    if ($stored) { // currently don't fetch covers
      // here we are assured to have an valid id
      $image_url = $this->get_value('image_url');
      if (!empty($image_url)) {
        global $TYPE_PUBLICATION;

        // create local directory and write it
        $id = $this->get_value('id');
        $folder = ImageUploadHandler::directory($id, $TYPE_PUBLICATION, TRUE);
        $fname = 'cover00';
        // get the extension from the url - TODO: check mime-type
        if (preg_match('/(\.[^\.]+)$/', $image_url, $matches))
          $ext = $matches[1];

        $fullname = $folder.$fname.'_large'.$ext;

        if (ImageUploadHandler::checkDirectory($folder, TRUE)) {
          $handle = fopen($image_url, "rb");
          $contents = stream_get_contents($handle);
          fclose($handle);

          $handle = fopen($fullname, "wb");
          if (fwrite($handle, $contents) === FALSE) {
            $ret .= "<p>Error writing $fullname.</p>";
          }
          fclose($handle);
          if (file_exists($fullname)) {
            $fname_store = $fullname;

            $fullname_scaled = $folder.$fname.'.jpg';

            if (defined('UPLOAD_PATH2MAGICK')) {
              $cmd = UPLOAD_PATH2MAGICK . 'convert'
                   . ' -geometry x164'
                   . ' ' . escapeshellarg($fullname)
                   . ' ' . escapeshellarg($fullname_scaled);
              // var_dump($cmd);
              $ret = exec($cmd, $lines, $retval);
              if (file_exists($fullname_scaled))
                $fname_store = $fullname_scaled;
            }
            else
              copy($fullname, $fullname_scaled);

            $size = @getimagesize($fname_store);
            if (isset($size)) {

              // insert/update the Media-record
              $dbconn = new DB;

              $handler = new ImageUploadHandler ($id, $TYPE_PUBLICATION);
              $record = $handler->instantiateUploadRecord($dbconn);

              $record->set_value('item_id', $id);
              $record->set_value('ord', 0);
              $record->set_value('name', $fname);
              $record->set_value('width', $size[0]);
              $record->set_value('height', $size[1]);
              $record->set_value('mimetype', $size['mime']);
// var_dump($size);
              // find out if we already have an item
              $querystr = sprintf("SELECT id FROM Media WHERE item_id=%d AND type=%d AND name='%s' ORDER BY ord DESC LIMIT 1", $id, $TYPE_PUBLICATION, $fname);
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
    else
      return FALSE;
  }
}

class DisplayPublication extends DisplayTable
{
  var $page_size = 30;
  var $table = 'Publication';
  var $fields_listing = array('id', 'IFNULL(author,editor)', 'title', 'YEAR(publication_date) AS year', 'status');

  var $condition = array(
      array('name' => 'search', 'method' => 'buildLikeCondition', 'args' => 'title,author,editor'),
      'Publication.status>=0'
      // alternative: buildFulltextCondition
  );
  var $order = array(
                     'author' => array('IFNULL(author,editor)', 'IFNULL(author,editor) DESC'),
                     'title' => array('title', 'title DESC'),
                     'year' => array('YEAR(publication_date) DESC', 'YEAR(publication_date)')
                );
  var $cols_listing = array('author' => 'Author/Editor', 'title' => 'Title', 'year' => 'Year', '');
  var $view_after_edit = TRUE;

  function instantiateRecord () {
    return new PublicationRecord(array('tables' => $this->table, 'dbconn' => $this->page->dbconn));
  }

  function buildOptions ($category) {
    $dbconn = & $this->page->dbconn;
    switch ($category) {
      case 'publisher':
          $querystr = sprintf("SELECT id, name FROM %s WHERE status >= 0 ORDER BY name",
                            $dbconn->escape_string(ucfirst($category)));
          break;
    }
    if (isset($querystr)) {
      $dbconn->query($querystr);
      $options = array();
      while ($dbconn->next_record())
        $options[$dbconn->Record['id']] = $dbconn->Record['name'];

      return $options;
    }
  }

  function buildRecord ($name = '') {
    $record = &parent::buildRecord($name);

    if (!isset($record))
      return;

    $publisher_options = $this->buildOptions('publisher');
    $record->add_fields(array(
        new Field(array('name'=>'id', 'type'=>'hidden', 'datatype'=>'int', 'primarykey'=>1)),
        new Field(array('name'=>'status', 'type'=>'hidden', 'datatype'=>'int', 'default' => 0)),
        new Field(array('name'=>'created', 'type'=>'hidden', 'datatype'=>'function', 'value'=>'NOW()', 'noupdate' => TRUE)),
        new Field(array('name'=>'created_by', 'type'=>'hidden', 'datatype'=>'int', 'value' => $this->page->user['id'], 'null'=>1, 'noupdate' => TRUE)),
        new Field(array('name'=>'changed', 'type'=>'hidden', 'datatype'=>'function', 'value'=>'NOW()')),
        new Field(array('name'=>'changed_by', 'type'=>'hidden', 'datatype'=>'int', 'value' => $this->page->user['id'], 'null'=>1)),
        new Field(array('name'=>'isbn', 'id' => 'isbn', 'type'=>'text', 'size'=>20, 'datatype'=>'char', 'maxlength'=>17, 'null'=>1)),
        new Field(array('name'=>'author', 'id' => 'author', 'type'=>'text', 'size'=>60, 'datatype'=>'char', 'maxlength'=>80, 'null'=>1)),
        new Field(array('name'=>'editor', 'id' => 'editor', 'type'=>'text', 'size'=>60, 'datatype'=>'char', 'maxlength'=>80, 'null'=>1)),
        new Field(array('name'=>'title', 'id' => 'title', 'type'=>'text', 'size'=>60, 'datatype'=>'char', 'maxlength'=>127)),
        new Field(array('name'=>'subtitle', 'id' => 'subtitle', 'type'=>'text', 'size'=>60, 'datatype'=>'char', 'maxlength'=>127, 'null'=>1)),
        new Field(array('name'=>'series', 'id' => 'series', 'type'=>'text', 'size'=>60, 'datatype'=>'char', 'maxlength'=>127, 'null'=>1)),
        new Field(array('name'=>'place', 'id' => 'place', 'type'=>'text', 'size'=>60, 'datatype'=>'char', 'maxlength'=>127, 'null'=>1)),
        new Field(array('name'=>'publisher_id', 'id' => 'publisher_id', 'type'=>'select',
                        'options' => array_merge(array(''), array_keys($publisher_options)),
                        'labels' => array_merge(array('-- select a publisher --'), array_values($publisher_options)), 'datatype'=>'int')),
        // new Field(array('name'=>'publisher', 'id' => 'publisher', 'type'=>'text', 'size'=>60, 'datatype'=>'char', 'maxlength'=>127, 'null'=>1)),
        new Field(array('name'=>'publication_date', 'id' => 'publication_date', 'type'=>'date', 'incomplete' => TRUE, 'datatype'=>'date', 'null' => 1)),
        new Field(array('name'=>'binding', 'id' => 'binding', 'type'=>'text', 'size'=>60, 'datatype'=>'char', 'maxlength'=>50, 'null'=>1)),
        new Field(array('name'=>'pages', 'id' => 'pages', 'type'=>'text', 'size'=>60, 'datatype'=>'char', 'maxlength'=>50, 'null'=>1)),
        new Field(array('name'=>'listprice', 'id' => 'listprice', 'type'=>'text', 'size'=>60, 'datatype'=>'char', 'maxlength'=>50, 'null'=>1)),
        new Field(array('name'=>'image_url', 'id' => 'image', 'type'=>'hidden', 'datatype'=>'char', 'null'=>1, 'nodbfield'=>1)),
        new Field(array('name'=>'url', 'id' => 'toc_url', 'type'=>'text', 'size'=>60, 'datatype'=>'char', 'maxlenght'=>255, 'null'=>1)),
      ));

    return $record;
  }

  function getEditRows () {
    $add_publisher_button = sprintf('<input type="button" value="%s" onclick="window.open(\'%s\')" />',
                                    tr('add new publisher'), htmlspecialchars($this->page->buildLink(array('pn' => 'publisher', 'edit' => -1))));
    return array(
      'id' => FALSE, 'status' => FALSE, // hidden fields

      'isbn' => array('label' => 'ISBN'),
      '<input type="button" value="' . tr('Get Info') . '" onclick="fetchPublicationByIsbn()" />',
      'author' => array('label' => 'Author(s)'),
      'editor' => array('label' => 'Editor(s)'),
      'title' => array('label' => 'Title'),
      'subtitle' => array('label' => 'Subtitle'),
      'series' => array('label' => 'Series'),
      'place' => array('label' => 'Place of publication'),
      'publisher_id' => array('label' => 'Publisher',
                              'value' => isset($this->form) ? $this->getFormField('publisher_id').$add_publisher_button : ''),
      'publication_date' => array('label' => 'Publication date'),
      'binding' => array('label' => 'Binding'),
      'pages' => array('label' => 'Pages/Ills.'),
      'listprice' => array('label' => 'List price'),
      'url' => array('label' => 'TOC URL'),
      'image_url' => FALSE, // hidden field

      isset($this->form) ? $this->form->show_submit(ucfirst(tr('save'))) : 'FALSE'
    );
  }

  function renderEditForm ($rows, $name = 'detail') {
    $this->script_url[] = $this->page->BASE_PATH.'script/scriptaculous/prototype.js';

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
          if (obj['isbn'] != null)
            msg += ' unter der ISBN: ' + obj['isbn'];
          alert(msg);
        }
        else {
          var fields = ['title', 'subtitle', 'author', 'editor', 'series', 'binding', 'pages', 'publication_date', 'place', 'listprice', 'image'];
          for (var i = 0; i < fields.length; i++) {
            var name = fields[i];
            if (null != obj[name]) {
              var field = \$(fields[i]);
              if (null != field)
                field.value = obj[name];
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
          'cover' => array(
                        'title' => tr('Cover Image'),
                        'multiple' => FALSE,
                        'imgparams' => array(/* 'height' => 164, 'scale' => 'both', 'keep' => 'large' */)
                        ));

    return array($TYPE_PUBLICATION, $images);
  }

  function getViewFormats () {
    // return array('body' => array('format' => 'p'));
  }

  function buildViewRows () {
    $resolve_options = array('publisher_id' => 'publisher');

    $rows = $this->getEditRows();
    if (isset($rows['title']))
      unset($rows['title']);
    unset($rows['publisher_id']['value']); // remove custom-edit value

    $formats = $this->getViewFormats();

    $view_rows = array();

    foreach ($rows as $key => $descr) {
      if ($descr !== FALSE && gettype($key) == 'string') {
        if (isset($formats[$key]))
          $descr = array_merge($descr, $formats[$key]);
        $view_rows[$key] = $descr;
        if (array_key_exists($key, $resolve_options)) {
          // var_dump($key);
          $view_rows[$key]['options'] = $this->buildOptions($resolve_options[$key]);
        }
      }
    }

    return $view_rows;
  }

  function renderView ($record, $rows) {
    $ret = '';
    if (!empty($this->page->msg))
      $ret .= '<p class="message">'.$this->page->msg.'</p>';

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
              $field_value = array_key_exists('format', $row_descr) && 'p' == $row_descr['format']
                ? $this->formatParagraphs($field_value) : $this->formatText($field_value);
              $value .= (!empty($value) ? ' ' : '').$field_value;
            }
          }
          else if (isset($row_descr['value']))
            $value = $row_descr['value'];
          else {
            $field_value = $record->get_value($key);
            if (isset($row_descr['options']) && isset($field_value) && '' !== $field_value) {
              $values = preg_split('/,\s*/', $field_value);
              for ($i = 0; $i < sizeof($values); $i++)
                if (isset($row_descr['options'][$values[$i]]))
                  $values[$i] = $row_descr['options'][$values[$i]];
              $field_value = implode(', ', $values);
            }
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
    return sprintf(' <span class="regular">[<a href="%s">edit</a>]</span>',
                   htmlspecialchars($this->page->buildLink(array('pn' => $this->page->name, $this->workflow->name(TABLEMANAGER_EDIT) => $this->id))));
  }

  function buildReviewSubject (&$record) {
    $editor = FALSE;
    $authors = $record->get_value('author');
    if (empty($authors)) {
      $authors = $record->get_value('editor');
      $editor = TRUE;
    }
    if (!empty($authors)) {
      // $parts = preg_split('/\s*\;\s/', $authors);
      // list($lastname, $firstname) = preg_split('/\s*,\s*/', $parts[0]);
      // $author_short = (!empty($firstname) ? $firstname[0].'. ' : '').$lastname;
      $author_short = $authors;
      if ($editor)
        $author_short .= ' (Hrsg.)';
      $author_short .= ': ';
    }
    else
      $author_short = '';

    $subject = $author_short . $this->formatText($record->get_value('title'));
    return htmlspecialchars_decode(strip_tags($subject));
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

      $ret = '<h2>'.$this->formatText($record->get_value('title')).' '.$edit.'</h2>';

      $ret .= $this->renderView($record, $rows);

      // show all reviews related to this publication
      $querystr = sprintf("SELECT Message.id AS id, subject, status"
                          ." FROM Message, MessagePublication"
                          ." WHERE MessagePublication.publication_id=%d AND MessagePublication.message_id=Message.id AND Message.status <> %d"
                          ." ORDER BY Message.id DESC",
                          $this->id, STATUS_DELETED);
      $dbconn = & $this->page->dbconn;
      $dbconn->query($querystr);
      $reviews = '';
      $params_view = array('pn' => 'review');
      $reviews_found = FALSE;
      while ($dbconn->next_record()) {
        if (!$reviews_found) {
          $reviews = '<ul>';
          $reviews_found = TRUE;
        }
        $params_view['view'] = $dbconn->Record['id'];
        $reviews .= sprintf('<li id="item_%d">', $dbconn->Record['id'])
          .sprintf('<a href="%s">'.$this->formatText($dbconn->Record['subject']).'</a>', htmlspecialchars($this->page->buildLink($params_view)))
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
                        'Wollen Sie diese Publikation wirklich l&ouml;schen?\n(kein UNDO)',
                        htmlspecialchars($url_delete),
                        tr('delete publication'));
      }
      $url_add = $this->page->buildLink(array('pn' => 'review', 'edit' => -1, 'subject' => $this->buildReviewSubject($record), 'publication' => $this->id));
      $ret .= '<h2>'.tr('Reviews').' <span class="regular">[<a href="'.htmlspecialchars($url_add).'">'.tr('add new').'</a>]</span></h2>'.$reviews;

      if (isset($uploadHandler))
        $ret .= $this->renderUpload($uploadHandler);

    }

    return $ret;
  }

  function buildListingCell (&$row, $col_index) {
    global $ITEM_STATUS;

    $val = NULL;
    if ($col_index == sizeof($this->fields_listing) - 1) {
      $url_preview = $this->page->buildLink(array('pn' => $this->page->name, $this->workflow->name(TABLEMANAGER_VIEW) => $row[0]));
      $val = sprintf('<div style="text-align:right;">[<a href="%s">%s</a>]</div>',
                     htmlspecialchars($url_preview),
                     tr('view'));
    }

    return parent::buildListingCell($row, $col_index, $val);
  }

}

$display = new DisplayPublication($page);
if (FALSE === $display->init())
  $page->redirect(array('pn' => ''));

$page->setDisplay($display);
