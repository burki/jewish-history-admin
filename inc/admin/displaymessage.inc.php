<?php
/*
 * displaymessage.inc.php
 *
 * Base-Class for managing messages
 *
 * (c) 2007-2013 daniel.burckhardt@sur-gmbh.ch
 *
 * Version: 2013-11-25 dbu
 *
 * Changes:
 *
 */

require_once INC_PATH . 'common/tablemanager.inc.php';
require_once INC_PATH . 'common/image_upload_handler.inc.php';

// small helper function
function array_merge_at ($array1, $array2, $after_field=NULL) {
  if (empty($after_field))
    return array_merge($array1, $array2);
  $ret = array();
  foreach ($array1 as $key => $val) {
    if ('integer' == gettype($key))
      $ret[] = $val; // renumber numeric indices
    else
      $ret[$key] = $val;
    if (isset($after_field) && $key == $after_field) {
      //var_dump($after_field);
      $ret = array_merge($ret, $array2);
      unset($after_field);
      //var_dump($ret);
    }
  }
  return $ret;
}

class MessageQueryConditionBuilder extends TableManagerQueryConditionBuilder
{
  function buildStatusCondition () {
    $num_args = func_num_args();
    if ($num_args <= 0)
      return;
    $fields = func_get_args();

    if (isset($this->term) && '' !== $this->term) {
      $ret = $fields[0].'='.intval($this->term);
      // build aggregate states
      /* if (0 == intval($this->term)) {
        // also show expired on holds
        $ret = "($ret "
          // ." OR ".$fields[0]. " = -3" // uncomment for open requests
          ." OR (".$fields[0]." = 2 AND hold <= CURRENT_DATE()))";
      } */
      return $ret;
    }
    else
      return  $fields[0].'<>-1';
  }

  function buildEditorCondition () {
    $num_args = func_num_args();
    if ($num_args <= 0)
      return;
    $fields = func_get_args();

    if (isset($this->term) && '' !== $this->term) {
      $ret = $fields[0].'='.intval($this->term);
      return $ret;
    }
    return;
  }

}

/* Common base class for the backend with paging and upload handling */
class DisplayBackend extends DisplayTable
{
  var $listing_default_action = TABLEMANAGER_EDIT;
  var $datetime_style = 'DD/MM/YYYY';


