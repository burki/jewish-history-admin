<?php
/*
 * admin_article.inc.php
 *
 * Manage the articles
 *
 * (c) 2009-2023 daniel.burckhardt@sur-gmbh.ch
 *
 * Version: 2023-04-20 dbu
 *
 * Changes:
 *
 */

require_once INC_PATH . 'common/classes.inc.php';
require_once INC_PATH . 'admin/displaymessage.inc.php';

class ArticleQueryConditionBuilder
extends MessageQueryConditionBuilder
{
  static function buildOverdueExpression () {
    return 'CASE Message.status'
         . ' WHEN -85 THEN DATE_ADD(publisher_received, INTERVAL 30 DAY)'
         . ' WHEN -76 THEN DATE_ADD(reviewer_request, INTERVAL 30 DAY)'
         . ' WHEN -73 THEN DATE_ADD(reviewer_deadline, INTERVAL 15 DAY)'
         . ' WHEN -69 THEN DATE_ADD(reviewer_deadline, INTERVAL 15 DAY)'
         . ' WHEN -68 THEN DATE_ADD(reviewer_deadline, INTERVAL 15 DAY)'
         . ' WHEN -67 THEN DATE_ADD(reviewer_deadline, INTERVAL 15 DAY)'
         . ' WHEN -66 THEN DATE_ADD(reviewer_deadline, INTERVAL 15 DAY)'
         . ' WHEN -59 THEN DATE_ADD(reviewer_received, INTERVAL 30 DAY)'
         . ' ELSE NULL END';
  }

  function buildStatusCondition () {
    $num_args = func_num_args();
    if ($num_args <= 0) {
      return;
    }

    $fields = func_get_args();

    if (isset($this->term) && '' !== $this->term) {
      if (100 == $this->term) {
        // overdue
        $ret = 'CURRENT_DATE() >= ' . self::buildOverdueExpression();
      }
      else {
        $ret = $fields[0] . '=' . intval($this->term);
      }

      // build aggregate states
      /* if (0 == intval($this->term)) {
        // also show expired on holds
        $ret = "($ret "
          // ." OR ".$fields[0]. " = -3" // uncomment for open requests
          ." OR (".$fields[0]." = 2 AND hold <= CURRENT_DATE()))";
      } */

      return $ret;
    }

    return  $fields[0] . '<>-1';
  }
}

class MessageWithPublicationRecord
extends MessageRecord
{
  function store ($args = '') {
    $stored = parent::store($args);

    if ($stored) {
      $publication = $this->get_value('publication');
      if (isset($publication) && intval($publication) > 0) {
        $dbconn = new DB();
        // add at the bottom
        $querystr = sprintf("SELECT MAX(ord) FROM MessagePublication WHERE message_id=%d",
                            intval($publication));
        $dbconn->query($querystr);
        $ord = $dbconn->next_record() && isset($dbconn->Record[0])
          ? $dbconn->Record[0] + 1 : 0;
        $querystr = sprintf("INSERT INTO MessagePublication (message_id, publication_id, ord) VALUES (%d, %d, %d) ON DUPLICATE KEY UPDATE ord=ord",
                            $this->get_value('id'), intval($publication), $ord);
        $dbconn->query($querystr);
      }
    }

    return $stored;
  }
}

