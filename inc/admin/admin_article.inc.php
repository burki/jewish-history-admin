<?php
/*
 * admin_article.inc.php
 *
 * Manage the articles
 *
 * (c) 2009 daniel.burckhardt@sur-gmbh.ch
 *
 * Version: 2009-08-03 dbu
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
  var $editor_options;

  function __construct (&$page) {
    global $MESSAGE_ARTICLE;
    $this->type = $MESSAGE_ARTICLE;
    $this->messages['item_new'] = 'New Article';
    parent::__construct($page);
    $this->order['date'] = array('IF(0 = reviewer_deadline + 0, published, reviewer_deadline) DESC', 'IF(0 = reviewer_deadline + 0, published, reviewer_deadline)');
    $this->fields_listing[sizeof($this->fields_listing) -1 ] = "DATE(reviewer_deadline) AS reviewer_deadline";
    $this->cols_listing['date'] = 'Author deadline';
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
        new Field(array('name'=>'url', 'id' => 'url', 'type'=>'text', 'datatype'=>'char', 'size'=>65, 'maxlength'=>200, 'null' => 1)),
        new Field(array('name'=>'urn', 'id' => 'urn', 'type'=>'text', 'datatype'=>'char', 'size'=>45, 'maxlength'=>200, 'null' => 1)),
       ));

    return $record;
  }

  function getEditRows ($mode = 'edit') {
    if ('edit' == $mode) {
      $url_ws = $this->page->BASE_PATH . 'admin/admin_ws.php';

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

  function generateUrn() {
    var permalink = \$F('url');
    if ("" == permalink) {
      alert('Bitte tragen Sie erst eine URL ins Feld "Permanent URL" ein');
      return;
    }
    var url = '{$url_ws}';
    var pars = 'pn=article&action=generateUrn&url=' + escape(permalink);

    var myAjax = new Ajax.Request(
          url,
          {
              method: 'get',
              parameters: pars,
              onComplete: setUrn
          });
  }

  function setUrn (originalRequest, obj) {
    if (obj.status > 0) {
      var field = \$('urn');
      if (null != field)
        field.value = obj.urn;
    }
    else
      alert('ret: ' + obj.msg + ' ' + obj.status);
  }

EOT;
      $reviewer_request_button = sprintf(' <input type="button" value="%s" onclick="generateCommunication(\'%s\', \'reviewer_request\')" />',
                                    tr('send letter'), htmlspecialchars($this->page->buildLink(array('pn' => 'communication', 'edit' => -1))));
      $reviewer_sent_button = sprintf(' <input type="button" value="%s" onclick="generateCommunication(\'%s\', \'reviewer_sent\')" />',
                                    tr('send letter'), htmlspecialchars($this->page->buildLink(array('pn' => 'communication', 'edit' => -1))));
      $reviewer_reminder_button = sprintf(' <input type="button" value="%s" onclick="generateCommunication(\'%s\', \'reviewer_reminder\')" />',
                                    tr('send letter'), htmlspecialchars($this->page->buildLink(array('pn' => 'communication', 'edit' => -1))));
      $urn_button = sprintf(' <input type="button" value="%s" onclick="generateUrn(\'xx\')" />',
                                    tr('generate'));
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
            'url' => array('label' => 'Permanent URL'),
            'urn' => array('label' => 'URN', 'value' => 'edit' == $mode ?
                  $this->getFormField('urn').$urn_button
                  : $this->record->get_value('urn')),
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
