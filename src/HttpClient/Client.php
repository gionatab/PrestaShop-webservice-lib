<?php

namespace Up3Up\Prestashop\HttpClient;

use GuzzleHttp\TransferStats;
use SimpleXMLElement;
use TypeError;
use Up3Up\Prestashop\HttpClient\Exceptions\PrestashopClientException;
use Up3Up\Prestashop\HttpClient\Exceptions\PrestashopResponseException;

class Client
{

    /**
     * Un'istanza di un client GuzzleHttp 
     *
     * @var \GuzzleHttp\Client
     */
    protected $client;

    /**
     * La chiave di autenticazione per l'API
     *
     * @var string
     */
    protected $key;

    /**
     * Viene salvato l'ultimo metodo usato, es. GET
     *
     * @var string
     */
    protected $lastRequestMethod;

    /**
     * Viene salvata l'ultima risorsa richiesta, es. categories/20
     *
     * @var string
     */
    protected $lastRequestUri;

    /**
     * Viene salvata l'ultima lista di parametri
     *
     * @var string[]
     */
    protected $lastRequestParams;

    /**
     * Salva le statistiche relative all'ultima richiesta
     *
     * @var TransferStats
     */
    protected $lastRequestStats;
    /**
     * Crea un client per connettersi al Prestashop Webservice API.
     * Sono due le informazioni necessarie:
     * - l'URI base del sito, ovvero la parte dell'URL che rappresenta la root del sito (solitamente la stessa URL usata per la home del frontend)
     * - la chiave API
     *
     * @param string $base_uri l'URI base del sito, ovvero la parte dell'URL che rappresenta la root del sito (solitamente la stessa URL usata per la home del frontend)
     * @param string $key la chiave del servizio API
     */
    public function __construct(string $base_uri, string $key)
    {
        $this->key = $key;
        $base_uri = trim($base_uri, '/') . '/api/';
        $this->client = new \GuzzleHttp\Client(['base_uri' => $base_uri]);
        $this->lastRequestMethod = '';
        $this->lastRequestUri = '';
    }

    /**
     * Fornisce accesso all'oggetto TranserStats relativo all'ultima richiesta. 
     * In questo modo è possibile accedere a tutte le statistiche relative alla richiesta appena completata.
     *
     * @return TransferStats|bool l'oggetto contenente le statistiche dell'ultima richiesta, o FALSE se tale oggetto non è impostato.
     */
    public function getStatsObj(): TransferStats|bool
    {
        if(! $this->lastRequestStats instanceof TransferStats) {
            return false;
        }
        return $this->lastRequestStats;
    }

    /**
     * Permette di accedere al valore di una statistica salvata nell'oggetto TransferStats.
     * $stat deve essere una stringa rappresentante il nome della statistica, con le parole separate dal trattino, '-'.
     * Es. per ottenere il tempo di esecuzione della richiesta, bisogna chiamare la funzione TransferStats::getTransferTime(). 
     * Usando invece getStats è possibile ottenere il valore del tempo di esecuzione con la seguente chiamata: getStats('transfer-time');
     * 
     * Nomi di alcune statistiche:
     * - transfer-time: tempo di esecuzione richiesta;
     * - effective-uri: l'URI completo della richiesta, compreso di uri base (l'indirizzo del sito o hostname, solitamente).
     *
     * @param  string $stat Il nome della statistica da prendere, con le parole separate dal trattino '-'.
     * @param  bool   $toString TRUE se la statistica deve essere data come stringa (alcune statistiche sono oggetti), FALSE per ottenere la statistica nativa.
     * 
     * @return mixed Il valore della statistica, NULL se l'oggetto delle statistiche non è impostato, FALSE se la funzione dell'oggetto ha avuto un errore (es. se la statistica non esiste).
     */
    public function getStats(string $stat, bool $toString = true): mixed
    {
        if(! $this->lastRequestStats instanceof TransferStats) {
            return null;
        }
        $parts = explode('-', trim($stat));
        $functionName = "get";
        foreach($parts as $part) {
            $functionName .= ucfirst($part);
        }
        try {
            $result =  call_user_func([$this->lastRequestStats, $functionName]);
            return $toString ? (string) $result : $result;
        }
        catch(TypeError $ex) {
            return false;
        }
    }

