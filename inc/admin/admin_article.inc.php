<?php
/*
 * admin_article.inc.php
 *
 * Manage the articles
 *
 * (c) 2009 daniel.burckhardt@sur-gmbh.ch
 *
 * Version: 2009-03-11 dbu
 *
 * Changes:
 *
 */

require_once INC_PATH . 'common/classes.inc.php';
require_once INC_PATH . 'admin/displaymessage.inc.php';


class DisplayArticle extends DisplayMessage
{
  var $status_options = array (
    '-99' => 'angedacht',
    '-76' => 'angefragt Autor',
    '-73' => 'vergeben Autor',
    '-69' => '1. Mahnung',
    '-68' => '2. Mahnung',
    '-67' => '3. Mahnung',
    '-66' => '4. Mahnung',
    '-59' => 'eingegangen Autor',
    '-55' => 'an Gutachter',
    '-53' => '&#220;berarbeitung Autor',
    '-49' => 'inhaltlich ok',
    '-45' => 'formal ok',
    '1'   => 'ver&#246;ffentlicht',
    '-100' => 'abgebrochen Redakteur',
    '-106' => 'abgebrochen Autor',
    '-112' => 'abgelehnt Artikel',
  );
  var $status_default = '-99';
  var $cols_listing = array('id' => 'ID', 'subject' => 'Subject', 'contributor' => 'Contributor', 'status' => 'Status', 'published' => 'Published');
  var $editor_options;

  function __construct (&$page) {
    global $MESSAGE_ARTICLE;
    $this->type = $MESSAGE_ARTICLE;
    $this->messages['item_new'] = 'New Article';
    parent::__construct($page);
  }

  function constructFulltextCondition () {
    $this->condition[] = array('name' => 'search',
                               'method' => 'buildFulltextCondition',
                               'args' => 'subject,User.firstname,User.lastname,body',
                               'persist' => 'session');
  }

  function buildOptions ($type = 'editor') {
    global $RIGHTS_EDITOR, $RIGHTS_REFEREE;

    $dbconn = & $this->page->dbconn;
    $querystr = "SELECT id, lastname, firstname FROM User";
    switch ($type) {
      case 'referee':
          $querystr .= sprintf(" WHERE 0 <> (privs & %d) AND id > 1 AND status <> %d",
                               $RIGHTS_REFEREE, STATUS_DELETED);
          break;
      case 'editor':
      default:
          // id > 1 so Daniel Burckhardt doesn't get displayed
          $querystr .= sprintf(" WHERE 0 <> (privs & %d) AND id > 1 AND status <> %d",
                               $RIGHTS_EDITOR, STATUS_DELETED);
          break;
    }
    $querystr .= " ORDER BY lastname, firstname";
    $dbconn->query($querystr);
    $options = array();
    while ($dbconn->next_record())
      $options[$dbconn->Record['id']] = $dbconn->Record['lastname'].' '.$dbconn->Record['firstname'];

    return $options;
  }

  function buildRecord ($name = '') {
    $record = parent::buildRecord($name);
    if (!isset($record))
      return;

    // get the options
    $this->editor_options = $this->buildOptions('editor');
    $this->view_options['editor'] = $this->editor_options;
    $this->referee_options = $this->buildOptions('referee');
    $this->view_options['referee'] = $this->referee_options;

    $record->add_fields(array(
        new Field(array('name'=>'publication', 'type'=>'hidden', 'datatype'=>'int', 'nodbfield' => 1, 'null' => 1)),
        new Field(array('name'=>'editor', 'type'=>'select',
                        'options' => array_merge(array(''), array_keys($this->editor_options)),
                        'labels' => array_merge(array(tr('-- none --')), array_values($this->editor_options)),
                        'datatype'=>'int', 'null' => 1)),
        new Field(array('name'=>'referee', 'type'=>'select',
                        'options' => array_merge(array(''), array_keys($this->referee_options)),
                        'labels' => array_merge(array(tr('-- none --')), array_values($this->referee_options)),
                        'datatype'=>'int', 'null' => 1)),
        new Field(array('name'=>'reviewer_request', 'type'=>'datetime', 'datatype'=>'datetime', 'null' => TRUE)),
        new Field(array('name'=>'reviewer_sent', 'type'=>'datetime', 'datatype'=>'datetime', 'null' => TRUE)),
        new Field(array('name'=>'reviewer_deadline', 'type'=>'datetime', 'datatype'=>'datetime', 'null' => TRUE)),
        new Field(array('name'=>'reviewer_received', 'type'=>'datetime', 'datatype'=>'datetime', 'null' => TRUE)),
        new Field(array('name'=>'referee_sent', 'type'=>'datetime', 'datatype'=>'datetime', 'null' => TRUE)),
        new Field(array('name'=>'referee_deadline', 'type'=>'datetime', 'datatype'=>'datetime', 'null' => TRUE)),
      ));

    return $record;
  }