  function buildSearchBar () {
    // var_dump($this->search);

    $ret = sprintf('<form action="%s" method="post" name="search">',
                   htmlspecialchars($this->page->buildLink(array('pn' => $this->page->name, 'page_id' => 0))));

    if (method_exists($this, 'buildSearchFields'))
        $search = $this->buildSearchFields();
    else
        $search = sprintf('<input type="text" name="search" value="%s" size="40" /><input class="submit" type="submit" value="%s" />',
                          $this->htmlSpecialchars(array_key_exists('search', $this->search) ?  $this->search['search'] : ''),
                          tr('Search'));

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
          $this->image = array('message_id' => $this->workflow->primaryKey(),
                               'type' => $media_type, 'name' => $name);
        }
      }
      return new ImageUploadHandler($this->workflow->primaryKey(), $media_type);
    }
  }

  function processUpload (&$imageUploadHandler) {
    $action = $this->page->buildLink(array('pn' => $this->page->name, $this->workflow->name(TABLEMANAGER_VIEW) => $this->id));
    $upload_results = array();
    foreach ($this->images as $img_name => $img_descr) {
      $img_params = $img_descr['imgparams'];
      if (isset($img_descr['multiple'])) {
        if ('boolean' == gettype($img_descr['multiple']))
          $max_images = $img_descr['multiple'] ? -1 : 1;
        else
          $max_images = intval($img_descr['multiple']);
      }
      else
        $max_images = 1;

      // check if we need to delete something
      if (array_key_exists('delete_img', $this->page->parameters))
        $imageUploadHandler->delete($this->page->parameters['delete_img']);

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
    $field = &$upload_form->field($name);
    if (isset($field)) {
      if (isset($this->invalid[$name]))
        $ret =  '<div class="error">'.$this->form->error_fulltext($this->invalid[$name], $this->page->lang).'</div>';

      $ret .= $field->show($args);
    }
    return $ret;
  }

  function renderUpload (&$imageUploadHandler, $title = 'Image Upload') {
    $ret = '<h2>' . $this->formatText(tr($title)) . '</h2>';

    $params_self = array('pn' => $this->page->name, $this->workflow->name(TABLEMANAGER_VIEW) => $this->id);
    $action = $this->page->buildLink($params_self);

    $first = TRUE;

    foreach ($this->images as $img_name => $img_descr) {
      $rows = array();
      if (isset($img_descr['title']))
        $ret .= '<h3>'.$img_descr['title'].'</h3>';

      $upload_results = isset($this->upload_results[$img_name])
        ? $this->upload_results[$img_name]: array();
      // var_dump($upload_results);

      if (isset($img_descr['multiple'])) {
        if ('boolean' == gettype($img_descr['multiple']))
          $max_images = $img_descr['multiple'] ? -1 : 1;
        else
          $max_images = intval($img_descr['multiple']);
      }
      else
        $max_images = 1;
      $img_params = $img_descr['imgparams'];

      $images = $imageUploadHandler->buildImages($img_name, $img_params, $max_images);
      $imageUpload = $imageUploadHandler->buildUpload($images, $action);

      if ($first) {
        $ret .= $imageUpload->show_start();
        $first = FALSE;
      }

      $imageUploadHandler->fetchAll();

      $count = 0;
      foreach ($imageUploadHandler->img_titles as $img_name => $title) {
        $img = $imageUpload->image($img_name);
        if (isset($img)) {
          $img_field = '';
          if ($max_images != 1) {
            if ($count > 0)
              $rows[] = '<hr />';
            $img_field .= '<h4>'.$title.'</h4>';
          }
          ++$count;

          $img_form = & $imageUploadHandler->img_forms[$img_name];
          if (isset($upload_results[$img_name]) && isset($upload_results[$img_name]['status']) && $upload_results[$img_name]['status'] < 0) {
            $img_field .= '<div class="message">'.$upload_results[$img_name]['msg'].'</div>';
          }

          $url_delete = $this->page->buildLink(array_merge($params_self,
                                               array('delete_img' => $img_name)));

          list($img_tag, $caption, $copyright) = $this->buildImage($imageUploadHandler->message_id, $imageUploadHandler->type, $img_name, TRUE, TRUE, TRUE);
          // var_dump($img_tag);
          if (!empty($img_tag)) {
            $img_field .= '<p><div style="margin-right: 2em; margin-bottom: 1em; float: left;">'.$img_tag.'</div>'
              .(!empty($caption) ? $this->formatText($caption).'<br />&nbsp;<br />' : '')
              .'[<a href="'.$url_delete.'">delete</a>]<br clear="left" /></p>';
          }

          $rows[] = $img_field;

          $rows[] = array('File', $img->show_upload_field());
          $rows[] = array('Image Caption', $this->getUploadFormField($img_form, 'caption', array('prepend' => $img_name.'_')));
          $rows[] = array('Copyright-Notice', $this->getUploadFormField($img_form, 'copyright', array('prepend' => $img_name.'_')));

          $rows[] = array('', '<input type="submit" value="'.ucfirst(tr('upload')).'" />');
        } // if
      }
      foreach ($rows as $row) {
        if ('array' == gettype($row))
          $ret .= $this->buildContentLine(tr($row[0]), $row[1]);
        else
          $ret .= $row;
      } // foreach
    } // foreach
    if (!$first)
      $ret .= $imageUpload->show_end();

    return $ret;
  }

}

class DisplayMessageFlow extends TableManagerFlow
{
  function __construct ($page) {
    parent::TableManagerFlow(TRUE); // view after edit
  }
}

class MessageRecord extends TablemanagerRecord
{
  var $datetime_style = '';

  function store ($args = '') {
    $stored = parent::store($args);
    if ($stored) {
      $message_id = $this->get_value('id');
      $user_id = $this->get_value('user_id');
      if (isset($message_id) && $message_id > 0 && isset($user_id) && $user_id > 0) {
        $dbconn = $this->params['dbconn'];

        $querystr = sprintf("DELETE FROM MessageUser WHERE message_id=%d", $message_id);
        $dbconn->query($querystr);

        $querystr = sprintf("INSERT INTO MessageUser (message_id, user_id) VALUES (%d, %d)",
                        $message_id, $user_id);
        $dbconn->query($querystr);
      }
    }

    return $stored;
  }

