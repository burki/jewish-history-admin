<?php
/*
 * GndService.php
 *
 * Try to determine Person GND from Lastname, Firstname
 *
 * (c) 2010-2018 daniel.burckhardt@sur-gmbh.ch
 *
 * Version: 2018-07-23 dbu
 *
 * Changes:
 *
 * TODO: Move to http://viaf.org/viaf/search
 *  as described on
 *    http://www.oclc.org/developer/documentation/virtual-international-authority-file-viaf/request-types
 *  e.g.
 *    http://viaf.org/viaf/search?query=local.personalNames+all+%22Andreev,%20Rossen%22+and+local.sources+any+%22dnb%22&maximumRecords=100&startRecord=1&sortKeys=holdingscount&httpAccept=text/xml
 *
 * TODO: Sparql against dbpedia
 *   http://dbpedia.org/sparql
 *   SELECT ?x WHERE { ?x  <http://dbpedia.org/ontology/individualisedPnd>  "118614827"@de }
 *  bzw
 *   http://de.dbpedia.org/sparql
 *   SELECT ?x WHERE { ?x  <http://dbpedia.org/ontology/individualisedPnd>  "118614827"@de }
 *   SELECT ?x WHERE { ?x  <http://de.dbpedia.org/property/pnd>  118614827 }
 *
 *   SELECT ?x WHERE { ?x  <http://dbpedia.org/ontology/individualisedPnd>  "118635123"@de }
 *
 */

class GndService
{
    function lookupByName ($fullname) {
        // lobid-service
        $url = sprintf('http://api.lobid.org/person?name=%s', urlencode($fullname));

        $client = new EasyRdf_Http_Client($url);
        $client->setHeaders('Accept', 'application/ld+json');
        $response = $client->request();
        if (!$response->isSuccessful()) {
            return null;
        }

        $persons = [];

        $entries = json_decode($response->getBody(), true);
        foreach ($entries as $entry) {
            if (isset($entry['@graph'])) {
                $entry = $entry['@graph'][0];
            }
            if (!isset($entry['@type'])) {
                continue;
            }

            $types = is_array($entry['@type'])
                ? $entry['@type'] : [$entry['@type']];

            if (!in_array('http://d-nb.info/standards/elementset/gnd#DifferentiatedPerson', $types)) {
                continue;
            }

            $gnd = $entry['gndIdentifier'];
            if (self::isGnd($gnd)) {
                // var_dump($entry);
                $person = [
                    'gnd' => $gnd,
                    'name' => $entry['preferredNameForThePerson'],
                ];

                $lifespan = ['', ''];
                $lifespan_set = false;
                if (!empty($entry['dateOfBirth'])) {
                    $lifespan[0] = is_array($entry['dateOfBirth'])
                        ? @$entry['dateOfBirth']['@value']
                        : $entry['dateOfBirth'];
                    $lifespan_set = true;
                }
                if (!empty($entry['dateOfDeath'])) {
                    $lifespan[1] = is_array($entry['dateOfDeath'])
                        ? $entry['dateOfDeath']['@value']
                        : $entry['dateOfDeath'];
                    $lifespan_set = true;
                }
                if ($lifespan_set) {
                    $person['lifespan'] = join('-', $lifespan);
                }

                if (array_key_exists('biographicalOrHistoricalInformation', $entry)) {
                    if (!empty($entry['biographicalOrHistoricalInformation']['@value']))
                        $person['profession'] = $entry['biographicalOrHistoricalInformation']['@value'];
                }
                $persons[] = $person;
            }
        }

        return $persons;
    }

