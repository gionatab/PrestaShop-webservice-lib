<?php
namespace Up3Up\PrestashopClient;

use Up3Up\PrestashopClient\Exceptions\PrestashopClientException;
use Up3Up\PrestashopClient\Exceptions\PrestashopResponseException;

class Client {
    
    /**
     * Un'istanza di un client GuzzleHttp 
     *
     * @var \GuzzleHttp\Client
     */
    protected $client;

    /** La chiave di autenticazione per l'API */
    protected $key;

    /** Viene salvato l'ultimo metodo usato, es. GET */
    protected $lastRequestMethod;

    /** Viene salvata l'ultima risorsa richiesta, es. categories/20 */
    protected $lastRequestUri;

    public function __construct($base_uri, $key)
    {
        $this->key = $key;
        $base_uri = trim($base_uri, '/').'/api/';
        $this->client = new \GuzzleHttp\Client(['base_uri' => $base_uri]);
        $this->lastRequestMethod = '';
        $this->lastRequestUri = '';
        
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

    /**
     * Prende la risposta ricevuta dalla richiesta, e la divide in 3 casi in base al codice della risposta:
     * - 404: la risorsa esiste, ma l'elemento non è stato trovato, viene restituito NULL;
     * - 200: la risorsa esiste, e l'elemento è stato trovato. Se il parse del corpo della risposta è stato fatto correttamente, allora restituiamo un XML (SimpleXML);
     * - altro: Qualsiasi tipo di codice errore al di fuori di 200 e 404, oppure un errore nel parsing del contenuto della risposta, risulta in una eccezione che fornisce dettagli sull'errore.
     * 
     * @param \Psr\Http\Message\ResponseInterface $response La risposta HTTP ricevuta in seguito a una richiesta.
     * @return SimpleXMLElement|null Se tutto va bene e la risposta ha codice 200, viene restituito un SimpleXMLElement (un oggetto che rappresenta un XML). Se la risposta ha codice 404 viene restiuito NULL poiché la risorsa non esiste.
     * @throws PrestashopClientException Fornisce informazioni su cosa è andato storto nella richiesta e/o nella lettura della risposta.
     */
    protected function elaborateResponse(\Psr\Http\Message\ResponseInterface $response) {
        $error = false;
        if($response->getStatusCode() == 404) {
            return null;
        }
        else {
            try {
                $xml = $this->parseXML($response->getBody());
                if(isset($xml->errors->error->message)) { //Non controlliamo il codice di errore, sembra stupido ma non si sa mai restituisce un codice 200 OK con un messaggio di errore nel contenuto...
                    $message = (string) $xml->errors->error->message;
                    $error = true;
                }
            } catch (PrestashopResponseException $e){
                $message = 'Errore nell\'analisi del contenuto della risposta.';
                $error = true;
            }
        }
        if($response->getStatusCode() == 200 && !$error) {
            return $xml;
        }
        else {      
            throw new PrestashopClientException($response->getStatusCode(), $response->getReasonPhrase(), $this->lastRequestMethod, $this->lastRequestUri, $message);
        }
    }

    /**
     * Esegue una richiesta GET.
     * l'URI non è altro che l'endpoint della REST API, ed è compreso di due elementi: {risorsa}/{id}. Nel caso id sia mancante, è possibile eseguire un GET con {risorsa} per ottenere la lista di tutti gli elementi. ATTENZIONE: l'URI non deve esere l'URL completo, quindi l'URI base del sito non deve essere indicato. In un Prestashop, un URI base è composto da {link_negozio}/api e non deve essere indicato nell'URI.
     * I parametri non sono altro che gli elementi della query, ovvero tutta la parte dopo il simbolo ? appeso alla fine dell'URI. I parametri vengono passati come coppia chiave/valore, esempio: 'display' => 'full' si trasforma in "{uri}?display=full.
     *
     * @param string $uri l'endpoint che indica quale risorsa/elemento richiedere.
     * @param array $params eventuali parametri della query
     * @return SimpleXMLElement|null Un XML se la risorsa è stata trovata, altrimenti NULL.
     * @throws PrestashopClientException Fornisce informazioni su cosa è andato storto nella richiesta e/o nella lettura della risposta.
     */
    public function get(string $uri, $params = []) {
        $this->lastRequestMethod = 'GET';
        $this->lastRequestUri = $uri;
        $response = $this->client->get($uri, $this->buildOptions($params));
        return $this->elaborateResponse($response);
    }

    public function post($uri, $body, $params = []) {
        $this->lastRequestMethod = 'POST';
        $this->lastRequestUri = $uri;
        $options = $this->buildOptions($params);
        $options['body'] = $body;
        $response = $this->client->post($uri, $options);
        return $this->elaborateResponse($response);
    }

    public function put($uri, $body, $params = []) {
        
    }

    public function delete($uri) {

    }

    protected function buildOptions($params = []) {
        $options = [
            'auth' => [$this->key, ''],
            'http_errors' => false //Disattiva eccezioni della libreria, la gestiamo noi.
        ];
        if(!empty($params)) {
            $options['query'] = $params;
        }
        return $options;
    }
}
?>