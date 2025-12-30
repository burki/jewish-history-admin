<?php

namespace OxGarage;

use \Buzz\Message\Form\FormRequest;
use \Buzz\Message\Form\FormUpload;


class Client
{
    protected $server;

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
                            $mime_from = 'TEI:text:xml',
                            $streamToClient = true)

    {
        $uri = $this->server
             . '/ege-webservice/Conversions/'
             . implode('/', [ urlencode($mime_from), urlencode($mime_to) ]);

        // $uri = 'https://echo.free.beeceptor.com'; // for debugging

        $headers = [ 'Accept' => '*' . '/*' ];

        $client = new \GuzzleHttp\Client();
        $response = $client->post($uri, [
            'headers' => $headers,
            'multipart' => [
                [
                    'name'     => 'upload',
                    'contents' => file_get_contents($fname),
                    'filename' => basename($fname),
                    'headers'  => [ 'Content-Type' => 'text/xml' ],
                ],
            ],
        ]);

        if ('OK' == $response->getReasonPhrase()) {
            if ($streamToClient) {
                $content_type = $response->getHeader('Content-Type')[0];
                $content_disposition = $response->getHeader('Content-Disposition')[0];
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

                echo $response->getBody();

                return;
            }

			return $response->getBody();
        }
    }
}