    function lookupByNameLegacy ($fullname) {
        // http-client
        require_once 'Zend/Http/Client.php';

        $client = new Zend_Http_Client('http://193.30.112.134/F/?func=find-a-0&local_base=hbz10',
                                       ['maxredirects' => 0, 'timeout' => 30]);

        try {
            $response = $client->request();

            if (200 != $response->getStatus()) {
                throw new Exception($response->getStatus() . ": " . $response->getMessage());
            }
            $body = $response->getBody();
            if (!preg_match('/<form method=get[^>]+name=form1[^>]+action="([^"]*)">/s',
                           $body, $matches))
                throw new Exception('form not found in body: ' . $body);

            $client->setUri($matches[1]);
            $client->setParameterGet([
                'func'  => 'find-a',
                'find_code' => 'WPE',
                'request' => $fullname,
            ]);

            $response = $client->request();
            if (200 != $response->getStatus()) {
                throw new Exception($response->getStatus() . ": " . $response->getMessage());
            }
        }
        catch (Exception $e) {
            die($e->getMessage());
        }

        $body = $response->getBody();

        $persons = [];
        if (preg_match_all('#<noscript>[\s\n]*<tr valign\=baseline>(.*?)</tr>#s', $body, $matches, PREG_PATTERN_ORDER)) {
          foreach ($matches[1] as $match) {
            if (preg_match_all('#<td class=td1[^>]*>(.*?)</td>#', $match, $record, PREG_PATTERN_ORDER)) {
                $record = $record[1];
                if (count($record) >= 6) {
                    $number = self::cleanTd($record[5]);
                    if (self::isGnd($number)) {
                        $persons[] = [
                            'gnd' => $number,
                            'name' => self::cleanTd($record[2]),
                            'lifespan' => self::cleanTd($record[3]),
                            'profession' => self::cleanTd($record['4']),
                        ];
                    }
                    else {
                        // ignore: var_dump($number . ' is not a PPN');
                    }
                }
            }
          }
        }

        return $persons;
    }

    /* small helper-function for hbz */
    private static function cleanTd ($fragment) {
        $fragment = preg_replace('/<BR>/', ' ', $fragment);
        $fragment = rtrim($fragment);
        $fragment = preg_replace('/&nbsp;$/', '', $fragment);
        return html_entity_decode($fragment, ENT_NOQUOTES, 'utf-8');
    }

    static function isGnd ($number) {
        if (!preg_match('/^[0-9]+x?$/i', $number)) {
            return false;
        }

        $normalized = strtoupper($number);
        if (strlen($normalized) == 9) {
            // we may have one digit less than isbn, prepend a 0
            $normalized = '0' . $normalized;
        }
        $checkdigit = self::getCheckdigit($normalized);
        if (false === $checkdigit) {
            // wrong length or wrong character
            return false;
        }

        return $checkdigit == $normalized[strlen($normalized) - 1];
    }

    static function getCheckdigit ($gnd) {
        // calculate the checkdigit mod-11
        if (strlen($gnd) != 10) {
            return false;
        }
        /*
         * this checkdigit calculation could probably be expressed in less
         * space using a lop, but this keeps it very clear what the math
         * involved is
         */
        $checkdigit = 11 - ( ( 10 * substr($gnd,0,1) + 9 * substr($gnd,1,1) + 8 * substr($gnd,2,1) + 7 * substr($gnd,3,1) + 6 * substr($gnd,4,1) + 5 * substr($gnd,5,1) + 4 * substr($gnd,6,1) + 3 * substr($gnd,7,1) + 2 * substr($gnd,8,1) ) % 11);
        /*
         * convert the numeric check value
         * into the single char version
         */
        switch ( $checkdigit )
        {
            case 10:
                $checkdigit = "X";
                break;
            case 11:
                $checkdigit = 0;
                break;
        }

        return $checkdigit;
    }
}

class BiographicalData
{
    private static $RDFParser = null;

    private static function getRDFParser () {
        if (!isset(self::$RDFParser)) {
            self::$RDFParser = ARC2::getRDFParser();
        }

        return self::$RDFParser;
    }

    /*
     * TODO: Fix!
     */
    static function fetchGeographicLocation ($url) {
        $parser = self::getRDFParser();
        $parser->parse($url . '/about/lds');
        $triples = $parser->getTriples();
        $index = ARC2::getSimpleIndex($triples, true) ; /* true -> flat version */
        if (isset($index[$url]['http://d-nb.info/standards/elementset/gnd#preferredNameForThePlaceOrGeographicName'])) {
            return $index[$url]['http://d-nb.info/standards/elementset/gnd#preferredNameForThePlaceOrGeographicName'][0];
        }
        if (isset($index[$url]['preferredNameForThePlaceOrGeographicName'])) {
            return $index[$url]['preferredNameForThePlaceOrGeographicName'][0];
        }

        foreach ($triples as $triple) {
            if ('sameAs' == $triple['p']) {
                if (preg_match('/d\-nb\.info/', $triple['o']) && $triple['o'] != $url) {
                    return self::fetchGeographicLocation($triple['o']);
                }
            }
        }
    }

