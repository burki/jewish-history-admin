<?php
/*
 * common.inc.php
 *
 * Common stuff for the admin pages
 *
 * (c) 2006-2016 daniel.burckhardt@sur-gmbh.ch
 *
 * Version: 2015-06-17 dbu
 *
 * Changes:
 *
 */

// require_once 'XML/Util.php';
require_once INC_PATH . 'common/classes.inc.php';

function send_mail ($msg) {
  if (!defined('MAIL_SEND') || !MAIL_SEND) {
?>
<pre>
To: <?php echo $msg['to'] ?>&nbsp;
<?php echo $msg['headers'] ?>

Subject: <?php echo $msg['subject'] ?>

<?php echo $msg['body'] ?>
</pre>
  <?php
    return 1;
  }
  return mail($msg['to'], $msg['subject'], $msg['body'], $msg['headers']);
}

function is_associative($array) {
  if (!is_array($array) || empty($array)) {
   return false;
  }

  $keys = array_keys($array);
  return array_keys($keys) !== $keys;
}

function native_to_utf8 ($str) {
  return mb_convert_encoding($str, 'UTF-8', 'Windows-1252');
}

function translit_7bit ($str) {
  $str = preg_replace_callback('/&#([0-9a-fx]+);/mi', 'replace_num_entity', utf8_encode($str));
        // var_dump($str);

  if (TRUE) {
    $str = mb_convert_encoding($str, 'HTML-ENTITIES', 'UTF-8');
    // var_dump($str);
    $str = preg_replace(
       array('/&szlig;/','/&(..)lig;/',
             '/&([aou])uml;/i', '/&euml;/i',
             '/&([aeiou])acute;/i',
             '/&([aeiou])grave;/i'),
       array('ss',"$1","$1".'e',"$1", "$1", "$1"),
       $str);

    return $str;
  }

  $str = iconv('UTF-8', 'us-ascii//TRANSLIT', $str);

  $str = preg_replace('/\\"([aou])/i', "\\1e", $str);
  return preg_replace("/[`'".'\\"]/', '', $str);
}

function replace_num_entity($ord) {
    $ord = $ord[1];
    if (preg_match('/^x([0-9a-f]+)$/i', $ord, $match))
    {
        $ord = hexdec($match[1]);
    }
    else
    {
        $ord = intval($ord);
    }

    $no_bytes = 0;
    $byte = array();

    if ($ord < 128)
    {
        return chr($ord);
    }
    else if ($ord < 2048)
    {
        $no_bytes = 2;
    }
    else if ($ord < 65536)
    {
        $no_bytes = 3;
    }
    else if ($ord < 1114112)
    {
        $no_bytes = 4;
    }
    else
    {
        return;
    }

    switch ($no_bytes)
    {
        case 2:
        {
            $prefix = array(31, 192);
            break;
        }
        case 3:
        {
            $prefix = array(15, 224);
            break;
        }
        case 4:
        {
            $prefix = array(7, 240);
        }
    }

    for ($i = 0; $i < $no_bytes; $i++)
    {
        $byte[$no_bytes - $i - 1] = (($ord & (63 * pow(2, 6 * $i))) / pow(2, 6 * $i)) & 63 | 128;
    }

    $byte[0] = ($byte[0] & $prefix[0]) | $prefix[1];

    $ret = '';
    for ($i = 0; $i < $no_bytes; $i++)
    {
        $ret .= chr($byte[$i]);
    }

    return $ret;
}

