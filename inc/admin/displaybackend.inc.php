<?php
/*
 * displabackend.inc.php
 *
 * Base-Class for backend
 *
 * (c) 2007-2018 daniel.burckhardt@sur-gmbh.ch
 *
 * Version: 2018-09-27 dbu
 *
 * Changes:
 *
 */

require_once INC_PATH . 'common/tablemanager.inc.php';
require_once INC_PATH . 'common/image_upload_handler.inc.php';

// small helper function
function array_merge_at ($array1, $array2, $after_field = null) {
  if (empty($after_field)) {
    return array_merge($array1, $array2);
  }

  $ret = [];
  foreach ($array1 as $key => $val) {
    if ('integer' == gettype($key)) {
      $ret[] = $val; // renumber numeric indices
    }
    else {
      $ret[$key] = $val;
    }

    if (isset($after_field) && $key === $after_field) {
      //var_dump($after_field);
      $ret = array_merge($ret, $array2);
      unset($after_field);
      //var_dump($ret);
    }
  }

  return $ret;
}

class DisplayBackendFlow
extends TableManagerFlow
{
  function __construct ($page) {
    parent::__construct(true); // view after edit
  }

  function init ($page) {
    $ret = parent::init($page);


    if (TABLEMANAGER_EDIT == $ret) {
      global $RIGHTS_EDITOR, $RIGHTS_ADMIN;
      if (empty($page->user)
          || 0 == ($page->user['privs'] & ($RIGHTS_ADMIN | $RIGHTS_EDITOR)))
      {
        // only view
        return isset($this->id) ? TABLEMANAGER_VIEW : TABLEMANAGER_LIST;
      }
    }

    return $ret;
  }
}

class CollectingReader
extends Sabre\Xml\Reader
{
  function xml($source, $encoding = null, $options = 0)
  {
    // hack for <?xml-model href="http://www.deutschestextarchiv.de/basisformat_ohne_header.rng"
    // type="application/xml"
    // schematypens="http://relaxng.org/ns/structure/1.0"?\>
    $source = preg_replace('/<\?xml\-model [\s\S]*?\?>/', '', $source);

    parent::xml($source, $encoding, $options);
  }

  function collect($output)
  {
    $this->collected[] = $output;
  }

  function parse()
  {
    $this->collected = [];
    parent::parse();
    return $this->collected;
  }

  static function collectElement(Sabre\Xml\Reader $reader)
  {
    $name = $reader->getClark();
    // var_dump($name);
    $attributes = $reader->parseAttributes();

    $res = [
      'name' => $name,
      'attributes' => $attributes,
      'text' => $reader->readText(),
    ];

    $reader->collect($res);

    $reader->next();
  }
}

