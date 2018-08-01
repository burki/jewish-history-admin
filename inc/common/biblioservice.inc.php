<?php
/*
 * biblioservice.inc.php
 *
 * Class for handling bibliographic information
 *
 * (c) 2007-2018 daniel.burckhardt@sur-gmbh.ch
 *
 * Version: 2018-05-22 dbu
 *
 * Changes:
 *
 */

require_once LIB_PATH . 'ISBN.php';
require_once INC_PATH . 'common/BiblioService_GBV.php';

define('AMAZON_API_KEY', '1HB1SJ1CB602FSGN59G2');
define('AMAZON_SECRET_KEY', 'wVxuEUnPflJDLg8jaUvuJ26i1NH0fZ8431rm43uZ');

class BiblioService_Amazon
{
    static $ws = []; // singleton by store
    static $associate_ids = [
        // string by store
        'US' => 'oskopevisua0a-20',
        'DE' => 'oskopevisuals-21',
    ];

    var $stores = [];

    static function getService ($store = 'US') {
        if (!isset(self::$ws[$store])) {
          // TODO: get AMAZON_API_KEY from ini-framework
          self::$ws[$store] = new Zend_Service_Amazon(AMAZON_API_KEY, $store, AMAZON_SECRET_KEY);
        }

        return self::$ws[$store];
    }

    static function buildTitleSubtitle ($fulltitle) {
        $parts = preg_split('/(\:|\.)\s+/', $fulltitle, 2);

        if (count($parts) == 2) {
            return $parts;
        }

        return [ $parts[0], '' ];
    }

    function __construct ($stores = []) {
        if (0 == count($stores)) {
            $stores = [ 'DE', 'US' ];
        }

        $this->stores = $stores;
    }

    function itemLookup ($isbn) {
        $success = false;
        $response = null;

        // amazon currently supports only isbn-10 in asin-query
        $isbn_query = $isbn;
        $isbn_version = ISBN::guessVersion($isbn);
        if (isset($isbn_version) && ISBN_VERSION_ISBN_13 == $isbn_version) {
            $isbn_query = ISBN::convert($isbn, $isbn_version, ISBN_VERSION_ISBN_10);
            if (false === $isbn_query) {
                $isbn_query = $isbn; // wind back if it failed
            }
        }
        $params = [ 'ResponseGroup' => 'Large' ];

        foreach ($this->stores as $store) {
            $ws = self::getService($store);
            if (isset(self::$associate_ids[$store])) {
                $params['AssociateTag'] = self::$associate_ids[$store];
            }

            try {
                $result = $ws->itemLookup($isbn_query, $params);
                $success = true;

                list($title, $subtitle) = self::buildTitleSubtitle($result->Title);
                $response = [ 'isbn' => $isbn, 'title' => $title, 'subtitle' => $subtitle ];

                // name fields
                foreach ([ 'Author', 'Creator' ] as $field) {
                    if (!empty($result->$field)) {
                        $value = $result->$field;
                        if ('array' == gettype($value)) {
                            for ($i = 0; $i < count($value); $i++) {
                                list($surname, $given) = BiblioService::buildSurnameGiven($value[$i]);
                                $value[$i] = $surname . (!empty($given) ? ', ' . $given : '');
                            }
                            $fullname = implode('; ', $value);
                        }
                        else {
                            list($surname, $given) = BiblioService::buildSurnameGiven($value);
                            $fullname = $surname . (!empty($given) ? ', ' . $given : '');
                        }

                        if ('Creator' == $field && isset($response['author']) && $fullname == $response['author']) {
                            continue;
                        }

                        $response['Creator' == $field ? 'editor' : strtolower($field)] = $fullname;
                    }
                }

                if ('Book' == $result->ProductGroup) {
                    // var_dump($result);
                    if (!empty($result->Publisher)) {
                        $response['publisher'] = $result->Publisher;
                    }

                    if (preg_match('/^(\d{4})/', $result->PublicationDate, $matches)) {
                        $response['publication_date'] = $matches[1];
                        $response['isbn'] = BiblioService::normalizeIsbn($response['isbn']);
                    }

                    if (!empty($result->Binding)) {
                        $response['binding'] = $result->Binding;
                    }

                    if (isset($result->NumberOfPages) && $result->NumberOfPages > 1) {
                        $response['pages'] = $result->NumberOfPages . ' p.';
                    }

                    if (!empty($result->FormattedPrice)) {
                        $response['listprice'] = $result->FormattedPrice;
                    }
                    else {
                        if (isset($result->Offers)) {
                            $offerSummary = &$result->Offers;
                        }
                        else {
                            $offerSummary = &$result->OfferSummary;
                        }

                        if (isset($offerSummary)) {
                            if (isset($offerSummary->Offers) && isset($offerSummary->Offers[0]->Price) && 'EUR' == $offerSummary->Offers[0]->CurrencyCode) {
                                $response['listprice'] = 'EUR ' . sprintf('%01.2f', $offerSummary->Offers[0]->Price / 100);
                            }
                        }
                    }

                    if (isset($offerSummary)) {
                        if (isset($offerSummary->Offers) && isset($offerSummary->Offers[0]->PriceFormatted)) {
                            $price['price'] = 'Price: ' . $offerSummary->Offers[0]->PriceFormatted;
                        }
                    }
                }

                if (isset($result->LargeImage)) {
                    $response['image'] = $result->LargeImage->Url->getUri();
                }
            }
            catch (Zend_Service_Exception $e) {
                // We did not find any matches for your request. (AWS.ECommerceService.NoExactMatches)
            }

            if ($success) {
                break;
            }
        }

        return $response;
    }
}