  function fetch ($args, $datetime_style = '') {
    if (empty($datetime_style))
      $datetime_style = $this->datetime_style;

    $fetched = parent::fetch($args, $datetime_style);
    if ($fetched) {
      $dbconn = $this->params['dbconn'];
      $message_id = $this->get_value('id');
      $querystr = sprintf("SELECT user_id, lastname, firstname FROM MessageUser"
                          ." LEFT OUTER JOIN User ON MessageUser.user_id=User.id"
                          ." WHERE message_id=%d",
                          $message_id);
      $dbconn->query($querystr);
      if ($dbconn->next_record()) {
        // var_dump($dbconn->Record);
        $this->set_value('user_id', $dbconn->Record['user_id']);
        if (isset($dbconn->Record['lastname']))
          $this->set_value('user', $dbconn->Record['lastname'].' '.$dbconn->Record['firstname']);
      }

    }

    return $fetched;
  }

  function delete ($id) {
    global $STATUS_REMOVED;

    $dbconn = $this->params['dbconn'];
    // remove message if not published
    $querystr = sprintf("UPDATE Message SET status=%s WHERE id=%d AND status <= 0",
                        $STATUS_REMOVED, $id);
    $dbconn->query($querystr);

    return $dbconn->affected_rows() > 0;
  }

}

class DisplayMessage extends DisplayBackend
{
  var $page_size = 30;
  var $table = 'Message';
  var $fields_listing = array('Message.id AS id', 'subject',
                              "CONCAT(User.lastname, ' ', User.firstname) AS fullname",
                              "CONCAT(U.lastname, ' ', U.firstname) AS editor",
                              'Message.status AS status', "DATE(published) AS published");
  var $joins_listing = array('LEFT OUTER JOIN MessageUser ON MessageUser.message_id=Message.id',
                             'LEFT OUTER JOIN User ON MessageUser.user_id=User.id',
                            'LEFT OUTER JOIN User U ON Message.editor=U.id');

  var $cols_listing = array('id' => 'ID', 'subject' => 'Title',
                            'contributor' => 'Contributor',
                            'editor' => 'Editor',
                            'status' => 'Status',
                            'date' => 'Publication');
  var $idcol_listing = TRUE;

  var $search_fulltext = NULL;

  var $tinymce_fields = NULL; // changed to array() if browser supports

  var $condition = array();
  var $order = array('id' => array('id DESC', 'id'),
                     'subject' => array('subject', 'subject DESC'),
                     'contributor' => array('fullname', 'fullname DESC'),
                     'editor' => array('editor', 'editor DESC'),
                     'status' => array('Message.status', 'Message.status DESC'),
                     'date' => array('IF(0 = published + 0, Message.changed, published) DESC', 'IF(0 = published + 0, Message.changed, published)'),
                     );

  var $status_options = array (
    '-59' => 'eingegangen',
    '-45' => 'ver&#246;ffentlichungsbereit',
    '1'   => 'ver&#246;ffentlicht',
    '-109' => 'abgelehnt',
  );
  var $status_default = '-59';
  var $status_deleted = '-1';

  var $view_options = array();

  function __construct (&$page, $workflow = NULL) {
    parent::__construct($page, isset($workflow) ? $workflow : new DisplayMessageFlow($page));
    if (isset($this->type))
      $this->condition[] = sprintf('type=%d', intval($this->type));

    $this->condition[] = array('name' => 'status',
                               'method' => 'buildStatusCondition',
                               'args' => $this->table.'.status',
                               'persist' => 'session');
    $this->condition[] = array('name' => 'editor',
                               'method' => 'buildEditorCondition',
                               'args' => $this->table.'.editor',
                               'persist' => 'session');

    $this->search_fulltext = $this->page->getPostValue('fulltext');
    if (!isset($this->search_fulltext))
      $this->search_fulltext = $this->page->getSessionValue('fulltext');
    $this->page->setSessionValue('fulltext', $this->search_fulltext);

    if ($this->search_fulltext) {
      $this->constructFulltextCondition();
    }
    else {
      $this->condition[] = array('name' => 'search',
                                 'method' => 'buildLikeCondition',
                                 'args' => 'subject,User.firstname,User.lastname',
                                 'persist' => 'session');
    }

    if ($page->lang() != 'en_US')
      $this->datetime_style = 'DD.MM.YYYY';
  }

  function constructFulltextCondition () {
    $this->condition[] = array('name' => 'search',
                               'method' => 'buildFulltextCondition',
                               'args' => 'subject,User.firstname,User.lastname,body',
                               'persist' => 'session');
  }

