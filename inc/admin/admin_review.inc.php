<?php
/*
 * admin_review.inc.php
 *
 * Manage the book reviews
 *
 * (c) 2007-2009 daniel.burckhardt@sur-gmbh.ch
 *
 * Version: 2009-01-05 dbu
 *
 * Changes:
 *
 */

require_once INC_PATH . 'common/classes.inc.php';
require_once INC_PATH . 'admin/displaymessage.inc.php';

class ReviewQueryConditionBuilder extends MessageQueryConditionBuilder
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
      . ' WHEN -55 THEN DATE_ADD(referee_deadline, INTERVAL 30 DAY)'
      . ' ELSE NULL END';
  }

  function buildStatusCondition () {
    $num_args = func_num_args();
    if ($num_args <= 0)
      return;
    $fields = func_get_args();

    if (isset($this->term) && '' !== $this->term) {
      if (100 == $this->term) { // ueberfaellig
        $ret = 'CURRENT_DATE() >= ' . self::buildOverdueExpression();
      }
      else
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
}

class ReviewRecord extends MessageRecord
{
  function store () {
    $stored = parent::store();

    if ($stored) {
      $publication = $this->get_value('publication');
      if (isset($publication) && intval($publication) > 0) {
        $dbconn = new DB;
        // add at the bottom
        $querystr = sprintf("SELECT MAX(ord) FROM MessagePublication WHERE message_id=%d", intval($publication));
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

class DisplayReview extends DisplayMessage
{
  var $status_options = array (
    '-99' => 'angedacht',
    '-89' => 'angefordert Verlag',
    '-85' => 'eingegangen Verlag',
    '-79' => 'gesucht Rezensent',
    '-76' => 'angefragt Rezensent',
    '-73' => 'verschickt Rezensent',
    '-69' => '1. Mahnung',
    '-68' => '2. Mahnung',
    '-67' => '3. Mahnung',
    '-66' => '4. Mahnung',
    '-59' => 'eingegangen Rezensent',
    '-55' => 'an Gutachter',
    '-53' => '&#220;berarbeitung Rezensent',
    '-49' => 'inhaltlich ok',
    '-45' => 'formal ok',
    '1'   => 'ver&#246;ffentlicht',
    '5'   => 'Belegexemplar verschickt',
    '-100' => 'abgebrochen Redakteur',
    '-103' => 'abgebrochen Verlag',
    '-106' => 'abgebrochen Rezensent',
    '-109' => 'abgelehnt Buch',
    '-112' => 'abgelehnt Rezension',
  );
  var $status_default = '-99';
  var $cols_listing = array('id' => 'ID', 'subject' => 'Subject', 'contributor' => 'Contributor', 'status' => 'Status', 'overdue' => 'Overdue');
  var $editor_options;

  function __construct (&$page) {
    global $MESSAGE_REVIEW_PUBLICATION;
    $this->type = $MESSAGE_REVIEW_PUBLICATION;
    $this->messages['item_new'] = 'New Review';
    $this->fields_listing[sizeof($this->fields_listing) - 1] = sprintf('DATE(%s) AS published',
                                      ReviewQueryConditionBuilder::buildOverdueExpression());
    parent::__construct($page);
  }

  function constructFulltextCondition () {
    $this->joins_listing[] = ' LEFT OUTER JOIN MessagePublication ON MessagePublication.message_id=Message.id'
      . ' LEFT OUTER JOIN Publication ON MessagePublication.publication_id=Publication.id';
    $this->distinct_listing = TRUE;
    $this->condition[] = array('name' => 'search',
                               'method' => 'buildFulltextCondition',
                               'args' => 'subject,User.firstname,User.lastname,body,Publication.title,Publication.author,Publication.editor',
                               'persist' => 'session');
  }

  function instantiateQueryConditionBuilder ($term) {
    return new ReviewQueryConditionBuilder($term);
  }

  function init () {
    $ret = parent::init();

    // update publications
    if (array_key_exists('publication_add', $_POST)) {
      if (($id_publication = intval($_POST['publication_add'])) > 0) {
        $dbconn = &$this->page->dbconn;
        // add at the bottom
        $querystr = sprintf("SELECT MAX(ord) FROM MessagePublication WHERE message_id=%d", $this->workflow->primaryKey());
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
        $querystr = sprintf("DELETE FROM MessagePublication WHERE message_id=%d AND publication_id=%d", $this->workflow->primaryKey(), $id_publication);
        $this->page->dbconn->query($querystr);
      }
    }

    if (array_key_exists('publication_order', $_POST)) {
      parse_str($_POST['publication_order'], $order);
      if (array_key_exists('publications', $order) && 'array' == gettype($order['publications'])) {
        $dbconn = &$this->page->dbconn;
        foreach ($order['publications'] as $ord => $id_publication) {
          $querystr = sprintf("UPDATE MessagePublication SET ord=%d WHERE message_id=%d AND publication_id=%d",
                              intval($ord), $this->workflow->primaryKey(), intval($id_publication));
          $dbconn->query($querystr);
        }
      }
    }

    return $ret;
  }

  function instantiateRecord () {
    return new ReviewRecord(array('tables' => $this->table, 'dbconn' => $this->page->dbconn));
  }

  function buildOptions ($type = 'editor') {
    global $RIGHTS_EDITOR, $RIGHTS_REFEREE;

    $dbconn = & $this->page->dbconn;
    $querystr = "SELECT id, lastname, firstname FROM User";
    switch ($type) {
      case 'referee':
          $querystr .= sprintf(" WHERE 0 <> (privs & %d) AND id > 1", $RIGHTS_REFEREE);
          break;
      case 'editor':
      default:
          // id > 1 so Daniel Burckhardt doesn't get displayed
          $querystr .= sprintf(" WHERE 0 <> (privs & %d) AND id > 1", $RIGHTS_EDITOR);
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
        new Field(array('name'=>'publisher_request', 'type'=>'datetime', 'datatype'=>'datetime', 'null' => TRUE)),
        new Field(array('name'=>'publisher_received', 'type'=>'datetime', 'datatype'=>'datetime', 'null' => TRUE)),
        new Field(array('name'=>'reviewer_request', 'type'=>'datetime', 'datatype'=>'datetime', 'null' => TRUE)),
        new Field(array('name'=>'reviewer_sent', 'type'=>'datetime', 'datatype'=>'datetime', 'null' => TRUE)),
        new Field(array('name'=>'reviewer_deadline', 'type'=>'datetime', 'datatype'=>'datetime', 'null' => TRUE)),
        new Field(array('name'=>'reviewer_received', 'type'=>'datetime', 'datatype'=>'datetime', 'null' => TRUE)),
        new Field(array('name'=>'referee_sent', 'type'=>'datetime', 'datatype'=>'datetime', 'null' => TRUE)),
        new Field(array('name'=>'referee_deadline', 'type'=>'datetime', 'datatype'=>'datetime', 'null' => TRUE)),
        new Field(array('name'=>'publisher_vouchercopy', 'type'=>'datetime', 'datatype'=>'datetime', 'null' => TRUE)),
      ));

    if (!isset($this->workflow->id)) {
      // for new entries, a subject or publication-id may be passed along
      if (array_key_exists('subject', $_GET))
        $record->set_value('subject', $_GET['subject']);
      if (array_key_exists('publication', $_GET) && intval($_GET['publication']) > 0)
        $record->set_value('publication', intval($_GET['publication']));
    }

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
          id_publication: form.elements['publication'].value
        };
        if ('referee_request' == mode) {
          params.id_to = form.elements['referee'].value;
          if ('' == params.id_to) {
            alert('Please set a Referee first');
            return;
          }
        }
        else if ('publisher_request' != mode) {
          params.id_to = form.elements['user_id'].value;
          if ('' == params.id_to) {
            alert('Please set a Contributor first');
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
      $publisher_request_button = sprintf(' <input type="button" value="%s" onclick="generateCommunication(\'%s\', \'publisher_request\')" />',
                                    tr('send letter'), htmlspecialchars($this->page->buildLink(array('pn' => 'communication', 'edit' => -1))));
      $reviewer_request_button = sprintf(' <input type="button" value="%s" onclick="generateCommunication(\'%s\', \'reviewer_request\')" />',
                                    tr('send letter'), htmlspecialchars($this->page->buildLink(array('pn' => 'communication', 'edit' => -1))));
      $reviewer_sent_button = sprintf(' <input type="button" value="%s" onclick="generateCommunication(\'%s\', \'reviewer_sent\')" />',
                                    tr('send letter'), htmlspecialchars($this->page->buildLink(array('pn' => 'communication', 'edit' => -1))));
      $reviewer_reminder_button = sprintf(' <input type="button" value="%s" onclick="generateCommunication(\'%s\', \'reviewer_reminder\')" />',
                                    tr('send letter'), htmlspecialchars($this->page->buildLink(array('pn' => 'communication', 'edit' => -1))));
      $referee_request_button = sprintf(' <input type="button" value="%s" onclick="generateCommunication(\'%s\', \'referee_request\')" />',
                                    tr('send letter'), htmlspecialchars($this->page->buildLink(array('pn' => 'communication', 'edit' => -1))));
      $publisher_vouchercopy_button = sprintf(' <input type="button" value="%s" onclick="generateCommunication(\'%s\', \'publisher_vouchercopy\')" />',
                                    tr('send letter'), htmlspecialchars($this->page->buildLink(array('pn' => 'communication', 'edit' => -1))));
    }
    $rows = parent::getEditRows($mode);
    $rows = array_merge_at($rows,
      array(
            'publication' => FALSE, // hidden
            'editor' => array('label' => 'Review Editor'),
            'referee' => array('label' => 'Referee'),
      ), 'status');
    $rows = array_merge_at($rows,
      array(
            'publisher_request' => array(
                'label' => 'Review copy requested',
                'value' => 'edit' == $mode ?
                  $this->getFormField('publisher_request').$publisher_request_button
                  : $this->record->get_value('publisher_request')
            ),
            'publisher_received' => array('label' => 'Review copy received'),
            'reviewer_request' => array(
                'label' => 'Reviewer contacted',
                'value' => 'edit' == $mode ?
                  $this->getFormField('reviewer_request').$reviewer_request_button
                  : $this->record->get_value('reviewer_request')
            ),
            'reviewer_sent' => array(
                'label' => 'Copy sent to reviewer',
                'value' => 'edit' == $mode ?
                  $this->getFormField('reviewer_sent').$reviewer_sent_button
                  : $this->record->get_value('reviewer_sent')
            ),
            'reviewer_deadline' => array(
                'label' => 'Reviewer deadline',
                'value' => 'edit' == $mode ?
                  $this->getFormField('reviewer_deadline').$reviewer_reminder_button
                  : $this->record->get_value('reviewer_deadline')
            ),
            'reviewer_received' => array('label' => 'Review received'),
            'referee_sent' => array(
                'label' => 'Review sent to referee',
                'value' => 'edit' == $mode ?
                  $this->getFormField('referee_sent').$referee_request_button
                  : $this->record->get_value('referee_sent')
            ),
            'referee_deadline' => array(
                'label' => 'Referee deadline',
                'value' => 'edit' == $mode ?
                  $this->getFormField('referee_deadline') // .$reviewer_reminder_button
                  : $this->record->get_value('referee_deadline')
            ),
            'publisher_vouchercopy' => array(
                'label' => 'Voucher copy sent',
                'value' => 'edit' == $mode ?
                  $this->getFormField('publisher_vouchercopy').$publisher_vouchercopy_button
                  : $this->record->get_value('publisher_vouchercopy')
            ),
      ), 'published');

    return $rows;
  }

  function buildEditButton () {
    return parent::buildEditButton()
      . sprintf(' <span class="regular">[<a href="%s" target="_blank">%s</a>]</span>',
                   htmlspecialchars('./?pn=issue&preview=' . $this->id),
                   tr('site preview'));
  }


  function buildView () {
    $ret = parent::buildView();

    $dbconn = $this->page->dbconn;

    // publications belonging to this item
    $this->script_url[] = $this->page->BASE_PATH
                          . 'script/scriptaculous/prototype.js';
    $this->script_url[] = $this->page->BASE_PATH
                          . 'script/scriptaculous/scriptaculous.js';

    $url_ws = $this->page->BASE_PATH . 'admin/admin_ws.php?pn=publication&action=matchPublication';

    $url_submit = $this->page->buildLink(array('pn' => $this->page->name, 'view' => $this->id));
    $publication_selector = <<<EOT
<form name="publicationSelector" action="$url_submit" method="post"><input type="hidden" name="publication_add" /><input type="text" id="publication" name="add_publication" style="width:400px; border: 1px solid black;" value="" /><div id="autocomplete_choices" class="autocomplete"></div><script type="text/javascript">new Ajax.Autocompleter('publication', 'autocomplete_choices', '$url_ws', {paramName: 'fulltext', minChars: 2, afterUpdateElement : function (text, li) { if (li.id != '') { var form = document.forms['publicationSelector']; if (null != form) {form.elements['publication_add'].value = li.id; form.submit(); } } }});</script></form>
EOT;
    // fetch the publications
    $querystr = sprintf("SELECT Publication.id AS id, title, author, editor, YEAR(publication_date) AS year, place, publisher FROM Publication, MessagePublication WHERE MessagePublication.publication_id=Publication.id AND MessagePublication.message_id=%d ORDER BY MessagePublication.ord", $this->id);
    $dbconn = &$this->page->dbconn;
    $dbconn->query($querystr);
    $publications = '';
    $params_remove = array('pn' => $this->page->name, 'view' => $this->id);
    $params_view = array('pn' => 'publication');
    while ($dbconn->next_record()) {
      if (empty($publications))
        $publications = '<ul id="publications" class="sortableList">';
      $params_remove['publication_remove'] = $params_view['view'] = $dbconn->Record['id'];
      $publisher_place_year = '';
      if (!empty($dbconn->Record['place']))
        $publisher_place_year = $dbconn->Record['place'];
      if (!empty($dbconn->Record['publisher']))
        $publisher_place_year .= (!empty($publisher_place_year) ? ': ' : '')
          . $dbconn->Record['publisher'];
      if (!empty($dbconn->Record['year']))
        $publisher_place_year .= (!empty($publisher_place_year) ? ', ' : '')
          . $dbconn->Record['year'];

      $publications .= sprintf('<li id="item_%d">', $dbconn->Record['id'])
        . (isset($dbconn->Record['author']) ? $dbconn->Record['author'] : $dbconn->Record['editor'])
        . ': <i>'.$this->formatText($dbconn->Record['title']).'</i>'
        . (!empty($publisher_place_year) ? ' ' : '')
        . $this->formatText($publisher_place_year)
        .sprintf(' [<a href="%s">%s</a>] [<a href="%s">%s</a>]',
                 htmlspecialchars($this->page->buildLink($params_view)), tr('view'),
                 htmlspecialchars($this->page->buildLink($params_remove)), tr('remove'))
        .'</li>';
    }
    if (!empty($publications)) {
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
    $ret .= '<hr />'.$this->buildContentLine(tr('Reviewed Publication(s)'), $publication_selector.$publications);

    return $ret;
  }

  function buildStatusOptions () {
    $options = array('100' => '_&#252;berf&#228;llig_') + $this->status_options;
    return parent::buildStatusOptions($options);
  }

}

$display = new DisplayReview($page);
if (FALSE === $display->init())
  $page->redirect(array('pn' => ''));
$page->setDisplay($display);
