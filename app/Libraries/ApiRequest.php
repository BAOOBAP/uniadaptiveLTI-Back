<?php

namespace App\Libraries;

use GuzzleHttp\Client;

class ApiRequest
{
    /**
     * This function return a web services response
     * @param string $url_lms
     * @param array $query
     * @param string $type
     *
     * @return object
     */
    public function moodleRequestWebServices(string $url_lms, array $query, $type = 'GET')
    {
        $client = new Client([
            'base_uri' => $url_lms . '/webservice/rest/server.php',
            'timeout' => 20.0,
        ]);
        $response = $client->request($type, '', $query);
        return json_decode($response->getBody());
    }
}