  function instantiateRecord ($table = '', $dbconn = '') {
    return new MessageRecord(array('tables' => $this->table, 'dbconn' => $this->page->dbconn));
  }

  function buildRecord ($name = '') {
    if ('list' == $name)
      return;

    $record = parent::buildRecord($name);
    $record->datetime_style = $this->datetime_style;

    $record->add_fields(array(
        new Field(array('name'=>'id', 'type'=>'hidden', 'datatype'=>'int', 'primarykey'=>1)),
        new Field(array('name'=>'type', 'type'=>'hidden', 'datatype'=>'int', 'value' => $this->type)),
        new Field(array('name'=>'status', 'type'=>'select',
                        'options' => array_keys($this->status_options),
                        'labels' => array_values($this->status_options),
                        'datatype'=>'int', 'default' => $this->status_default)),
        new Field(array('name'=>'created', 'type'=>'hidden', 'datatype'=>'function', 'value'=>'NOW()', 'noupdate' => TRUE)),
        new Field(array('name'=>'created_by', 'type'=>'hidden', 'datatype'=>'int', 'value' => $this->page->user['id'], 'null'=>1, 'noupdate' => TRUE)),
        new Field(array('name'=>'changed', 'type'=>'hidden', 'datatype'=>'function', 'value'=>'NOW()')),
        new Field(array('name'=>'changed_by', 'type'=>'hidden', 'datatype'=>'int', 'value' => $this->page->user['id'], 'null'=>1)),
        new Field(array('name'=>'published', 'type'=>'datetime', 'datatype'=>'datetime', 'null' => TRUE)),
        new Field(array('name'=>'subject', 'type'=>'text', 'size'=>40, 'datatype'=>'char', 'maxlength'=>80)),
        new Field(array('name'=>'user', 'type'=>'text', 'nodbfield' => TRUE, 'null' => TRUE)),
        new Field(array('name'=>'user_id', 'type'=>'int', 'nodbfield' => TRUE, 'null' => TRUE)),
        new Field(array('name'=>'body', 'type'=>'textarea', 'datatype'=>'char', 'cols'=>65, 'rows'=>20, 'null' => TRUE)),
        new Field(array('name'=>'comment', 'type'=>'textarea', 'datatype'=>'char', 'cols'=>50, 'rows' => 4, 'null' => TRUE)),
      ));

    return $record;
  }

  function unformatParagraphs ($html) {
    // remove unwanted tags
    $allowable_tags = '<p><a><br><strong><b><em><i>';
    $ret= strip_tags($html, $allowable_tags);

    // reconvert the allowed ones
    $ret = preg_replace('#<p>(.*?)</p>#is', '\1'."\n\n", $ret);
    $ret = preg_replace('#<br\s*/?\>#is', "\n", $ret);
    $ret = preg_replace('#<(em|i)>(.*?)</\1>#is', '_\2_', $ret);
    $ret = preg_replace('#<(strong|b)>(.*?)</\1>#is', '*\2*', $ret);
    $ret = preg_replace('#<a [^>]*href="([^"]*)"[^>]*>(.*?)</a>#is', '|\1|\2|', $ret);

    // reverse specialchars
    // see http://www.alanwood.net/unicode/general_punctuation.html
    $match = array('/&mdash;/', '/&ndash;/', '/&lsquo;/', '/&rsquo;/', '/&ldquo;/', '/&rdquo;/', '/&bdquo;/'); //  '/&amp;/', '/&lt;/', '/&gt;/', '/&nbsp;/');
    $replace = array('--', '&#8211;', '&#8216;', '&#8217;', '&#8220;', '&#8221;', '&#8222;'); // , '&', '<', '>', ' ');
    $ret = preg_replace($match, $replace, $ret, -1);

    // replace literal entities
    $ret = html_entity_decode($ret, ENT_QUOTES);

    return $ret;
  }

  function instantiateQueryConditionBuilder ($term) {
    return new MessageQueryConditionBuilder($term);
  }

  function setInput () {
    parent::setInput();
    if (isset($this->tinymce_fields) && count($this->tinymce_fields) > 0) {
      foreach ($this->tinymce_fields as $fieldname)
        $this->form->set_value($fieldname, $this->unformatParagraphs($this->form->get_value($fieldname)));
    }
  }

