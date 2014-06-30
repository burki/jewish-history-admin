<?php
/*
 * admin_communication.inc.php
 *
 * Class for managing communication
 *
 * (c) 2008-2014 daniel.burckhardt@sur-gmbh.ch
 *
 * Version: 2014-06-30 dbu
 *
 * Changes:
 *
 */

require_once INC_PATH . 'common/tablemanager.inc.php';
require_once INC_PATH . 'admin/common.inc.php';

class DisplayCommunication extends DisplayTable
{
  static $TYPE_MAP = array(
    'publisher_request' => 0,
    'reviewer_request' => 10,
    'reviewer_sent' => 20,
    'reviewer_reminder' => 30,
    'referee_request' => 40,
    'publisher_vouchercopy' => 50,
    );
  var $page_size = 30;
  var $table = 'Communication';
  var $fields_listing = array('id', 'to_email', 'subject', 'IFNULL(sent,changed)'); // , 'status');

  var $condition = array(
      array('name' => 'search', 'method' => 'buildLikeCondition', 'args' => 'to_email'),
  );
  var $order = array(array('sent DESC'));
  var $view_after_edit = TRUE;
  var $listing_default_action = TABLEMANAGER_VIEW;

  var $defaults = array();
  var $publications;

  function init () {
    if (!isset($this->id) && array_key_exists('mode', $_GET)) {
      if (array_key_exists($_GET['mode'], self::$TYPE_MAP)) {
        $this->defaults['type'] = self::$TYPE_MAP[$_GET['mode']];
      }

      $from = $this->fetchUser($this->page->user['id']);
      if (isset($from)) {
        global $MAIL_SETTINGS;

        $this->defaults['from_email'] = $from_email = array_key_exists('from_communication', $MAIL_SETTINGS)
          ? $MAIL_SETTINGS['from_communication'] : $from['email'];
      }

      if (array_key_exists('id_to', $_GET) && intval($_GET['id_to']) > 0) {
        $to = $this->fetchUser(intval($_GET['id_to']));
        if (isset($to)) {
          $this->defaults['to_email'] = $to['email'];
          $this->defaults['to_id'] = $to['id'];
        }
      }

      if (array_key_exists('id_review', $_GET) && intval($_GET['id_review']) > 0) {
        $this->defaults['message_id'] = intval($_GET['id_review']);
      }

      // set the publications for bibinfo and publication_request
      $publications = array();
      if (array_key_exists('id_publication', $_GET) && preg_match('/\d/', $_GET['id_publication'])) {
        $publications = preg_split('/\s*,\s*/', $_GET['id_publication']);
      }

      if (count($publications) == 0 && isset($this->defaults['message_id'])) {
        $dbconn = & $this->page->dbconn;
        $dbconn->query(sprintf("SELECT DISTINCT publication_id FROM MessagePublication WHERE message_id=%d",
                               $this->defaults['message_id']));

        while ($dbconn->next_record()) {
          $publications[] = $dbconn->Record['publication_id'];
        }
      }
      $this->publications = $publications;

      if (in_array($_GET['mode'], array('publisher_request', 'publisher_vouchercopy'))
          && count($this->publications) > 0) {
        $dbconn = & $this->page->dbconn;
        $querystr = sprintf("SELECT DISTINCT Publisher.email_contact AS to_email"
                            . " FROM Publisher INNER JOIN Publication ON Publisher.id=Publication.publisher_id"
                            . " WHERE Publication.id IN (%s)",
                            implode(',', $this->publications));

        $dbconn->query($querystr);
        $to_emails = array();
        while ($dbconn->next_record()) {
          $to_emails[] = $dbconn->Record['to_email'];
        }

        if (count($to_emails) > 0) {
          $this->defaults['to_email'] = implode(', ', $to_emails);
        }
      }

      $SUBJECT = array(
        'publisher_request' => 'Bestellung eines Rezensionsexemplars f&#252;r',
        'reviewer_request' => 'Artikel f&#252;r',
        'reviewer_sent' => 'Artikel f&#252;r',
        'reviewer_reminder' => 'Erinnerung Artikel f&#252;r',
        'referee_request' => 'Gutachteranfrage',
        'publisher_vouchercopy' => 'Link zur Rezension bei',
      );

      switch ($_GET['mode']) {
        case 'publisher_request':
        case 'reviewer_request':
        case 'reviewer_sent':
        case 'reviewer_reminder':
        case 'referee_request':
        case 'publisher_vouchercopy':
            global $SITE;
            $this->defaults['subject'] =
              (array_key_exists($_GET['mode'], $SUBJECT) ? $SUBJECT[$_GET['mode']] . ' ' : '')
              . tr($SITE['pagetitle']);
            $fname_template = INC_PATH . 'messages/' . $_GET['mode'] . '.txt';
            if (FALSE !== ($template = @file_get_contents($fname_template))) {
              // fill in template
              $this->defaults['body'] = preg_replace_callback('|\%([a-z_0-9]+)\%|',
                                                              array($this, 'replacePlaceholder'),
                                                              $template);
            }
            break;
      }
    }

    return parent::init();
  }

