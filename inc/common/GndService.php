<?php
/*
 * GndService.php
 *
 * Lookup Person by Name
 * Fetch Information by GND
 *
 * (c) 2010-2023 daniel.burckhardt@sur-gmbh.ch
 *
 * Version: 2023-04-20 dbu
 *
 * Changes:
 *
 * TODO:
 *  Maybe integrate
 *    http://viaf.org/viaf/search
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

if (!function_exists('is_is_associative')) {
    // see https://stackoverflow.com/questions/173400/how-to-check-if-php-array-is-associative-or-sequential
    // for alternative implementations
    function is_associative($array) {
        if (!is_array($array) || empty($array)) {
            return false;
        }

        $keys = array_keys($array);

        return array_keys($keys) !== $keys;
    }
}

class GndService
{
    protected function getLobidResults ($query, $baseUrl = 'https://lobid.org/gnd/search') {
        $querystring = http_build_query($query);

        $url = $baseUrl . '?' . $querystring;

        $client = new \EasyRdf\Http\Client($url);
        $client->setHeaders('Accept', 'application/ld+json');
        $response = $client->request();
        if (!$response->isSuccessful()) {
            return null;
        }

        return json_decode($response->getBody(), true);
    }

    protected function processLobidResults ($entries, $expectedType = 'DifferentiatedPerson') {
        $results = [];

        foreach ($entries['member'] as $entry) {
            if (!isset($entry['type'])) {
                continue;
            }

            $types = is_array($entry['type'])
                ? $entry['type'] : [ $entry['type'] ];

            if (!in_array($expectedType, $types)) {
                continue;
            }

            $gnd = $entry['gndIdentifier'];
            if ($expectedType != 'DifferentiatedPerson' || self::isGnd($gnd)) {
                switch ($expectedType) {
                    case 'DifferentiatedPerson':
                        $person = [
                            'gnd' => $gnd,
                            'name' => $entry['preferredName'],
                        ];

                        $lifespan = [ '', '' ];
                        $lifespan_set = false;
                        if (!empty($entry['dateOfBirth'])) {
                            $lifespan[0] = is_array($entry['dateOfBirth'])
                                ? $entry['dateOfBirth'][0]
                                : $entry['dateOfBirth'];
                            $lifespan_set = true;
                        }

                        if (!empty($entry['dateOfDeath'])) {
                            $lifespan[1] = is_array($entry['dateOfDeath'])
                                ? $entry['dateOfDeath'][0]
                                : $entry['dateOfDeath'];
                            $lifespan_set = true;
                        }

                        if ($lifespan_set) {
                            $person['lifespan'] = join('-', $lifespan);
                        }

                        if (array_key_exists('biographicalOrHistoricalInformation', $entry)) {
                            $person['profession'] = is_array($entry['biographicalOrHistoricalInformation'])
                                ? $entry['biographicalOrHistoricalInformation'][0]
                                : $entry['biographicalOrHistoricalInformation'];
                        }

                        $results[$gnd] = $person;

                        break;

                    case 'CorporateBody':
                        $result = [
                            'gnd' => $gnd,
                            'name' => $entry['preferredName'],
                            'placeLabel' => null, // TODO
                        ];

                        if (!empty($entry['placeOfBusiness'])) {
                            $result['placeLabel'] = $entry['placeOfBusiness'][0]['label'];
                        }

                        $results[$gnd] = $result;

                        break;

                    default:
                        var_dump($entry);
                        die('TODO: handle ' . $expectedType);
                }
            }
        }

        return $results;
    }

    function lookupPersonByName ($fullname) {
        $persons = [];

        $nameParts = preg_split('/[,\s]+/', $fullname);
        if (empty($nameParts)) {
            return $persons;
        }

        // run two queries, first only
        //  '+' . $namePart
        $queryStrict = [
            'q' => implode(' ', array_map(function ($namePart) {
                    return '+' . $namePart;
                }, $nameParts)),
            'filter' => '+(type:DifferentiatedPerson)',
            'size' => 15,
        ];

        $entries = $this->getLobidResults($queryStrict);

        if (!empty($entries)) {
            $persons = $this->processLobidResults($entries, 'DifferentiatedPerson');
        }

        if (count($persons) < 10) {
            $query = [
                'q' => implode(' ', array_map(function ($namePart) {
                        return '+' . $namePart . '*';
                    }, $nameParts)),
                'filter' => '+(type:DifferentiatedPerson)',
                'size' => 15,
            ];

            if (!empty($entries)) {
                $additionalPersons = $this->processLobidResults($entries);

                foreach ($additionalPersons as $gnd => $person) {
                    if (!array_key_exists($gnd, $persons)) {
                        $persons[$gnd] = $person;
                    }
                }
            }
        }

        return array_values($persons);
    }

    /*
     * Legacy
     */
    function lookupByName ($fullname) {
        return $this->lookupPersonByName($fullname);
    }

    function lookupOrganizationByName ($name, $limit = 20) {
        // try exact match first
        $query = [
            'q' => '"' . $name . '"',
            'filter' => '+(type:CorporateBody)',
            'size' => 15,
        ];

        $entries = $this->getLobidResults($query);

        if (empty($entries) || 0 == $entries['totalItems']) {
            $query['q'] = $name; // try with non-exact query next
            $entries = $this->getLobidResults($query);
        }

        if (!empty($entries)) {
            $corporateBodies = $this->processLobidResults($entries, 'CorporateBody');

            if (!empty($corporateBodies)) {
                return $corporateBodies;
            }
        }

        // alternative
        $name_escaped = addslashes($name);
        $phrase_escaped = '"' . $name_escaped . '"'; // TODO: put this at top if matches
        $query = <<<EOT
# Text search for corporate bodies (pretty output)
#
# Uses diverse literal properties, brings the best match on top
# of the list
PREFIX  gndo:   <http://d-nb.info/standards/elementset/gnd#>
PREFIX  text:   <http://jena.apache.org/text#>
#
SELECT DISTINCT
    ?gndId ?corp (?name as ?corpLabel)
    (?placeName as ?placeLabel) ?dateOfEstablishment ?dateOfTermination
WHERE {
    # limit number of results to $limit
    (?corp ?score) text:query ('{$name_escaped}' $limit) .
    ?corp a gndo:CorporateBody ;
        gndo:preferredNameForTheCorporateBody ?name ;
        gndo:gndIdentifier ?gndId.

    OPTIONAL {
        ?corp gndo:placeOfBusiness ?place.
        ?place gndo:preferredNameForThePlaceOrGeographicName ?placeName
    }.

    OPTIONAL {
        ?corp gndo:dateOfEstablishment ?dateOfEstablishment
    }.

    OPTIONAL {
        ?corp gndo:dateOfTermination ?dateOfTermination
    }.
}
ORDER BY DESC(?score)
EOT;

        $sparql = new \EasyRdf\Sparql\Client('http://zbw.eu/beta/sparql/gnd/query');

        $result = $sparql->query($query);

        $matches = [];
        foreach ($result as $row) {
            $key = $row->corp->getUri();
            $match = [
                'gnd' => $row->gndId->getValue(),
                'name' => $row->corpLabel->getValue(),
                'placeLabel' => property_exists($row, 'placeLabel')
                    ? $row->placeLabel->getValue() : null,
            ];
            $matches[$key] = $match;
        }

        return $matches;
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

    static function fetchGeographicLocation ($url) {
        $parser = self::getRDFParser();
        $parser->parse($url . '/about/lds');
        $triples = $parser->getTriples();
        $index = ARC2::getSimpleIndex($triples, true) ; /* true -> flat version */
        if (isset($index[$url]['https://d-nb.info/standards/elementset/gnd#preferredNameForThePlaceOrGeographicName'])) {
            return $index[$url]['https://d-nb.info/standards/elementset/gnd#preferredNameForThePlaceOrGeographicName'][0];
        }
    }

    static function fetchByGnd ($gnd) {
        $parser = self::getRDFParser();
        $url = sprintf('https://d-nb.info/gnd/%s/about/lds', $gnd);
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
                case 'https://d-nb.info/standards/elementset/gnd#dateOfBirth':
                    $bio->dateOfBirth = $triple['o'];
                    break;

                case 'https://d-nb.info/standards/elementset/gnd#placeOfBirth':
                case 'placeOfBirth':
                    $placeOfBirth = self::fetchGeographicLocation($triple['o']);
                    if (!empty($placeOfBirth)) {
                        $bio->placeOfBirth = $placeOfBirth;
                    }
                    break;

                case 'https://d-nb.info/standards/elementset/gnd#placeOfActivity':
                case 'placeOfActivity':
                    $placeOfActivity = self::fetchGeographicLocation($triple['o']);
                    if (!empty($placeOfActivity)) {
                        $bio->placeOfActivity = $placeOfActivity;
                    }
                    break;

                case 'https://d-nb.info/standards/elementset/gnd#dateOfDeath':
                case 'dateOfDeath':
                    $bio->dateOfDeath = $triple['o'];
                    break;

                case 'https://d-nb.info/standards/elementset/gnd#placeOfDeath':
                case 'placeOfDeath':
                    $placeOfDeath = self::fetchGeographicLocation($triple['o']);
                    if (!empty($placeOfDeath)) {
                        $bio->placeOfDeath = $placeOfDeath;
                    }
                    break;

                case 'https://d-nb.info/standards/elementset/gnd#forename':
                case 'forename':
                    $bio->forename = $triple['o'];
                    break;

                case 'https://d-nb.info/standards/elementset/gnd#surname':
                case 'surname':
                    $bio->surname = $triple['o'];
                    break;

                case 'https://d-nb.info/standards/elementset/gnd#preferredNameForThePerson':
                case 'preferredNameForThePerson':
                    if (!isset($bio->preferredName) && 'literal' == $triple['o_type']) {
                        $bio->preferredName = $triple['o'];
                    }
                    else if ('bnode' == $triple['o_type']) {
                        $nameRecord = $index[$triple['o']];
                        $bio->preferredName = [
                            $nameRecord['https://d-nb.info/standards/elementset/gnd#surname'][0]['value'],
                            $nameRecord['https://d-nb.info/standards/elementset/gnd#forename'][0]['value'],
                        ];
                        // var_dump($index[$triple['o']]);
                    }
                    break;

                case 'https://d-nb.info/standards/elementset/gnd#academicDegree':
                case 'academicDegree':
                    $bio->academicDegree = $triple['o'];
                    break;

                case 'https://d-nb.info/standards/elementset/gnd#biographicalOrHistoricalInformation':
                case 'biographicalOrHistoricalInformation':
                    $bio->biographicalInformation = $triple['o'];
                    break;

                case 'https://d-nb.info/standards/elementset/gnd#professionOrOccupation':
                case 'professionOrOccupation':
                    // TODO: links to external resource
                    break;

                case 'https://d-nb.info/standards/elementset/gnd#variantNameForThePerson':
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
