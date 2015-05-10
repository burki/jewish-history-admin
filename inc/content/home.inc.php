<?php
/*
 * home.inc.php
 *
 * Start page
 *
 * (c) 2010-2015 daniel.burckhardt@sur-gmbh.ch
 *
 * Version: 2015-03-24 dbu
 *
 * Changes:
 *
 */


class HomeDisplay extends FrontendDisplay
{
  function buildContent () {
    $content = <<<EOT
  <h3>„Schlüsseldokumente zur deutsch-jüdischen Geschichte von der frühen Neuzeit bis in die Gegenwart“</h3>

  <figure style="float: left;">
    <img src="./media/online_quellenedition.jpg" alt="" width="350">
    <figcaption class="caption" style="width:350px">
      Entwurf der geplanten Website (Edelweiss)
    </figcaption>
  </figure>

  <p>Die vom <a href="http://www.igdj-hh.de/">Institut für die Geschichte der deutschen Juden</a> geplante Online-Quellenedition wird am Beispiel von rund 150 Schlüsseldokumenten thematische Schlaglichter auf zentrale Aspekte der lokalen, regionalen sowie der allgemeinen deutsch-jüdischen Geschichte werfen. Zunächst wird der Fokus auf dem Hamburger Raum liegen. Die Quellenedition möchte so auch dazu beitragen, das aufgrund von Migration und Verfolgung in alle Welt verstreute jüdische Erbe der Stadt digital wieder zusammenzuführen, zugänglich zu machen und für zukünftige Generationen zu bewahren.</p>
  <p>Neben der Bereitstellung der Materialien als Transkript und PDF-Dokument werden die Quellen durch Interpretations- und Hintergrundtexte in ihre historischen Kontexte eingebettet sowie durch Informationen zur Überlieferung, zur Rezeptionsgeschichte und zu wissenschaftlichen Kontroversen angereichert.</p>
  <p>Die Quellenedition richtet sich an Studierende und Forschende ebenso wie an SchülerInnen und interessierte Laien. Je nach Informationsbedürfnis werden dem Nutzer unterschiedliche Zugänge und Vertiefungsebenen angeboten. So soll ein Spektrum von eher allgemeinen überblicksartigen, über eng an der Quelle argumentierenden bis hin zu fachspezifischen Texten abgedeckt werden. Inhaltlich wird das Projekt durch einen Beirat unterstützt, dem renommierte Wissenschaftler der deutsch-jüdischen Geschichte sowie Experten aus dem Bereich Digitalisierung angehören.</p>
  <p>Die Online-Edition, für die derzeit Mittel eingeworben werden, ergänzt die bisherigen Veranstaltungen und Publikationen des IGdJ und stärkt seine Rolle als Verbindungsglied zwischen Wissenschaft und interessierter Öffentlichkeit. Die Website wird im Jahr 2016 anlässlich des 50-jährigen Instituts-Jubiläums präsentiert werden.</p>
  <h4>Beiratsmitglieder</h4>
  <p>Dr. Sylvia Asmus (Deutsche Nationalbibliothek, Deutsches Exilarchiv 1933-1945)<br>Prof. Hartmut Berghoff (German Historical Institute, Washington D.C.)<br>PD Dr. Jörg Deventer (Simon Dubnow Institut)<br>Dr. Annette Haller (Germania Judaica e. V., Köln Compact Memory)<br>Dr. Rachel Heuberger (Judaica Europeana/ Leiterin der Hebraica- und Judaica-Sammlung der Universitätsbibliothek Johann Christian Senckenberg)<br>Anke Hönnig (Staatsarchiv Hamburg)<br>Prof. Dr. Rüdiger Hohls (Clio-online e.V./ H-Soz-Kult)<br>Prof. Dr. Simone Lässig (Georg Eckert Institut/ AG Digitale Geschichtswissenschaft)<br>Harald Lordick (DARIAH-DE/ Salomon Ludwig Steinheim-Institut für deutsch-jüdische Geschichte)<br>Dr. Aubrey Pomerance (Leiter Archiv des Leo Baeck Instituts/ Jüdisches Museum Berlin) <br>Prof. Dr. Reinhard Rürup (Prof. emer. Technische Universität Berlin)</p>
  <h4>Call for Articles</h4>
  <p>Zurzeit werden für die oben beschriebene Online-Quellenedition Vorschläge für Quellen zur jüdischen Geschichte Hamburgs gesucht, die zugleich auf allgemeine Fragestellungen der deutsch-jüdischen Geschichte verweisen und exemplarisch für ein größeres Quellenkonvolut stehen. Für die Online-Edition sollen zu den Quellen jeweils eine knappe Beschreibung (150-200 Wörter) sowie ein Interpretationstext (max. 1.500 Wörter) angefertigt werden.</p>
  <p>Kurze, aussagekräftige Vorschläge für solche Texte, die kurz die Quelle und ihre Einordnung in die Themenkategorie skizzieren sollten, können Sie an Anna Menny (<a href="mailto:anna.menny@public.uni-hamburg.de">anna.menny@public.uni-hamburg.de</a>) senden. Wir möchten explizit auch Nachwuchswissenschaftlerinnen und Nachwuchswissenschaftler ermuntern, sich zu bewerben.</p>
  <p><a href="./data/CfA_Schluesseldokumente-Edition.pdf">Ausführlicher Call for Articles</a> [PDF]</p>
  </div>
EOT;
    return $content;
  } // buildContent

}

$page->setDisplay(new HomeDisplay($page));