class BiblioService
{
    // helper function
    static function validateIsbn ($isbn, $version = null) {
        return ISBN::validate($isbn, isset($version) ? $version : ISBN_VERSION_UNKNOWN);
    }

    static function normalizeIsbn ($isbn, $publication_year = -1) {
        $isbn = strtoupper($isbn);
        $isbn = preg_replace('/[^0-9X]/', '', $isbn);
        if ($publication_year > 0 && ISBN::validate($isbn)) {
            $version = ISBN::guessVersion($isbn);
            if ($publication_year <= 2006 && ISBN_VERSION_ISBN_10 != $version)
                $isbn = ISBN::convert($isbn, $version, ISBN_VERSION_ISBN_10);
            else if ($publication_year > 2006 && ISBN_VERSION_ISBN_10 == $version)
                $isbn = ISBN::convert($isbn, $version, ISBN_VERSION_ISBN_13);
        }

        return $isbn;
    }

    static function buildSurnameGiven ($name) {
        $parts = preg_split('/\s+/', trim($name));

        # if the last part is roman numeral, append to but last
        if (count($parts) > 1
            && (preg_match('/^[IVX]+$/', $parts[count($parts) - 1])
                || preg_match('/^(Jr|Sr)\.$/', $parts[count($parts) - 1]))) {
                $parts[count($parts) - 2] .=  ' ' . $parts[count($parts) - 1];
                array_pop($parts);
        }

        if (count($parts) == 1) {
            return [ $name ];
        }

        // exactly two parts, that's the easy case
        if (2 == count($parts)) {
            return [ $parts[1], $parts[0] ];
        }

        // 3 or more parts - decide which are given names
        $given = [ $parts[0] ]; // the first is always given
        $surname = [ $parts[count($parts) -1] ]; // and the last surname
        // decide where we file the others
        for ($i = 1; $i < count($parts) -1; $i++) {
            if (!self::isGiven($parts[$i])) {
                $surname = array_slice($parts, $i);
                break;
            }
            $given[] = $parts[$i];
        }

        return [ implode(' ', $surname), implode(' ', $given) ];
    }