class DisplayArticle
extends DisplayMessage
{
  // var $show_xls_export = true;
  var $status_options;
  var $status_default = '-99';

  function __construct (&$page) {
    global $MESSAGE_ARTICLE, $STATUS_OPTIONS;

    $this->status_options = $STATUS_OPTIONS;
    $this->type = $MESSAGE_ARTICLE;
    $this->messages['item_new'] = tr('New Article');
    parent::__construct($page);

    // referee and section
    $this->joins_listing[] = 'LEFT OUTER JOIN User R ON Message.referee=R.id';
    $this->joins_listing[] = 'LEFT OUTER JOIN Term T ON Message.section=T.id';

    $this->view_options['section'] = $this->section_options = $this->buildOptions('section');
    $index = 3;
    $array = $this->fields_listing;
    $this->fields_listing = array_merge(array_slice($array, 0, $index), [
        "CONCAT(R.lastname, ' ', R.firstname) AS referee",
        $this->table . '.section AS section',
        $this->table . '.status_flags AS status_flags',
      ],
      array_slice($array, $index, count($array) - 1));

    $this->cols_listing = array_merge_at($this->cols_listing, [
      'referee' => 'Referee', 'section' => 'Section'], 'contributor');

    $this->condition[] = [
      'name' => 'status_translation',
      'method' => 'buildEqualCondition',
      'args' => $this->table . '.status_translation',
      'persist' => 'session',
    ];
    $this->condition[] = [
      'name' => 'referee',
      'method' => 'buildEqualCondition',
      'args' => $this->table . '.referee',
      'persist' => 'session',
    ];
    $this->condition[] = [
      'name' => 'section',
      'method' => 'buildSectionCondition',
      'args' => $this->table . '.section',
      'persist' => 'session',
    ];
    $this->order['referee'] = [ 'referee', 'referee DESC' ];
    $this->order['section'] = [ 'section', 'section DESC' ];

    $this->order['date'] =[
      'IF(0 = reviewer_deadline + 0, published, reviewer_deadline) DESC',
      'IF(0 = reviewer_deadline + 0, published, reviewer_deadline)',
    ];
    $this->fields_listing[count($this->fields_listing) - 1] = "DATE(reviewer_deadline) AS reviewer_deadline";
    $this->cols_listing['date'] = 'Author deadline';
  }

  function instantiateQueryConditionBuilder ($term) {
    return new ArticleQueryConditionBuilder($term);
  }

  function constructFulltextCondition () {
    $this->condition[] = [
      'name' => 'search',
      'method' => 'buildFulltextCondition',
      'args' => 'subject,User.firstname,User.lastname,body',
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

      return $ret;
    }

    // update publications
    if (array_key_exists('publication_add', $_POST)) {
      if (($id_publication = intval($_POST['publication_add'])) > 0) {
        $dbconn = &$this->page->dbconn;
        // add at the bottom
        $querystr = sprintf("SELECT MAX(ord) FROM MessagePublication WHERE message_id=%d",
                            $this->workflow->primaryKey());
        $dbconn->query($querystr);
        $ord = $dbconn->next_record() && isset($dbconn->Record[0])
          ? $dbconn->Record[0] + 1 : 0;
        $querystr = sprintf("INSERT INTO MessagePublication (message_id, publication_id, ord) VALUES (%d, %d, %d) ON DUPLICATE KEY UPDATE ord=ord",
                            $this->workflow->primaryKey(), $id_publication, $ord);
        $dbconn->query($querystr);
      }
    }
    else if (array_key_exists('publication_remove', $_GET)) {
      if (($id_publication = intval($_GET['publication_remove'])) > 0) {
        $querystr = sprintf("DELETE FROM MessagePublication WHERE message_id=%d AND publication_id=%d",
                            $this->workflow->primaryKey(), $id_publication);
        $this->page->dbconn->query($querystr);
      }
    }

    if (array_key_exists('publication_order', $_POST)) {
      parse_str($_POST['publication_order'], $order);
      if (array_key_exists('publications', $order) && is_array($order['publications'])) {
        $dbconn = &$this->page->dbconn;
        foreach ($order['publications'] as $ord => $id_publication) {
          $querystr = sprintf("UPDATE MessagePublication SET ord=%d WHERE message_id=%d AND publication_id=%d",
                              intval($ord),
                              $this->workflow->primaryKey(), intval($id_publication));
          $dbconn->query($querystr);
        }
      }
    }

    return $ret;
  }

  function instantiateRecord ($table = '', $dbconn = '') {
    return new MessageWithPublicationRecord([ 'tables' => $this->table, 'dbconn' => $this->page->dbconn ]);
  }

  function buildStatusOptions ($options = null, $show_all = true) {
    $options = [ '100' => '_&#252;berf&#228;llig_' ] + $this->status_options;

    return parent::buildStatusOptions($options);
  }

  function buildOptions ($type = 'editor') {
    global $RIGHTS_EDITOR, $RIGHTS_REFEREE, $RIGHTS_TRANSLATOR;

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
        global $LICENSE_OPTIONS_ARTICLE;
        $licenses = [];
        foreach ($LICENSE_OPTIONS_ARTICLE as $key => $label) {
          $licenses[$key] = tr($label);
        }

        return $licenses;
        break;

      case 'status_translation':
        global $STATUS_TRANSLATION_OPTIONS;

        return $STATUS_TRANSLATION_OPTIONS;
        break;

      case 'section':
          $querystr = sprintf("SELECT id, name FROM Term"
                              . " WHERE category='%s' AND status >= 0"
                              . " ORDER BY ord, name",
                              addslashes($type));
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

      case 'editor':
      default:
          $querystr = "SELECT id, lastname, firstname FROM User";
          // id > 1 so Daniel Burckhardt doesn't get displayed
          $querystr .= sprintf(" WHERE 0 <> (privs & %d) AND id > 1 AND status <> %d",
                               $RIGHTS_EDITOR, STATUS_USER_DELETED);
          $querystr .= " ORDER BY lastname, firstname";
          break;
    }
    $dbconn->query($querystr);
    $options = [];
    while ($dbconn->next_record()) {
      $label = 'section' == $type
        ? $dbconn->Record['name']
        : $dbconn->Record['lastname'] . ', ' . $dbconn->Record['firstname'];

      $options[$dbconn->Record['id']] = $label;
    }

    return $options;
  }

  function buildRecord ($name = '') {
    $record = parent::buildRecord($name);
    if (!isset($record)) {
      return;
    }

    // get the options
    $this->view_options['section'] = $this->section_options = $this->buildOptions('section');
    $this->view_options['editor'] = $this->editor_options = $this->buildOptions('editor');
    $this->view_options['referee'] = $this->referee_options = $this->buildOptions('referee');
    $this->view_options['translator'] = $this->translator_options = $this->buildOptions('translator');
    $this->view_options['lang'] = $this->buildOptions('lang');
    $languages_ordered = [ '' => tr('-- please select --') ] + $this->view_options['lang'];
    $this->view_options['status_translation'] = $this->status_translation_options
      = [ '' => tr('-- please select --') ] + $this->buildOptions('status_translation');
    $this->view_options['license'] = $license_options = $this->buildOptions('license');

    $record->add_fields([
      new Field([ 'name' => 'status_flags', 'type' => 'checkbox', 'datatype' => 'bitmap', 'null' => true, 'default' => 0,
                  'labels' => [
                    0x01 => tr('Peer Review') . ' ' . tr('finalized'),
                    0x02 => tr('Markup') . ' ' . tr('finalized'),
                    0x04 => tr('Bibliography') . ' ' . tr('finalized'),
                    0x08 => tr('Translation') . ' ' . tr('finalized'),
                    0x10 => tr('Translation Markup') . ' ' . tr('finalized'),
                    0x20 => tr('ready for publishing'),
                   ]]),
      new Field([ 'name' => 'publication', 'type' => 'hidden', 'datatype' => 'int',
                  'nodbfield' => true, 'null' => true ]),
      new Field([ 'name' => 'section', 'type' => 'select',
                  'options' => array_merge([ /*''*/ ], array_keys($this->section_options)),
                  'labels' => array_merge([ /*tr('-- please select --')*/ ], array_values($this->section_options)),
                  /* 'datatype' => 'int', 'multiple' => false, */
                  'datatype' => 'char', 'multiple' => true, 'class' => 'chosen-select',
                  'null' => false ]),
      new Field([ 'name' => 'editor', 'type' => 'select',
                  'options' => array_merge([ '' ], array_keys($this->editor_options)),
                  'labels' => array_merge([ tr('-- none --') ], array_values($this->editor_options)),
                  'datatype' => 'int', 'null' => true ]),
      new Field([ 'name' => 'referee', 'type' => 'select',
                  'options' => array_merge([ '' ], array_keys($this->referee_options)),
                  'labels' => array_merge([ tr('-- none --') ], array_values($this->referee_options)),
                  'datatype' => 'int', 'null' => true ]),
      new Field([ 'name' => 'lang', 'type' => 'select', 'datatype' => 'char',
                  'options' => array_keys($languages_ordered),
                  'labels' => array_values($languages_ordered), 'null' => true ]),
      new Field([ 'name' => 'translator', 'type' => 'select',
                  'options' => array_merge([ '' ], array_keys($this->translator_options)),
                  'labels' => array_merge([ tr('-- none --') ], array_values($this->translator_options)),
                  'datatype' => 'int', 'null' => true ]),
      new Field([ 'name' => 'status_translation', 'type' => 'select', 'datatype' => 'char',
                  'options' => array_keys($this->status_translation_options),
                  'labels' => array_values($this->status_translation_options), 'null' => true ]),

      new Field([ 'name' => 'modified', 'type' => 'datetime', 'datatype' => 'datetime', 'null' => true ]),
      new Field([ 'name' => 'reviewer_request', 'type' => 'datetime', 'datatype' => 'datetime', 'null' => true ]),
      new Field([ 'name' => 'reviewer_sent', 'type' => 'datetime', 'datatype' => 'datetime', 'null' => true ]),
      new Field([ 'name' => 'reviewer_deadline', 'type' => 'datetime', 'datatype' => 'datetime', 'null' => true ]),
      new Field([ 'name' => 'reviewer_received', 'type' => 'datetime', 'datatype' => 'datetime', 'null' => true ]),
      new Field([ 'name' => 'referee_sent', 'type' => 'datetime', 'datatype' => 'datetime', 'null' => true ]),
      new Field([ 'name' => 'referee_deadline', 'type' => 'datetime', 'datatype' => 'datetime', 'null' => true ]),

      new Field([ 'name' => 'publisher_request', 'type' => 'datetime', 'datatype' => 'datetime', 'null' => true ]),
      new Field([ 'name' => 'publisher_received', 'type' => 'datetime', 'datatype' => 'datetime', 'null' => true ]),

      new Field([ 'name' => 'imprimatur_sent', 'type' => 'datetime', 'datatype' => 'datetime', 'null' => true ]),

      new Field([ 'name' => 'slug_de', 'id' => 'slug_de', 'type' => 'text', 'datatype' => 'char', 'size' => 45, 'maxlength' => 200, 'null' => true ]),
      new Field([ 'name' => 'slug', 'id' => 'slug', 'type' => 'text', 'datatype' => 'char', 'size' => 45, 'maxlength' => 200, 'null' => true ]),
      new Field([ 'name' => 'license', 'id' => 'license', 'type' => 'select',
                  'options' => array_keys($license_options),
                  'labels' => array_values($license_options), 'datatype' => 'char', 'null' => true ]),
      new Field([ 'name' => 'comment_imprimatur', 'type' => 'textarea', 'datatype' => 'char', 'cols' => 65, 'rows' => 8, 'null' => true ]),
      new Field([ 'name' => 'comment_review', 'type' => 'textarea', 'datatype' => 'char', 'cols' => 65, 'rows' => 8, 'null' => true ]),
      new Field([ 'name' => 'comment_markup', 'type' => 'textarea', 'datatype' => 'char', 'cols' => 65, 'rows' => 8, 'null' => true ]),
      new Field([ 'name' => 'comment_bibliography', 'type' => 'textarea', 'datatype' => 'char', 'cols' => 65, 'rows' => 8, 'null' => true ]),
      new Field([ 'name' => 'comment_translation', 'type' => 'textarea', 'datatype' => 'char', 'cols' => 65, 'rows' => 8, 'null' => true ]),
      new Field([ 'name' => 'comment_translation_markup', 'type' => 'textarea', 'datatype' => 'char', 'cols' => 65, 'rows' => 8, 'null' => true ]),
    ]);

    if (!isset($this->workflow->id)) {
      // for new entries, a subject or publication-id may be passed along
      if (array_key_exists('subject', $_GET)) {
        $record->set_value('subject', $_GET['subject']);
      }
      if (array_key_exists('publication', $_GET) && intval($_GET['publication']) > 0) {
        $record->set_value('publication', intval($_GET['publication']));
      }
    }

    return $record;
  }

  function getEditRows ($mode = 'edit') {
    if ('edit' == $mode) {
      $url_ws = $this->page->BASE_PATH . 'admin_ws.php';

      $this->stylesheet[] = 'css/chosen.css';
      $this->script_url[] = 'script/chosen.jquery.min.js';

      $messages = [
        'no_results_text' => json_encode(tr('No results match')),
        'placeholder_text_single' => json_encode(tr('-- please select --')),
        'placeholder_text_multiple' => json_encode(tr('-- please select --')),
      ];

      $this->script_code .= <<<EOT
    // for chosen
    jQuery(document).ready(function() {
      jQuery('.chosen-select').chosen({
        width: "95%",
        no_results_text: {$messages['no_results_text']},
        placeholder_text_single: {$messages['placeholder_text_single']},
        placeholder_text_multiple: {$messages['placeholder_text_multiple']}
      });
    }); //


    function generateCommunication (url, mode) {
      var form = document.forms['detail'];
      if (null != form) {
        var params = {
          mode: mode,
          id_review: form.elements['id'].value,
          title: form.elements['subject'].value
        };
        if ('publisher_request' == mode) {
          params.id_reviewer = form.elements['user_id'].value;
          if ('' == params.id_reviewer) {
            alert('Please set a Contributor first');
            return;
          }
        }
        else {
          params.id_to = form.elements['user_id'].value;

          if ('' == params.id_to) {
            alert('Please set a Contributor first');
            return;
          }

          if ('' == params.title) {
            alert('Please set a Title first');
            return;
          }

          if ('' == form.elements['section[]'].value) {
            alert('Please select a Section first');
            return;
          }
          else {
            var elt = form.elements['section[]'];
            var selected = [];
            for (var i = 0; i < elt.options.length; i++) {
              if (elt.options[ i ].selected) {
                selected.push(elt.options[ i ].text);
              }
            }
            params.section = selected.join(', ');
          }
        }

        if ('reviewer_sent' == mode || 'reviewer_reminder' == mode) {
          params.reviewer_deadline = form.elements['reviewer_deadline'].value;

          if (null == params.reviewer_deadline || "" == params.reviewer_deadline) {
            alert('Bitte setzen Sie erst ein Datum im Feld "Vereinbarte Abgabe"');
            return;
          }
        }
        for (var key in params) {
          url += '&' + key + '=' + params[key];
        }

        window.open(url);
      }
    }

    function generateSlug() {
      var subject = \$('subject');
      if (null === subject) {
        return;
      }

      title = subject.value;
      if ("" == title) {
        alert('Bitte tragen Sie erst einen Titel ein');

        return;
      }

      var url = '{$url_ws}';
      var pars = 'pn=article&action=generateSlug&title=' + encodeURIComponent(title);

      var form = document.forms['detail'];
      if (null != form && null != form.elements['user_id']) {
        var user_id = form.elements['user_id'].value;
        if ("" != user_id) {
          user_id = + user_id;

          if (!isNaN(user_id)) {
            pars += '&user_id=' + user_id;
          }
        }
      }

      var myAjax = new Ajax.Request(url, {
        method: 'get',
        parameters: pars,
        onComplete: setSlug
      });
    }

    function setSlug (originalRequest, obj) {
      if (obj.status > 0) {
        var field = \$('slug_de');

        if (null != field) {
          field.value = obj.title_slug;
        }
      }
      else {
        alert('ret: ' + obj.msg + ' ' + obj.status);
      }
    }

EOT;
      $publisher_request_button = sprintf(' <input type="button" value="%s" onclick="generateCommunication(\'%s\', \'publisher_request\')" />',
                                          tr('send letter'), htmlspecialchars($this->page->buildLink([ 'pn' => 'communication', 'edit' => -1 ])));
      $reviewer_request_button = sprintf(' <input type="button" value="%s" onclick="generateCommunication(\'%s\', \'reviewer_request\')" />',
                                         tr('send letter'), htmlspecialchars($this->page->buildLink([ 'pn' => 'communication', 'edit' => -1 ])));
      $reviewer_sent_button = sprintf(' <input type="button" value="%s" onclick="generateCommunication(\'%s\', \'reviewer_sent\')" />',
                                      tr('send letter'), htmlspecialchars($this->page->buildLink([ 'pn' => 'communication', 'edit' => -1 ])));
      $reviewer_reminder_button = sprintf(' <input type="button" value="%s" onclick="generateCommunication(\'%s\', \'reviewer_reminder\')" />',
                                          tr('send letter'), htmlspecialchars($this->page->buildLink([ 'pn' => 'communication', 'edit' => -1 ])));
      $imprimatur_sent_button = sprintf(' <input type="button" value="%s" onclick="generateCommunication(\'%s\', \'imprimatur_sent\')" />',
                                          tr('send letter'), htmlspecialchars($this->page->buildLink([ 'pn' => 'communication', 'edit' => -1 ])));
      $slug_button = sprintf(' <input type="button" value="%s" onclick="generateSlug()" />',
                             tr('generate'));
    }

    $rows = parent::getEditRows($mode);

    $rows = array_merge_at($rows, [
        'section' => [ 'label' => 'Section' ],
      ], 'user');

    $rows = array_merge_at($rows, [
        'slug_de' => [
          'label' => 'Kurz-URL (de)',
          'value' => 'edit' == $mode
            ? $this->getFormField('slug_de') . $slug_button
            : $this->record->get_value('slug_de')
        ],
        'slug' => [ 'label' => 'Kurz-URL (en)' ],
        'editor' => [ 'label' => 'Article Editor' ],
        'referee' => [ 'label' => 'Referee' ],
        'lang' => [ 'label' => 'Quellsprache' ],
        'translator' => [ 'label' => 'Translator' ],
        'status_translation' => [ 'label' => 'Translation Status' ],
      ], 'status');

    $rows = array_merge_at($rows, [
      'modified' => ['label' => 'Last Modified Date'],
      'reviewer_request' => [
      'label' => 'Author contacted',
      'value' => 'edit' == $mode
        ? $this->getFormField('reviewer_request') . $reviewer_request_button
        : $this->record->get_value('reviewer_request')
      ],
      'reviewer_sent' => [
        'label' => 'Author accepted',
        'value' => 'edit' == $mode
          ? $this->getFormField('reviewer_sent') . $reviewer_sent_button
          : $this->record->get_value('reviewer_sent')
      ],
      'reviewer_deadline' => [
        'label' => 'Author deadline',
        'value' => 'edit' == $mode
          ? $this->getFormField('reviewer_deadline') . $reviewer_reminder_button
          : $this->record->get_value('reviewer_deadline')
      ],
      'reviewer_received' => [ 'label' => 'Article received' ],
      'referee_sent' => [ 'label' => 'Article sent to referee' ],
      'referee_deadline' =>[
        'label' => 'Referee deadline',
        'value' => 'edit' == $mode
          ? $this->getFormField('referee_deadline') // .$reviewer_reminder_button
          : $this->record->get_value('referee_deadline')
      ],
      'publisher_request' => [
        'label' => 'Holding Institution request',
        'value' => 'edit' == $mode
          ? $this->getFormField('publisher_request') . $publisher_request_button
          : $this->record->get_value('publisher_request')
      ],
      'publisher_received' => [ 'label' => 'Holding Institution response' ],
      'imprimatur_sent' => [
        'label' => 'Imprimatur sent to author',
        'value' => 'edit' == $mode
          ? $this->getFormField('imprimatur_sent') . $imprimatur_sent_button
          : $this->record->get_value('imprimatur_sent')
      ]], 'published');

    $additional = [ 'license' => [ 'label' => 'License' ] ];
    if ('edit' == $mode) {
      $status_flags = $this->form->field('status_flags');
    }
    else {
      $status_flags_value = $this->record->get_value('status_flags');
    }

    foreach ([
        'review' => [ 'label' => 'Peer Review', 'mask' => 0x1 ],
        'markup' => [ 'label' => 'Markup', 'mask' => 0x02 ],
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
             : $this->record->get_value('comment_' . $key)),
      ];
    }

    if ('edit' == $mode) {
      $finalized = $status_flags->show(0x20) . '<br />';
    }
    else {
      $finalized = (0 != ($status_flags_value & 0x20) ? tr('ready for publishing') . '<br />' : '');
    }

    $additional['status_source'] = [
      'label' => 'Source',
      'value' => $finalized,
    ];

    $additional['comment_imprimatur'] = [ 'label' => 'R&uuml;ckmeldung Imprimatur' ];

    $rows = array_merge_at($rows, $additional, 'users');

    return $rows;
  }

  function instantiateUploadHandler ($className = 'XmlUploadHandler') {
    require_once INC_PATH . 'admin/xml_upload_handler.inc.php';

    return parent::instantiateUploadHandler($className);
  }

  function buildView () {
    $ret = $this->addPreviewOverlay()
      . parent::buildView();

    $dbconn = $this->page->dbconn;

    // publications belonging to this item
    $this->script_url[] = 'script/scriptaculous/prototype.js';
    $this->script_url[] = 'script/scriptaculous/scriptaculous.js';

    $url_ws = $this->page->BASE_URL . 'admin_ws.php?pn=publication&action=matchPublication';

    $url_submit = $this->page->buildLink([ 'pn' => $this->page->name, 'view' => $this->id ]);
    $publication_selector = <<<EOT
<form name="publicationSelector" action="$url_submit" method="post"><input type="hidden" name="publication_add" /><input type="text" id="publication" name="add_publication" style="width:400px; border: 1px solid black;" value="" /><div id="autocomplete_choices" class="autocomplete"></div><script type="text/javascript">new Ajax.Autocompleter('publication', 'autocomplete_choices', '$url_ws', {paramName: 'fulltext', minChars: 2, afterUpdateElement : function (text, li) { if (li.id != '') { var form = document.forms['publicationSelector']; if (null != form) {form.elements['publication_add'].value = li.id; form.submit(); } } }});</script></form>
EOT;
    // fetch the publications
    $querystr = sprintf("SELECT Publication.id AS id, title, author, editor, YEAR(publication_date) AS year, place, publisher FROM Publication, MessagePublication WHERE MessagePublication.publication_id=Publication.id AND MessagePublication.message_id=%d ORDER BY MessagePublication.ord", $this->id);
    $dbconn = &$this->page->dbconn;
    $dbconn->query($querystr);
    $publications = '';
    $params_remove = [ 'pn' => $this->page->name, 'view' => $this->id ];
    $params_view = [ 'pn' => 'publication' ];
    while ($dbconn->next_record()) {
      if (empty($publications)) {
        $publications = '<ul id="publications" class="sortableList">';
      }

      $params_remove['publication_remove'] = $params_view['view'] = $dbconn->Record['id'];
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
        . ($this->checkAction(TABLEMANAGER_EDIT)
            ? sprintf(' [<a href="%s">%s</a>]',
                  htmlspecialchars($this->page->buildLink($params_remove)), tr('remove'))
            : '')
        . '</li>';
    }

    if ($this->checkAction(TABLEMANAGER_EDIT) && !empty($publications)) {
      $publications .= '</ul>';
      $msg_submit = tr('Store updated order');
      $publications .= <<<EOT
<form name="publicationOrder" action="$url_submit" method="post" onSubmit="populateHiddenVars();"><input type="hidden" id="publicationsListOrder" name="publication_order" /><input type="submit" value="$msg_submit" /></form>
<script type="text/javascript">
Sortable.create('publications',{tag:'li'});

function populateHiddenVars() {
document.getElementById('publicationsListOrder').value = Sortable.serialize('publications');
return true;
}
</script>
EOT;
    }

    $ret .= '<hr />'
          . $this->buildContentLine(tr('Covered Source(s)'), $publication_selector . $publications);

    return $ret;
  }

  function buildSearchFields ($options = []) {
    $options['status_translation'] = '';
    $options['section'] = 'Section';
    $options['referee'] = 'Referee';

    return parent::buildSearchFields($options);
  }

  function buildListingRow (&$row) {
    if ('xls' == $this->page->display) {
      $xls_row = [];
      for ($i = 0; $i < $this->cols_listing_count; $i++) {
        $val = $row[$i];
        if (count($this->cols_listing) - 2 == $i && isset($val)) {
          $val = $this->status_options[$val];
        }

        $xls_row[] = $val;
      }

      $this->xls_data[] = $xls_row;

      return;
    }

    return parent::buildListingRow($row);
  }

  function getImageDescriptions () {
    $images = [
      'document' => [
        'title' => tr('Documents (Texts, Images, ...)'),
        'multiple' => true,
        'imgparams' =>[
          'max_width' => 300, 'max_height' => 300,
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
        'labels' => [
          'source' => 'Source',
          'displaydate' => 'Creation Date',
        ],
      ],
    ];

    return [ $GLOBALS['TYPE_MESSAGE'], $images ];
  }
}

$display = new DisplayArticle($page);
if (false === $display->init()) {
  $page->redirect([ 'pn' => '' ]);
}

$page->setDisplay($display);