  private function exportRtf () {
    require_once LIB_PATH . 'rtf/Rtf.php';

    // Font
    $times12 = new Font(12, 'Times new Roman');
    $times10 = new Font(10, 'Times new Roman');

    // ParFormat
    $parFormat = new ParFormat();
    $parRight = new ParFormat('right');

    //Rtf document
    $rtf = new Rtf();

    // headers

    $header = $rtf->addHeader('first');
    $header->addImage(INC_PATH . 'messages/rtf_header.png', $parRight);

    $footer = $rtf->addFooter('first');
    $footer->writeText(preg_replace('/\n/', "\r\n", file_get_contents(INC_PATH . 'messages/rtf_footer.txt')),
                       $times10, $parFormat);

    $footer = $rtf->addFooter('all');
    $footer->writeText(preg_replace('/\n/', "\r\n", file_get_contents(INC_PATH . 'messages/rtf_footer.txt')),
                       $times10, $parFormat);


    //Section
    $sect = $rtf->addSection();
    $null = NULL;
    //Write utf-8 encoded text.
    //Text is from file. But you can use another resouce: db, sockets and other

    // in office-documents, start with the address
    $to_id = $this->record->get_value('to_id');
    if (!empty($to_id)) {
      $newline = "\r\n";
      if (0 == $this->record->get_value('type')) {
        $publisher = $this->fetchPublisher($to_id);
        if (isset($publisher)) {
          $address = $publisher['name'] . $newline
                   . $publisher['name_contact'] . $newline
                   . $publisher['address'] . $newline
                   . $publisher['zip'] . ' ' . $publisher['place'];
        }
      }
      else {
        $user = $this->fetchUser($to_id);
        if (isset($user)) {
          $newline = "\r\n";
          $address = // ('F' == $user['sex'] ? 'Frau' : 'Herr') . " " .
                     $user['firstname'] . ' ' . $user['lastname'] . $newline
                   . (!empty($user['address']) ? $user['address'] . $newline : '')
                   . (!empty($user['zip']) ? $user['zip'] . ' ' : '')
                   . (!empty($user['place']) ? $user['place'] : '');
        }
      }
      if (!empty($address)) {
        $parLeft = new ParFormat('left');
        $sect->writeText($address, $times12, $parLeft, true);
        $sect->emptyParagraph($times12, $parLeft);
        $sect->emptyParagraph($times12, $parLeft);
      }
    }


    // add the rest
    $sect->writeText($this->record->get_value('body'), $times12, $par);

    $rtf->sendRtf();

    return TRUE;
  }

  private function fetchUser ($id) {
    static $_users = array();

    if (!isset($_users[$id])) {
      $dbconn = & $this->page->dbconn;
      $dbconn->query(sprintf("SELECT id, email, firstname, lastname, sex, title, institution, phone, address, zip, place"
                             . " FROM User WHERE id=%d",
                             $id));

      if ($dbconn->next_record()) {
        $_users[$id] = $dbconn->Record;
      }
    }
    return isset($_users[$id]) ? $_users[$id] : NULL;
  }

  private function fetchMessage ($id) {
    static $_messages = array();

    if (!isset($_messages[$id])) {
      $dbconn = & $this->page->dbconn;
      $dbconn->query(sprintf("SELECT id, subject, DATE_FORMAT(published, '%%d.%%m.%%Y') AS published_display, DATE_FORMAT(published, '%%Y%%m') AS yearmonth FROM Message WHERE id=%d", $id));

      if ($dbconn->next_record()) {
        $_messages[$id] = $dbconn->Record;
      }
    }

    return isset($_messages[$id]) ? $_messages[$id] : NULL;
  }