    static function isGiven ($name) {
        static $GIVEN = null;

        if (preg_match('/^(of|to|y)$/', $name)
           || preg_match('/^[A-Z]\.?$/', $name)
           || preg_match('/^[A-Z](\.|\-)[A-Z]\.?$/', $name)) {
            return 1;
        }

        if (null === $GIVEN) {
            // read a list of given-names from a file
            $fname = dirname(__FILE__) . DIRECTORY_SEPARATOR . 'given_names.txt';
            $given_names = @file($fname);
            if (false !== $given_names) {
                foreach ($given_names as $given_names) {
                    $GIVEN[strtolower($given_names)] = true;
                }
            }
            else {
                $GIVEN = []; // do not retry if it failed
            }
        }

        return isset($GIVEN[strtolower($name)]);
    }

    // private - external need to call BiblioService::getInstance()
    private function __construct () {
    }

    static function getInstance () {
        return new BiblioService ();
    }

    protected function _prepareOptions($options, $defaultOptions) {
        if (!isset($options)) {
            $options = [];
        }

        return array_merge($defaultOptions, $options);
    }

    private function getDbConn () {
        static $dbconn = null;
        if (!isset($dbconn)) {
            $dbconn = new DB;
        }

        return $dbconn;
    }

    function buildCitation ($identifier, $options = null) {
        if (preg_match('/^\d+$/', $identifier)) {
            $dbconn = $this->getDbConn();
            $querystr = "SELECT isbn FROM Publication WHERE id=" . $identifier;

            $dbconn->query($querystr);
            if ($dbconn->next_record()) {
                $identifier = $dbconn->Record['isbn'];
            }
        }

        $record = $this->fetchByIsbn($identifier);
        if (isset($record)) {
            $ret = isset($record['author']) ? $record['author'] : $record['editor'] . ' (Hrsg.)';
            $ret .= ': '.$record['title'];

            $publisher_place_year = '';
            if (!empty($record['place'])) {
              $publisher_place_year = $record['place'];
            }

            if (!empty($record['publisher'])) {
              $publisher_place_year .= (!empty($publisher_place_year) ? ': ' : '')
                . $record['publisher'];
            }

            if (!empty($record['publication_date'])) {
              $publisher_place_year .= (!empty($publisher_place_year) ? ' ' : '')
                . $record['publication_date'];
            }

            $isbn = new ISBN($identifier);

            return $ret . (preg_match('/[\!\?\.]$/', $ret) ? '' : '.')
                 . ' ' . $publisher_place_year . '. '
                 . $isbn->getISBNDisplayable(' i:=');
        }
    }

