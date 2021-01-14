<?php

namespace OxGarage;

use \Buzz\Message\Form\FormRequest;
use \Buzz\Message\Form\FormUpload;


class Client
{
    // default is https://oxgarage.tei-c.org
    // temporary work-around was https://oxgarage-paderborn.tei-c.org
    // prod has dns issues, added ip to /etc/hosts
    // see https://listserv.brown.edu/cgi-bin/wa?A2=ind1904&L=TEI-L&P=91123
    public function __construct($server = 'https://oxgarage.tei-c.org')
    {
        $this->server = $server;
    }

    public function convert($fname,
                            $mime_to = 'docx:application:vnd.openxmlformats-officedocument.wordprocessingml.document',
                            $mime_from = 'TEI:text:xml')

    {
        $uri = $this->server
             . '/ege-webservice/Conversions/'
             . implode('/', [ urlencode($mime_from), urlencode($mime_to) ]);

        $request = new \Buzz\Message\Form\FormRequest();
        $request->fromUrl($uri);
        $request->setHeaders([ 'Accept' => '*' . '/*' ]);
        $request->setMethod('POST');
        // TODO: maybe switch to Guzzle: https://github.com/guzzle/guzzle/issues/768
        // FormUpload is needed since this is the only way that Buzz\FormRequest::isMultipart() sends true
        /*
        private function isMultipart()
        {
            foreach ($this->fields as $name => $value) {
                if (is_object($value) && $value instanceof FormUploadInterface) {
                    return true;
                }
            }

            return false;
        }
        */

        $request->setField('upload', new FormUpload(realpath($fname)));

        $client = new \Buzz\Client\Curl();

        $response = new \Buzz\Message\Response();

        $client->send($request, $response);

        $client->send($request, $response);
        if ($response->isSuccessful()) {
            $content_type = $response->getHeader('Content-Type');
            $content_disposition = $response->getHeader('Content-Disposition');
            if (!empty($content_disposition)
                && preg_match('/filename\="([^"]+)"/', $content_disposition, $matches))
            {
                $parts = explode('/', $matches[1]);
                $last = end($parts);
                $parts = explode('\\', $last);
                $last = end($parts);
                if (!empty($last)) {
                    $content_disposition = preg_replace('/filename\="([^"]+)"/',
                                                        'filename="' . $last . '"',
                                                        $content_disposition);
                    header('Content-Disposition' . ': ' . $content_disposition);
                }
            }

            header('Content-Type' . ': ' . $content_type);
            echo $response->getContent();
        }
    }
}