    static function fetchByGnd ($gnd) {
        $parser = self::getRDFParser();
        $url = sprintf('http://d-nb.info/gnd/%s/about/lds', $gnd);
        $parser->parse($url);
        $triples = $parser->getTriples();
        if (empty($triples)) {
            return;
        }

        $index = ARC2::getSimpleIndex($triples, false) ; /* false -> non-flat version */
/* var_dump($triples);
exit; */
        $bio = new BiographicalData();
        $bio->gnd = $gnd;
        foreach ($triples as $triple) {
            switch ($triple['p']) {
                case 'http://d-nb.info/standards/elementset/gnd#dateOfBirth':
                case 'dateOfBirth':
                    $bio->dateOfBirth = $triple['o'];
                    break;

                case 'http://d-nb.info/standards/elementset/gnd#placeOfBirth':
                case 'placeOfBirth':
                    $placeOfBirth = self::fetchGeographicLocation($triple['o']);
                    if (!empty($placeOfBirth))
                        $bio->placeOfBirth = $placeOfBirth;
                    break;

                case 'http://d-nb.info/standards/elementset/gnd#placeOfActivity':
                case 'placeOfActivity':
                    $placeOfActivity = self::fetchGeographicLocation($triple['o']);
                    if (!empty($placeOfActivity))
                        $bio->placeOfActivity = $placeOfActivity;
                    break;

                case 'http://d-nb.info/standards/elementset/gnd#dateOfDeath':
                case 'dateOfDeath':
                    $bio->dateOfDeath = $triple['o'];
                    break;

                case 'http://d-nb.info/standards/elementset/gnd#placeOfDeath':
                case 'placeOfDeath':
                    $placeOfDeath = self::fetchGeographicLocation($triple['o']);
                    if (!empty($placeOfDeath))
                        $bio->placeOfDeath = $placeOfDeath;
                    break;

                case 'http://d-nb.info/standards/elementset/gnd#forename':
                case 'forename':
                    $bio->forename = $triple['o'];
                    break;

                case 'http://d-nb.info/standards/elementset/gnd#surname':
                case 'surname':
                    $bio->surname = $triple['o'];
                    break;

                case 'http://d-nb.info/standards/elementset/gnd#preferredNameForThePerson':
                case 'preferredNameForThePerson':
                    if (!isset($bio->preferredName) && 'literal' == $triple['o_type'])
                        $bio->preferredName = $triple['o'];
                    else if ('bnode' == $triple['o_type']) {
                        $nameRecord = $index[$triple['o']];
                        $bio->preferredName = [$nameRecord['http://d-nb.info/standards/elementset/gnd#surname'][0]['value'],
                                                    $nameRecord['http://d-nb.info/standards/elementset/gnd#forename'][0]['value']];
                        // var_dump($index[$triple['o']]);
                    }
                    break;

                case 'http://d-nb.info/standards/elementset/gnd#academicDegree':
                case 'academicDegree':
                    $bio->academicDegree = $triple['o'];
                    break;

                case 'http://d-nb.info/standards/elementset/gnd#biographicalOrHistoricalInformation':
                case 'biographicalOrHistoricalInformation':
                    $bio->biographicalInformation = $triple['o'];
                    break;

                case 'http://d-nb.info/standards/elementset/gnd#professionOrOccupation':
                case 'professionOrOccupation':
                    // TODO: links to external resource
                    break;

                case 'http://d-nb.info/standards/elementset/gnd#variantNameForThePerson':
                case 'variantNameForThePerson':
                    // var_dump($triple);
                    break;

                default:
                    if (!empty($triple['o'])) {
                        // var_dump($triple);
                    }
                    // var_dump($triple['p']);
            }
        }

        return $bio;
    }

    var $gnd;
    var $preferredName;
    var $academicDegree;
    var $biographicalInformation;
    var $dateOfBirth;
    var $placeOfBirth;
    var $placeOfActivity;
    var $dateOfDeath;
    var $placeOfDeath;
}