class BackendImageUploadHandler
extends ImageUploadHandler
{
  function instantiateUploadRecord ($dbconn) {
    $img_record = parent::instantiateUploadRecord($dbconn);

    $img_record->add_fields([
      new Field([ 'name' => 'additional', 'type' => 'hidden', 'cols' => 60, 'rows' => 3, 'datatype' => 'char', 'null' => true ]),
    ]);

    return $img_record;
  }

  function storeImgData ($img, $img_form, $img_name) {
    $imgdata = isset($img) ? $img->find_imgdata() : [];

    if (count($imgdata) > 0) {
      if (isset($this->dbconn)) {
        $dbconn = & $this->dbconn;
      }
      else {
        $dbconn = new DB;
      }
      $additional = [];
      if ('application/xml' == $imgdata[0]['mime']) {
        $input = file_get_contents($imgdata[0]['fname']);
        $reader = new CollectingReader();
        $reader->elementMap = [
            '{http://www.tei-c.org/ns/1.0}persName' => 'CollectingReader::collectElement',
            '{http://www.tei-c.org/ns/1.0}placeName' => 'CollectingReader::collectElement',
        ];

        try {
          $reader->xml($input);
          $output = $reader->parse();
          foreach ($output as $entity) {
            if (empty($entity['attributes']['ref'])) {
              continue;
            }
            $uri = trim($entity['attributes']['ref']);
            switch ($entity['name']) {
              case '{http://www.tei-c.org/ns/1.0}placeName':
                $type = 'place';
                if (preg_match('/^'
                               . preg_quote('http://vocab.getty.edu/tgn/', '/')
                               . '\d+$/', $uri))
                {
                }
                else {
                  // die($uri);
                  unset($uri);
                }
                break;

              case '{http://www.tei-c.org/ns/1.0}persName':
                $type = 'person';
                if (preg_match('/^'
                               . preg_quote('http://d-nb.info/gnd/', '/')
                               . '\d+[xX]?$/', $uri))
                {
                }
                else {
                  // die($uri);
                  unset($uri);
                }
                break;

              default:
                unset($uri);
            }

            if (isset($uri)) {
              if (!isset($additional[$type])) {
                $additional[$type] = [];
              }
              if (!isset($additional[$type][$uri])) {
                $additional[$type][$uri] = 0;
              }
              ++$additional[$type][$uri];
            }
          }
        }
        catch (\Exception $e) {
        }
      }

      // we have an image
      $img_form->set_values([
        'name' => $img_name, 'width' => isset($imgdata[0]['width']) ? $imgdata[0]['width'] : -1,
        'height' => isset($imgdata[0]['height']) ? $imgdata[0]['height'] : -1,
        'mimetype' => $imgdata[0]['mime'],
        'additional' => json_encode($additional),
      ]);

      // find out if we already have an item
      $querystr = sprintf("SELECT id FROM Media WHERE item_id=%d AND type=%d AND name='%s' ORDER BY ord DESC LIMIT 1",
                          $this->item_id, $this->type, $img_name);
      $dbconn->query($querystr);
      if ($dbconn->next_record()) {
        $img_form->set_value('id', $dbconn->Record['id']);
        // remove old entries from MediaEntity
        $querystr = sprintf("DELETE FROM MediaEntity WHERE media_id=%d",
                            $dbconn->Record['id']);
        $dbconn->query($querystr);
      }

      $img_form->set_values([ 'item_id' => $this->item_id, 'ord' => 0 ]);
      $img_form->store();
      if (!empty($additional)) {
        global $TYPE_PERSON, $TYPE_PLACE;

        $media_id = $img_form->get_value('id');
        if ($media_id > 0) {
          foreach ($additional as $type => $entries) {
            foreach ($entries as $uri => $num) {
              $querystr = "INSERT INTO MediaEntity (media_id, uri, type, num)"
                        . sprintf(" VALUES(%d, '%s', %d, %d)",
                                  $media_id, addslashes($uri),
                                  'person' == $type ? $TYPE_PERSON : $TYPE_PLACE,
                                  $num)
                        . sprintf(' ON DUPLICATE KEY UPDATE num=%d', $num);
              $dbconn->query($querystr);
            }
          }
        }
      }
    }
  }

  // methods
  function delete ($img_name) {
    $media_id = parent::delete($img_name);
    if (isset($media_id) && $media_id > 0) {
      $querystr = sprintf("DELETE FROM MediaEntity WHERE media_id=%d",
                          $media_id);
      $dbconn = new DB;
      $dbconn->query($querystr);
    }
  }
}

