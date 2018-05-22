<?php
/*
 * BiblioService_GBV.php
 *
 * Class for querying bibliographic information from the GBV
 *
 * (c) 2008-2018 daniel.burckhardt@sur-gmbh.ch
 *
 * Version: 2018-05-22 dbu
 *
 * Usage:
 *
 * $ws_gbv = new BiblioService_GBV();
 * $results = $ws_gbv->searchRetrieve('3909252133');
 *
 */

require_once 'Zend/Http/Client.php';

require_once INC_PATH . 'common/biblioservice.inc.php';


// see http://de3.php.net/ucfirst
if (!function_exists('mb_ucfirst') && function_exists('mb_substr')) {
    function mb_ucfirst($string) {
        mb_internal_encoding('UTF-8');
        $string = mb_strtoupper(mb_substr($string, 0, 1)) . mb_substr($string, 1);
        return $string;
    }
}

if (!function_exists('mb_lcfirst') && function_exists('mb_substr')) {
    function mb_lcfirst($string) {
        mb_internal_encoding('UTF-8');
        $string = mb_strtolower(mb_substr($string, 0, 1)) . mb_substr($string, 1);
        return $string;
    }
}

class BiblioService_GBV
{
    const WS_URL = 'http://sru.gbv.de/gvk';

    /**
     * Maximum number of redirects to follow during HTTP operations
     *
     * @var int
     */
    protected static $_maxRedirects = 5;

    /**
     * Client object used to communicate
     *
     * @var Zend_Http_Client
     */
    protected $_httpClient;

    /**
     * Base URL
     * TODO: Add setters and getters
     *
     * @var string
     */
    protected $_url = null;

    /**
     * Create BiblioService_GBV object
     *
     * @param Zend_Http_Client $client (optional) The HTTP client to use when
     *          when communicating with the Google servers.
     */
    public function __construct($client = null)
    {
        if ($client === null) {
            $client = new Zend_Http_Client();
        }
        $this->_httpClient = $client;
    }

    /**
     * @return string querystring
     */
    public function getQueryString($params)
    {
        $queryArray = [];
        foreach ($params as $name => $value) {
            if (substr($name, 0, 1) == '_') {
                continue;
            }
            $queryArray[] = urlencode($name) . '=' . urlencode($value);
        }
        if (count($queryArray) > 0) {
            return '?' . implode('&', $queryArray);
        } else {
            return '';
        }
    }

    /**
     * Returns the generated full query URL
     *
     * @return string The URL
     */
    public function getQueryUrl($params)
    {
        if (isset($this->_url)) {
            $url = $this->_url;
        } else {
            $url = self::WS_URL;
        }
        return $url . $this->getQueryString($params);
    }

    /**
     * Get the maximum number of redirects to follow during HTTP operations
     *
     * @return int Maximum number of redirects to follow
     */
    public static function getMaxRedirects()
    {
        return self::$_maxRedirects;
    }

    /**
     * Imports a feed located at $uri.
     *
     * @param  string $uri
     * @param  Zend_Http_Client $client The client used for communication
     * @return string
     */
    public static function import($uri, $client)
    {
        $client->resetParameters();
        $client->setHeaders('x-http-method-override', null);
        $client->setUri($uri);
        $client->setConfig(array('maxredirects' => self::getMaxRedirects()));
        $response = $client->request('GET');
        if ($response->getStatus() !== 200) {
            return FALSE;
            // TODO: maybe switch to an exception
            /* require_once 'Zend/Gdata/App/HttpException.php';
            $exception = new Zend_Gdata_App_HttpException('Expected response code 200, got ' . $response->getStatus());
            $exception->setResponse($response);
            throw $exception; */
        }
        $feedContent = $response->getBody();

        return $feedContent;
    }

    private function setResponseFromSubfields ($field, &$response, $keymap) {
        $field->registerXPathNamespace('srw', 'info:srw/schema/5/picaXML-v1.0');
        $subfields = $field->xpath('srw:subfield');
        foreach ($subfields as $subfield) {
            $code = (string)$subfield->attributes()->code;
            if ('' !== (string)$subfield && array_key_exists($code, $keymap)) {
                if (isset($response[$keymap[$code]])) {
                    // append
                    if ('string' == gettype($response[$keymap[$code]])) {
                        $response[$keymap[$code]] = array($response[$keymap[$code]]);
                    }
                    $response[$keymap[$code]][] = (string)$subfield;
                }
                else {
                    $response[$keymap[$code]] = (string)$subfield;
                }
            }
        }
    }

