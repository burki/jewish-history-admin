<?php
/*
 * admin_export.inc.php
 *
 * Export page
 *
 * (c) 2006-2008 daniel.burckhardt@sur-gmbh.ch
 *
 * Version: 2008-07-10 dbu
 *
 * Changes:
 *
 */

class OjsPage extends Page {
  // we use this class so links and title are set correctly
  function __construct ($dbconn, $record) {
    $this->record = $record;
    return parent::__construct($dbconn);
  }

  function title () {
    return $this->record['subject'];
  }
}

class OjsDisplay extends PageDisplay {
  // use this class to customize the html-code of the exported article
  var $stylesheet = NULL;
}


class DisplayExport extends PageDisplay
{
  private $_xw;

  function init () {
    if (array_key_exists('issue', $_POST) && 0 != intval($_POST['issue'])) {
      global $MESSAGE_REVIEW_PUBLICATION;

      $issue = intval($_POST['issue']);
      $querystr = "SELECT Message.id AS id, subject, body, date_format(published, '%Y%m') AS yearmonth, DATE(published) AS published, User.* FROM Message"
        . " LEFT OUTER JOIN MessageUser ON Message.id=MessageUser.message_id LEFT OUTER JOIN User ON User.id=MessageUser.user_id"
        . sprintf(" WHERE Message.type=%d AND Message.status > 0 AND YEAR(published) = %d AND MONTH(published) = %d",
                  $MESSAGE_REVIEW_PUBLICATION, $issue / 100, $issue % 100)
        . " ORDER BY published";

      $dbconn = & $this->page->dbconn;
      $dbconn->query($querystr);
      $found = FALSE;
      while ($dbconn->next_record()) {
        if (!$found) {
          $found = TRUE;
          $this->startXmlIssue($issue);
        }
        $this->addXmlArticle($dbconn->Record);
      }
      if ($found) {
        $this->endXmlIssue();
        $issue_xml = $this->outputXmlIssue();

        $validated = TRUE;
        if (TRUE) {
          $doc = new DOMDocument();
          $doc->loadXML($issue_xml);
          // $validated = $doc->validate(); // don't know how to set to local copy of dtd
          $validated = @ $doc->relaxNGValidate(INC_PATH.'common/native.rng');
        }

        if ($validated) {
          header('Content-type: text/xml');
          header(sprintf('Content-Disposition: attachment; filename=issue_%s.xml', $issue));
          echo $issue_xml;
          return FALSE;
        }
        else
          $this->message = 'Validation of the exported issue failed. Please contact the system administrator.';
      }
    }
    return TRUE;
  }

  private function buildHtml ($record) {
    $view = new OjsDisplay(new OjsPage($this->page->dbconn, $record));
    $ret = $view->buildHtmlStart();
    $ret .= '<h1>' . $this->formatText($record['subject']) . '</h1>';
    $ret .= $view->formatParagraphs($record['body']);
    $ret .= $view->buildHtmlEnd();

    return $ret;
  }

  private function startXmlIssue ($issue) {
    // see http://pkp.sfu.ca/files/docs/importexport/importexport.pdf

    $this->_xw = new xmlWriter();
    $this->_xw->openMemory();
    $this->_xw->setIndent(TRUE);

    $this->_xw->startDocument('1.0', 'UTF-8');
    $this->_xw->startDTD('issues', "-//PKP//OJS Articles and Issues XML//EN", "http://pkp.sfu.ca/ojs/dtds/native.dtd");
    $this->_xw->endDTD();
    $this->_xw->startElement('issues');
    $this->_xw->startElement('issue');
    $this->_xw->writeAttribute('identification', 'num_vol_year');
    $this->_xw->writeAttribute('published', 'false');
    $this->_xw->writeAttribute('current', 'false');

    $this->_xw->writeElement('title', 'TODO: format '.$issue);
    $year = floor($issue / 100);
    $this->_xw->writeElement('volume', $year > 2007 ? $year - 2007 : 0);
    $this->_xw->writeElement('number', $issue % 100);
    $this->_xw->writeElement('year', $year);

    // start review section
    $this->_xw->startElement('section');

    // section title
    $this->_xw->startElement('title');
    $this->_xw->writeAttribute('locale', 'de_DE');
    $this->_xw->text('Rezensionen');
    $this->_xw->endElement();

    // section abbrev
    $this->_xw->startElement('abbrev');
    $this->_xw->writeAttribute('locale', 'de_DE');
    $this->_xw->text('REZ');
    $this->_xw->endElement();
  }