  function getEditRows ($mode = 'edit') {
    $record = isset($this->form) ? $this->form : $this->record;

    $user = $record->get_value('user');
    $user_id = $record->get_value('user_id');

    if (!empty($user))
      $user = @FormField::htmlspecialchars($user);
    else
      $user = '';

    if ('edit' == $mode) {
      // build the user-autocompleter

      $url_ws = $this->page->BASE_PATH . 'admin/admin_ws.php?pn=user&action=matchUser';
      $user_value = <<<EOT
<input type="hidden" name="user_id" value="$user_id" />
<input type="text" id="user" name="user" style="width:350px; border: 1px solid black;" value="$user" /><div id="autocomplete_choices" class="autocomplete"></div>
<script type="text/javascript">new Ajax.Autocompleter('user', 'autocomplete_choices', '$url_ws', {paramName: 'fulltext', minChars: 3, afterUpdateElement : function (text, li) { if(li.id != '') { var form = document.forms['detail']; if(null != form) { form.elements['user_id'].value = li.id; } } }});</script>
EOT;
    }
    else {
      $user_value = $user;
      if (isset($user_id) && $user_id > 0)
        $user_value = sprintf('<a href="%s">%s</a>',
                              htmlspecialchars($this->page->buildLink(array('pn' => 'subscriber', 'view' => $user_id))),
                              $user_value);
    }

    return array(
      'id' => FALSE, 'type' => FALSE, // hidden fields
      'user' => array('label' => 'Contributor', 'value' => $user_value),
      'subject' => array('label' => 'Title'),
      'status' => array('label' => 'Editing Status'),
      'published' => array('label' => 'Publication Date'),
      isset($this->form) ? $this->form->show_submit(ucfirst(tr('save'))) : 'FALSE',

      'body' => array('label' => 'Text'),
      'comment' => array('label' => 'Internal notes and comments'),

      isset($this->form) ? $this->form->show_submit(ucfirst(tr('save'))) : 'FALSE',
    );
  }

  function renderEditForm ($rows, $name = 'detail') {
    // for user-selection and similar stuff
    $this->script_url[] = 'script/scriptaculous/prototype.js';
    $this->script_url[] = 'script/scriptaculous/scriptaculous.js';

    if (isset($this->tinymce_fields) && count($this->tinymce_fields) > 0) {
      $this->script_url[] = 'script/tiny_mce/tiny_mce.js';
      $this->script_code .= <<<EOT
tinyMCE.init({
	mode : "exact",
	elements : "body",
	theme : "advanced",
	theme_advanced_buttons1 : "bold,italic,undo,redo,link,unlink",
	theme_advanced_buttons2 : "",
	theme_advanced_buttons3 : "",
	theme_advanced_toolbar_location : "top",
	theme_advanced_toolbar_align : "left",
	theme_advanced_path_location : "bottom",
	insertlink_callback : 'myInsertLink',
	browsers : "msie,gecko,opera",
    convert_urls : false,
	extended_valid_elements : "a[href|target|title],img[class|src|border=0|alt|title|hspace|vspace|width|height|align|onmouseover|onmouseout|name],hr[class|width|size|noshade],font[face|size|color|style],span[class|align|style]"
});

function myInsertLink (href, target, title, onclick, action) {
	var result = new Array();

	// Do some custom logic
	result['href'] = prompt('URL:', href);
	result['target'] = "_self";
	result['title'] = "";
	result['onclick'] = "";

	return result;
}


EOT;

      foreach ($this->tinymce_fields as $fieldname)
        $this->form->set_value($fieldname, $this->formatParagraphs($this->form->get_value($fieldname)));
    }

    $changed = isset($this->id) ? $this->buildChangedBy() : '';

    return $changed . parent::renderEditForm($rows, $name);
  }

  function getViewFormats () {
    return array('body' => array('format' => 'p'));
  }

