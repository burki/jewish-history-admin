<?php
/*
 * ws_place.inc.php
 *
 * Webservices for managing places
 *
 * (c) 2007-2018 daniel.burckhardt@sur-gmbh.ch
 *
 * Version: 2018-07-23 dbu
 *
 * Changes:
 *
 */

class WsPlace
extends WsHandler
{
  // example-call: http://localhost/juedische-geschichte/admin/admin_ws.php?pn=place&action=lookupWikidataByTgn&_debug=1&tgn=7004417
  function buildResponse () {
    $valid_actions = [ 'lookupWikidataByTgn' ];

    $action = array_key_exists('action', $_GET)
      && in_array($_GET['action'], $valid_actions)
      ? $_GET['action'] : $valid_actions[0];
    $action_name = $action.'Action';

    return $this->$action_name();
  }

  function lookupWikidataByTgnAction () {
    require_once INC_PATH . 'common/GettyService.php';

    $ret = [];

    $tgn = $this->getParameter('tgn');
    if (empty($tgn)) {
      // jquery ui autocomplete passes term, could be changed: 
    }

    if (!empty($tgn)) {
      $place = GettyPlaceData::fetchByIdentifier('tgn:' . $tgn);
      if (!is_null($place) && !empty($place->latitude) && !empty($place->longitude)) {
        // for geospatial, see
        // https://addshore.com/2016/05/geospatial-search-for-wikidata-query-service/
        //
        // for the prefixes
        // https://www.mediawiki.org/wiki/Wikibase/Indexing/RDF_Dump_Format#Full_list_of_prefixes

        $query = <<<EOT
PREFIX wd: <http://www.wikidata.org/entity/>
PREFIX wdt: <http://www.wikidata.org/prop/direct/>
PREFIX bd: <http://www.bigdata.com/rdf#>
PREFIX geo: <http://www.opengis.net/ont/geosparql#>

SELECT DISTINCT ?place ?placeLabel ?placeType ?placeTypeLabel ?geonamesId ?location ?dist WHERE {
    {
        SELECT ?place ?placeType ?geonamesId WHERE {
            ?place wdt:P31/wdt:P279* wd:Q486972.
            ?place wdt:P31 ?placeType.

            OPTIONAL {
                ?place wdt:P1566 ?geonamesId.
            }.
        }
    }

    SERVICE wikibase:around {
        ?place wdt:P625 ?location .
        bd:serviceParam wikibase:center "Point({$place->longitude} {$place->latitude})"^^geo:wktLiteral .
        bd:serviceParam wikibase:radius "10" .
        bd:serviceParam wikibase:distance ?dist.
    }

    SERVICE wikibase:label {
        bd:serviceParam wikibase:language "[AUTO_LANGUAGE],en".
    }
} ORDER BY ASC(?dist)
EOT;

        $sparqlClient =  new \EasyRdf_Sparql_Client('https://query.wikidata.org/sparql');

        $result = $sparqlClient->query($query);
        $candidates = [];
        foreach ($result as $row) {
          $qid = (string)$row->place;
          if (!array_key_exists($qid, $candidates)) {
              $candidate = [
                  'qid' => $qid,
                  'name' => (string)$row->placeLabel,
                  'dist' => (string)$row->dist,
                  'geonamesId' => property_exists($row, 'geonamesId') ? (string)$row->geonamesId : null,
                  'types' => [],
              ];
              $candidates[$qid] = $candidate;
          }

          $candidates[$qid]['types'][(string)$row->placeType] = (string)$row->placeTypeLabel;
        }

        $fuse = new \Fuse\Fuse(array_values($candidates), [
          'keys' => [ 'name' ],
          'includeScore' => true,
          'threshold' => 0.2, // At what point does the match algorithm give up. A threshold of 0.0 requires a perfect match (of both letters and location), a threshold of 1.0 would match anything.
        ]);

        $matches = $fuse->search($place->preferredName);

        foreach ($matches as $match) {
          $item = $match['item'];

          $qid =  $item['qid'];
          if (preg_match('/(Q\d+)$/', $qid, $matches)) {
            $qid = $matches[1];
          }

          $label = sprintf('%s (%s, %s)',
                           $item['name'], $qid,
                           $item['geonamesId']) . "\n";

          $ret[] = [
            'value' => $qid,
            'label' => $label,
          ];
        }
      }
    }

    return new JsonResponse($ret);
  }
}

WsHandlerFactory::registerClass('place', 'WsPlace');