  private function addXmlArticle (& $record) {
    $this->_xw->startElement('article');
    $this->_xw->writeElement('title', $record['subject']);
    $this->_xw->writeElement('date_published', $record['published']);

    $authors = array($record); // currently only single authors
    if (isset($authors)) {
      $primary_contact = TRUE;

      foreach ($authors as $author) {
        $this->_xw->startElement('author');
        if ($primary_contact) {
          $this->_xw->writeAttribute('primary_contact', 'true');
          $primary_contact = FALSE;
          foreach (array('firstname', 'middlename', 'lastname', 'email') as $fieldname) {
            $this->_xw->startElement($fieldname);
            if (!empty($author[$fieldname])) {
              $this->_xw->text($author[$fieldname]);
            }
            else
              $this->_xw->writeCData(''); // empty cdata as placeholder
            $this->_xw->endElement(); // $fieldname
          }
        }
        $this->_xw->endElement(); // </author>
      }
      if (!empty($record['body'])) {
        $this->_xw->startElement('htmlgalley');
        // not in DTD: $this->_xw->writeAttribute('locale', 'de_DE'); // TODO: what other language(s)?
        $this->_xw->writeElement('label', 'HTML');

        $this->_xw->startElement('file');
        $this->_xw->startElement('embed');
        $this->_xw->writeAttribute('encoding', 'base64');
        $this->_xw->writeAttribute('filename', sprintf('review_%d_%05d.html', $record['yearmonth'], $record['id']));
        $this->_xw->writeAttribute('mime_type', 'text/html');
        $this->_xw->text(base64_encode($this->buildHtml($record)));
        // for testing, use instead: $this->_xw->writeCData($this->formatParagraphs($record['body']));
        $this->_xw->endElement(); // </embed>
        $this->_xw->endElement(); // </file>

        $this->_xw->endElement(); // </htmlgalley>
      }

    }
    $this->_xw->endElement(); // article
  }

  private function endXmlIssue () {
    $this->_xw->endElement(); // </section>

    $this->_xw->endElement(); // </issue>

    $this->_xw->endElement(); // </issues>
  }

  private function outputXmlIssue () {
    return $this->_xw->outputMemory(true);
  }

  function buildContent () {
    global $MESSAGE_REVIEW_PUBLICATION;

    $dbconn = & $this->page->dbconn;

    $querystr = "SELECT DISTINCT date_format(published, '%Y%m') AS yearmonth FROM Message"
      . sprintf(" WHERE type=%d AND status > 0",
                $MESSAGE_REVIEW_PUBLICATION)
      . " ORDER BY yearmonth DESC";

    $dbconn->query($querystr);
    $issues = array();
    while ($dbconn->next_record()) {
      $issues[] = $dbconn->Record['yearmonth'];
    }

    $ret = !empty($this->message) ? '<p class="error">'.$this->message.'</p>' : '';
    if (sizeof($issues) > 0) {
      $issue_select = '<select name="issue">';
      foreach ($issues as $issue) {
        $issue_select .= sprintf('<option value="%d">%d-%02d</option>', $issue, $issue / 100, $issue % 100);
      }
      $issue_select .= '</select>';
      $ret .= sprintf('<form action="%s" method="post">Issue: %s<input type="submit" value="%s" /></form>',
                    htmlspecialchars($this->page->buildLink(array('pn' => $this->page->name))), $issue_select, tr('export'));
    }

    return $ret;
  } // buildContent

}

$display = new DisplayExport($page);
if (FALSE == $display->init())
  exit();
$page->setDisplay($display);