  function getEditRows ($mode = 'edit') {
    if ('edit' == $mode) {
      $this->script_code .= <<<EOT
  function generateCommunication (url, mode) {
      var form = document.forms['detail'];
      if (null != form) {
        var params = {
          mode: mode,
          id_review: form.elements['id'].value,
          title: form.elements['subject'].value
        };
        if ('publisher_request' != mode) {
          params.id_to = form.elements['user_id'].value;
          if ('' == params.id_to) {
            alert('Please set a Contributor first');
            return;
          }
          if ('' == params.title) {
            alert('Please set a Title first');
            return;
          }
        }
        if ('reviewer_sent' == mode || 'reviewer_reminder' == mode) {
          params.reviewer_deadline = form.elements['reviewer_deadline'].value;
          if (null == params.reviewer_deadline || "" == params.reviewer_deadline) {
            alert('Bitte setzen Sie erst ein Datum im Feld "Vereinbarte Abgabe"');
            return;
          }
        }
        for (var key in params)
          url += '&' + key + '=' + params[key];

        window.open(url);
      }
  }
EOT;
      $reviewer_request_button = sprintf(' <input type="button" value="%s" onclick="generateCommunication(\'%s\', \'reviewer_request\')" />',
                                    tr('send letter'), htmlspecialchars($this->page->buildLink(array('pn' => 'communication', 'edit' => -1))));
      $reviewer_sent_button = sprintf(' <input type="button" value="%s" onclick="generateCommunication(\'%s\', \'reviewer_sent\')" />',
                                    tr('send letter'), htmlspecialchars($this->page->buildLink(array('pn' => 'communication', 'edit' => -1))));
      $reviewer_reminder_button = sprintf(' <input type="button" value="%s" onclick="generateCommunication(\'%s\', \'reviewer_reminder\')" />',
                                    tr('send letter'), htmlspecialchars($this->page->buildLink(array('pn' => 'communication', 'edit' => -1))));
    }
    $rows = parent::getEditRows($mode);
    $rows = array_merge_at($rows,
      array(
            'editor' => array('label' => 'Article Editor'),
            'referee' => array('label' => 'Referee'),
      ), 'status');
    $rows = array_merge_at($rows,
      array(
            'reviewer_request' => array(
                'label' => 'Author contacted',
                'value' => 'edit' == $mode ?
                  $this->getFormField('reviewer_request').$reviewer_request_button
                  : $this->record->get_value('reviewer_request')
            ),
            'reviewer_sent' => array(
                'label' => 'Author accepted',
                'value' => 'edit' == $mode ?
                  $this->getFormField('reviewer_sent').$reviewer_sent_button
                  : $this->record->get_value('reviewer_sent')
            ),
            'reviewer_deadline' => array(
                'label' => 'Author deadline',
                'value' => 'edit' == $mode ?
                  $this->getFormField('reviewer_deadline').$reviewer_reminder_button
                  : $this->record->get_value('reviewer_deadline')
            ),
            'reviewer_received' => array('label' => 'Article received'),
            'referee_sent' => array('label' => 'Article sent to referee'),
            'referee_deadline' => array(
                'label' => 'Referee deadline',
                'value' => 'edit' == $mode ?
                  $this->getFormField('referee_deadline') // .$reviewer_reminder_button
                  : $this->record->get_value('referee_deadline')
            ),
      ), 'published');

    return $rows;
  }

  /*
  function buildEditButton () {
    return parent::buildEditButton()
      . sprintf(' <span class="regular">[<a href="%s" target="_blank">%s</a>]</span>',
                   htmlspecialchars('./?pn=article&preview=' . $this->id),
                   tr('site preview'));
  } */

/*
  function buildStatusOptions () {
    $options = array('100' => '_&#252;berf&#228;llig_') + $this->status_options;
    return parent::buildStatusOptions($options);
  }
*/

}

$display = new DisplayArticle($page);
if (FALSE === $display->init())
  $page->redirect(array('pn' => ''));
$page->setDisplay($display);