    private function buildRecord ($record) {
        $response = [];

        $record->registerXPathNamespace('srw', 'info:srw/schema/5/picaXML-v1.0');
        $fields = $record->xpath('srw:datafield');
        foreach ($fields as $field) {
            switch ($field->attributes()->tag) {
                case '004A':
                case '004B':
                    $this->setResponseFromSubfields($field, $response,
                                array('0' => 'isbn', 'A' => 'isbn',
                                    'g' => 'binding', 'f' => 'listprice'));
                    break;
                case '011@':
                    $this->setResponseFromSubfields($field, $response,
                                array('a' => 'publication_date'));
                    break;
                case '021A':
                    $this->setResponseFromSubfields($field, $response,
                                array('a' => 'title', 'd' => 'subtitle'));
                    break;
                case '028A':
                    $this->setResponseFromSubfields($field, $response,
                                array('d' => 'author_given', 'a' => 'author_surname'));
                    break;
                case '028B':
                    $this->setResponseFromSubfields($field, $response,
                                array('d' => 'authoradditional_given', 'a' => 'authoradditional_surname'));
                    break;
                case '028C':
                    $this->setResponseFromSubfields($field, $response,
                                array('d' => 'editor_given', 'a' => 'editor_surname'));
                    break;
                case '033A':
                    $this->setResponseFromSubfields($field, $response,
                                array('p' => 'place', 'n' => 'publisher'));
                    break;
                case '034D':
                    $this->setResponseFromSubfields($field, $response,
                                array('a' => 'pages'));
                    break;
                case '036E':
                    $this->setResponseFromSubfields($field, $response,
                                array('a' => 'series'));
                    break;
            }
        }

        // postprocesssing for title and subtitle
        foreach (array('title', 'subtitle') as $fieldname) {
            if (isset($response[$fieldname])) {
                // GBV uses @ to alphabetize differently than first word, e.g. "The @most ..."
                $response[$fieldname] = preg_replace('/@\b/', '', $response[$fieldname]);
                if ('subtitle' == $fieldname) {
                    $response[$fieldname] = mb_ucfirst($response[$fieldname]);
                }
            }
        }

        // postprocessing for author and editor

        // currently use 28B: additional only if 28A is empty
        if (isset($response['authoradditional_surname'])) {
            if (!isset($response['author_surname'])) {
                $response['author_surname'] = $response['authoradditional_surname'];
            }
            unset($response['authoradditional_surname']);
        }
        if (isset($response['authoradditional_given'])) {
            if (!isset($response['author_given'])) {
                $response['author_given'] = $response['authoradditional_given'];
            }
            unset($response['authoradditional_given']);
        }
        foreach (array('author', 'editor') as $prefix) {
            if (isset($response[$prefix.'_surname'])) {
                if ('array' != gettype($response[$prefix . '_surname'])) {
                    $response[$prefix . '_surname'] = array($response[$prefix . '_surname']);
                }
                if ('array' != gettype($response[$prefix . '_given'])) {
                    $response[$prefix . '_given'] = array($response[$prefix . '_given']);
                }
                $persons = [];
                for ($i = 0; $i < count($response[$prefix . '_surname']); $i++) {
                    $fullname = trim($response[$prefix . '_surname'][$i]);
                    if (!empty($response[$prefix . '_given'][$i])) {
                        $fullname .= ', ' . trim($response[$prefix . '_given'][$i]);
                    }
                    $persons[] = $fullname;
                }
                $response[$prefix] = implode('; ', $persons);
                unset($response[$prefix . '_surname']);
            }
            unset($response[$prefix . '_given']);
        }

        // isbn post-processing
        if (array_key_exists('isbn', $response) && 'array' == gettype($response['isbn'])) {
            $isbns = array_unique($response['isbn']); // throw away duplicates
            sort($isbns);
            // take lowest for older books, highest (hopefully isbn-13) for newer
            $response['isbn'] = array_key_exists('publication_date', $response)
                && $response['publication_date'] < 2007
                ? $isbns[0] : $isbns[count($isbns) - 1];
        }
        if (array_key_exists('isbn', $response)) {
            $response['isbn'] = BiblioService::normalizeIsbn($response['isbn']);
        }
        if (array_key_exists('binding', $response) && is_array($response['binding'])) {
            $count_before = count($response['binding']);
            $response['binding'] = array_unique($response['binding']);
            if (count($response['binding']) != $count_before
                && array_key_exists('listprice', $response) && is_array($response['listprice'])) {
                $response['listprice'] = array_unique($response['listprice']);
            }
            $response['binding'] = implode(', ', $response['binding']);
        }
        if (array_key_exists('listprice', $response) && is_array($response['listprice'])) {
            $response['listprice'] = implode(', ', $response['listprice']);
        }

        if (array_key_exists('listprice', $response)) {
            $response['listprice'] = preg_replace('/Eur\b/', 'EUR', $response['listprice']);
        }

        return $response;
    }

    public function searchRetrieve($query, $params = []) {
        if (is_string($query)) {
            // TODO: check if it is an isbn
            $query = 'pica.isb=' . preg_replace('/[^0-9X]/', '', $query);
        }
        else {
            die('Other query-types than isbns not implemented yet');
        }

        $params = array('query' => $query,
                        'version' => '1.1',
                        'operation' => 'searchRetrieve',
                        'recordSchema' => 'picaxml',
                        'maximumRecords' => 10,
                        'startRecord' => 1,
                        'recordPacking' => 'xml');

        $uri = $this->getQueryUrl($params);

        $xml = self::import($uri, $this->_httpClient);

        if (FALSE !== $xml) {
            $result = simplexml_load_string($xml);
            $result->registerXPathNamespace('zs', 'http://www.loc.gov/zing/srw/');
            $result->registerXPathNamespace('srw', 'info:srw/schema/5/picaXML-v1.0');

            list($count) = $result->xpath('//zs:numberOfRecords[1]');
            if (!isset($count)) {
                return;
            }

            $results = [];
            if ((int)$count <= 0) {
                return $results;
            }

            foreach ($result->xpath('//zs:recordData/srw:record') as $record) {
                $results[] = $this->buildRecord($record);
            }

            return $results;
        }
    }

}