  private function replacePlaceholder ($matches) {
    $ret = '';
    switch ($matches[1]) {
      case 'name_from':
      case 'email_from':
      case 'phone_from':
          $user = $this->fetchUser($this->page->user['id']);
          if (isset($user)) {
            switch ($matches[1]) {
              case 'email_from':
                $ret = $user['email'];
                break;
              case 'phone_from':
                $ret = $user['phone'];
                break;
              default:
                $ret = (!empty($user['firstname']) ? $user['firstname'].' ' : '')
                 . $user['lastname'];
            }
          }
          break;

      case 'salutation_name_de':
          if (isset($this->defaults['to_id'])) {
            $user = $this->fetchUser($this->defaults['to_id']);
            if (isset($user)) {
              $ret = ('F' == $user['sex'] ? 'Sehr geehrte Frau' : 'Sehr geehrter Herr')
                . ' ' . $user['lastname'];
            }
          }
          if (empty($ret))
            $ret = 'Sehr geehrte/r Herr/Frau';
          break;

      case 'reviewer_sex_de':
          if (isset($this->defaults['to_id']))
            $user = $this->fetchUser($this->defaults['to_id']);
          $ret = isset($user) && 'F' == $user['sex']
            ? ' Rezensentin' : 'n Rezensenten';
          break;

      case 'bibinfo':
          if (count($this->publications) > 0) {
            require_once INC_PATH . 'common/biblioservice.inc.php';
            $biblio_client = BiblioService::getInstance();
            foreach ($this->publications as $id) {
              if (intval($id) > 0) {
                $citation = $biblio_client->buildCitation(intval($id));
                if (isset($citation))
                  $ret = (!empty($ret) ? $ret . "\n\n" : '') . $citation;
              }
            }
          }
          break;

      case 'reviewer_info':
          if (isset($this->defaults['to_id']))
            $user = $this->fetchUser($this->defaults['to_id']);
          if (isset($user)) {
            $ret = (!empty($user['firstname']) ? $user['firstname'].' ' : '')
             . $user['lastname'];
            if (!empty($user['institution']))
              $ret .= ', ' . $user['institution'];
          }
          break;

      /*
      case 'review_url':
          if (isset($this->defaults['message_id'])) {
            $message = $this->fetchMessage($this->defaults['message_id']);
            $issue = isset($message) ? $message['yearmonth'] : 0;
            $ret = sprintf('http://www.kritikon.de/issue/%d/%d',
                           $issue,
                           $this->defaults['message_id']);
          }
          break;
      */
      case 'review_date':
          if (isset($this->defaults['message_id'])) {
            $message = $this->fetchMessage($this->defaults['message_id']);
            if (!empty($message['published_display'])) {
              $ret = ' am ' . $message['published_display'];
            }
          }
          break;

      default:
          if (array_key_exists($matches[1], $_GET)) {
            $ret = $_GET[$matches[1]];
          }
          break;
    }

    return $ret;
  }

