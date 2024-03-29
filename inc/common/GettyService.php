<?php
/*
 * GettyService.php
 *
 * Try to determine Person GND from Lastname, Firstname
 *
 * (c) 2015-2023 daniel.burckhardt@sur-gmbh.ch
 *
 * Version: 2023-04-20 dbu
 *
 *
 */

class GettyPlaceData
{
    /**
     * Executes a query
     *
     * @param string $query
     *
     * @throws NoResultException
     *
     * @return \EasyRdf\Graph graph object representing the query result
     */
    protected function executeRdfQuery($query)
    {
        $client = new \EasyRdf\Http\Client($query);
        $client->setHeaders('Accept', 'application/rdf+xml');
        $response = $client->request();
        if (!$response->isSuccessful()) {
            return null;
        }

        $content = $response->getBody();
        $graph = new \EasyRdf\Graph($query);
        try {
            $num_triples = $graph->parse($content);
        }
        catch (\EasyRdf\Exception $e) {
            throw new \Exception(sprintf('Problem executing query %s: %s', $query, $e->getMessage()));
        }

        return $graph;
    }

    /**
     * easyrdf-Helper Stuff
     */
    protected function setValuesFromResource (&$values, $resource, $propertyMap, $prefix = '') {
        foreach ($propertyMap as $src => $target) {
            $key = $src;
            if (is_int($src)) {
                // numerical indexed
                $key = $target;
            }

            if (!empty($prefix) && !preg_match('/\:/', $key)) {
                $key = $prefix . ':' . $key;
            }

            $count = $resource->countValues($key);
            if ($count > 1) {
                $collect = [];
                $properties = $resource->all($key);
                foreach ($properties as $property) {
                    $value = $property->getValue();
                    if (!empty($value)) {
                        $collect[] = $value;
                    }
                }
                $values[$target] = $collect;

            }
            else if ($count == 1) {
                $property = $resource->get($key);

                if (isset($property) && !($property instanceof \EasyRdf\Resource)) {
                    $value = $property->getValue();
                    if (!empty($value)) {
                        $values[$target] = $value;
                    }
                }
            }
        }
    }

    //
    static function fetchByIdentifier ($tgn_id) {
        $parts = preg_split('/\:/', $tgn_id, 2);
        $url = sprintf('http://vocab.getty.edu/%s/%s', $parts[0], $parts[1]);

        $place = new GettyPlaceData();

        \EasyRdf\RdfNamespace::set('gvp', 'http://vocab.getty.edu/ontology#');
        $graph = $place->executeRdfQuery($url, [ 'Accept' => 'application/rdf+xml' ]);
        if (!isset($graph)) {
            return;
        }
        // echo $graph->dump();

        $uri = $graph->getUri();

        if (!empty($uri)) {
            $resource = $graph->resource($uri);
        }

        $place->tgn = $resource->get('dc11:identifier')->getValue();
        $prefLabels = $resource->all('skos:prefLabel');
        $preferredName = '';
        if (empty($prefLabels)) {
            $prefLabels = $resource->all('skosxl:prefLabel');
            if (empty($prefLabels)) {
                echo $resource->dump();
                exit;
            }
            foreach ($prefLabels as $prefLabel) {
                if ($prefLabel instanceof \EasyRdf\Resource) {
                    $subgraph = $place->executeRdfQuery($prefLabel->getUri(),
                                                        ['Accept' => 'application/rdf+xml']);
                    $subresource = $subgraph->resource($prefLabel->getUri());
                    $preferredName = $subresource->get('gvp:term')->getValue();
                    $values['preferredName'] = $preferredName;
                }
            }
        }
        else {
            foreach ($prefLabels as $prefLabel) {
                if (empty($preferredName) || 'de' == $prefLabel->getLang()) {
                    $preferredName = $prefLabel->getValue();
                    $values['preferredName'] = $preferredName;
                }
            }
        }

        $place->setValuesFromResource($values, $resource, [ 'parentString' => 'parentPath'],
                                     'gvp');
        $uri = $resource->get('gvp:broaderPreferred')->getUri();
        if (preg_match('/'
                       . preg_quote('http://vocab.getty.edu/tgn/', '/')
                       . '(\d+)/',
                       $uri, $matches))
        {
            $values['tgn_parent'] = $matches[1];
        }

        $placeTypePreferred = $resource->get('gvp:placeTypePreferred')->getUri();
        switch ($placeTypePreferred) {
            case 'http://vocab.getty.edu/aat/300128207':
                $values['type'] = 'nation';
                break;

            case 'http://vocab.getty.edu/aat/300387506':
                $values['type'] = 'country';
                break;

            case 'http://vocab.getty.edu/aat/300008347':
                $values['type'] = 'inhabited place';
                break;

            case 'http://vocab.getty.edu/aat/300000745':
                $values['type'] = 'neighborhood';
                break;

            case 'http://vocab.getty.edu/aat/300387178':
                $values['type'] = 'historical region';
                break;

            default:
                die('TODO: handle place type ' . $placeTypePreferred);
        }

        $schemaPlace = $resource->get('foaf:focus');
        if (isset($schemaPlace)) {
            $place->setValuesFromResource($values, $schemaPlace,
                                         ['lat' => 'latitude', 'long' => 'longitude'],
                                         'geo');

        }
        // echo $schemaPlace->dump();
        foreach ($values as $key => $val) {
            $place->$key = $val;
        }

        return $place;
    }

    var $tgn;
    var $preferredName;
    var $type;
    var $tgn_parent;
    var $parentPath;
    var $latitude;
    var $longitude;
}
