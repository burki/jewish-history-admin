<?php
/*
 * admin_root.inc.php
 *
 * Start page
 *
 * (c) 2010-2018 daniel.burckhardt@sur-gmbh.ch
 *
 * Version: 2018-08-17 dbu
 *
 * Changes:
 *
 */


class DisplayRoot
extends PageDisplay
{
  function buildTasklist () {
    return ''; // '<p>There are no current tasks.</p>';
  }

  function buildActions () {
    global $RIGHTS_EDITOR, $RIGHTS_ADMIN;

    $actions = [
      [
        'name' => 'article',
        'title' => 'Articles'
      ],
      [
        'name' => 'publication',
        'title' => 'Sources'
      ],
      [
        'name' => 'author',
        'title' => 'Authors',
        'privs' => $RIGHTS_ADMIN | $RIGHTS_EDITOR,
      ],
      [
        'name' => 'publisher',
        'title' => 'Holding Institutions',
        'privs' => $RIGHTS_ADMIN | $RIGHTS_EDITOR,
      ],
      'communication' => [
        'name' => 'communication',
        'title' => 'Communication',
        'privs' => $RIGHTS_ADMIN | $RIGHTS_EDITOR,
      ],
      [
        'name' => 'term',
        'title' => 'Term Sets',
        'privs' => $RIGHTS_ADMIN,
      ],
      [
        'name' => 'person',
        'title' => 'Normdata: Persons',
        'privs' => $RIGHTS_ADMIN,
      ],
      [
        'name' => 'organization',
        'title' => 'Normdata: Organizations',
        'privs' => $RIGHTS_ADMIN,
      ],
      [
        'name' => 'place',
        'title' => 'Normdata: Places',
        'privs' => $RIGHTS_ADMIN,
      ],
      [
        'name' => 'landmark',
        'title' => 'Normdata: Landmarks',
        'privs' => $RIGHTS_ADMIN,
      ],
      [
        'name' => 'event',
        'title' => 'Normdata: Events',
        'privs' => $RIGHTS_ADMIN,
      ],
      [
        'name' => 'account',
        'title' => 'Accounts',
        'privs' => $RIGHTS_ADMIN,
      ],
    ];

    $ret = '';
    foreach ($actions as $action) {
      if (!isset($action['privs']) || 0 != ($action['privs'] & $this->page->user['privs'])) {
        if (empty($ret)) {
          $ret = '<ul>';
        }
        $url = isset($action['url']) ? $action['url'] : $this->page->buildLink([ 'pn' => $action['name'] ]);
        $ret .= '<li><a href="' . htmlspecialchars($url) . '">'
              . $this->formatText(tr($action['title']))
              . '</a></li>';
      }
    }

    if (!empty($ret)) {
      $ret .= '</ul>';
    }

    return $ret;
  }

  function buildWorkplaceInternal () {
    return $this->buildTasklist()
         . $this->buildActions();
  }

  function buildWorkplaceExternal () {
    global $RIGHTS_REFEREE;

    return <<<EOT
<h1>Projektinfo</h1>
<p>Die vom Institut für die Geschichte der deutschen Juden geplante Online-Quellenedition wird am Beispiel von rund 150 Schlüsseldokumenten thematische Schlaglichter auf zentrale Aspekte der lokalen, regionalen sowie der allgemeinen deutsch-jüdischen Geschichte werfen. Zunächst wird der Fokus auf dem Hamburger Raum liegen. Die Quellenedition möchte so auch dazu beitragen, das aufgrund von Migration und Verfolgung in alle Welt verstreute jüdische Erbe der Stadt digital wieder zusammenzuführen, zugänglich zu machen und für zukünftige Generationen zu bewahren.</p>
<p>Neben der Bereitstellung der Materialien als Transkript und PDF-Dokument werden die Quellen durch Interpretations- und Hintergrundtexte in ihre historischen Kontexte eingebettet sowie durch Informationen zur Überlieferung, zur Rezeptionsgeschichte und zu wissenschaftlichen Kontroversen angereichert.</p>
<p>Die Quellenedition richtet sich an Studierende und Forschende ebenso wie an SchülerInnen und interessierte Laien. Je nach Informationsbedürfnis werden dem Nutzer unterschiedliche Zugänge und Vertiefungsebenen angeboten. So soll ein Spektrum von eher allgemeinen überblicksartigen, über eng an der Quelle argumentierenden bis hin zu fachspezifischen Texten abgedeckt werden. Inhaltlich wird das Projekt durch einen Beirat unterstützt, dem renommierte Wissenschaftler der deutsch-jüdischen Geschichte sowie Experten aus dem Bereich Digitalisierung angehören.</p>
EOT;
  }

  function buildContent () {
    global $RIGHTS_ADMIN;

    return $this->is_internal || 0 != ($this->page->user['privs'] & $RIGHTS_ADMIN)
      ? $this->buildWorkplaceInternal()
      : $this->buildWorkplaceExternal();
  } // buildContent
}

$page->setDisplay(new DisplayRoot($page));