  function buildRecord ($name = '') {
    global $COUNTRIES_FEATURED;

    $record = parent::buildRecord($name);

    if (!isset($record))
      return;

    $record->add_fields(array(
      new Field(array('name'=>'id', 'type'=>'hidden', 'datatype'=>'int', 'primarykey'=>1)),
      new Field(array('name'=>'sent', 'type'=>'hidden', 'datatype'=>'function', 'null' => 1, 'noupdate' => TRUE)),
      new Field(array('name'=>'created', 'type'=>'hidden', 'datatype'=>'function', 'value'=>'NOW()', 'noupdate' => TRUE)),
      new Field(array('name'=>'created_by', 'type'=>'hidden', 'datatype'=>'int', 'value' => $this->page->user['id'], 'null'=>1, 'noupdate' => TRUE)),
      new Field(array('name'=>'changed', 'type'=>'hidden', 'datatype'=>'function', 'value'=>'NOW()')),
      new Field(array('name'=>'changed_by', 'type'=>'hidden', 'datatype'=>'int', 'value' => $this->page->user['id'], 'null'=>1)),
      new Field(array('name'=>'from_email', 'type'=>'email', 'datatype'=>'char', 'default' => array_key_exists('from_email', $this->defaults) ? $this->defaults['from_email'] : '')),
      new Field(array('name'=>'from_id', 'type'=>'hidden', 'datatype'=>'int', 'value' => $this->page->user['id'], 'null' => TRUE)),
      new Field(array('name'=>'to_email', 'type'=>'email', 'datatype'=>'char', 'default' => array_key_exists('to_email', $this->defaults) ? $this->defaults['to_email'] : '')),
      new Field(array('name'=>'to_id', 'type'=>'hidden', 'datatype'=>'int', 'default' => array_key_exists('to_id', $this->defaults) ? $this->defaults['to_id'] : '', 'null' => TRUE)),
      new Field(array('name'=>'message_id', 'type'=>'hidden', 'datatype'=>'int', 'default' => array_key_exists('message_id', $this->defaults) ? $this->defaults['message_id'] : '', 'null' => TRUE)),
      new Field(array('name'=>'type', 'type'=>'hidden', 'datatype'=>'int', 'default' => array_key_exists('type', $this->defaults) ? $this->defaults['type'] : 0, 'null' => TRUE, 'noupdate' => TRUE)),
      new Field(array('name'=>'flags', 'type'=>'checkbox', 'datatype'=>'bitmap', 'null' => TRUE,
                      'default'=> array_key_exists('type', $this->defaults) && $this->defaults['type'] == self::$TYPE_MAP['reviewer_sent'] ? 0x02 : 0, 'labels'=> array('Send Bcc to From', 'Attach review guidelines'))),

      new Field(array('name'=>'subject', 'type'=>'text', 'size'=>40, 'datatype'=>'char', 'maxlength'=>80, 'default' => array_key_exists('subject', $this->defaults) ? $this->defaults['subject'] : '')),
      new Field(array('name'=>'body', 'type'=>'textarea', 'datatype'=>'char', 'default' => array_key_exists('body', $this->defaults) ? $this->defaults['body'] : '', 'cols'=>65, 'rows'=>20)),
    ));

    return $record;
  }

  function getEditRows () {
    if (isset($this->form)) {
      $show_mask = 0x01;
      if (($this->form->get_value('type') == NULL ? $this->defaults['type'] : $this->form->get_value('type'))
            == self::$TYPE_MAP['reviewer_sent'])
        $show_mask |= 0x02;

      $flags = $this->form->field('flags');
      $flags_value = TABLEMANAGER_VIEW == $this->step
                    ? (($flags->value() & 0x01) != 0 ? $this->form->get_value('from_email') : '')
                    : $flags->show($show_mask);
    }
    else {
      $flags_value = ($this->record->get_value('flags') & 0x01) != 0
        ? $this->record->get_value('from_email') : '';
    }

    $rows = array(
      'id' => FALSE, 'status' => FALSE, 'message_id' => FALSE, 'type' => FALSE, // hidden fields

      'from_id' => FALSE, 'from_email' => array('label' => 'From'),
      'to_id' => FALSE, 'to_email' => array('label' => 'To'),
    );

    if (!empty($flags_value))
      $rows['flags'] = array('label' => 'Options', 'value' => $flags_value);

    $rows = $rows + array(
      'subject' => array('label' => 'Subject'),
      'body' => array('label' => 'Body'),

      '<hr noshade="noshade" />',

      isset($this->form) ? $this->form->show_submit(ucfirst(tr('preview'))) : 'FALSE'
    );

    return $rows;
  }

  function getViewFormats () {
    // return array('body' => array('format' => 'p'));
  }

  function buildEditButton () {
    return sprintf(' <span class="regular">[<a href="%s">edit</a>]</span>',
                   htmlspecialchars($this->page->buildLink(array('pn' => $this->page->name, $this->workflow->name(TABLEMANAGER_EDIT) => $this->id))));
  }

  function buildViewRows () {
    $rows = $this->getEditRows();
    if (isset($rows['title']))
      unset($rows['title']);

    $formats = $this->getViewFormats();

    $view_rows = array();

    foreach ($rows as $key => $descr)
      if ($descr !== FALSE && gettype($key) == 'string') {
        if (isset($formats[$key]))
          $descr = array_merge($descr, $formats[$key]);
        $view_rows[$key] = $descr;
      }

    return $view_rows;
  }

