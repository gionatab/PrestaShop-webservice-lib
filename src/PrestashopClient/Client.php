<?php
namespace Up3Up\PrestashopClient;

use Up3Up\PrestashopClient\Exceptions\PrestashopClientException;
use Up3Up\PrestashopClient\Exceptions\PrestashopResponseException;

class Client {
    protected $client;
    protected $key;
    public function __construct($base_uri, $key)
    {
        $this->key = $key;
        $this->client = new \GuzzleHttp\Client(['base_uri' => $base_uri]);
        
    }

    /**
     * Load XML from string. Can throw exception
     *
     * @param string $response String from a CURL response
     *
     * @return SimpleXMLElement status_code, response
     * @throws PrestaShopWebserviceException
     */
    protected function parseXML($body)
    {
        if ($body != '') {
            libxml_clear_errors();
            libxml_use_internal_errors(true);
            $xml = simplexml_load_string(trim($body), 'SimpleXMLElement', LIBXML_NOCDATA);
            if (libxml_get_errors()) {
                $msg = var_export(libxml_get_errors(), true);
                libxml_clear_errors();
                throw new PrestashopResponseException('HTTP XML response is not parsable: ' . $msg);
            }
            return $xml;
        } else {
            throw new PrestashopResponseException('HTTP XML response is empty');
        }
    }

    protected function elaborateResponse($uri, \Psr\Http\Message\ResponseInterface $response) {
        if($response->getStatusCode() == 200) {
            $xml = $this->parseXML($response->getBody());
            return $xml;
        }
        else if($response->getStatusCode() == 404) {
            return false;
        }
        else {      
            try {
                $xml = $this->parseXML($response->getBody());
                if(isset($xml->prestashop->errors->error->message)) {
                    $message = (string) $xml->prestashop->errors->error->message;
                }
            } catch (PrestashopResponseException $e){
                $message = 'Risposta senza contenuto.';
            }
            throw new PrestashopClientException($response->getStatusCode(), $response->getReasonPhrase(), 'GET', $uri, $message);
        }
    }

    public function get($uri, $params = []) {
        $response = $this->client->get($uri, $this->buildOptions($params));
        return $this->elaborateResponse($uri, $response);
    }

    public function post($uri, $body, $params = []) {
        //$response = $this->client
    }

    public function put($uri, $body, $params = []) {
        
    }

    public function delete($uri) {

    }

    protected function buildOptions($params = []) {
        $options = [
            'auth' => [$this->key, '']
        ];
        if(!empty($params)) {
            $options['query'] = $params;
        }
        return $options;
    }
}
?>