function writeStringBIFF8(&$writer, $row, $col, $str, $format = 0) {
    $strlen    = strlen($str);
    $record    = 0x00FD;                   // Record identifier
    $length    = 0x000A;                   // Bytes to follow
    $xf        = $writer->_XF($format);      // The cell format
    $encoding  = 0x1;

    $num_chars = function_exists('mb_strlen') ? mb_strlen($str, 'UTF-16LE') : ($strlen / 2);

    $str_error = 0;

    // Check that row and col are valid and store max and min values
    if ($writer->_checkRowCol($row, $col) == false) {
        return -2;
    }

    // $str = pack('vC', $strlen, $encoding).$str;
    $str = pack('vC', $num_chars, $encoding).$str;

    /* check if string is already present */
    if (!isset($writer->_str_table[$str])) {
        $writer->_str_table[$str] = $writer->_str_unique++;
    }
    $writer->_str_total++;

    $header    = pack('vv',   $record, $length);
    $data      = pack('vvvV', $row, $col, $xf, $writer->_str_table[$str]);
    $writer->_append($header.$data);
    return $str_error;
}

function writeMultibyte (&$sheet, $row, $col, $str, $format = 0) {
  if (preg_match('/&#([0-9a-fx]+);/i', $str)) {
    return writeStringBIFF8($sheet, $row, $col, mb_convert_encoding(preg_replace_callback('/&#([0-9a-fx]+);/mi', 'replace_num_entity', $str), "UTF-16LE", "UTF-8"), $format);
  }
  return $sheet->write($row, $col, $str, $format);
}

function strip_specialchars($txt) {
  $match = array('/’/s', '/[“”]/s', '/—/s');
  $replace = array("'", '"', '-');
  return preg_replace($match, $replace, $txt, -1);
}

function format_mailbody ($txt) {
  $paras = preg_split('/\n\s*\n/', $txt);
  for ($i = 0; $i < count($paras); $i++) {
    $paras[$i] = wordwrap($paras[$i], MAIL_LINELENGTH, "\r\n");
  }
  return strip_specialchars(join("\r\n\r\n", $paras));
}

class AuthorListing
{
  static $status_deleted = -100;
  static $status_list = array(
        0 => 'not subscribed',
        /* -3 => 'outstanding request', */
        1 => 'subscribed',
        /* 2 => 'hold', */
        -5 => 'signed off',
        -1 => 'rejected',
        -100 => 'deleted',
    );
  var $id;
  var $record;

  function __construct ($id = -1) {
    if ($id >= 0) {
      $this->id = $id;
    }
  }

  function query (&$dbconn) {
    if (!isset($this->id)) {
      return FALSE;
    }

    $tables = 'User';
    $fields = array(
        'User.id AS id', 'status', 'status_flags',
        'email', 'firstname', 'lastname', 'title', 'position',
        'UNIX_TIMESTAMP(created) AS created',
        'UNIX_TIMESTAMP(subscribed) AS subscribed',
        'UNIX_TIMESTAMP(unsubscribed) AS unsubscribed',
        'UNIX_TIMESTAMP(hold) AS hold',
        // contact
        'email_work', 'institution', 'address', 'zip', 'place', 'country AS cc', 'phone', 'fax',
        // public
        'url', 'gnd', 'description_de', 'description',
        // personal
        'supervisor', 'areas', 'expectations', 'knownthrough', 'forum',
        // review
        'review', 'review_areas', 'review_suggest',

        // internal
        'comment',
    );

    $where_conditions = array("User.id =".$this->id);

    if (defined('COUNTRIES_FROM_DB') && COUNTRIES_FROM_DB) {
      $tables .= ', Country';
      $fields[] = 'Country.zipcodestyle AS zipcodestyle';
      $where_conditions[] = 'User.country=Country.cc';
    }

    $querystr = "SELECT " . implode(', ', $fields)
              . " FROM $tables"
              . " WHERE " . implode(' AND ', $where_conditions);

    $dbconn->query($querystr);
    if ($dbconn->next_record()) {
      $this->record = $dbconn->Record;
      return TRUE;
    }
    return FALSE;
  }

  function initFromRecord ($record) {
    $this->record = $record;
  }

