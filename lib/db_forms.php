<?php
 /*
  * db_forms.php
  *
  * Requires: phplib-database classes
  * Author  : Daniel Burckhardt, daniel.burckhardt@sur-gmbh.ch
  *
  * (c) 2000-2023
  *
  * Version : 2023-04-20 dbu
  *
  * Changes :
  *
  * 2010-01-09 dbu Start making PHP 5.3 E_STRICT compliant
  * 2009-12-07 dbu Add support for readonly/disabled
  * 2009-11-23 dbu Add support for reserved values in fieldnames
  * 2009-03-11 dbu Added 'url_not_allowed' error message
  * 2008-06-11 dbu Added 'id' field to select-fields
  * 2008-04-20 dbu Add initial support for datatype 'decimal'
  * 2008-05-16 dbu Remove warning about undefined $datetime_style
  * 2008-01-08 dbu Option to use Zend_Validate_EmailAddress
  * 2007-03-27 dbu $args['label_class'] to set a class for <label> around radio/checkbox
  * 2007-03-03 dbu Start adding 'id' fields to input-fields
  * 2007-01-31 dbu Start adding closing-tags to be XHTML-compliant
  * 2007-01-28 dbu Remove warnings in sql_encode
  * 2006-12-05 dbu support for 'incomplete' => 1 in date-field: MM/YYYY or YYYY
  * 2006-11-23 dbu PHP strict checking cleanup
  * 2006-07-12 dbu Add eu to $CCs
  * 2006-07-07 dbu Fixed delete-method in RecordSQL
  * 2006-05-02 dbu german messages
  * 2006-03-11 dbu Support for disabled select options
  * 2006-03-07 dbu Fixed bug in delete
  * 2005-09-24 dbu Key-value pairs for checkbox-labels
  * 2005-06-16 dbu Added 'default' to FormFieldTextarea
  * 2005-03-19 dbu Added passing of style-attribute to FormFieldDate::show()/FormFieldPassword::show()
  * 2005-01-07 dbu Added passing of style-attribute to FormFieldText::show()/FormFieldSelect::show()
  * 2004-12-13 dbu Added passing of attributes like onchange to FormFieldText::show()
  * 2004-10-21 dbu Added <label>-tag around checkboxes
  * 2004-01-15 dbu Add a validate-function to DateTimeParser
  * 2003-11-18 dbu Add a format_iso-function to DateTimeParser
  * 2003-08-14 dbu Small fix for datetime-support in fetch ($hour =''; $min = '')
  * 2003-08-01 dbu Added delete-method to RecordSQL
  * 2003-07-19 dbu Added support for FormFieldSelectMultiple (type=>"select" multiple=>1)
  * 2002-08-03 dbu Added new toplevel-domains: aero:biz:coop:info:museum:name:pro:
  * 2002-05-24 dbu Cosmetic cleanup: don't show prepend=".." in <textarea>
  * 2001-12-14 dbu Added 'century_window' to date/datetime
  * 2001-06-30 dbu Added datetime support for datetime fields
  * 2001-06-16 dbu replaced htmlspecialchars through custom-version
  *                since MySQL stores special chars as &#dddd; (e.g € -> &#8364;)
  * 2001-06-13 dbu configurable dateformat for FormFieldDate/FormFieldDatetime
  * 2001-05-26 dbu Update for datetime-datatype
  * 2001-05-25 dbu Added support for 'int' datatype in FormFieldText
  * 2001-05-25 dbu Added 'noupdate'-parameter
  * 2001-05-09 dbu Incomplete support of $args to Form::show_start()
  * 2001-04-30 dbu Use htmlspecialchars to quote the value in FormFieldText/FormFieldTextArea::show()
  * 2001-04-20 dbu Use 'name' in Form::show_start()
  * 2001-03-31 dbu More quoting issues in FormFieldText::show()
  * 2001-03-27 dbu Added 'alias' to FormField-def
  * 2001-03-24 dbu Small change in FormFieldCheckbox::show()
  * 2001-03-12 dbu Added optional parameters to store()
  * 2001-03-10 dbu Added 'nonempty_required' to FormFieldDate
  * 2001-03-08 dbu Added 'prepend'-parameter to ::show(), set_value()
  * 2001-02-26 dbu Added 'nonempty_required' to FormFieldDatetime
  * 2001-02-09 dbu Added 'default' to FormFieldDatetime
  *                Handle 'nodbfield' in SELECT
  * 2001-02-03 dbu Corrected bug in datetime-parsing
  * 2000-11-01 dbu Changed fetch to handle TIMESTAMP with 0-value
  *                Encode TIMESTAMP as 'YYYY-MM-DD HH:MM' so the size doesn't matter
  * 2000-09-13 dbu Added more error messages
  * 2000-09-11 dbu Added 'date'-datatype.
  * 2000-09-11 dbu Added support for verbose error messages
  * 2000-09-07 dbu Changed default-join in Radiobuttons to ' ';
  * 2000-08-31 dbu Added FormFieldRadio
  * 2000-08-30 dbu Small change in FormFieldCheckbox::show()
  * 2000-08-17 dbu Attempt to make this work with php3
  * 2000-08-16 dbu Change Date to MM/DD/YYYY
  * 2000-08-13 dbu Mail validation
  * 2000-07-21 dbu Date/DateTime-types
  *
  */

  function _UrlValidate ($url, $Level = 1, $Timeout = 1500) {
    $fail = 0;

    /* if (!preg_match('!^[a-z]+://!i', $url))
      $url = 'http://' . $url; */

    if (!validateUrlSyntax(trim($url))) {
      $fail = 1;
    }
    $Level--;

    if (!$fail && $Level > 0) {
      // TODO: do a DNS-check of the domain
    }

    return $fail;
  }

 /************************************************************************
  * http://www.zend.com/codex.php?id=88&single=1
  * This function checks the format of an email address. There are five levels of
  * checking:
  *
  * 1 - Basic format checking. Ensures that:
  *     There is an @ sign with something on the left and something on the right
  *     To the right of the @ sign, there's at least one dot, with something to the left
  *     and right.
  *     To the right of the last dot is either 2 or 3 letters, or the special case "arpa"
  * 2 - The above, plus the letters to the right of the last dot are:
  *     com, net, org, edu, mil, gov, int, arpa or one of the two-letter country codes
  * 3 - The above, plus attempts to check if there is an MX (Mail eXchange) record for the
  *     domain name.
  * 4 - The above, plus attempt to connect to the mail server
  * 5 - The above, plus check to see if there is a response from the mail server. The third
  *     argument to this function is optional, and sets the number of times to loop while
  *     waiting for a response from the mail server. The default is 15000. The actual
  *     waiting time, of course, depends on such things as the speed of your server.
  */
  function _MailValidate ($Addr, $Level, $Timeout = 15000) {
//  Valid Top-Level Domains
    $gTLDs = "com:net:org:edu:gov:mil:int:arpa:aero:asia:biz:cat:coop:info:museum:name:pro:tel:travel:xxx:nyc:";
    $CCs   = "ad:ae:af:ag:ai:al:am:an:ao:aq:ar:as:at:au:aw:ax:az:ba:bb:bd:be:bf:".
             "bg:bh:bi:bj:bm:bn:bo:br:bs:bt:bv:bw:by:bz:ca:cc:cd:cf:cg:ch:ci:".
             "ck:cl:cm:cn:co:cr:cs:cu:cv:cx:cy:cz:de:dj:dk:dm:do:dz:ec:ee:eg:".
             "eh:er:es:et:eu:fi:fj:fk:fm:fo:fr:fx:ga:gb:gd:ge:gf:gg:gh:gi:gl:gm:gn:".
             "gp:gq:gr:gs:gt:gu:gw:gy:hk:hm:hn:hr:ht:hu:id:ie:il:im:in:io:iq:ir:".
             "is:it:je:jm:jo:jp:ke:kg:kh:ki:km:kn:kp:kr:kw:ky:kz:la:lb:lc:li:lk:".
             "lr:ls:lt:lu:lv:ly:ma:mc:md:me:mg:mh:mk:ml:mm:mn:mo:mp:mq:mr:ms:mt:".
             "mu:mv:mw:mx:my:mz:na:nc:ne:nf:ng:ni:nl:no:np:nr:nt:nu:nz:om:pa:".
             "pe:pf:pg:ph:pk:pl:pm:pn:pr:ps:pt:pw:py:qa:re:ro:rs:ru:rw:sa:sb:sc:sd:".
             "se:sg:sh:si:sj:sk:sl:sm:sn:so:sr:st:su:sv:sy:sz:tc:td:tf:tg:th:".
             "tj:tk:tl:tm:tn:to:tp:tr:tt:tv:tw:tz:ua:ug:uk:um:us:uy:uz:va:vc:ve:".
             "vg:vi:vn:vu:wf:ws:ye:yt:yu:za:zm:zr:zw:";

//  The countries can have their own 'TLDs', e.g. mydomain.com.au
    $cTLDs = "com:net:org:edu:gov:mil:co:ne:or:ed:go:mi:";

    $fail = 0;

//  Shift the address to lowercase to simplify checking
    $Addr = strtolower($Addr);

//  Split the Address into user and domain parts
    $UD = explode("@", $Addr);
    if (count($UD) != 2) $fail = 1;

//  Split the domain part into its Levels
    if (count($UD) >= 2) {
      $Levels = explode(".", $UD[1]); $sLevels = count($Levels);
      if ($sLevels < 2)
        $fail = 1;
      else {


      //  Get the TLD, strip off trailing ] } ) > and check the length
          $tld = $Levels[$sLevels-1];
          $tld = preg_replace("/[>)}]$|]$/", "", $tld);
          if (strlen($tld) < 2) $fail = 1;
      }
    }

    $Level--;

//  If the string after the last dot isn't in the generic TLDs or country codes, it's invalid.
    if ($Level && !$fail) {
      $Level--;
      if (!preg_match('/' . preg_quote($tld, '/') . ":/", $gTLDs)
          && !preg_match('/' . preg_quote($tld, '/') . ":/", $CCs)) $fail = 2;
    }

//  If it's a country code, check for a country TLD; add on the domain name.
    if ($Level && !$fail) {
      $cd = $sLevels - 2; $domain = $Levels[$cd] . "." . $tld;
      if (preg_match('/' . preg_quote($Levels[$cd], '/') . ':/', $cTLDs)) { $cd--; $domain = $Levels[$cd].".".$domain; }
    }

//  See if there's an MX record for the domain
    if ($Level && !$fail) {
      $Level--;
      if (!getmxrr($domain, $mxhosts, $weight)) $fail = 3;
    }

//  Attempt to connect to port 25 on an MX host
    if ($Level && !$fail) {
      $Level--;
      while (!$sh && list($nul, $mxhost) = each($mxhosts))
        $sh = fsockopen($mxhost, 25);
      if (!$sh) $fail = 4;
    }

//  See if anyone answers
    if ($Level && !$fail) {
      $Level--;
      set_socket_blocking($sh, false);
      $out = ""; $t = 0;
      while ($t++ < $Timeout && !$out)
        $out = fgets($sh, 256);
      if (!preg_match("/^220/", $out)) $fail = 5;
    }

    if (isset($sh) && $sh) fclose($sh);

    return $fail;
  } // _MailValidate

  class DateTimeParser
  {
    var $datestyle = 'MM/DD/YYYY';

    function __construct ($args = '') {
      if (is_string($args) && !empty($args)) {
        $this->datestyle = $args;
      }
    }

    function parse ($datetimestring, $century_window = 50, $incomplete = false) {
      $datetimestring = trim($datetimestring); // get rid of spaces first
      // TODO: add support for time only

      $date = $datetimestring;
      if (isset($date)) {
        $matched = 0;
        $daypos = preg_match('/^MM/i', $this->datestyle) ? 2 : 1;
        $monthpos = $daypos == 2 ? 1 : 2;
        $year_handle_short = TRUE;

        if (preg_match("/^[[:blank:]]*([0-9]+)[[:blank:]]*[\/\.][[:blank:]]*([0-9]+)[[:blank:]]*[\/\.][[:blank:]]*([0-9]+)([[:blank:]]+.+)$/",
                       $date, $matches)) {
          $matched = 1;
          $day = intval($matches[$daypos]);
          $month = intval($matches[$monthpos]);
          $year = $matches[3];
          if ('0000' === $year) {
            $year_handle_short = false;
          }

          $year = intval($year);

          $timestr = $matches[4];
        }
        else if (preg_match('/^[[:blank:]]*([0-9]+)[[:blank:]]*[\/\.][[:blank:]]*([0-9]+)[[:blank:]]*[\/\.][[:blank:]]*([0-9]+)[[:blank:]]*$/', $date, $matches)) {
          $matched = 1;
          $day = intval($matches[$daypos]);
          $month = intval($matches[$monthpos]);
          $year = $matches[3];
          if ('0000' === $year) {
            $year_handle_short = false;
          }

          $year = intval($year);
        }
        else if ($incomplete) {
          // MM.YYYY or YYYY
          if (preg_match('/^[[:blank:]]*([0-9]+)[[:blank:]]*[\/\.][[:blank:]]*([0-9]+)[[:blank:]]*$/', $date, $matches)) {
            $matched = 1;
            $month = intval($matches[1]);
            $year = $matches[2];
            if ('0000' === $year) {
              $year_handle_short = false;
            }

            $year = intval($year);
          }

          if (preg_match('/^[[:blank:]]*([0-9]+)[[:blank:]]*$/', $date, $matches)) {
            $matched = 1;
            $day = null;
            $month = null;
            $year = $matches[1];
            if ('0000' === $year) {
              $year_handle_short = false;
            }

            $year = intval($year);
          }
        }

        if ($matched) {
          if ($year_handle_short && $year < 100) {
            $now = getdate();
            $current_century = $now['year'] - $now['year'] % 100;
            if (is_int($century_window)) {
              // if $century_window is a two-digit, set it so it will never be in the future
              if ($century_window < 100) {
                $century_window += $current_century;
                if ($century_window > $now['year']) {
                  $century_window -= 100;
                }
              }

              // now set the year so it will be greater or equal $century_window
              // 1970: 71 -> 1971, 69 -> 2069
              // 2010: 11 -> 2011,  9 -> 2109
              $century_window_century = $century_window - $century_window % 100;
              $year += ($year < $century_window % 100 ? $century_window_century + 100 : $century_window_century);
            }
            else if (is_string($century_window)) {
              $year += $current_century;
              if ($century_window == 'future' && $year < $now['year']) {
                $year += 100;
              }
              else if ($century_window == 'past' && $year > $now['year']) {
                $year -= 100;
              }
            }
          }

          $hour = 0; $min = 0;

          if (!empty($timestr)) {
            # die($matches[4]);
            if (preg_match('/^[[:blank:]]*([0-9]+)[[:blank:]]*[\.\:][[:blank:]]*([0-9]*)/',
                           $timestr, $timematches)) {
              $hour   = intval($timematches[1]);
              $min = intval($timematches[2]);
            }
          }

          return [
            'year' => $year, 'month' => $month, 'day' => isset($day) ? $day : null,
            'hour' => $hour, 'min' => $min,
          ];
        }
      }
    }  // parse

    function format_iso ($datetime) {
      return sprintf('%04d-%02d-%02d %02d:%02d:%02d',
                     $datetime['year'], $datetime['month'], $datetime['day'],
                     $datetime['hour'], $datetime['min'],
                     isset($datetime ['sec']) ? $datetime['sec'] : 0);
    }

    function validate ($date, $incomplete = false) {
      $valid = true;
      $invalid = '';

      if (isset($date)) {
        // check the date
        $year  = intval($date['year']);
        $month = intval($date['month']);
        if ($month < 1 || $month > 12) {
          $invalid = 'datetime_invalid_month';
          $valid = false;
        }

        if ($valid) {
          $day = intval($date['day']);
          if ($day >= 1 && $day <= 31) {
            if ($month == 2) {
              if ($day > 29
                 || ($day == 29
                     && !($year % 4 == 0 && ($year % 100 != 0 || $year % 400 == 0))
                     )
              ) {
                 $valid = false;
              }
            }
            else if ($day == 31
              && ($month == 4 || $month == 6 || $month == 9 || $month == 11))
              $valid = false;
          }
          else
            $valid = false;

          if (!$valid) {
            $invalid = 'datetime_invalid_day';
          }
        }
        if ($valid) {
          $hour = intval($date['hour']);
          $min  = intval($date['min']);
          if ($hour < 0 || $hour > 23 || $min < 0 || $min > 59 ) {
            $valid = false; $invalid = 'datetime_invalid_time';
          }
        }
      }
      else {
        $valid = false;
        $invalid = 'datetime_invalid_format';
      }

      return $valid;
    } // validate
  } // DateTimeParser

  class Field
  {
    var $field;

    function __construct ($field = '') {
      if (is_array($field)) {
        $this->field = $field;
      }
    }

    function sql_value ($datatype, $value) {
      switch ($datatype) {
        case 'date':
          $encoded = empty($value)
                   ? 'NULL'
                   : sprintf("'%04d-%02d-%02d'",
                             $value['year'], $value['month'], $value['day']);
          break;

        case 'datetime':
          $encoded = empty($value)
                   ? 'NULL'
                   : sprintf("'%04d-%02d-%02d %02d:%02d:%02d'",
                             isset($value['year']) ? $value['year'] : 0,
                             isset($value['month']) ? $value['month'] : 0,
                             isset($value['day']) ? $value['day'] : 0,
                             isset($value['hour']) ? $value['hour'] : 0,
                             isset($value['min']) ? $value['min'] : 0,
                             isset($value['sec']) ? $value['sec'] : 0);
          break;

        case 'timestamp14':
        case 'timestamp':
          $encoded = isset($value) && '' !== $value
                   ? sprintf("'%04d-%02d-%02d %02d:%02d:%02d'",
                             isset($value['year']) ? $value['year'] : 0,
                             isset($value['month']) ? $value['month'] : 0,
                             isset($value['day']) ? $value['day'] : 0,
                             isset($value['hour']) ? $value['hour'] : 0,
                             isset($value['min']) ? $value['min'] : 0,
                             isset($value['sec']) ? $value['sec'] : 0)
                   : 0; // null - which would be the clean way, may lead to current-date due to timestamp auto-update
          break;

        case 'char':
        case 'varchar':
          if (is_array($value)) {
            $value = implode(', ', $value);
          }

          $encoded = isset($value) && (chop($value) != '')
            ? "'" . addslashes($value) . "'" : 'NULL';
          break;

        case 'bitmap' :
          if (is_array($value)) {
            $newval = 0;
            for ($i = 0; $i < count($value); $i++) {
              $newval += $value[$i];
            }
            $value = $newval;
          }
          if (isset($value)) {
            $encoded = $value;
          }
          else {
            $encoded = 'NULL';
          }
          break;
        default:
          $encoded = isset($value) && (chop($value) != '')
            ? $value : 'NULL';
      }

      return $encoded;
    }

    function value_internal () {
      return $this->get('value_internal');
    }

    function value () {
      return $this->get('value');
    }

    function name () {
      return $this->get('name');
    }

    function get ($key) {
      // echo 'Field::get ' . $this->field['name'] . ' ' . $key . '->' . $this->field[$key] . '<br>';
      if (array_key_exists($key, $this->field)) {
        return $this->field[$key];
      }
    }

    function set ($key, $val) {
      // echo "Field::set $key -> $val: " . '<br>';
      $this->field[$key] = $val;
    }
  }

 /*
  * Record: contains attributes and a set of (named) Fields
  *
  */
  class Record
  {
    var $data, $fields;

    function get ($key) {
      return $data[$key];
    }

    function set ($key, $val) {
      return $data[$key] = $val;
    }

    function _set_fieldvalue (&$field, $key, $val) {
      if (isset($field)) {
        // echo '_set_fieldvalue: ' . $field->name() . ' ' . $key . '->' . $val . '<br>';
        $field->set($key, $val);
      }
    }

    function set_fieldvalue ($name, $key, $val) {
      // php3 doesn't like $this->fields[$name]->set($key, $val);
      return $this->_set_fieldvalue($this->fields[$name], $key, $val);
    }

    function set_value ($name, $val) {
      // php3 doesn't like $this->fields[$name]->set('value', $val);
      $this->_set_fieldvalue($this->fields[$name], 'value', $val);
    }

    function set_field ($name, $field) {
      // set_field($name, $field in Field) -> $field
      $field->set('name', $name);

      return $this->fields[$name] = $field;
    }

    function get_value ($name) {
      $fields = $this->fields;

      if (isset($fields[$name])) {
        return $fields[$name]->value();
      }
      // TODO: Emit warning
    }

    function add_fields ($fields) {
      if (is_array($fields)) {
        for ($i = 0; $i < count($fields); $i++) {
          // echo $i . ':' . $fields[$i]->name() . '<br />';
          $this->set_field($fields[$i]->name(), $fields[$i]);
          // $field = $this->get_field($fields[$i]->name());
        }
      }
    }

    function remove_field ($name) {
      if (isset($this->fields[$name])) {
        unset($this->fields[$name]);

        return true;
      }

      return false;
    }

    function get_fieldnames () {
      $names = [];

      foreach ($this->fields as $name => $thisfield) {
        $names[] = $name;
      }

      return $names;
    }

    function get_field ($name) {
      if (array_key_exists($name, $this->fields)) {
        return $this->fields[$name];
      }
    }
  }

  class RecordSQL
  extends Record
  {
    private static $RESERVED = [ 'usage', 'condition', 'lead' ];

    var $params;

    function __construct ($params = '') {
      $this->params = $params;
    }

    function sql_decode ($type, $value) {
      switch ($type) {
        default:
          $encoded = $value;
      }

      return $encoded;
    }

    function fetch ($args, $datetime_style = '') {
      if (empty($datetime_style)) {
        $datetime_style = 'MM/DD/YYYY';
      }

      $dbconn = $this->params['dbconn'];
      if (!isset($dbconn)) {
        return -1;
      }

      $orderby = '';
      if (is_array($args)) {
        if (isset($args['where'])) {
          $where = ' WHERE ' . $args['where'];
        }
        else {
          die('RecordSQL->fetch([]) has no where-clause');
        }

        if (isset($args['orderby'])) {
          $orderby = ' ORDER BY ' . $args['orderby'];
        }
      }

      if (is_array($args) && isset($args['fields'])) {
        $fields = $args['fields'];
      }

      foreach ($this->fields as $name => $thisfield) {
        if (!isset($thisfield)) {
          $skip = true;
        }
        else if (isset($fields)) {
          $skip = !in_array($name, $fields);
        }
        else {
          $nodbfield = $thisfield->get('nodbfield');
          $skip = isset($nodbfield) && $nodbfield;
        }

        if (!$skip) {
          $dbfield = $thisfield->get('datafieldname');
          if (!isset($dbfield) || empty($dbfield)) {
            $dbfield = $name;
          }
          $fieldnames[$name] = $dbfield;
          if ($thisfield->get('primarykey') && !isset($where)) {
            $where = " WHERE $name="
                   . $thisfield->sql_value($thisfield->get('datatype'), $args);
          }
        }
      }

      if (!isset($where)) {
        return -2;
      }

      $tables = is_array($args) && isset($args['tables'])
              ? $args['tables'] : $this->params['tables'];
      if (is_array($tables)) {
        $tables = join(', ', $tables);
      }

      if (count($fieldnames) == 0) {
        return -3;
      }

      foreach ($fieldnames as $formfield => $dbfield) {
        if ($formfield != $dbfield) {
          $column = "$dbfield AS $formfield";
        }
        else if (in_array($dbfield, self::$RESERVED)) {
          $column = "`$dbfield`";
        }
        else {
          $column = $dbfield;
        }

        $columns[] = $column;
      }

      $querystr = "SELECT " . join(', ', $columns)
                . " FROM $tables $where $orderby LIMIT 1";
// echo $querystr;
      $dbconn->query($querystr);
      if ($dbconn->next_record()) {
        foreach ($this->fields as $name => $thisfield) {
          if (!isset($fieldnames[$name])) {
            continue;
          }
          $value = $dbconn->Record[$name];
// echo $fieldnames[$name].": $name -> $value <br />";
          switch ($thisfield->get('datatype')) {
            case 'date':
            case 'datetime':
            case 'timestamp':
            case 'timestamp14':
              $hour = $min = '';
              $matched = 0;
              if ($thisfield->get('datatype') == 'date'
                 || $thisfield->get('datatype') == 'datetime'
                 || (($thisfield->get('datatype') == 'timestamp'
                 || $thisfield->get('datatype') == 'timestamp14')
                     && preg_match('/[^0-9]/', $value)) // Warning Mysql 4.1.0: Incompatible change! TIMESTAMP is now returned as a string of type 'YYYY-MM-DD HH:MM:SS'

              ) {
                if (isset($value) && preg_match(
                 '/^([0-9]{4})\-?([0-9]{2})\-?([0-9]{2})/',
                 $value, $matches))
                {
                  $matched = 1;
                  $year = $matches[1]; $month=$matches[2]; $day = $matches[3];
                  if ($thisfield->get('datatype') == 'datetime' || $thisfield->get('datatype') == 'timestamp' || $thisfield->get('datatype') == 'timestamp14') {
                    $datetimeparts = preg_split('/\s+/', $value, 2);
                    if (count($datetimeparts) > 1
                      && preg_match('/^([0-9]{2})\:?([0-9]{2})\:?([0-9]{2})/', $datetimeparts[1], $matches)) {
                      if (intval($matches[1]) != 0 || intval($matches[2]) != 0) {
                        $hour = $matches[1]; $min = $matches[2];
                      }
                    }
                  }
                }
              }
              else {
                if (intval($value) == 0) {
                  $value = '';
                }
                else if (preg_match(
                 '/^([0-9]{4})([0-9]{2})([0-9]{2})([0-9]*)$/',
                 $value, $matches))
                {
                  $matched = 1;
                  $year  = intval($matches[1]);
                  if ($year < 1900) {
                    $year += 1900;
                  }

                  $month = $matches[2];
                  $day   = $matches[3];
                  $hour = '00'; $min = '00';
                  if (!empty($matches[4])) {
                    if (preg_match('/^([0-9]{2})([0-9]{2})/', $matches[4], $timematches)) {
                      $hour = $timematches[1]; $min = $timematches[2];
                    }
                  }
                }
              }

              if ($matched) {
                if (intval($month) != 0 || intval($day) != 0 || intval($year) != 0) {
                  $value = $datetime_style;
                  if ($thisfield->get('incomplete')) {
                    if ($day == 0) {
                      $value = preg_replace('/D+[\.\/]*/i', '', $value);
                    }

                    if ($month == 0) {
                      $value = preg_replace('/M+[\.\/]*/i', '', $value);
                    }
                  }
                  $value = preg_replace(['/(M+)/i', '/(D+)/i', '/(Y+)/i'],
                                        [sprintf("%02d", $month), sprintf("%02d", $day), sprintf("%02d", $year)], $value);
                }
                else {
                  $value = '';
                }

                // format timeparts
                if ($thisfield->get('type') == 'datetime') {
                  if (!empty($hour) || !empty($min)) {
                    $value .= " $hour:$min";
                  }
                }

                $this->set_fieldvalue($name, 'value_internal', [
                  'year' => $year, 'month' => $month, 'day' => $day,
                  'hour' => $hour, 'min' => $min,
                ]);
              }
          }

          $this->set_value($name, $value);
        }

        return 1;
      }

      return 0;
    }

    function insertupdate ($table, $fieldnames, $keyname, $keyvalue) {
      $dbconn = $this->params['dbconn'];
      if (!isset($dbconn)) {
        return -1;
      }

      $update = false;
      if (isset($keyvalue)) {
        $dbconn->query("SELECT COUNT(*) AS count_exists FROM $table WHERE $keyname = $keyvalue");
        $update = $dbconn->next_record() && $dbconn->Record['count_exists'];
      }

      for ($i = 0; $i < count($fieldnames); $i++) {
        $usefield[$fieldnames[$i]] = 1;
      }

      $fields = $values = [];
      foreach ($this->fields as $name => $thisfield) {
        if (!$usefield[$name]) {
          continue;
        }

        if ($update) {
          $noupdate = $thisfield->get('noupdate');
          if (isset($noupdate) && $noupdate > 0) {
            continue;
          }
        }

        $value = $thisfield->value_internal();
        if (!isset($value)) {
          $value = $thisfield->value();
        }

        $fields[] = in_array($name, self::$RESERVED) ? "`$name`" : $name;
        $values[] = $thisfield->sql_value($thisfield->get('datatype'), $value);
      }

      if (count($fields) == 0) {
        return -1;
      }

      if ($update) {
        $querystr = "UPDATE $table SET ";
        for ($i = 0; $i < count($fields); $i++) {
          if ($i != 0) {
            $querystr .= ', ';
          }

          $querystr .= $fields[$i] . '=' . $values[$i];
        }

        $querystr .= " WHERE $keyname=$keyvalue";
      }
      else {
        if (isset($keyvalue)) {
          $fields[] = $keyname;
          $values[] = $keyvalue;
        }

        $querystr = "INSERT INTO $table (" . join(', ', $fields). ")"
                  . " VALUES (" . join(', ', $values) . ")";
      }

      $dbconn->query($querystr);

      return 1;
    } // insertupdate

    function store ($args = '') {
      $dbconn = $this->params['dbconn'];

      if (!isset($dbconn)) {
        return -1;
      }

      $update = 0;
      unset($fieldlist);
      if (is_array($args)) {
        $fieldlist = $args['fields'];
        $where = ' WHERE ' . $args['where'];
        $tables = isset($args['tables']) ? $args['tables'] : $this->params['tables'];
      }
      else {
        $tables = $this->params['tables'];
      }

      if (is_array($tables)) {
        $tables = join(', ', $tables);
      }

      if (!empty($where)) {
        // find out if we INSERT/UPDATE
        $querystr = "SELECT COUNT(*) AS countaffected FROM $tables $where";
        $dbconn->query($querystr);
        $update = $dbconn->next_record() && $dbconn->Record['countaffected'] > 0;
      }

      $fields = []; $values = [];

      foreach ($this->fields as $name => $thisfield) {
        if (isset($thisfield)) {
          if (isset($fieldlist)) {
            $nodbfield = !in_array($name, $fieldlist);
          }
          else {
            $nodbfield = $thisfield->get('nodbfield');
            if ($update && (!isset($nodbfield) || !$nodbfield)) {
              $noupdate = $thisfield->get('noupdate');
              if (isset($noupdate) && $noupdate > 0) {
                $nodbfield = 1;
              }
            }
          }

          if (!isset($nodbfield) || !$nodbfield) {
            if ($thisfield->get('primarykey')) {
              $primarykey = $name;
              $value = $thisfield->value();
              if (!$update) {
                $update = isset($value) && $value != '' ? 1 : 0;
                if ($update) {
                  $where = " WHERE $name=" . $thisfield->sql_value($thisfield->get('datatype'), $value);
                }
              }
            }
            else {
              $value = $thisfield->value_internal();
              if (!isset($value)) {
                $value = $thisfield->value();
              }

              $dbfield = $thisfield->get('datafieldname');
              $column = !empty($dbfield) ? $dbfield : $name;
              if (in_array($column, self::$RESERVED)) {
                $column = "`$column`";
              }

              $fields[] = $column;
              $values[] = $thisfield->sql_value($thisfield->get('datatype'), $value);
            }
          }
        }
      }

      if ($update) {
        $querystr = "UPDATE $tables SET ";
        for ($i = 0; $i < count($fields); $i++) {
          if ($i != 0) {
            $querystr .= ', ';
          }

          $querystr .= $fields[$i] . '=' . $values[$i];
        }

        $querystr .= $where;

        if (is_array($args) && !empty($args['orderby'])) {
          // so only the first record gets changed
          $querystr .= ' ORDER BY ' . $args['orderby'] . ' LIMIT 1';
        }
      }
      else {
        $querystr = "INSERT INTO $tables (" . join(', ', $fields) . ")"
                  . " VALUES (" . join(', ', $values) . ")";
      }

      if (count($fields) == 0) {
        return -1;
      }

// var_dump($querystr);
      $dbconn->query($querystr);
      if ($update) {
        return $dbconn->affected_rows() >= 0 ? 1 : -2;
      }
      else {
        // echo $querystr;
        if ($dbconn->affected_rows() >= 0) {
          if (isset($primarykey)) {
            $this->set_value($primarykey, $dbconn->last_insert_id());
          }
          return 1;
        }
        return -3;
      }
    }

    function delete ($args) {
      $dbconn = $this->params['dbconn'];
      if (!isset($dbconn)) {
        return -1;
      }

      if (is_array($args)) {
        if (isset($args['where'])) {
          $where = ' WHERE ' . $args['where'];
        }
        else {
          die ('RecordSQL->fetch([]) has no where-clause');
        }

        $tables = isset($args['tables'])
          ? $args['tables']
          : $this->params['tables'];
      }
      else {
        $tables = $this->params['tables'];
      }

      if (is_array($tables)) {
        $tables = join(', ', $tables);
      }

      foreach ($this->fields as $name => $thisfield) {
        if ($thisfield->get('primarykey') && !isset($where)) {
          $where = " $name=" . $thisfield->sql_value($thisfield->get('datatype'), $args);
        }
      }

      if (!isset($where)) {
        return -2;
      }

      $querystr = "DELETE FROM $tables WHERE $where";
      $dbconn->query($querystr);
      return $dbconn->affected_rows() >= 0 ? 1 : -3;
    }
  }

 /*
  * TODO: One Form can contain multiple records, Record can be array of Records
  */
  class Form
  {
    var $record;

    function __construct ($record = '') {
      die('Form is an abstract class. Instantiate a derived class (e.g FormHTML instead)');
    }

    // accessor function
    function set_record ($record, $name = '') {
      return $this->record = $record;
    }

    function get_record ($name = '') {
      return $this->record;
    }

    function field ($name) {
      // TODO: allow hierarchic names like record1.id
      // TODO: return a reference to $this->fields[$name] (PHP4)

      if (gettype($this->record) == 'NULL') {
        return;
      }

      return $this->record->get_field($name);
    }
  }

  /* FormField wraps around Field */
  class FormField
  extends Field
  {
    var $form;
    var $name;
    var $field;
    var $_trans;

    function __construct ($form, $name) {
      if (is_object($form)) {
        $this->form = $form;
      }

      $this->name = $name;
    }

    // utility functions
    function not_empty ($val) {
      return isset($val) && trim($val) != '';
    }

    /**  '&' (ampersand) becomes '&amp;'
      '"' (double quote) becomes '&quot;' when ENT_NOQUOTES is not set.
      '<' (less than) becomes '&lt;'
      '>' (greater than) becomes '&gt;' */
    function htmlspecialchars ($txt) {
      if (is_null($txt)) {
        return $txt;
      }

      $match = [ '/&(?!\#x?\d+;)/s', '/</s', '/>/s', '/"/s' ];
      $replace = [ '&amp;', '&lt;', '&gt;', '&quot;' ];

      return preg_replace($match, $replace, $txt, -1);
    }

    // accessor functions
    function name () {
      return $this->name;
    }

    function value ($val = '') {
      $value = $this->get('value');

      return $value;
    }

    function get ($key) {
      if (isset($this->field[$key])) {
        return $this->field[$key];
      }

      // pass the call on to the appropriate record-field
      /* // dbu 2000-08-17 php3 didn't like this code
      if (isset($this->form) && isset($this->form->record->fields[$this->name])) {
        return $this->form->record->fields[$this->name]->get($key);
      }
      */

      // try this ugly bit of code instead
      if (isset($this->form)) {
        $form = $this->form;
        $record = $form->record;
        if (isset($record)) {
          $field = $record->fields[$this->name];

          if (isset($field)) {
            return $field->get($key);
          }
        }
      }
    }

    function set ($key, $val) {
      return $this->field[$key] = $val;
    }

    function show () {
    }

    function validate (&$invalid) {
      $valid = 1;
      $non_empty = $this->not_empty($this->value());

      if ($this->get('datatype') == 'int') {
        $val = $this->value();
        if (!is_null($val) && !preg_match('/^\s*(-\s*\d+|\d*)\s*$/', $val)) {
          $invalid[$this->name] = 'int_invalid';

          return 0;
        }
      }
      else if ($this->get('datatype') == 'decimal') {
        if ($non_empty && !preg_match('/^\s*([+-]?((([0-9]+\.?)|([0-9]*\.[0-9]+))))\s*$/', $this->value())) {
          $invalid[$this->name] = 'decimal_invalid';

          return 0;
        }
      }

      $primarykey = $this->get('primarykey');
      $null = $this->get('null');

      $null = isset($primarykey) ? $primarykey : (isset($null) ? $null : 0);

      if (!$null) {
        $valid = $non_empty;
        if (!$valid) {
          $invalid[$this->name] = 'nonempty_required';
        }
      }

      return $valid;
    }
  }

  class FormFieldText
  extends FormField
  {
    var $DEFAULT_SIZE = 32;
    var $DEFAULT_SIZE_INT = 7;
    var $DEFAULT_SIZE_DECIMAL = 7; // TODO: make this dependent of field settings

    function __construct ($form, $name) {
      $this->form = $form;
      $this->name = $name;
    }

    function show ($params = '') {
      $prepend = is_array($params) && isset($params['prepend']) ? $params['prepend'] : '';

      $datatype = $this->get('datatype');
      $attrnames = [ 'id', 'class', 'size', 'maxlength', 'disabled', 'readonly' ];
      $attrs = [];
      for ($i = 0; $i < count($attrnames); $i++) {
        $val = $this->get($attrnames[$i]);
        if (in_array($attrnames[$i], [ 'disabled', 'readonly' ]) && isset($val)) {
          $val = $val ? $attrnames[$i] : null;
        }

        if (!isset($val) && $attrnames[$i] == 'size') {
          switch ($datatype) {
            case 'int':
                $val = $this->DEFAULT_SIZE_INT;
                break;

            case 'decimal':
                $val = $this->DEFAULT_SIZE_DECIMAL;
                break;

            default:
                $val = $this->DEFAULT_SIZE;
          }
        }

        if (isset($val)) {
          $attrs[] = $attrnames[$i] . '="' . $val . '"';
        }
      }

      // add additional attrs from params
      if (is_array($params)) {
        // var_dump($params);
        $attrnames = array_keys($params);
        for ($i = 0; $i < count($attrnames); $i++) {
          switch (strtolower($attrnames[$i])) {
            case 'style':
            case 'onchange':
            case 'tabindex':
            case 'accesskey':
            case 'onfocus':
            case 'onblur':
            case 'onchange':
            case 'onkeypress':
            case 'id':
            case 'class':
            case 'autocomplete':
              $attrs[] = $attrnames[$i] . '="' . $this->htmlspecialchars($params[$attrnames[$i]]) . '"';
              break;
          }
        }
      }

      $current_value = $this->value();
      if (!isset($current_value)) {
        $current_value = $this->get('default');
      }

      $current_value = '"' . $this->htmlspecialchars($current_value) . '"';

      return '<input type="text" name="' . $prepend . $this->name() . '" '
            . 'value=' . $current_value
            . (count($attrs) > 0
               ? ' ' . join(' ', $attrs) : '')
            . ' />';
    }
  }

  class FormFieldTextarea
  extends FormField
  {
    function __construct ($form, $name) {
      $this->form = $form;
      $this->name = $name;
    }

    function show ($params = '') {
      $prepend = is_array($params) && isset($params['prepend']) ? $params['prepend'] : '';
      $defaults = [ 'rows' => 4, 'cols' => 30, 'wrap' => 'virtual' ];
      foreach ([ 'id', 'disabled', 'maxlength', 'readonly' ] as $name) {
        $val = $this->get($name);
        if (in_array($name, [ 'disabled', 'readonly' ]) && isset($val)) {
          $val = $val ? $name : null;
        }
        if (isset($val)) {
          $defaults[$name] = $val;
        }
      }

      if (is_array($params)) {
        foreach ($params as $attrname => $value) {
          if ($attrname != 'prepend') {
            $defaults[$attrname] = $value;
          }
        }
      }

      $attrs = [];
      foreach ($defaults as $attrname => $default) {
        $val = $this->get($attrname);
        $attrs[] = $attrname . '="' . (isset($val) ? $val : $default) . '"';
      }

      $attrnames = [ 'id', 'class' ];
      for ($i = 0; $i < count($attrnames); $i++) {
        $val = $this->get($attrnames[$i]);
        if (isset($val)) {
          $attrs[] = $attrnames[$i] . '="' . $val . '"';
        }
      }

      $current_value = $this->value();
      if (!isset($current_value)) {
        $current_value = $this->get('default');
      }

      return '<textarea name="' . $prepend . $this->name() . '"'
             . (count($attrs) > 0 ? ' '.join(' ', $attrs) : '')
             . '>'
             . $this->htmlspecialchars($current_value)
             . '</textarea>';
    }
  }

  class FormFieldEmail
  extends FormFieldText
  {
    var $VALIDATE_LEVEL = 2;
    var $USE_ZEND_VALIDATOR = false;

    function __construct ($form, $name) {
      $this->form = $form;
      $this->name = $name;
    }

    function validate (&$invalid) {
      $valid = 1;
      $null = $this->get('null');
      $null = isset($null) ? $null : 0;
      if ($this->not_empty($this->value())) {
        if ($this->USE_ZEND_VALIDATOR) {
          $validator = new Zend_Validate_EmailAddress(['allow' => Zend_Validate_Hostname::ALLOW_DNS,
                                                            'mx'    => true]);
          if (!$validator->isValid($this->value())) {
            $valid = 0;
            $invalid[$this->name()] = 'email_nonvalid';
          }
        }
        else if (_MailValidate($this->value(), $this->VALIDATE_LEVEL) != 0) {
          $valid = 0;
          $invalid[$this->name()] = 'email_nonvalid';
        }
      }
      else if (!$null) {
        $valid = 0;
        $invalid[$this->name()] = 'nonempty_required';
      }

      return $valid;
    } // validate
  }

  class FormFieldDatetime
  extends FormFieldText
  {
    var $DEFAULT_SIZE = 16;
    var $datetime_parser;

    function __construct ($form, $name) {
      $this->form = $form;
      $this->name = $name;
    }

    function parse_datetime ($datetime_string, $datetime_style = '', $century_window = 50) {
      if (!isset($datetimeparser[$datetime_style])) {
        $datetimeparser[$datetime_style] = new DateTimeParser($datetime_style);
      }

      return $datetimeparser[$datetime_style]->parse($datetime_string, $century_window);
    }

    function validate (&$invalid) {
      $valid = 1;

      $null = $this->get('null');
      if ($this->not_empty($this->value())) {
        $date = $this->value_internal();
        if (isset($date)) {
          // check the date
          $year  = intval($date['year']);
          $month = intval($date['month']);
          if ($month < 1 || $month > 12) {
            $invalid[$this->name] = 'datetime_invalid_month';
            $valid = 0;
          }

          if ($valid) {
            $day = intval($date['day']);
            if ($day >= 1 && $day <= 31) {
              if ($month == 2) {
                if ($day > 29
                   || ($day == 29
                       && !($year % 4 == 0 && ($year % 100 != 0 || $year % 400 == 0))
                       )
                ) {
                   $valid = 0;
                }
              }
              else if ($day == 31
                && ($month == 4 || $month == 6 || $month == 9 || $month == 11)) {
                $valid = 0;
              }
            }
            else {
              $valid = 0;
            }

            if (!$valid) {
              $invalid[$this->name] = 'datetime_invalid_day';
            }
          }

          if ($valid) {
            $hour = intval($date['hour']);
            $min  = intval($date['min']);
            if ($hour > 23 || $min > 59) {
              $valid = 0; $invalid[$this->name] = 'datetime_invalid_time';
            }
          }
        }
        else {
          $invalid[$this->name] = 'datetime_invalid_format';
          $valid = 0;
        }
      }
      else {
        $valid = $null;
        if (!$valid) {
          $invalid[$this->name] = 'nonempty_required';
        }
      }

      return $valid;
    }

    function show ($params = '') {
      $prepend = is_array($params) && isset($params['prepend'])
        ? $params['prepend']
        : '';

      $attrnames = [ 'id', 'size', 'maxlength', 'disabled', 'readonly' ];
      $attrs = [];
      for ($i = 0; $i < count($attrnames); $i++) {
        $val = $this->get($attrnames[$i]);
        if (in_array($attrnames[$i], [ 'disabled', 'readonly' ]) && isset($val)) {
          $val = $val ? $attrnames[$i] : null;
        }

        if (!isset($val) && $attrnames[$i] == 'size') {
          $val = $this->DEFAULT_SIZE;
        }

        if (isset($val)) {
          $attrs[] = $attrnames[$i] . '="' . $val . '"';
        }
      }

      // add additional attrs from params
      if (is_array($params)) {
        // var_dump($params);
        $attrnames = array_keys($params);
        for ($i = 0; $i < count($attrnames); $i++) {
          switch (strtolower($attrnames[$i])) {
            case 'style':
            case 'onchange':
            case 'tabindex':
            case 'accesskey':
            case 'onfocus':
            case 'onblur':
            case 'onchange':
              $attrs[] = $attrnames[$i] . '="' . $this->htmlspecialchars($params[$attrnames[$i]]) . '"';
              break;
          }
        }
      }

      $current_value = $this->value();
      if (!isset($current_value)) {
        $current_value = $this->get('default');
      }

      return '<input type="text" name="' . $prepend . $this->name() . '"'
           . ' value="' . $current_value . '"'
           . (count($attrs) > 0 ? ' ' . join(' ', $attrs) : '')
           . ' />';
    }
  } // class FormFieldDateTime

  class FormFieldDate
  extends FormFieldText
  {
    var $DEFAULT_SIZE = 12;
    var $datetime_parser;

    function __construct ($form, $name) {
      $this->form = $form;
      $this->name = $name;
    }

    function parse_datetime ($datetime_string, $datetime_style = '', $century_window = 50) {
      if (!isset($datetimeparser[$datetime_style])) {
        $datetimeparser[$datetime_style] = new DateTimeParser($datetime_style);
      }

      return $datetimeparser[$datetime_style]->parse($datetime_string, $century_window, $this->get('incomplete'));
    }

    function validate (&$invalid) {
      $incomplete = $this->get('incomplete');
      // var_dump($incomplete);
      $valid = 1;

      $null = $this->get('null');
      if ($this->not_empty($this->value())) {
        $date = $this->value_internal();
        if (isset($date)) {
          // check the date
          $year  = intval($date['year']);
          $month = intval($date['month']);
          if ($month < 1 || $month > 12) {
            if (!($incomplete && !isset($date['month']))) {
              $invalid[$this->name] = 'datetime_invalid_month';
              $valid = 0;
            }
          }
          if ($valid) {
            if ($incomplete && !isset($date['day'])) {
            }
            else {
              $day = intval($date['day']);
              if ($day >= 1 && $day <= 31) {
                if ($month == 2) {
                  if ($day > 29
                     || ($day == 29
                         && !($year % 4 == 0 && ($year % 100 != 0 || $year % 400 == 0))
                         )
                  ) {
                     $valid = 0;
                  }
                }
                else if ($day == 31
                  && ($month == 4 || $month == 6 || $month == 9 || $month == 11))
                {
                  $valid = 0;
                }
              }
              else {
                $valid = 0;
              }
            }

            if (!$valid) {
              $invalid[$this->name] = 'datetime_invalid_day';
            }
            else if ($incomplete && isset($day) && $day > 0 && 0 == $month) {
              $invalid[$this->name] = 'datetime_invalid_month';
              $valid = 0;
            }
          }
        }
        else {
          $invalid[$this->name] = 'datetime_invalid_format';
          $valid = 0;
        }
      }
      else {
        $valid = $null;
        if (!$valid) {
          $invalid[$this->name] = 'nonempty_required';
        }
      }

      return $valid;
    }

    function showAsSelect ($params) {
      static $now;

      $prepend = is_array($params) && isset($params['prepend'])
        ? $params['prepend']
        : '';
      $name = $prepend . $this->name();
      $may_null = $this->get('null');
      if (isset($may_null) && $may_null) {
        $days[] = '<option value="">DD</option>';
        $months[] = '<option value="">MM</option>';
        $years[] = '<option value="">YYYY</option>';
      }

      $preset = $this->value_internal();
      for ($i = 1; $i <= 31; $i++) {
        $days[] = '<option' . ($i == $preset['day'] ? ' selected="selected"' : '') . '>' . $i . '</option>';
      }

      if (!isset($now)) {
        $now = localtime(time(), true);
      }

      if (isset($params['year_from'])) {
        $first_year = $params['year_from'];
      }
      else {
        $first_year = $now['tm_year'] + 1900;
      }

      if (isset($params['year_to'])) {
        $last_year = $params['year_to'];
      }
      else {
        $last_year = $first_year + 1;
      }

      if ($preset['year'] != 0) {
        if ($first_year > $preset['year']) {
          $first_year = $preset['year'];
        }
        else if ($last_year < $preset['year']) {
          $last_year = $preset['year'];
        }
      }

      for ($i = $first_year; $i <= $last_year; $i++) {
        $selected = false;
        if (($preset['year'] != 0 && $i == $preset['year'])
            || (!($preset['year'] != 0) && $i == $now['tm_year'] + 1900 && !(isset($may_null) && $may_null)))
        {
          $selected = true;
        }

        $years[] = '<option value="' . $i . '"'
          . ($selected ? ' selected="selected"' : '')
          . '>'
          . $i
          . '</option>';
      }

      for ($i = 1; $i <= 12; $i++) {
        $month = strftime('%b', mktime(0, 0, 0, $i, 1, $first_year));
        $months[] = '<option value="' . $i . '"'
          . ($i == $preset['month'] ? ' selected="selected"' : '')
          . '>'
          . $month
          . '</option>';
      }

      return '<select name="' . $name . '[]">' . implode($months) . '</select>'
           . '<select name="' . $name . '[]">' . implode($days) . '</select>'
           . '<select name="' . $name . '[]">' . implode($years) . '</select>';
    }

    function show ($params = '') {
      $pulldown_style = is_array($params) && isset($params['pulldown']) && $params['pulldown'];

      if ($pulldown_style) {
        return $this->showAsSelect($params);
      }

      $prepend = is_array($params) && isset($params['prepend'])
        ? $params['prepend']
        : '';

      $attrnames = [ 'id', 'size', 'maxlength', 'disabled', 'readonly', 'style' ];
      $attrs = [];
      for ($i = 0; $i < count($attrnames); $i++) {
        $val = $this->get($attrnames[$i]);
        if (in_array($attrnames[$i], [ 'disabled', 'readonly' ]) && isset($val)) {
          $val = $val ? $attrnames[$i] : null;
        }

        if (!isset($val) && $attrnames[$i] == 'size') {
          $val = $this->DEFAULT_SIZE;
        }

        if (isset($val)) {
          $attrs[] = $attrnames[$i] . '="' . $val . '"';
        }
      }

      // add additional attrs from params
      if (is_array($params)) {
        // var_dump($params);
        $attrnames = array_keys($params);
        for ($i = 0; $i < count($attrnames); $i++) {
          switch (strtolower($attrnames[$i])) {
            case 'style':
            case 'onchange':
            case 'tabindex':
            case 'accesskey':
            case 'onfocus':
            case 'onclick':
            case 'onblur':
            case 'onchange':
              $attrs[] = $attrnames[$i] . '="' . $this->htmlspecialchars($params[$attrnames[$i]]) . '"';
              break;
          }
        }
      }

      return '<input type="text" name="' . $prepend . $this->name() . '" ' .
              'value="' . $this->value() . '"'
          . (count($attrs) > 0 ? ' ' . join(' ', $attrs) : '')
          . ' />';
    }
  } // class FormFieldDate

  class FormFieldSelect
  extends FormField
  {
    var $multiple = false;

    function __construct ($form, $name) {
      if (gettype($form) == 'object') {
        $this->form = $form;
      }

      $this->name = $name;
    }

    function show ($args = '') {
      $args_isarray = is_array($args);

      $prepend = $args_isarray && isset($args['prepend']) ? $args['prepend'] : '';
      $options = $args_isarray && isset($args['options']) ? $args['options'] : $this->get('options');
      $labels = $args_isarray && isset($args['labels']) ? $args['labels'] : $this->get('labels');

      $attrs = [];
      if (isset($options) && is_array($options)) {
        $val = $this->value();
        if (!isset($val)) {
          $val = $this->get('default');
        }

        $val_isarray = is_array($val);
        if ($this->multiple && !$val_isarray && !empty($val)) {
          $val = preg_split('/\,\s*/', $val);
          $val_isarray = true;
        }

        $size = $args_isarray && isset($args['size'])
          ? $args['size'] : $this->get('size');
        $size = isset($size)
          ? ' size="' . $this->htmlspecialchars($size) . '"' : '';

        $attrnames = [ 'id', 'class', 'disabled', 'data-placeholder' ];
        $attrs = [];
        for ($i = 0; $i < count($attrnames); $i++) {
          $attrval = $this->get($attrnames[$i]);
          if ($attrnames[$i] == 'disabled') {
            $attrval = isset($attrval) && $attrval ? $attrnames[$i] : null;
          }

          if (isset($attrval)) {
            $attrs[] = $attrnames[$i] . '="' . $attrval . '"';
          }
        }

        $style = $this->get('style');
        if (!empty($style)) {
          $attrs['style'] = 'style="' . $this->htmlspecialchars($style) . '"';
        }

        if ($args_isarray) {
          // add additional attrs from args
          // var_dump($args);
          $attrnames = array_keys($args);
          for ($i = 0; $i < count($attrnames); $i++) {
            switch (strtolower($attrnames[$i])) {
              case 'style':
              case 'onchange':
              case 'tabindex':
              case 'accesskey':
              case 'onfocus':
              case 'onblur':
              case 'onchange':
              case 'id':
              case 'class':
                $attrs[] = $attrnames[$i] . '="' . $this->htmlspecialchars($args[$attrnames[$i]]) . '"';
                break;
            }
          }
        }

        $name = $prepend . $this->name();
        if ($this->multiple) {
          $attrs[] = 'multiple="multiple"';
          if (!preg_match('/\[\]/', $name)) {
            $name .= '[]';
          }
        }

        $ret = '<select name="' . $name . '"' . $size
             . (isset($attrs) && count($attrs) > 0
                ? ' ' . join(' ', $attrs) : '')
             . '>';

        for ($i = 0; $i < count($options); $i++) {
          $value = $options[$i];
          $label = $labels[$i];
          if ($label === false) {
            // special value to mark an not selectable element <-> invert key <-> value
            $label = $value;
            $value = '';
          }
          else if (!isset($label)) {
            $label = $options[$i];
          }

          $selected = ($val_isarray ? in_array($options[$i], $val) : $options[$i] == $val) ? ' selected="selected"' : '';
          $disabled = is_bool($labels[$i]) ? ' disabled="disabled"' : '';
          $ret .= '<option value="' . $this->htmlspecialchars($value) . '"'
                . $selected . $disabled . '>'
                . $this->htmlspecialchars($label)
                . '</option>';
        }

        $ret .= '</select>';

        return $ret;
      }
    }
  }

  class FormFieldSelectMultiple
  extends FormFieldSelect
  {
    var $multiple = true;

    // utility functions
    function not_empty ($val) {
      if (!isset($val) || !is_array($val)) {
        return parent::not_empty($val);
      }

      return count($val) > 0;
    }
  }

  class FormFieldHidden
  extends FormField
  {
    function __construct ($form, $name) {
      if (is_object($form)) {
        $this->form = $form;
      }

      $this->name = $name;
    }

    function show ($params = '') {
      $prepend = is_array($params) && isset($params['prepend'])
        ? $params['prepend']
        : '';

      $attrnames = [ 'id' ];
      $attrs = [];
      for ($i = 0; $i < count($attrnames); $i++) {
        $val = $this->get($attrnames[$i]);
        if (isset($val)) {
          $attrs[count($attrs)] = $attrnames[$i] . '="' . $val . '"';
        }
      }

      // add additional attrs from params
      if (is_array($params)) {
        // var_dump($params);
        $attrnames = array_keys($params);
        for ($i = 0; $i < count($attrnames); $i++) {
          switch (strtolower($attrnames[$i])) {
            case 'id':
              $attrs[count($attrs)] = $attrnames[$i] . '="' . $this->htmlspecialchars($params[$attrnames[$i]]) . '"';
              break;
          }
        }
      }

      $current_value = $this->value();
      if (!isset($current_value)) {
        $current_value = $this->get('default');
      }

      return '<input type="hidden" name="' . $prepend . $this->name()
           . '" value="' . $this->htmlspecialchars($current_value) . '"'
           . (count($attrs) > 0 ? ' ' . join(' ', $attrs) : '')
           . ' />';
    }
  }

  class FormFieldPassword
  extends FormField
  {
    var $DEFAULT_SIZE = 32;

    function __construct ($form, $name) {
      $this->form = $form;
      $this->name = $name;
    }

    function show ($params = '') {
      $prepend = is_array($params) && isset($params['prepend'])
        ? $params['prepend'] : '';
      $attrnames = [ 'size', 'maxlength' ];
      $attrs = [];
      for ($i = 0; $i < count($attrnames); $i++) {
        $val = $this->get($attrnames[$i]);
        if (!isset($val) && $attrnames[$i] == 'size') {
          $val = $this->DEFAULT_SIZE;
        }

        if (isset($val)) {
          $attrs[count($attrs)] = $attrnames[$i] . '="' . $val . '"';
        }
      }

      if (is_array($params)) {
        // var_dump($params);
        $attrnames = array_keys($params);
        for ($i = 0; $i < count($attrnames); $i++) {
          switch (strtolower($attrnames[$i])) {
            case 'style':
            case 'onchange':
            case 'tabindex':
            case 'accesskey':
            case 'onfocus':
            case 'onblur':
            case 'onchange':
              $attrs[count($attrs)] = $attrnames[$i] . '="' . $this->htmlspecialchars($params[$attrnames[$i]]) . '"';
              break;
          }
        }
      }

      return '<input type="password" name="' . $prepend . $this->name() . '"'
          . ' value="' . $this->value() . '"'
          . (count($attrs) > 0 ? ' ' . join(' ', $attrs) : '')
          . ' />';
    }
  }

  class FormFieldCheckbox
  extends FormField
  {
    var $show_hidden = 1;

    function __construct ($form, $name) {
      $this->form = $form;
      $this->name = $name;
    }

    function show ($which = -1, $args = '') {
      $args_isarray = is_array($args);

      $join = $args_isarray
        ? (isset($args['join']) ? $args['join'] : '<br />')
        : (!empty($args) ? $args : '<br />');

      $prepend = $args_isarray && isset($args['prepend']) ? $args['prepend'] : '';

      // label might have class and style
      $label_attrs = '';
      if ($args_isarray && isset($args['label_class'])) {
        $label_attrs = ' class="' . $this->htmlspecialchars($args['label_class']) . '"';
      }

      $readonly =  $args_isarray  && isset($args['readonly']) ? $args['readonly'] : false;

      $name = $prepend . $this->name();
      $ret = '';
      if ($this->show_hidden) {
        $this->show_hidden = 0;
        $ret = '<input type="hidden" name="' . $name . '[]" value="0" />';
      }

      $show = [];
      $labels = $this->get('labels');
      if (isset($labels) && is_array($labels)) {
        $current_value = $this->value();
        if (!isset($current_value)) {
          $current_value = $this->get('default');
        }

        // labels can be key/value-pairs or just a list
        if (array_keys($labels) === range(0, count($labels) - 1)) {
          // plain array, transform [ 'label1', 'label2', .. ] => [ 1^I => 'labelI' ];
          for ($i = 0; $i < count($labels); $i++) {
            $labels_new[1 << $i] = $labels[$i];
          }
          $labels = $labels_new;
        }

        $values = array_keys($labels);
        for ($i = 0; $i < count($values); $i++) {
          $value = $values[$i];
          if (($value & $which) != 0) {
            $checked = ($current_value & $value) != 0 ? ' checked="checked"' : '';
            $readonly_str = $readonly ? ' disabled="disabled"' : '';
            $show[count($show)]
              = '<label' . $label_attrs . '><input type="checkbox" name="' . $name
              . '[]" value="' . $value . '"' . $checked . $readonly_str . ' /> '
              . $labels[$value]
              . '</label>';
          }
        }
      }

      return $ret . join($join, $show);
    }
  }

  class FormFieldRadio
  extends FormField
  {
    function __construct ($form, $name) {
      $this->form = $form;
      $this->name = $name;
    }

    function show ($which = '', $args = ' ') {
      $args_isarray = is_array($args);

      $join = $args_isarray
        ? (isset($args['join']) ? $args['join'] : '<br />')
        : (!empty($args) ? $args : '<br />');

      $prepend = $args_isarray && isset($args['prepend']) ? $args['prepend'] : '';

      // label might have class and style
      $label_attrs = '';
      if ($args_isarray && isset($args['label_class'])) {
        $label_attrs = ' class="' . $this->htmlspecialchars($args['label_class']) . '"';
      }

      $name = $prepend . $this->name();
      $show = [];
      $labels = $this->get('labels');
      if (isset($labels)) {
        $values = [];

        $current_value = $this->value();
        if (!isset($current_value)) {
          $current_value = $this->get('default');
        }

        // find out which buttons to show
        if ($which == '') {
          // show all
          foreach ($labels as $value => $label) {
            $values[] = $value;
          }
        }
        else if (is_string($which)) {
          // show just one
          $values[] = $which;
        }
        else if (is_array($which)) {
          // show a list
          $values = $which;
        }

        for ($i = 0; $i < count($values); $i++) {
          $value = $values[$i];
          $checked = ($current_value == $value) ? ' checked="checked"' : '';
          $show[count($show)]
              = '<label' . $label_attrs . '>'
              . '<input type="radio" name="' . $prepend . $this->name()
              . '" value="' . $value . '"' . $checked . ' /> '
              . $labels[$value]
              . '</label>';
        }
      }

      return join($join, $show);
    }
  }

  class FormHTML
  extends Form
  {
    var $params;
    var $fields = [];
    var $invalid;

    function __construct ($params, $record = '') {
      $this->params = $params;
      $this->invalid = []; // no errors to start with
      if (!(gettype($record) == 'string' && $record == '')) {
        $this->record = $record;
      }
    }

    function invalid () {
      return $this->invalid;
    }

    function error_fulltext ($err_name, $lang = 'en') {
      $error_msg = ['en' =>
          ['unknown'                => 'Invalid data',
                'nonempty_required'      => 'This field cannot not be empty',
                'email_nonvalid'         => 'This is not a valid email address',
                'int_invalid'            => 'This is not a valid number',
                'decimal_invalid'        => 'This is not a valid decimal number',
                'datetime_invalid_format' => 'Invalid date'
                  . (!empty($this->params['datetime_style']) ? '(please specify as ' . $this->params['datetime_style'] . ')' : ''),
                'datetime_invalid_month' => 'Invalid month (valid range 1-12)',
                'datetime_invalid_day'   => 'Invalid day (valid range 1-31 depending on the month)',
                'datetime_invalid_time'  => 'Invalid time (valid 0:00 to 23:59)',
                'url_not_allowed'        => 'Because we have problems with fake registrations, you are not allowed to enter Web-Site Addresses into this field',
           ],
          'de' =>
          ['unknown'                => 'Ungültige Daten',
                'nonempty_required'      => 'Dieses Feld darf nicht leer sein',
                'email_nonvalid'         => 'Dies ist keine gültige E-Mail-Adresse',
                'int_invalid'            => 'Dies ist keine gültige Zahl',
                'datetime_invalid_format'=> 'Ungültiges Datum'
                  .(!empty($this->params['datetime_style']) ? '(bitte in folgendem Format eingeben: '.$this->params['datetime_style'].')' : ''),
                'datetime_invalid_month' => 'Ungültiger Monat (gültiger Bereich 1-12)',
                'datetime_invalid_day'   => 'Ungültiger Tag (gültiger Bereich 1-31, je nach Monat)',
                'datetime_invalid_time'  => 'Ungültige Uhrzeit (gültig 0:00 bis 23:59)',
                'url_not_allowed'        => "Sie dürfen keine URL in das Adressfeld eintragen. Bitte tragen Sie diese in das Feld 'Homepage (URL)' ein.",
           ],
        ];
      if (!isset($error_msg[$lang])) {
        // set default language
        $lang = 'en';
      }

      if (!isset($error_msg[$lang][$err_name])) {
        // set default error
        $err_name = 'unknown';
      }

      return $error_msg[$lang][$err_name];
    }

    function get_value ($field) {
      if (!isset($this->record)) {
        return;
      }

      return $this->record->get_value($field);
    }

    function set_value ($field, $value) {
      $this->set_values([ $field => $value ]);
    }

    function set_property ($fieldname, $property, $value) {
      $this->record->set_fieldvalue($fieldname, $property, $value);
    }

    function set_values ($values = '', $params = '') {
      if (isset($params['datetime_style'])) {
        $datetime_style = $params['datetime_style'];
      }
      else if (isset($this->params['datetime_style'])) {
        $datetime_style = $this->params['datetime_style'];
      }
      else {
        $datetime_style = null;
      }

      if (!isset($this->record)) {
        return;
      }

      $prepend = is_array($params) && isset($params['prepend'])
        ? $params['prepend'] : '';

      if (is_array($values)) {
        foreach ($values as $name => $val) {
          if (is_string($val)) {
            $val = rtrim($val);
          }

          if ($prepend != '') {
            if (preg_match("/^$prepend/", $name)) {
              $name = preg_replace("/^$prepend/", '', $name);
            }
            else {
              continue;
            }
          }

          $thisfield = $this->field($name);

          if (isset($thisfield)) {
            switch ($thisfield->get('type')) {
              case 'date' :
              case 'datetime':
                if (is_array($val)) {
                  // from select
                  // a nasty hack for datetime-selects
                  $format = $datetime_style;
                  if (!isset($format)) {
                    $parser = new DateTimeParser();
                    $format = $parser->datestyle;
                  }

                  $format = preg_replace('/[D]+/', '%02d', $format);
                  $format = preg_replace('/[M]+/', '%02d', $format);
                  $format = preg_replace('/[Y]+/', '%04d', $format);
                  $val = (!isset($val[0]) || $val[0] == '')
                        && (!isset($val[1]) || $val[1] == '')
                        && (!isset($val[2]) || $val[2] == '')
                    ? ''
                    : sprintf($format, $val[0], $val[1], $val[2]);
                }

                $this->record->set_value($name, $val);
                $century_window = $thisfield->get('century_window');
                $datetime_internal = isset($century_window)
                  ? $thisfield->parse_datetime($val, $datetime_style, $century_window)
                  : $thisfield->parse_datetime($val, $datetime_style);

                if (isset($datetime_internal)) {
                  $this->record->set_fieldvalue($name, 'value_internal', $datetime_internal);
                }

                break;

              case 'checkbox' :
                if ($thisfield->get('datatype') == 'bitmap' && gettype($val) == 'array') {
                  $newval = 0;
                  for ($i = 0; $i < count($val); $i++) {
                    $newval += $val[$i];
                  }
                  $val = $newval;
                }
                if (isset($val)) {
                  $this->record->set_value($name, $val);
                }
                break;

              case 'hidden':
                if (is_array($val)) {
                  // so we can access them
                  $this->record->set_fieldvalue($name, 'value_internal', $val);
                }
                else if (isset($val)) {
                  $this->record->set_value($name, $val);
                }
                break;

              default:
                if (isset($val)) {
                  $this->record->set_value($name, $val);
                }
            }
          }
        }
      }
      else {
        // TODO: hunt through HTTP_GET/POST-vars
        die('FormHTML->set_values(): Automatic value setting not implemented yet (needs track_vars enabled)');
      }
    }

    function clear_values ($fieldnames = '') {
      if (!is_array($fieldnames)) {
        $fieldnames = $this->record_get_fieldnames();
      }

      for ($i = 0; $i < count($fieldnames); $i++) {
        $field = $this->set_value($fieldnames[$i], '');
      }
    }

    function get_fieldnames () {
      return $this->record->get_fieldnames();
    }

    function validate () {
      $success = 1;
      $this->invalid = [];
      $fieldnames = $this->record->get_fieldnames();
      for ($i = 0; $i < count($fieldnames); $i++) {
        $field = $this->field($fieldnames[$i]);
        if (isset($field)) {
          $valid = $field->validate($this->invalid);
          /* if (!$valid)
           echo $field->name() . '<br>'; */
          if (!$valid) {
            $success = 0;
          }
        }
      }

      return $success;
    }

    function fetch ($args) {
      if (is_array($args) && isset($args['datetime_style'])) {
        $datetime_style = $args['datetime_style'];
      }
      else if (isset($this->params['datetime_style'])) {
        $datetime_style = $this->params['datetime_style'];
      }
      else {
        $datetime_style = null;
      }

      return $this->record->fetch($args, $datetime_style);
    }

    function store ($args = '') {
      return $this->record->store($args);
    }

    function insertupdate ($table, $fieldnames, $keyname, $keyvalue) {
      return $this->record->insertupdate($table, $fieldnames, $keyname, $keyvalue);
    }

    function submitted () {
      return isset($_POST['_submit']) && !empty($_POST['_submit']);
    }

    function show_start ($args = '') {
      if (is_array($args)) {
        die('multiple options for Form::show_start not implemented yet');
      }
      else if (!empty($args)) {
        $args_string = ' ' . $args;
      }
      else {
        $args_string = '';
      }

      return sprintf('<form method="%s" action="%s"%s%s>',
                     isset($this->params['method'])
                     ? $this->params['method'] : 'post',
                     htmlspecialchars($this->params['action']),
                     isset($this->params['name']) ? ' name="' . $this->params['name'] . '"' : '',
                     $args_string)
            . '<input type="hidden" name="_submit" value="' . uniqid('') . '" />';
    }

    function show_end () {
      return '</form>';
    }

    function show_submit ($label = '') {
      $value = trim($label) != '' ? ' value="' . $label . '"' : '';

      return '<input type="submit"' . $value . ' />';
    }

    function field ($name) {
      // TODO allow hierarchic names like record1.id
      if (gettype($this->record) == 'NULL') {
        return;
      }

      if (!isset($this->fields[$name])) { // wrap a FormField around the basic field
        $field = $this->record->get_field($name);
        if (!isset($field)) {
          return;
        }

        switch ($field->get('type')) {
          case 'hidden':
            $this->fields[$name] = new FormFieldHidden($this, $name);
            break;

          case 'password':
            $this->fields[$name] = new FormFieldPassword($this, $name);
            break;

          case 'email':
            $this->fields[$name] = new FormFieldEmail($this, $name);
            break;

          case 'date':
            $this->fields[$name] = new FormFieldDate($this, $name);
            break;

          case 'datetime':
            $this->fields[$name] = new FormFieldDatetime($this, $name);
            break;

          case 'select':
            $this->fields[$name] = $field->get('multiple')
              ? new FormFieldSelectMultiple($this, $name) : new FormFieldSelect($this, $name);
            break;

          case 'checkbox':
            $this->fields[$name] = new FormFieldCheckbox($this, $name);
            break;

          case 'radio':
            $this->fields[$name] = new FormFieldRadio($this, $name);
            break;

          case 'textarea':
            $this->fields[$name] = new FormFieldTextarea($this, $name);
            break;

          default:
            $this->fields[$name] = new FormFieldText($this, $name);
        }
      }

      return $this->fields[$name];
    }
  }