    /**
     * Load XML from string.
     * Can throw exceptions, usually if the string can't be parsed.
     *
     * @param string $response String from an HTTP response
     *
     * @return SimpleXMLElement The body of the response parsed as XML.
     * @throws PrestashopResponseException
     */
    protected function parseXML(string $body): SimpleXMLElement
    {
        if ($body != '') {
            libxml_clear_errors();
            libxml_use_internal_errors(true);
            $body = $this->cleanXMLString($body);
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
     * Fixes parsing errors from SimpleXML when trying to parse a string in UTF-8 with non-supported characters.
     * 
     * It does not check if the string is well formatted for XML.
     *
     * @param  string $string Any string that should be "cleaned" of non-supported characters for the XML format
     *
     * @return string a cleaned string that can be parsed to XML.
     */
    protected function cleanXMLString(string $string): string
    {
        return preg_replace('/[^\x{0009}\x{000a}\x{000d}\x{0020}-\x{D7FF}\x{E000}-\x{FFFD}]+/u', ' ', $string);
    }


    /**
     * Prende la risposta ricevuta dalla richiesta, e la divide in 3 casi in base al codice della risposta:
     * - 404: la risorsa esiste, ma l'elemento non è stato trovato, viene restituito NULL;
     * - 200: la risorsa esiste, e l'elemento è stato trovato. Se il parse del corpo della risposta è stato fatto correttamente, allora restituiamo un XML (SimpleXML);
     * - altro: Qualsiasi tipo di codice errore al di fuori di 200 e 404, oppure un errore nel parsing del contenuto della risposta, risulta in una eccezione che fornisce dettagli sull'errore.
     * 
     * @param \Psr\Http\Message\ResponseInterface $response La risposta HTTP ricevuta in seguito a una richiesta.
     * @return SimpleXMLElement|null|bool Se tutto va bene e la risposta ha codice 200 con contenuto, viene restituito un SimpleXMLElement (un oggetto che rappresenta un XML). Se la risposta ha codice 404 viene restiuito NULL poiché la risorsa non esiste. Se la risposta ha codice 200 ma content-length = 0, allora restituisce 'true' poiché la richiesta ha avuto successo e non ci si aspetta informazioni di risposta (come nel caso di un DELETE).
     * @throws PrestashopClientException Fornisce informazioni su cosa è andato storto nella richiesta e/o nella lettura della risposta.
     */
    protected function elaborateResponse(\Psr\Http\Message\ResponseInterface $response): SimpleXMLElement|null|bool
    {
        $error = false;
        $message = 'UNKNOWN';
        $status_code = $response->getStatusCode();
        if ($status_code == 404) {
            return null;
        }
        /* In alcuni casi come richieste DELETE, la risposta è 200 OK senza contenuto, quindi se non ci aspettiamo niente nel body (Content-Length è zero) e riceviamo un OK allora la risposta è completata correttamente. */ else if ($status_code == 200 && isset($response->getHeader('Content-Length')[0]) && $response->getHeader('Content-Length')[0] == 0) {
            return true;
        } else {
            try {
                $xml = $this->parseXML($response->getBody());
                if (isset($xml->errors->error->message)) { //Non controlliamo il codice di errore, sembra stupido ma non si sa mai restituisce un codice 200 OK con un messaggio di errore nel contenuto...
                    $message = (string) $xml->errors->error->message;
                    $error = true;
                }
            } catch (PrestashopResponseException $e) {
                $message = 'Errore nell\'analisi del contenuto della risposta.';
                $error = true;
            }
        }
        if ($status_code >= 200 && $status_code < 300 && !$error) {
            return $xml;
        } else {
            throw new PrestashopClientException($response->getStatusCode(), $response->getReasonPhrase(), $this->lastRequestMethod, $this->lastRequestUri, $this->lastRequestParams, $message);
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
    public function get(string $uri, array $params = []): SimpleXMLElement|null|bool
    {
        $this->lastRequestMethod = 'GET';
        $this->lastRequestUri = $uri;
        $options = $this->buildOptions($params);
        $this->lastRequestParams = $params;
        $response = $this->client->get($uri, $options);
        return $this->elaborateResponse($response);
    }

    /**
     * Esegue una richiesta POST.
     * POST viene usato per creare una nuova risorsa, l'endpoint deve quindi essere sempre una risorsa, e non un elemento indicato dall'ID, esempio: {risorsa}, 'customers'.
     * I parametri servono soprattutto in multinegozio per indicare per quale id_shop o id_shop_group creare la risorsa.
     * 
     * @param string $uri l'endpoint che indica quale risorsa creare.
     * @param string $body un XML sintatticamente e strutturalmente corretto rappresentato come stringa.
     * @param array  $params Con il multinegozio attivo, è possibile specificare in quale negozio o gruppo di negozi creare la risorsa. 
     * @return SimpleXMLElement La nuova risorsa appena creata. NULL e BOOL non dovrebbero mai essere restituiti a meno che qualcosa non sia andato storto sul server.
     * @throws PrestashopClientException Fornisce informazioni su cosa è andato storto nella richiesta e/o nella lettura della risposta.
     */
    public function post(string $uri, string $body, array $params = []): SimpleXMLElement|null|bool
    {
        $this->lastRequestMethod = 'POST';
        $this->lastRequestUri = $uri;
        $options = $this->buildOptions($params);
        $options['body'] = $body;
        $this->lastRequestParams = $params;
        $response = $this->client->post($uri, $options);
        return $this->elaborateResponse($response);
    }

    /**
     * Esegue una richiesta PUT.
     * PUT viene usato per aggiornare una risorsa già esistente, l'endpoint deve specificare la risorsa e l'id dell'elemento da sovrascrivere, esempio: {risorsa}/{id}, 'customers/7'.
     * I parametri servono soprattutto in multinegozio per indicare per quale id_shop o id_shop_group modificare la risorsa.
     *
     * @param string $uri l'endpoint che indica quale elemento modificare.
     * @param string $body un XML sintatticamente e strutturalmente corretto rappresentato come stringa.
     * @param array  $params Con il multinegozio attivo, è possibile specificare in quale negozio o gruppo di negozi modificare la risorsa. 
     * @return SimpleXMLElement La nuova risorsa appena modificata. NULL e BOOL non dovrebbero mai essere restituiti a meno che qualcosa non sia andato storto sul server.
     * @throws PrestashopClientException Fornisce informazioni su cosa è andato storto nella richiesta e/o nella lettura della risposta.
     */
    public function put(string $uri, string $body, array $params = []): SimpleXMLElement|null|bool
    {
        $this->lastRequestMethod = 'PUT';
        $this->lastRequestUri = $uri;
        $options = $this->buildOptions($params);
        $options['body'] = $body;
        $this->lastRequestParams = $params;
        $response = $this->client->put($uri, $options);
        return $this->elaborateResponse($response);
    }

    /**
     * Esegue una richiesta DELETE.
     * DELETE viene usato per cancellare un elemento di una risorsa, l'endpoint deve specificare la risorsa e l'id dell'elemento da cancellare. esempio: {risorsa}/{id}, 'customers/7'.
     * La risposta nel caso di successo usa stato 200 ma non contiene un body.
     *
     * @param string $uri l'endpoint che indica quale elemento da cancellare.
     * @return null|boolean 'true' se l'elemento è stato cancellato, NULL se non esiste.
     * @param array  $params Con il multinegozio attivo, è possibile specificare su quale negozio o gruppo di negozi cancellare la risorsa.
     * @throws PrestashopClientException Fornisce informazioni su cosa è andato storto nella richiesta e/o nella lettura della risposta.
     */
    public function delete(string $uri, array $params = []): SimpleXMLElement|null|bool
    {
        $this->lastRequestMethod = 'DELETE';
        $this->lastRequestUri = $uri;
        $options = $this->buildOptions($params);
        $this->lastRequestParams = $params;
        $response = $this->client->delete($uri, $options);
        return $this->elaborateResponse($response);
    }

    /**
     * Crea un array con le opzioni standard, come l'autenticazione.
     * Inoltre aggiunge eventuali parametri di query.
     *
     * @param array $params I parametri di query per la richiesta, opzionale.
     * @return array Una lista di opzioni per la richiesta.
     */
    protected function buildOptions(array $params = []): array
    {
        $options = [
            'auth' => [$this->key, ''],
            'http_errors' => false, //Disattiva eccezioni della libreria, la gestiamo noi.
            'on_stats' => function (TransferStats $stats) {
                $this->lastRequestStats = $stats;
            }
        ];
        if (!empty($params)) {
            $options['query'] = $params;
        }
        return $options;
    }
}