  function buildViewRows () {
    // a bit of a hack since formatText messes up numerical entities
    $status_options = array();
    foreach ($this->status_options as $key => $val)
      $status_options[$key] = mb_convert_encoding($val, 'UTF-8', 'HTML-ENTITIES');
    $this->view_options['status'] = $status_options;

    $rows = $this->getEditRows('view');
    if (isset($rows['title']))
      unset($rows['title']);

    $formats = $this->getViewFormats();

    $view_rows = array();

    foreach ($rows as $key => $descr) {
      if ($descr !== FALSE && gettype($key) == 'string') {
        if (isset($formats[$key]))
          $descr = array_merge($descr, $formats[$key]);
        $view_rows[$key] = $descr;
        if (isset($this->view_options[$key]))
          $view_rows[$key]['options'] = $this->view_options[$key];
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
              for($i = 0; $i < count($values); $i++)
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
    if (count($fields) > 0)
      $ret .= $this->buildContentLineMultiple($fields);

    return $ret;
  }

  function buildEditButton () {
    return sprintf(' <span class="regular">[<a href="%s">%s</a>]</span>',
                   htmlspecialchars($this->page->buildLink(array('pn' => $this->page->name, $this->workflow->name(TABLEMANAGER_EDIT) => $this->id))),
                   tr('edit'));
  }

  function buildViewFooter ($found = TRUE) {
    $ret = ($found ? '<hr />' : '')
        .'[<a href="'.htmlspecialchars($this->page->buildLink(array('pn' => $this->page->name))).'">'.tr('back to overview').'</a>]';

    if ($found && isset($this->record)) {
      $status = $this->record->get_value('status');
      if ($status <= 0) {
        global $JAVASCRIPT_CONFIRMDELETE;

        $this->script_code .= $JAVASCRIPT_CONFIRMDELETE;
        $url_delete = $this->page->buildLink(array('pn' => $this->page->name, $this->workflow->name(TABLEMANAGER_DELETE) => $this->id));
        $ret .= sprintf(" [<a href=\"javascript:confirmDelete('%s', '%s')\">%s</a>]",
                          'Wollen Sie diesen Beitrag wirklich l&ouml;schen?\n(kein UNDO)',
                          htmlspecialchars($url_delete),
                          tr('delete message'));
      }
    }

    return $ret;
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

      $ret = '<h2>' . $this->formatText($record->get_value('title')) . ' ' . $edit.'</h2>';

      $ret .= $this->renderView($record, $rows);

      if (isset($uploadHandler))
        $ret .= $this->renderUpload($uploadHandler);

    }
    $ret .= $this->buildViewFooter($found);

    return $ret;
  }

  function buildStatusOptions ($options = NULL) {
    if (!isset($options))
      $options = & $this->status_options;

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
    return tr('Editing Status')
      . ': <select name="status">' . implode($status_options) . '</select>';
  }

  function buildSearchFields () {
    $search = sprintf('<input type="text" name="search" value="%s" size="40" />',
                      $this->htmlSpecialchars(array_key_exists('search', $this->search) ?  $this->search['search'] : ''));
    $search .= sprintf('<label><input type="hidden" name="fulltext" value="0" /><input type="checkbox" name="fulltext" value="1"%s /> %s</label>',
                       $this->search_fulltext ? ' checked="checked"' : '',
                       tr('Fulltext'));

    $search .=  '<br />' . $this->buildStatusOptions();

    if (method_exists($this, 'buildOptions')) {
      // Betreuer - TODO: make a bit more generic
      $editor_options = array('<option value="">'.tr('-- all --').'</option>');
      foreach ($this->buildOptions('editor') as $id => $label) {
        $selected = isset($this->search['editor'])
            && $this->search['editor'] !== ''
            && $this->search['editor'] == $id
        ? ' selected="selected"' : '';
        $editor_options[] = sprintf('<option value="%s"%s>%s</option>', $id, $selected, htmlspecialchars(tr($label)));
      }
      $search .= ' '.tr('Article Editor').': <select name="editor">'.implode($editor_options).'</select>';
    }

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
          var selectfields = ['status', 'editor'];
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
    $search .= ' <input class="submit" type="submit" value="'.tr('Search').'" />';

    return $search;
  }

  function buildListingCell (&$row, $col_index, $val = NULL) {
    global $MESSAGE_STATUS;

    $val = NULL;

    if (count($this->cols_listing) - 2 == $col_index) {
      $val = (isset($row[$col_index]) ? $this->status_options[$row[$col_index]] : '');
    }
    else if (count($this->cols_listing) - 1 == $col_index) {
      $action = TABLEMANAGER_EDIT == $this->listing_default_action
        ? TABLEMANAGER_VIEW : TABLEMANAGER_EDIT;
      $name = $this->workflow->name($action);
      $url_preview = $this->page->buildLink(array('pn' => $this->page->name, $name => $row[0]));
      $val = sprintf('<div style="text-align:right">%s&nbsp;[<a href="%s">%s</a>]</div>',
                     $row[count($this->cols_listing) - 1],
                     htmlspecialchars($url_preview),
                     tr($name));
    }

    return parent::buildListingCell($row, $col_index, $val);
  }

}
