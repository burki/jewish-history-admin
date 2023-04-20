<?php
/*
 * displaymessage.inc.php
 *
 * Base-Class for managing messages
 *
 * (c) 2007-2023 daniel.burckhardt@sur-gmbh.ch
 *
 * Version: 2023-04-20 dbu
 *
 * Changes:
 *
 */

require_once INC_PATH . 'admin/displaybackend.inc.php';

class MessageQueryConditionBuilder
extends TableManagerQueryConditionBuilder
{
  function buildStatusCondition () {
    $num_args = func_num_args();
    if ($num_args <= 0) {
      return;
    }

    $fields = func_get_args();

    if (isset($this->term) && '' !== $this->term) {
      $ret = $fields[0] . '=' . intval($this->term);
      // build aggregate states
      /* if (0 == intval($this->term)) {
        // also show expired on holds
        $ret = "($ret "
          // ." OR ".$fields[0]. " = -3" // uncomment for open requests
          ." OR (".$fields[0]." = 2 AND hold <= CURRENT_DATE()))";
      } */

      return $ret;
    }

    return  $fields[0] . '<> -1';
  }

  function buildEqualCondition () {
    $num_args = func_num_args();
    if ($num_args <= 0) {
      return;
    }

    $fields = func_get_args();

    if (isset($this->term) && '' !== $this->term) {
      $ret = $fields[0] . '=' . intval($this->term);
      return $ret;
    }

    return;
  }

  function buildSectionCondition () {
    $num_args = func_num_args();
    if ($num_args <= 0) {
      return;
    }

    $fields = func_get_args();

    if (isset($this->term) && '' !== $this->term) {
      $ret = $fields[0] . " REGEXP '"
        . addslashes(MYSQL_REGEX_WORD_BEGIN)
        . intval($this->term)
        . addslashes(MYSQL_REGEX_WORD_END)
        . "'";

      return $ret;
    }

    return;
  }
}

class DisplayMessageFlow
extends DisplayBackendFlow
{
}