  function buildPlaceWithZip ($record, $append_country = TRUE) {
    static $country_shortnames = array('UK' => 'UK', 'US' => 'USA');

    $separator = ', ';
    $single = TRUE;

    $zipcode_town = $record['place'];
    if (isset($record['zip'])) {
      $zipcodestyle = isset($record['zipcodestyle']) ? $record['zipcodestyle'] : Countries::zipcodeStyle($record['cc']);
      switch ($zipcodestyle) {
        case 1:
          $zipcode_town .= $separator . $record['zip'];
          break;
        case 2:
          $zipcode_town .= ($single ? ' ' : "\n") . $record['zip'];
          break;
        default:
          $zipcode_town = $record['zip'] . ' ' . $zipcode_town;
      }
    }
    if ($append_country && !empty($record['cc'])) {
      $zipcode_town .= $separator
        . (isset($country_shortnames[$record['cc']]) ? $country_shortnames[$record['cc']] : Countries::name($record['cc']));
    }
    return $zipcode_town;
  }

  function formatUrl ($url, $show_protocol = FALSE) {
    if (!preg_match('/^http(s)?\:/', $url)) {
      $url = 'http://' . $url;
    }

    // split link into protocol and destination, if available...
    $url_parts = preg_split('/\:/', $url, 2);
    if ($show_protocol) {
      $name = $url;
    }
    else {
      $name = count($url_parts) == 1 ? $url_parts[0] : preg_replace('!^/+!', '', $url_parts[1]);
      if (preg_match('!^[^/]+/$!', $name)) {
        // if there is just a '/' after domain, then remove
        $name = preg_replace('!/$!', '', $name);
      }
    }

    return '<a href="' . htmlspecialchars($url) . '" target="_blank">'
          . htmlspecialchars($name) . '</a>';
  }

  function buildSection (&$view, $title) {
    return '<div style="margin-top: 1em; width: 100%; margin-bottom: 0.5em; border-bottom: 1px solid gray;"><span style="color: gray;">'
      . $view->formatText(tr($title)) . '</span></div>';
  }

  function buildEntry (&$view, $entry, $label = '', $format_entry = TRUE) {
    $ret = !empty($label)
           ? '<span class="listingLabel">' . tr($label) . '</span> '
           : '';

    $ret .= ($format_entry ? $view->formatText($entry) : $entry);

    return $ret;
  }

  function buildSubscriptionAction (&$view, $status, $field = 'status') {
    return ''; // no subscription actions

    $actions = array();

    if ('email' == $field) {
      switch ($status) {
        case 1:
        case 2:
          $actions = array('change' => tr('change'));
          break;
      }
    }
    else {
      switch ($status) {
        case -10:
        case 0:
        case -5:
          $actions = array(
            'add' => tr('subscribe')
          );
          break;

        case 1:
          $actions = array(
            'delete' => tr('unsubscribe'),
            'nomail' => tr('suspend'),
          );
          break;

        case 2:
          $actions = array('mail' => tr('reactivate'));
          break;
      }
    }

    $ret = array();
    foreach ($actions as $action => $label) {
      $ret[] = sprintf('[<a href="%s">%s</a>]',
                       htmlspecialchars($view->page->buildLink(array('pn' => 'author', 'listserv' => $this->id, 'action' => $action))), $label);
    }

    return implode(' ', $ret);
  }