  function renderView ($record, $rows) {
    if (array_key_exists('export', $_POST)) {
      if ($this->exportRtf()) {
        exit;
      }
    }


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
              $field_value = 'p' == $row_descr['format']
                ? $this->formatParagraphs($field_value) : $this->formatText($field_value);
              $value .= (!empty($value) ? ' ' : '').$field_value;
            }
          }
          else if (isset($row_descr['value']))
            $value = $row_descr['value'];
          else {
            $field_value = $record->get_value($key);
            $value = isset($row_descr['format']) && 'p' == $row_descr['format']
                ? $this->formatParagraphs($field_value) : $this->formatText($field_value);
          }

          $fields[] = array($label, $value);
        }
      }
    }

    if (0 != (0x02 & $record->get_value('flags'))) {
      $fields[] = array('Attachment', sprintf('<a href="%sdata/formale_hinweise_artikel.pdf">formale_hinweise_artikel.pdf</a>',
                                              BASE_PATH));
    }

    if (count($fields) > 0)
      $ret .= $this->buildContentLineMultiple($fields);

    return $ret;
  }

  private function sendMessage () {
    require_once INC_PATH . 'common/MailMessage.php';

    // build the message
    $mail = new MailMessage($this->record->get_value('subject'));
    $mail->attachPlain($this->record->get_value('body'));
    $mail->attachHtml($this->formatParagraphs($this->record->get_value('body')));

    if (0 != (0x02 & $this->record->get_value('flags'))
        && file_exists($fname_full = BASE_FILEPATH . 'data/formale_hinweise_artikel.pdf'))
    {
      $attachment = Swift_Attachment::newInstance(file_get_contents($fname_full), 'formale_hinweise_artikel.pdf', 'application/pdf');
      $mail->attach($attachment);
    }

    $mail->addTo($this->record->get_value('to_email'));

    $flags = $this->record->get_value('flags');
    if (($flags & 0x01) != 0) {
      $mail->addBcc($this->record->get_value('from_email'));
    }

    $from = $this->record->get_value('from_email');
    $user = $this->fetchUser($this->record->get_value('from_id'));
    if (isset($user)) {
      $from = array($from => (!empty($user['firstname']) ? $user['firstname'] . ' ' : '')
                    . $user['lastname']);
    }
    $mail->setFrom($from);

    $number_sent = $mail->send();
    if ($number_sent > 0) {
      $dbconn = & $this->page->dbconn;
      $querystr = "UPDATE Communication SET sent=NOW() WHERE id=" . $this->id;
      $dbconn->query($querystr);
      // refetch
      $this->record->fetch($this->id);
      return $this->record->get_value('sent');
    }

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
      $sent = $this->record->get_value('sent');

      if (!isset($sent) && array_key_exists('send_email', $_POST) && !empty($_POST['send_email'])) {
        $sent = $this->sendMessage();
      }

      if (!isset($sent)) {
        $edit = $this->buildEditButton();
      }
      else {
        $edit = '';
      }


      $ret = '<h2>' . $this->formatText($record->get_value('subject')) . ' ' . $edit . '</h2>';

      $actions = sprintf('<form action="%s" method="post"><p>',
                         htmlspecialchars($this->page->buildLink(array('pn' => $this->page->name, 'view' => $this->id))));
      if (!isset($sent)) {
        $actions .= sprintf('<input type="submit" name="send_email" value="%s" />', tr('Send E-Mail'));
      }
      else
        $actions .= 'Sent on ' . $sent;
      $actions .= sprintf(' <input type="submit" name="export" value="%s" /></p></form>',
                          tr('Show Word-file'));

      $ret .= $actions;

      $ret .= $this->renderView($record, $rows);

      if (isset($uploadHandler))
        $ret .= $this->renderUpload($uploadHandler);

    }

    return $ret;
  }

  function buildSearchBar () {
    $ret = sprintf('<form action="%s" method="post">',
                   htmlspecialchars($this->page->buildLink(array('pn' => $this->page->name, 'page_id' => 0))));
    if ($this->cols_listing_count > 0) {
      $ret .= sprintf('<tr><td colspan="%d" nowrap="nowrap"><input type="text" name="search" value="%s" size="40" /><input class="submit" type="submit" value="%s" /></td></tr>',
                      $this->cols_listing_count,
                      $this->htmlSpecialchars($this->search['search']),
                      tr('Search'));
    }

    $ret .= '</form>';

    return $ret;
  } // buildSearchBar

}

$display = new DisplayCommunication($page);
if (FALSE === $display->init()) {
  $page->redirect(array('pn' => ''));
}

$page->setDisplay($display);
