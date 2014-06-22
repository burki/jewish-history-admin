<?php

require_once 'Zend/Date.php';
require_once 'Zend/Registry.php';

class Countries
{
  static $countries;
  static $from_db = COUNTRIES_FROM_DB;

  static function getAll () {
    if (isset(self::$countries))
      return self::$countries;

    if (self::$from_db) {
      $querystr = "SELECT cc, name FROM Country ORDER BY name";
      $dbconn = &$this->page->dbconn;
      $dbconn->query($querystr);
      $countries = array();
      while ($dbconn->next_record()) {
        self::$countries[$dbconn->Record['cc']] = $dbconn->Record['name'];
      }
    }
    else {
      global $COUNTRIES;
      require_once INC_PATH . 'countries.inc.php';
      self::$countries = $COUNTRIES;
    }

    return self::$countries;
  }

  static function name ($cc) {
    if (isset(self::$countries))
      self::getAll();
    return isset(self::$countries[$cc]) ? self::$countries[$cc] : $cc;
  }

  static function zipcodeStyle ($cc) {
    switch($cc) {
      case 'UK':
        return 2;
      case 'AU':
      case 'CA':
      case 'NZ':
      case 'PR':
      case 'US':
      case 'VE':
        return 1;
    }
    return 0;
  }

}

class MysqlFulltextSimpleParser {
  var $min_length = 0; // you might have to turn this up to ft_min_word_len

 /**
    * Callback function (or mode / state), called by the Lexer. This one
    * deals with text outside of a variable reference.
    * @param string the matched text
    * @param int lexer state (ignored here)
    */
  function accept($match, $state) {
    // echo "$state: -$match-<br />";
    if ($state == LEXER_UNMATCHED && strlen($match) < $this->min_length && strpos($match, '*') === FALSE)
      return TRUE;

    if ($state != LEXER_MATCHED)
      $this->output .= '+';

    $this->output .= $match;
    return TRUE;
  }

  function writeQuoted($match, $state) {
    static $words;

    switch ($state) {
    // Entering the variable reference
    case LEXER_ENTER:
        $words = array();
        break;

    // Contents of the variable reference
    case LEXER_MATCHED:
        break;

    case LEXER_UNMATCHED:
        if (strlen($match) >= $this->min_length)
          $words[] = $match;
        break;

    // Exiting the variable reference
    case LEXER_EXIT:
        if (count($words) > 0)
          $this->output .= '+"'.implode(' ', $words).'"';
        break;
    }

    return TRUE;
  }
}

class Journal
{
  static function formatIssue ($issue) {
    return $issue;
  }

  static function getCurrentNr (&$dbconn) {
    global $MESSAGE_REVIEW_PUBLICATION;

    $querystr = "SELECT DATE_FORMAT(published, '%Y%m') AS yearmonth FROM Message"
      . sprintf(" WHERE type=%d AND status > 0",
                $MESSAGE_REVIEW_PUBLICATION)
      . " ORDER BY yearmonth DESC LIMIT 1";

    $dbconn->query($querystr);

    if ($dbconn->next_record()) {
      return $dbconn->Record['yearmonth'];
    }
  }

  static function getIssue (&$dbconn, $nr = NULL) {
    if (!isset($nr))
      $nr = self::getCurrentNr($dbconn);
    if (isset($nr)) {
      $year = intval($nr / 100);
      $issue_date = new Zend_Date(array(
                                'year' => $year,
                                'month' => $nr % 100,
                                'day' => 1));
      return array('title' => sprintf('%s, Bd. %d',
                                      $issue_date->toString('MMMM yyyy', Zend_Registry::get('Zend_Locale')),
                                      $year - 2008 + 1),
                   'issue' => $nr);
    }
  }

  static function getTocBySection (&$dbconn, $view, $issue, $preview = FALSE) {
    global $MESSAGE_REVIEW_PUBLICATION;

    $querystr = "SELECT Message.id AS id, Message.type AS type, subject, body, DATE_FORMAT(published, '%Y%m') AS yearmonth, DATE(published) AS published,"
      . " User.firstname, User.lastname,"
      . " Publication.id AS publication_id, Publication.title AS title, subtitle, author, Publication.editor AS editor, YEAR(publication_date) AS year, Publication.place, Publisher.name AS publisher"
      . " FROM Message"
      . " LEFT OUTER JOIN MessageUser ON Message.id=MessageUser.message_id LEFT OUTER JOIN User ON User.id=MessageUser.user_id"
      . " LEFT OUTER JOIN MessagePublication"
      . " ON MessagePublication.message_id=Message.id"
      . " LEFT OUTER JOIN Publication ON MessagePublication.publication_id=Publication.id"
      . " LEFT OUTER JOIN Publisher ON Publication.publisher_id=Publisher.id"
      . sprintf(" WHERE Message.type=%d AND Message.status > 0 AND YEAR(published) = %d AND MONTH(published) = %d",
                $MESSAGE_REVIEW_PUBLICATION, $issue['issue'] / 100, $issue['issue'] % 100)
      . " ORDER BY IFNULL(Publication.author, Publication.editor), year, User.lastname, Message.id";
// var_dump($querystr);
    $dbconn->query($querystr);
    $articles = array();
    while ($dbconn->next_record()) {
      $articles[] = $dbconn->Record;
    }
    return count($articles) > 0 ? array('REVIEWS' => $articles) : array();
  }

  static function getPublications ($dbconn, $message_id) {
    $querystr = sprintf("SELECT Publication.id AS id, title, subtitle, series, author, editor, YEAR(publication_date) AS year, Publication.place AS place, Publisher.name AS publisher, Publication.isbn AS isbn, pages, listprice, Publication.url AS url"
                        . " FROM MessagePublication, Publication"
                        . " LEFT OUTER JOIN Publisher ON Publication.publisher_id=Publisher.id"
                        . " WHERE MessagePublication.publication_id=Publication.id AND MessagePublication.message_id=%d"
                        . " ORDER BY MessagePublication.ord",
                        $message_id);
    $dbconn->query($querystr);
    $publications = array();
    while ($dbconn->next_record()) {
      $publications[] = $dbconn->Record;
    }

    return $publications;
  }

  static function getNewsArticles (&$dbconn) {
    global $MESSAGE_ARTICLE;

    $querystr = "SELECT Message.id AS id, Message.type AS type, subject, DATE_FORMAT(published, '%d.%m.%Y') AS published_display, DATE(published) AS published,"
      . " User.firstname, User.lastname"
      . " FROM Message"
      . " LEFT OUTER JOIN MessageUser ON Message.id=MessageUser.message_id LEFT OUTER JOIN User ON User.id=MessageUser.user_id"
      . sprintf(" WHERE Message.type=%d AND Message.status > 0 AND published <= CURRENT_DATE()",
                $MESSAGE_ARTICLE)
      . " ORDER BY published DESC, User.lastname, Message.id";
// var_dump($querystr);
    $dbconn->query($querystr);
    $articles = array();
    while ($dbconn->next_record()) {
      $articles[] = $dbconn->Record;
    }
    return $articles;
  }

}