/* Common base class for the backend with paging and upload handling */
class DisplayBackend
extends DisplayTable
{
  var $listing_default_action = TABLEMANAGER_EDIT;
  var $datetime_style = 'DD.MM.YYYY';
  var $status_deleted = '-1';

  function __construct (&$page, $workflow = '') {
    if (!is_object($workflow)) {
      $workflow = new DisplayBackendFlow($page);
    }

    parent::__construct($page, $workflow);

    $this->script_url[] = 'script/jquery-1.9.1.min.js';
    $this->script_url[] = 'script/jquery-noconflict.js';
    $this->script_url[] = 'script/jquery-ui-1.10.3.custom.min.js';
  }

  function checkAction ($step) {
    if ($this->page->isAdminUser()) {
      // admins have all rights
      return true;
    }

    if (TABLEMANAGER_EDIT == $step) {
      // all editors and admins may edit by default (item overrides this)
      global $RIGHTS_EDITOR, $RIGHTS_ADMIN;

      if (!empty($this->page->user)
          && 0 != ($this->page->user['privs'] & ($RIGHTS_ADMIN | $RIGHTS_EDITOR)))
      {
        return true;
      }
    }

    return false;
  }

  function mayEdit ($row) {
    return $this->checkAction(TABLEMANAGER_EDIT);
  }

  function buildListingAddItem () {
    if (!$this->checkAction(TABLEMANAGER_EDIT)) {
      return '';
    }

    return parent::buildListingAddItem();
  }

  function buildEditButton () {
    if (!$this->checkAction(TABLEMANAGER_EDIT)) {
      return '';
    }

    return sprintf(' <span class="regular">[<a href="%s">%s</a>]</span>',
                   htmlspecialchars($this->page->buildLink([ 'pn' => $this->page->name, $this->workflow->name(TABLEMANAGER_EDIT) => $this->id ])),
                   tr('edit'));
  }

  function initMultiselect () {
    // activate multiselect
    $this->script_url[] = 'script/jquery.uix.multiselect.min.js';
    $this->stylesheet[] = 'css/jquery.uix.multiselect.css';

    $setLocale = '';
    if ('de_DE' == $this->page->lang()) {
      $this->script_url[] = 'script/locale/jquery.uix.multiselect_de.js';
      $setLocale = ".multiselect('locale', 'de')";
    }

    $this->script_ready[] = "jQuery('select.uiMultiselect').multiselect()" . $setLocale . ";";
  }

  // try to determine change-conflicts
  function renderEditFormHiddenFields ($name) {
    $ret = parent::renderEditFormHiddenFields($name);
    $changed_datetime = $this->form->get_value('changed');
    if (!empty($changed_datetime) && 'NOW()' != $changed_datetime) {
      $ret .= sprintf('<input type="hidden" name="_changed" value="%s" />',
                      htmlspecialchars($changed_datetime));
    }

    return $ret;
  }

  function validateInput () {
    $success = parent::validateInput();

    if ($success) {
      if (!empty($_POST['_changed'])) {
        $id = $this->workflow->primaryKey();
        $record = $this->buildRecord();
        if ($found = $record->fetch($id)) {
          if (strcmp($_POST['_changed'], $record->get_value('changed')) < 0) {
            $this->page->msg = 'Dieser Datensatz wurde scheinbar zwischenzeitlich aktualisiert. Um einen Versionskonflikt zu vermeiden, &ouml;ffnen Sie ihn bitte erneut und tragen die &Auml;nderung nochmal ein.';

            return false;
          }
        }
      }
    }

    return $success;
  }

  function buildView () {
    $this->id = $this->workflow->primaryKey();
    $record = $this->buildRecord();
    $ret = '';

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

      if (isset($uploadHandler)) {
        $ret .= $this->renderUpload($uploadHandler);
      }
    }

    $ret .= $this->buildViewFooter($found);

    return $ret;
  }

  function getViewFormats () {
    return [
      'body' => [ 'format' => 'p' ],
    ];
  }

  function buildViewRows () {
    // a bit of a hack since formatText messes up numerical entities
    $status_options = [];
    if (isset($this->status_options)) {
      foreach ($this->status_options as $key => $val) {
        $status_options[$key] = mb_convert_encoding($val, 'UTF-8', 'HTML-ENTITIES');
      }
      $this->view_options['status'] = $status_options;
    }

    $rows = $this->getEditRows('view');
    if (isset($rows['title'])) {
      unset($rows['title']);
    }

    $formats = $this->getViewFormats();

    $view_rows = [];

    foreach ($rows as $key => $descr) {
      if ($descr !== false && gettype($key) == 'string') {
        if (isset($formats[$key])) {
          $descr = array_merge($descr, $formats[$key]);
        }
        $view_rows[$key] = $descr;
        if (isset($this->view_options[$key])) {
          $view_rows[$key]['options'] = $this->view_options[$key];
        }
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

  function buildRelatedPublications($uri) {
    // fetch the publications
    $querystr = sprintf("SELECT DISTINCT Publication.id AS id, title, author, editor, YEAR(publication_date) AS year, place, publisher"
                        . " FROM Publication, Media, MediaEntity"
                        . " WHERE MediaEntity.uri='%s' AND MediaEntity.media_id=Media.id AND Media.type=%s AND Media.item_id=Publication.id"
                        . " ORDER BY Publication.title",
                        $uri, $GLOBALS['TYPE_PUBLICATION']);
    $dbconn = &$this->page->dbconn;
    $dbconn->query($querystr);
    $publications = '';
    $params_view = [ 'pn' => 'publication' ];
    while ($dbconn->next_record()) {
      if (empty($publications)) {
        $publications = '<hr style="clear: both" /><ul id="publications">';
      }
      $params_view['view'] = $dbconn->Record['id'];
      $publisher_place_year = '';
      if (!empty($dbconn->Record['place'])) {
        $publisher_place_year = $dbconn->Record['place'];
      }
      if (!empty($dbconn->Record['publisher'])) {
        $publisher_place_year .= (!empty($publisher_place_year) ? ': ' : '')
          . $dbconn->Record['publisher'];
      }
      if (!empty($dbconn->Record['year'])) {
        $publisher_place_year .= (!empty($publisher_place_year) ? ', ' : '')
          . $dbconn->Record['year'];
      }

      $publications .= sprintf('<li id="item_%d">', $dbconn->Record['id'])
        . (isset($dbconn->Record['author']) ? $dbconn->Record['author'] : $dbconn->Record['editor'])
        . ': <i>' . $this->formatText($dbconn->Record['title']) . '</i>'
        . (!empty($publisher_place_year) ? ' ' : '')
        . $this->formatText($publisher_place_year)
        . sprintf(' [<a href="%s">%s</a>]',
                  htmlspecialchars($this->page->buildLink($params_view)), tr('view'))
        . '</li>';
    }

    if (!empty($publications)) {
      $publications .= '</ul>';
    }

    return $publications;
  }

  function buildViewFooter ($found = true) {
    $ret = ($found ? '<hr />' : '')
         . '[<a href="' . htmlspecialchars($this->page->buildLink([ 'pn' => $this->page->name ])) . '">'
         . tr('back to overview')
         . '</a>]';

    if ($found && isset($this->record)) {
      $status = $this->record->get_value('status');
      if ($status <= 0 && $this->checkAction(TABLEMANAGER_DELETE)) {
        global $JAVASCRIPT_CONFIRMDELETE;

        $this->script_code .= $JAVASCRIPT_CONFIRMDELETE;
        $url_delete = $this->page->buildLink([ 'pn' => $this->page->name, $this->workflow->name(TABLEMANAGER_DELETE) => $this->id ]);
        $ret .= sprintf(" [<a href=\"javascript:confirmDelete('%s', '%s')\">%s</a>]",
                          'Wollen Sie diesen Eintrag wirklich l&ouml;schen?\n(kein UNDO)',
                          htmlspecialchars($url_delete),
                          tr('delete entry'));
      }
    }

    return $ret;
  }

  function buildSearchBar () {
    // var_dump($this->search);

    $ret = sprintf('<form action="%s" method="post" name="search">',
                   htmlspecialchars($this->page->buildLink([ 'pn' => $this->page->name, 'page_id' => 0 ])));

    if (method_exists($this, 'buildSearchFields')) {
      $search = $this->buildSearchFields();
    }
    else {
      $search = sprintf('<input type="text" name="search" value="%s" size="40" /><input class="submit" type="submit" value="%s" />',
                        $this->htmlSpecialchars(array_key_exists('search', $this->search) ?  $this->search['search'] : ''),
                        tr('Search'));
    }

    $ret .= sprintf('<tr><td colspan="%d" nowrap="nowrap">%s</td></tr>',
                    $this->cols_listing_count,
                    $search);

    $ret .= '</form>';

    return $ret;
  } // buildSearchBar

  function getImageDescriptions () {
    // override for actual images
  }

  function instantiateUploadHandler () {
    list($media_type, $this->images) = $this->getImageDescriptions();

    if (isset($this->images)) {
      // check if we have to setup an image for body-rendering
      foreach ($this->images as $name => $descr) {
        if (isset($descr['placement']) && 'body' == $descr['placement']) {
          $this->image = [
            'message_id' => $this->workflow->primaryKey(),
            'type' => $media_type, 'name' => $name,
          ];
        }
      }

      return new BackendImageUploadHandler($this->workflow->primaryKey(), $media_type);
    }
  }

  function processUpload (&$imageUploadHandler) {
    $action = $this->page->buildLink([ 'pn' => $this->page->name, $this->workflow->name(TABLEMANAGER_VIEW) => $this->id ]);
    $upload_results = [];
    foreach ($this->images as $img_name => $img_descr) {
      $img_params = $img_descr['imgparams'];
      if (isset($img_descr['multiple'])) {
        if (is_bool($img_descr['multiple'])) {
          $max_images = $img_descr['multiple'] ? -1 : 1;
        }
        else {
          $max_images = intval($img_descr['multiple']);
        }
      }
      else {
        $max_images = 1;
      }

      // check if we need to delete something
      if (array_key_exists('delete_img', $this->page->parameters)) {
        $imageUploadHandler->delete($this->page->parameters['delete_img']);
      }

      $images = $imageUploadHandler->buildImages($img_name, $img_params, $max_images);
      $imageUpload = $imageUploadHandler->buildUpload($images, $action);

      if ($imageUpload->submitted()) {
        $upload_results[$img_name] = $imageUploadHandler->process($imageUpload, $images);
      }
    }

    $this->upload_results = $upload_results;
  }

  function getUploadFormField(&$upload_form, $name, $args = '') {
    $ret = '';
    $field = $upload_form->field($name);

    if (isset($field)) {
      if (isset($this->invalid[$name])) {
        $ret = '<div class="error">'
             . $this->form->error_fulltext($this->invalid[$name], $this->page->lang)
             . '</div>';
      }

      $ret .= $field->show($args);
    }

    return $ret;
  }

  function renderUpload (&$imageUploadHandler, $title = 'File Upload') {
    $ret = '';

    $params_self = [ 'pn' => $this->page->name, $this->workflow->name(TABLEMANAGER_VIEW) => $this->id ];
    $action = $this->page->buildLink($params_self);

    $first = true;

    foreach ($this->images as $img_name => $img_descr) {
      $rows = [];
      if (isset($img_descr['title'])) {
        $ret .= '<h3>' . $img_descr['title'] . '</h3>';
      }

      $upload_results = isset($this->upload_results[$img_name])
        ? $this->upload_results[$img_name]: [];
      // var_dump($upload_results);

      $max_images = 1;
      if (isset($img_descr['multiple'])) {
        if (is_bool($img_descr['multiple'])) {
          $max_images = $img_descr['multiple'] ? -1 : 1;
        }
        else {
          $max_images = intval($img_descr['multiple']);
        }
      }

      $img_params = $img_descr['imgparams'];

      $options = [];
      if (isset($img_params['title'])) {
        $options['title'] = $img_params['title'];
      }

      $images = $imageUploadHandler->buildImages($img_name, $img_params, $max_images, $options);
      $imageUpload = $imageUploadHandler->buildUpload($images, $action);

      if ($first) {
        $ret .= $imageUpload->show_start();
        $first = false;
      }

      $imageUploadHandler->fetchAll();

      $count = 0;
      foreach ($imageUploadHandler->img_titles as $img_name => $img_title) {
        $img = $imageUpload->image($img_name);
        if (isset($img)) {
          $img_field = '';
          if ($max_images != 1) {
            if ($count > 0) {
              $rows[] = '<hr />';
            }
            $img_field .= '<h4>' . $img_title . '</h4>';
          }
          ++$count;

          $img_form = & $imageUploadHandler->img_forms[$img_name];
          if (isset($upload_results[$img_name]) && isset($upload_results[$img_name]['status']) && $upload_results[$img_name]['status'] < 0) {
            $img_field .= '<div class="message">'
                        . $upload_results[$img_name]['msg']
                        . '</div>';
          }

          $url_delete = $this->page->buildLink(array_merge($params_self,
                                               [ 'delete_img' => $img_name ]));

          list($img_tag, $caption, $copyright) = $this->buildImage($imageUploadHandler->item_id, $imageUploadHandler->type, $img_name, true, true, true);
          // var_dump($img_tag);
          if (!empty($img_tag)) {
            $delete = '';
            if ($this->checkAction(TABLEMANAGER_EDIT)) {
              $delete = '[<a href="' . htmlspecialchars($url_delete) . '">' . tr('delete') . '</a>]';
            }

            $img_field .= '<p>'
                        . '<div style="margin-right: 2em; margin-bottom: 1em; float: left;">'
                        . $img_tag
                        . '</div>'
                        . (!empty($caption) ? $this->formatText($caption).'<br />&nbsp;<br />' : '')
                        . $delete
                        . '<br clear="left" />'
                        . '</p>';
          }

          $rows[] = $img_field;

          if ($this->checkAction(TABLEMANAGER_EDIT)) {
            $rows[] = [ 'File', $img->show_upload_field() ];
            $rows[] = [
              'Image Caption',
                $this->getUploadFormField($img_form, 'caption', [ 'prepend' => $img_name . '_' ]),
            ];
            $rows[] = [
              'Copyright-Notice',
              $this->getUploadFormField($img_form, 'copyright', [ 'prepend' => $img_name . '_' ]),
            ];

            $rows[] = [ '', '<input type="submit" value="' . ucfirst(tr('upload')) . '" />' ];
          }
        } // if
      }

      foreach ($rows as $row) {
        if (is_array($row)) {
          $ret .= $this->buildContentLine(tr($row[0]), $row[1]);
        }
        else {
          $ret .= $row;
        }
      } // foreach
    } // foreach

    if (!$first) {
      $ret = '<h2>' . $this->formatText(tr($title)) . '</h2>'
           . $ret;
      $ret .= $imageUpload->show_end();
    }

    return $ret;
  }
}