class MessageRecord
extends TablemanagerRecord
{
  var $datetime_style = '';
  var $users = [];

  function store ($args = '') {
    $stored = parent::store($args);
    if ($stored) {
      $message_id = $this->get_value('id');

      $users = [];
      $user_id = $this->get_value('user_id');
      $users[] = isset($user_id) && $user_id > 0 ? $user_id : '';

      if (array_key_exists('users_order', $_POST)) {
        parse_str($_POST['users_order'], $users_order);
        if (!empty($users_order['additional_users'])) {
          foreach ($users_order['additional_users'] as $ord => $user_id)
            if (isset($user_id) && $user_id > 0) {
              $users[] = $user_id;
              $this->users[] = $user_id;
            }
        }
      }

      if (isset($message_id) && $message_id > 0 && count($users) > 0) {
        $dbconn = $this->params['dbconn'];

        $querystr = sprintf("DELETE FROM MessageUser WHERE message_id=%d",
                            $message_id);
        $dbconn->query($querystr);

        $ord = 0;
        $stored = [];
        foreach ($users as $user_id) {
          if (!isset($stored[$user_id])) {
            $sql_values = [];
            $sql_values['name'] = 0 == $ord && !empty($_POST['user']) ? sprintf("'%s'", addslashes($_POST['user'])) : 'NULL';
            /* foreach ([ 'email', 'institution' ] as $name) {
              $value = 0 == $ord ? $this->get_value('user_' . $name) : null;
              $sql_values[$name] = !empty($value) ? sprintf("'%s'", addslashes($value)) : 'NULL';
            } */
            if (!empty($user_id)) {
              $querystr = sprintf("INSERT INTO MessageUser (message_id, user_id, ord)"
                                  . " VALUES (%d, %s, %d)",
                                  $message_id, !empty($user_id) ? $user_id : 'NULL', $ord++);
              $dbconn->query($querystr);
            }
          }
          $stored[$user_id] = true;
        }
      }
    }

    return $stored;
  }

  function fetch ($args, $datetime_style = '') {
    if (empty($datetime_style)) {
      $datetime_style = $this->datetime_style;
    }

    $fetched = parent::fetch($args, $datetime_style);
    if ($fetched) {
      $dbconn = $this->params['dbconn'];
      $message_id = $this->get_value('id');
      $querystr = sprintf("SELECT user_id, lastname, firstname FROM MessageUser"
                          . " LEFT OUTER JOIN User ON MessageUser.user_id=User.id"
                          . " WHERE message_id=%d ORDER BY MessageUser.ord",
                          $message_id);
      $dbconn->query($querystr);
      $first = true;
      $users_options = [];
      $users_labels = [];
      while ($dbconn->next_record()) {
        if ($first) {
          $this->set_value('user_id', $dbconn->Record['user_id']);
          // $this->set_value('user_email', $dbconn->Record['user_email']);
          // $this->set_value('user_institution', $dbconn->Record['user_institution']);
          if (!empty($dbconn->Record['user_name'])) {
            $this->set_value('user', $dbconn->Record['user_name']);
          }
          else if (isset($dbconn->Record['lastname'])) {
            $this->set_value('user',
                             $dbconn->Record['lastname'] . ' ' . $dbconn->Record['firstname']);
          }

          $first = false;
        }
        else {
          $this->users[(string)$dbconn->Record['user_id']]
            = $dbconn->Record['lastname'] . ' ' . $dbconn->Record['firstname'];
        }
      }
      $this->set_fieldvalue('users', 'options', array_keys($this->users));
      $this->set_fieldvalue('users', 'labels', array_values($this->users));
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

class DisplayMessage
extends DisplayBackend
{
  var $page_size = 30;
  var $table = 'Message';
  var $fields_listing = [
    'Message.id AS id', 'subject',
    "CONCAT(User.lastname, ' ', User.firstname) AS fullname",
    // "CONCAT(U.lastname, ' ', U.firstname) AS editor",
    'Message.status AS status', "DATE(published) AS published",
  ];
  var $joins_listing = [
    'LEFT OUTER JOIN MessageUser ON MessageUser.message_id=Message.id AND MessageUser.ord=0',
    'LEFT OUTER JOIN User ON MessageUser.user_id=User.id',
    // 'LEFT OUTER JOIN User U ON Message.editor=U.id',
  ];

  var $cols_listing = [
    'id' => 'ID', 'subject' => 'Title',
    'contributor' => 'Contributor',
    // 'editor' => 'Editor',
    'status' => 'Status',
    'date' => 'Publication',
  ];
  var $idcol_listing = true;

  var $search_fulltext = null;

  var $tinymce_fields = null; // changed to [] if browser supports

  var $condition = [];
  var $order = [
    'id' => [ 'id DESC', 'id' ],
    'subject' => [ 'subject', 'subject DESC' ],
    'contributor' => [ 'fullname', 'fullname DESC' ],
    // 'editor' => [ 'editor', 'editor DESC' ],
    'status' => [ 'Message.status', 'Message.status DESC' ],
    'date' => [
      'IF(0 = published + 0, Message.changed, published) DESC',
      'IF(0 = published + 0, Message.changed, published)',
    ],
  ];

  var $status_options = [
    '-59' => 'eingegangen',
    '-45' => 'ver&#246;ffentlichungsbereit',
    '1'   => 'ver&#246;ffentlicht',
    '-109' => 'abgelehnt',
  ];
  var $status_default = '-59';
  var $status_deleted = '-1';

  var $view_options = [];

  function __construct (&$page, $workflow = null) {
    parent::__construct($page, isset($workflow) ? $workflow : new DisplayMessageFlow($page));

    if (isset($this->type)) {
      $this->condition[] = sprintf('type=%d', intval($this->type));
    }

    $this->condition[] = [
      'name' => 'status',
      'method' => 'buildStatusCondition',
      'args' => $this->table . '.status',
      'persist' => 'session',
    ];
    /*
    $this->condition[] = [
      'name' => 'editor',
      'method' => 'buildEqualCondition',
      'args' => $this->table . '.editor',
      'persist' => 'session',
    ];
    */

    $this->search_fulltext = $this->page->getPostValue('fulltext');
    if (!isset($this->search_fulltext)) {
      $this->search_fulltext = $this->page->getSessionValue('fulltext');
    }
    $this->page->setSessionValue('fulltext', $this->search_fulltext);

    if ($this->search_fulltext) {
      $this->constructFulltextCondition();
    }
    else {
      $this->condition[] = [
        'name' => 'search',
        'method' => 'buildLikeCondition',
        'args' => 'subject,User.firstname,User.lastname',
        'persist' => 'session',
      ];
    }

    if ($page->lang() != 'en_US') {
      $this->datetime_style = 'DD.MM.YYYY';
    }
  }

  function constructFulltextCondition () {
    $this->condition[] = [
      'name' => 'search',
      'method' => 'buildFulltextCondition',
      'args' => 'subject,User.firstname,User.lastname,body',
      'persist' => 'session',
    ];
  }

  function instantiateRecord ($table = '', $dbconn = '') {
    return new MessageRecord([ 'tables' => $this->table, 'dbconn' => $this->page->dbconn ]);
  }

  function buildRecord ($name = '') {
    if ('list' == $name) {
      return;
    }

    $record = parent::buildRecord($name);
    $record->datetime_style = $this->datetime_style;

    $record->add_fields([
      new Field([ 'name' => 'id', 'type' => 'hidden', 'datatype' => 'int', 'primarykey' => true ]),
      new Field([ 'name' => 'type', 'type' => 'hidden', 'datatype' => 'int', 'value' => $this->type]),
      new Field([ 'name' => 'status', 'type' => 'select',
                  'options' => array_keys($this->status_options),
                  'labels' => array_values($this->status_options),
                  'datatype' => 'int', 'default' => $this->status_default ]),
      new Field([ 'name' => 'created', 'type' => 'hidden', 'datatype' => 'function', 'value' => 'NOW()', 'noupdate' => true ]),
      new Field([ 'name' => 'created_by', 'type' => 'hidden', 'datatype' => 'int', 'value' => $this->page->user['id'], 'null' => true, 'noupdate' => true ]),
      new Field([ 'name' => 'changed', 'type' => 'hidden', 'datatype' => 'function', 'value' => 'NOW()' ]),
      new Field([ 'name' => 'changed_by', 'type' => 'hidden', 'datatype' => 'int', 'value' => $this->page->user['id'], 'null' => true ]),
      new Field([ 'name' => 'published', 'type' => 'datetime', 'datatype' => 'datetime', 'null' => true ]),
      new Field([ 'name' => 'subject', 'id' => 'subject', 'type' => 'text', 'size' => 60, 'datatype' => 'char', 'maxlength' => 160 ]),
      new Field([ 'name' => 'user', 'type' => 'text', 'nodbfield' => true, 'null' => true ]),
      new Field([ 'name' => 'user_id', 'type' => 'int', 'nodbfield' => true, 'null' => true ]),
      new Field([ 'name' => 'users', 'type' => 'select', 'multiple' => true, 'nodbfield' => true, 'null' => true ]),
      new Field([ 'name' => 'body', 'type' => 'textarea', 'datatype' => 'char', 'cols' => 65, 'rows' => 20, 'null' => true ]),
      new Field([ 'name' => 'comment', 'type' => 'textarea', 'datatype' => 'char', 'cols' => 65, 'rows' => 15, 'null' => true ]),
    ]);

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
    $match = ['/&mdash;/', '/&ndash;/', '/&lsquo;/', '/&rsquo;/', '/&ldquo;/', '/&rdquo;/', '/&bdquo;/']; //  '/&amp;/', '/&lt;/', '/&gt;/', '/&nbsp;/');
    $replace = ['--', '&#8211;', '&#8216;', '&#8217;', '&#8220;', '&#8221;', '&#8222;']; // , '&', '<', '>', ' ');
    $ret = preg_replace($match, $replace, $ret, -1);

    // replace literal entities
    $ret = html_entity_decode($ret, ENT_QUOTES);

    return $ret;
  }

  function instantiateQueryConditionBuilder ($term) {
    return new MessageQueryConditionBuilder($term);
  }

  function setInput ($values = null) {
    parent::setInput($values);
    if (isset($this->tinymce_fields) && count($this->tinymce_fields) > 0) {
      foreach ($this->tinymce_fields as $fieldname) {
        $this->form->set_value($fieldname, $this->unformatParagraphs($this->form->get_value($fieldname)));
      }
    }
  }

  function getEditRows ($mode = 'edit') {
    $record = isset($this->form) ? $this->form : $this->record;

    $user = $record->get_value('user');
    $user_id = $record->get_value('user_id');

    if (!empty($user)) {
      $user = $this->htmlSpecialchars($user);
    }
    else {
      $user = '';
    }

    if ('edit' == $mode) {
      // build the user-autocompleter

      $url_ws = $this->page->BASE_PATH . 'admin_ws.php?pn=user&action=matchUser';
      $user_value = <<<EOT
<input type="hidden" name="user_id" value="$user_id" />
<input type="text" id="user" name="user" style="width:350px; border: 1px solid black;" value="$user" />
<div id="autocomplete_choices" class="autocomplete"></div>
<script type="text/javascript">
    function fetchUser(id) {
      var url = '{$url_ws}';
      var pars = 'pn=user&action=fetchUser&id=' + escape(id);
      var myAjax = new Ajax.Request(
      url,
      {
        method: 'get',
        parameters: pars,
        onComplete: setUser
      });
    }

    function setUser (originalRequest, obj) {
      if (obj.status > 0) {
        var field = \$('user');
        if (null != field) {
          field.value = obj['firstname'] + ' ' + obj['lastname'];
        }
        if (null != obj.id) {
          var msg_field = \$('missingUserRelation');
          if (null != msg_field) {
            msg_field.hide();
          }
        }

        var fields = ['email', 'institution'];
        for (var i = 0; i < fields.length; i++) {
          var name = fields[i];
          if (null != obj[name]) {
            var field = \$('user_' + fields[i]);
            if (null != field && '' == field.value) {
              field.value = obj[name];
              if ('institution' == name && '' == obj[name])
                field.value = obj['place'];
            }
          }
        }
      }
      else {
        alert('ret: ' + obj.msg + obj.status);
      }
    }

new Ajax.Autocompleter('user', 'autocomplete_choices', '$url_ws', {paramName: 'fulltext', minChars: 3, afterUpdateElement : function (text, li) { if(li.id != '') { var form = document.forms['detail']; if (null != form) { form.elements['user_id'].value = li.id; fetchUser(li.id); } } }});</script>
EOT;

      $li_users = '';
      $users = $this->form->record->get_field('users');
      $options = $users->get('options');
      if (!empty($options)) {
        $labels = $users->get('labels');
        for ($i = 0; $i < count($labels); $i++)
          $li_users .= sprintf('<li id="user_%d">%s [<a href="#" onclick="removeUser(%d); return false;">%s</a>]</li>',
                               $options[$i],
                               $this->htmlSpecialchars($labels[$i]),
                               $options[$i],
                               tr('remove'));
      }

      $additional_user_value = <<<EOT
      <ul id="additional_users" class="sortableList">
      $li_users
      </ul>
<input type="hidden" id="usersListOrder" name="users_order" /><input type="text" id="additional_user" name="add_additional_user" style="width:350px; border: 1px solid black;" value="" /><div id="additional_autocomplete_choices" class="autocomplete"></div>
<script type="text/javascript">
function removeUser (id) {
  \$('user_' + id).remove();
}

\$\$('[name="detail"]')[0].onsubmit = function() { populateHiddenVars(); return true;  };
new Ajax.Autocompleter('additional_user', 'additional_autocomplete_choices', '$url_ws', {paramName: 'fulltext', minChars: 3, afterUpdateElement : addItemToList });
Sortable.create('additional_users', {tag: 'li'});

function addItemToList(text, li) {
  /* the following doesn't work in IE 8:
  \$('additional_users').insert('<li id="user_' + li.id + '>' + li.innerHTML + '</li>'); */
  var li_new = document.createElement("li");
  li_new.id = "user_" + li.id;
  li_new.innerHTML = li.innerHTML;
  \$('additional_users').appendChild(li_new);
  Sortable.create('additional_users', {tag: 'li'});
  \$('additional_user').value = '';
}
function populateHiddenVars() {
  document.getElementById('usersListOrder').value = Sortable.serialize('additional_users');
  return true;
}
</script>
EOT;
    }
    else {
      $user_value = $user;
      if (isset($user_id) && $user_id > 0) {
        $user_value = sprintf('<a href="%s">%s</a>',
                              htmlspecialchars($this->page->buildLink([ 'pn' => 'author', 'view' => $user_id ])),
                              $user_value);
      }
      $additional_user_value = '';
      if (!empty($this->record->users)) {
        foreach ($this->record->users as $user_id => $user_name) {
          $additional_user_value .= sprintf('<a href="%s">%s</a><br />',
                                htmlspecialchars($this->page->buildLink([ 'pn' => 'author', 'view' => $user_id ])),
                                $this->htmlSpecialchars($user_name));
        }

      }
    }

    return [
      'id' => false, 'type' => false, // hidden fields
      'user' => [ 'label' => 'Contributor', 'value' => $user_value ],
      'subject' => [ 'label' => 'Title' ],
      'status' => [ 'label' => 'Editing Status' ],
      'published' => [ 'label' => 'Publication Date' ],
      isset($this->form) ? $this->form->show_submit(ucfirst(tr('save'))) : false,

      'body' => [ 'label' => 'Text' ],
      'users' => [ 'label' => 'Additional Contributors', 'value' => $additional_user_value ],
      'comment' => [ 'label' => 'Internal notes and comments' ],

      isset($this->form) ? $this->form->show_submit(ucfirst(tr('save'))) : false,
    ];
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
    var result = new [];

    // Do some custom logic
    result['href'] = prompt('URL:', href);
    result['target'] = "_self";
    result['title'] = "";
    result['onclick'] = "";

    return result;
  }

EOT;

      foreach ($this->tinymce_fields as $fieldname) {
        $this->form->set_value($fieldname, $this->formatParagraphs($this->form->get_value($fieldname)));
      }
    }

    $changed = isset($this->id) ? $this->buildChangedBy() : '';

    return $changed . parent::renderEditForm($rows, $name);
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

  function buildSearchFields ($options = []) {
    $search = sprintf('<input type="text" name="search" value="%s" size="40" />',
                      $this->htmlSpecialchars(array_key_exists('search', $this->search) ?  $this->search['search'] : ''));
    $search .= sprintf('<label><input type="hidden" name="fulltext" value="0" /><input type="checkbox" name="fulltext" value="1"%s /> %s</label>',
                       $this->search_fulltext ? ' checked="checked"' : '',
                       tr('Fulltext'));

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

          var selectfields = {$select_fields_json};
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

  /* attention - only fits admin_article */
  function buildListingCell (&$row, $col_index, $val = null) {
    $val = null;

    if (count($this->cols_listing) - 3 == $col_index && !empty($this->view_options['section'])) {
      $sections = [];
      foreach (preg_split('/\s*,\s/', $row['section']) as $section) {
        $sections[] = $this->view_options['section'][$section];
      }
      $val = implode(', ', $sections);
    }
    else if (count($this->cols_listing) - 2 == $col_index) {
      $val = (isset($row['status']) ? $this->status_options[$row['status']] : '');
      if (isset($row['status_flags'])) {
        $status_labels = [];
        $status_flag_labels = [
          0x01 => tr('Peer Review') . ' ' . tr('finalized'),
          0x02 => tr('Markup') . ' ' . tr('finalized'),
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
    else if (count($this->cols_listing) - 1 == $col_index) {
      $action = TABLEMANAGER_VIEW;
      if ($this->mayEdit($row)) {
        $action = TABLEMANAGER_EDIT == $this->listing_default_action
          ? TABLEMANAGER_VIEW : TABLEMANAGER_EDIT;
      }

      $name = $this->workflow->name($action);
      $url_preview = $this->page->buildLink([ 'pn' => $this->page->name, $name => $row[0] ]);
      $val = sprintf('<div style="text-align:right">%s&nbsp;[<a href="%s">%s</a>]</div>',
                     $row['reviewer_deadline'],
                     htmlspecialchars($url_preview),
                     tr($name));
    }
    else if (count($this->cols_listing) == $col_index) {
      return null;
    }

    return parent::buildListingCell($row, $col_index, $val);
  }
}