  function build (&$view, $mode = 'default') {
    // 'restricted', 'default', 'admin'
    if (!isset($this->record)) {
      $found = $this->query($view->page->dbconn);
      if (!$found) {
        return 'ERROR in query';
      }
    }

// var_dump($this->record);

    $show_edit = 'default' == $mode || 'admin' == $mode;
    $show_merge = 'admin' == $mode;
    $show_delete = 'admin' == $mode && !in_array($this->record['status'], array(1, 2)); // delete only those not subscribed or on hold

    // title
    $edit = $show_edit
      ? ' <span class="regular">'
        . '[<a href="' . htmlspecialchars($view->page->buildLink(array('pn' => 'author', (isset($view->workflow) ? $view->workflow->name(TABLEMANAGER_EDIT) : 'edit') => $this->id)))
        . '">' . tr('edit') . '</a>]</span>'
      : '';

    $merge = '';
    if ($show_merge) {
      // check if there might be someone to merge with
      $dbconn = isset($view->page->dbconn) ? $view->page->dbconn : new DB;
      $querystr = sprintf("SELECT COUNT(*) AS count_candidate FROM User WHERE (email = '%s' OR (lastname LIKE '%s' AND firstname LIKE '%s')) AND id<>%d AND status <> %d",
                          empty($this->record['email']) ? 'DUMMY' : $dbconn->escape_string($this->record['email']),
                          $dbconn->escape_string($this->record['lastname']),
                          $dbconn->escape_string($this->record['firstname']),
                          $this->id, AuthorListing::$status_deleted);
      $dbconn->query($querystr);
      if ($dbconn->next_record() && $dbconn->Record['count_candidate'] > 0) {
        $merge = ' <span class="regular">[<a href="' . htmlspecialchars($view->page->buildLink(array('pn' => 'author', 'merge' => $this->id))) . '">'
               . tr('merge')
               . '</a>]</span>';
      }
    }
    $delete = '';
    if ($show_delete) {
      $url_delete = $view->page->buildLink(array('pn' => $view->page->name, 'delete' => $this->id));
      $delete = sprintf(' <span class="regular">[<a href="javascript:if (confirm(%s)) window.location.href=%s">',
                        "'" . tr('Do you want to delete this record (no undo)?') . "'",
                        "'" . htmlspecialchars($url_delete) . "'")
              . tr('delete')
              . '</a>]</span>';
    }

    $ret = '';
    if ('admin' == $mode) {
      $ret .= '<h2>' . $view->htmlSpecialChars(
              $this->record['firstname'] . ' ' . $this->record['lastname'])
              . (isset($this->record['title']) ? ', '.$this->record['title'] : '')
            . $edit . $merge . $delete
            . '</h2>';

      if (isset($this->record['created']) && $this->record['created'] > 0) {
        $ret .= $this->buildEntry($view, $view->formatTimestamp($this->record['created']), 'Created')
              . '<br />';
      }

      if (isset($this->record['position'])) {
        $ret .= $this->buildEntry($view, $this->record['position'], 'Position');
      }
    }

    // top
    $top = array();

    if (isset($this->record['email'])) {
      $top[] = $this->buildEntry($view, $this->record['email']
                                 . (TRUE || 'admin' == $mode
                                    ? ' ' . $this->buildSubscriptionAction($view, $this->record['status'], 'email') : ''), '<!--Subscription -->E-mail', FALSE);
    }

    if (count($top) > 0) {
      $ret .= '<br />' // $this->buildSection($view, 'Subscription')
            . implode('<br />', $top);
    }

    if (!('admin' == $mode)) {
      $ret .= '<h3>' . tr('Personal Info') . $edit . '</h3>'
            . $view->htmlSpecialChars(
                                      $this->record['firstname'].' '.$this->record['lastname'])
            . (isset($this->record['title']) ? ', '.$this->record['title'] : '');

      if (isset($this->record['position'])) {
        $ret .= '<br />'
              . $this->buildEntry($view, $this->record['position'], 'Position');
      }
    }
    // contact
    $contact = array();
    $institutional_fields = array('email_work' => 'Institutional E-Mail', 'institution' => 'Institution');
    foreach ($institutional_fields as $field => $label) {
      if (!empty($this->record[$field])) {
        $contact[] = $this->buildEntry($view, $this->record[$field], $label);
      }
    }

    // address
    $address = array();
    if (isset($this->record['address'])) {
      $address[] = $this->record['address'];
    }
    $place_with_country = $this->buildPlaceWithZip($this->record, TRUE);
    if (!empty($place_with_country)) {
      $address[] = $place_with_country;
    }
    if (count($address) > 0) {
      $contact[] = $this->buildEntry($view, implode("\n", $address), 'Address');
    }

    if ('admin' == $mode) {
      $contact_fields = array('phone' => 'Phone', 'fax' => 'Fax');
      foreach ($contact_fields as $field => $label) {
        if (!empty($this->record[$field])) {
          $contact[] = $this->buildEntry($view, $this->record[$field], $label);
        }
      }
    }

    if (count($contact) > 0) {
      $ret .= $this->buildSection($view, 'Contact Info')
            . implode('<br />', $contact)
            . '</p>';
    }

    // public info
    $public = array();
    $public_fields = array(
        'url' => 'Homepage',
        'description_de' => 'Public CV (de)',
        'description' => 'Public CV (en)',
        'gnd' => 'GND',
    );
    foreach ($public_fields as $field => $label) {
      if (!empty($this->record[$field])) {
        if ('url' == $field) {
          $value = $this->formatUrl($this->record[$field]);
        }
        else if ('gnd' == $field) {
          $value = $this->formatUrl('http://d-nb.info/gnd/' . $this->record[$field]);
        }
        else {
          $value = $this->record[$field];
        }

        $public[] = $this->buildEntry($view, $value, $label, !in_array($field, array('url', 'gnd')));
      }
    }

    if (count($public) > 0) {
      $ret .= $this->buildSection($view, 'Public Info')
            . implode('<br />', $public);
    }

    // personal info
    $personal = array();

    $personal_fields = array(
        'supervisor' => 'Supervisor',
        'areas' => 'Areas of interest',
    );

    if ('admin' == $mode) {
      $personal_fields = array_merge(
        $personal_fields,
        array(
          'expectations' => 'Expectations',
          'knownthrough' => 'How did you get to know us',
          'forum' => 'Other lists and fora',
          ));
    }

    foreach ($personal_fields as $field => $label) {
      if (!empty($this->record[$field])) {
        $value = 'url' == $field
          ? $this->formatUrl($this->record[$field])
          : $this->record[$field];

        $personal[] = $this->buildEntry($view, $value, $label, 'url' != $field);
      }
    }

    if (count($personal) > 0) {
      $ret .= $this->buildSection($view, 'Personal Info')
            . implode('<br />', $personal);
    }

    // review info
    if ('admin' == $mode) {
      $review = array();
      $review_fields = array(
          'review' => 'Willing to contribute',
          'review_areas' => 'Contribution areas',
          'review_suggest' => 'Article suggestion',
      );

      foreach ($review_fields as $field => $label) {
        if (!empty($this->record[$field])) {
          if ('review' == $field) {
            $value = 'Y' == $this->record[$field] ? tr('yes') : tr('no');
          }
          else {
            $value = $this->record[$field];
          }

          $review[] = $this->buildEntry($view, $value, $label);
        }
      }


      if (isset($this->record['status_flags'])) {
        $status_labels = array();
        foreach (array(
                       'cv' => array('label' => 'CV', 'mask' => 0x1),
                       )
                 as $key => $options)
        {
          if (0 != ($this->record['status_flags'] & $options['mask'])) {
            $status_labels[] = $options['label'] . ' ' . tr('finalized');
          }
        }
        if (!empty($status_labels)) {
          $review[] = $this->buildEntry($view, implode('<br />', $status_labels), '');
        }
      }

      // get all messages involved
      if (count($review) > 0) {
        $ret .= $this->buildSection($view, 'Contributor Info')
              . implode('<br />', $review);
      }
    }
    else {
      global $MAIL_SETTINGS;
      /*
      // just a general text
      $ret .= $this->buildSection($view, 'Review suggestion')
        .tr('If you have a suggestion for a review, please contact us at')
        .' '.'<a href="mailto:'.$MAIL_SETTINGS['assistance'].'">'
        .$MAIL_SETTINGS['assistance'].'</a>.';
        */
    }

    if ('admin' == $mode && !empty($this->record['comment'])) {
      $ret .= $this->buildSection($view, 'Internal notes and comment')
            . $view->formatParagraphs($this->record['comment']);
    }

    return $ret;
  }
}