    function fetchByIsbn ($isbn, $options = null) {
        // cache_external: 1 - overwrite, 0 - insert if not exists, -1: don't cache
        static $defaultOptions = [
            'from_db' => true,
            'from_external' => true,
            'cache_external' => 0,
        ];

        $options = $this->_prepareOptions($options, $defaultOptions);

        $isbn = self::normalizeIsbn($isbn); // remove blanks and hyphens
        if (self::validateIsbn($isbn)) {
            $isbn_version = ISBN::guessVersion($isbn);
        }

        if ($options['from_db'] || $options['cache_external'] >= 0) {
            $dbconn = $this->getDbConn();

            // first check if we have it in the database
            $isbns_sql = [ "'" . $dbconn->escape_string($isbn) . "'" ];

            // we query also for the variant
            if (isset($isbn_version)) {
                if (ISBN_VERSION_ISBN_10 == $isbn_version) {
                    $isbn_variant = ISBN::convert($isbn, $isbn_version, ISBN_VERSION_ISBN_13);
                }
                else if (ISBN_VERSION_ISBN_10 != $isbn_version) {
                    $isbn_variant = ISBN::convert($isbn, $isbn_version, ISBN_VERSION_ISBN_10);
                }
            }
            if (isset($isbn_variant)) {
                $isbns_sql[] = "'" . $dbconn->escape_string($isbn_variant) . "'";
            }
        }

        if ($options['from_db']) {
            $querystr = sprintf("SELECT Publication.ID AS source_id, Publication.isbn AS isbn, title, author, editor, publication_date, binding, publisher, Publisher.name AS publisher_name, Publication.place AS place, listprice"
                                . " FROM Publication"
                                . " LEFT OUTER JOIN Publisher ON publisher_id=Publisher.id AND Publisher.status >= 0"
                                . " WHERE Publication.isbn IN (%s) AND Publication.status <> %d AND Publisher.status >= 0 ORDER BY Publication.isbn='%s' DESC LIMIT 1",
                                implode(', ', $isbns_sql),
                                STATUS_DELETED,
                                $dbconn->escape_string($isbn));
            $dbconn->query($querystr);
            if ($dbconn->next_record()) {
                $ret = $dbconn->Record;
                if (!empty($ret['publisher_name'])) {
                    $ret['publisher'] = $ret['publisher_name'];
                }

                // clean publication_date
                if (!empty($ret['publication_date'])) {
                    // TODO: handle other dates as well
                    $ret['publication_date'] = preg_replace('/\-00\-00\b.*/', '', $ret['publication_date']);
                }
                $ret['source'] = 'from_db';

                return $ret;
            }
        }

        if ($options['from_external']) {
            $ws_amazon = new BiblioService_Amazon([ 'DE', 'US' ]);

            $level = error_reporting();
            error_reporting($level & (~E_NOTICE));
            $response_amazon = $ws_amazon->itemLookup($isbn);
            error_reporting($level);

            $ws_gbv = new BiblioService_GBV();
            $results = $ws_gbv->searchRetrieve($isbn);
            if (isset($results) && count($results) > 0) {
                $response = $results[0];
                if (!isset($response['image'])
                    && isset($response_amazon) && !empty($response_amazon['image'])) {
                    $response['image'] = $response_amazon['image'];
                }
            }
            else if (isset($response_amazon)) {
                $response = $response_amazon;
            }

            if (isset($response)) {
                // check if we have to store to db
                if (false && $options['cache_external'] >= 0) {
                    // TODO: insert/update the $response into the database
                    // TODO: do proper utf8-handling
                    $querystr = sprintf("SELECT id FROM Publication WHERE isbn IN(%s) AND status >= 0 ORDER BY isbn='%s' DESC LIMIT 1",
                                implode(', ', $isbns_sql), $dbconn->escape_string($isbn));
                    $dbconn->query($querystr);
                    $update = false;
                    if ($dbconn->next_record()) {
                        $update = $dbconn->Record['id'];
                    }
                    if (false === $update || $options['cache_external'] > 0) {
                        // we insert/update the record
                        $fields = $values = [];
                        foreach ($response as $name => $value) {
                            if (in_array($name, [ 'image' ])) {
                                // skip certain fields
                                continue;
                            }

                            $fields[] = $name;
                            if ('publication_date' == $name) {
                                $value = sprintf("%04d-00-00", $value);
                            }

                            $values[] = sprintf("'%s'", $dbconn->escape_string($value));
                        }

                        if (false !== $update) {
                            $querystr = "UPDATE Publication SET ";
                            for ($i = 0; $i < count($fields); $i++) {
                                if ($i > 0) {
                                    $querystr .= ', ';
                                }
                                $querystr .= $fields[$i] . '=' . $values[$i];
                            }
                            $querystr .= " WHERE id=" . $update;
                        }
                        else {
                            $querystr = "INSERT INTO Publication (" . implode(', ', $fields)
                                . " ) VALUES (" . implode(', ', $values) . ")";
                        }
                        // die($querystr);
                        $dbconn->query($querystr);
                    }
                }

                return $response;
            }
        }
    }
}
