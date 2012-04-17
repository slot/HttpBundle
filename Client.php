<?php
namespace Slot\HttpBundle;

use Slot\HttpBundle\HttpClientException as Exception;

use Symfony\Bridge\Monolog\Logger;
use Symfony\Component\HttpKernel\Util\Filesystem;

/**
 * A simple HTTP Client
 *
 * Client supports HTTP/HTTPS calls with methods GET and POST.
 * It also support gzip compressed responses.
 *
 * @package    Slot\HttpBundle
 * @copyright  2012 Sven Loth (sven.loth@me.com)
 * @author     svenloth
 *
 * @version 1, 09.02.12
 */
class Client
{

    const HTTP_DEFAULT_PORT = 80;
    const HTTP_DEFAULT_SSL_PORT = 443;
    const HTTP_SCHEME = 'http';
    const HTTP_SCHEME_SSL = 'https';
    const HTTP_METHOD_GET = 'GET';
    const HTTP_METHOD_POST = 'POST';
    const HTTP_METHOD_PUT = 'PUT';
    const HTTP_METHOD_DELETE = 'DELETE';
    const HTTP_METHOD_HEAD = 'HEAD';
    const HTTP_METHOD_OPTIONS = 'OPTIONS';
    const HTTP_METHOD_TRACE = 'TRACE';

    /**
     * Socket connection
     *
     * @var Resource
     */
    private $connection = null;

    /**
     * Hostname
     *
     * @var string
     */
    private $host;

    /**
     * URL path
     *
     * @var string
     */
    private $path;

    /**
     * Request Headers
     *
     * @var array
     */
    private $headers = array();

    /**
     * Complete URL with protocol and query string
     *
     * @var string
     */
    private $url;

    /**
     * Connection port
     *
     * @var string
     */
    private $port;

    /**
     * Does connection use ssl?
     *
     * @var bool
     */
    private $ssl = false;

    /**
     * Response Body
     *
     * @var String
     */
    private $responseBody;

    /**
     * Response status code
     *
     * @var string
     */
    private $responseStatusCode;

    /**
     * Response status message
     *
     * @var string
     */
    private $responseStatusMessage;

    /**
     * Response headers as key value pairs
     *
     * @var array
     */
    private $responseHeaders = array();

    /**
     * Basic auth sername
     *
     * @var string
     */
    private $user;

    /**
     * Basic auth password
     *
     * @var
     */
    private $password;

    /**
     * Query String
     *
     * @var string
     */
    private $query = '';

    /**
     * Timeout
     *
     * If not set it will be default_socket_timeout
     *
     * @var int
     */
    private $timeout;

    /**
     * Monolog instance
     *
     * @var Logger
     */
    private $logger;

    /**
     * Filesystem
     *
     * @var \Symfony\Component\Filesystem\Filesystem
     */
    protected $filesystem;


    /**
     * Request body
     *
     * @var string
     */
    private $body = '';

    /**
     * Do we accept content type gzip?
     *
     * default is false
     *
     * @var bool
     */
    private $compression = false;

    /**
     * HTTP Request method
     *
     * @var string
     */
    private $method;

    /**
     * Maximum number of redirects
     *
     * default is 3.
     *
     * @var int
     */
    private $maxRedirects = 3;

    /**
     * Number of redirects in call
     *
     * @var int
     */
    private $totalRedirects = 0;

    /**
     * Max number of retries if connection or read failed
     *
     * @var int
     */
    private $maxRetries = 3;

    /**
     * Maximum Number of retries in current request
     *
     * @var int
     */
    private $totalRetries = 0;

    /**
     * Post data as key value pairs
     *
     * @var array
     */
    private $postData = array();

    /**
     * Constructor
     *
     * @param $logger
     */
    public function __construct($logger, Filesystem $filesystem)
    {
        $this->logger = $logger;
        $this->filesystem = $filesystem;
        $this->init();
    }

    /**
     * Init a call
     *
     * @return Client
     */
    protected function init()
    {
        $this->timeout = ini_get("default_socket_timeout");

        return $this;
    }

    /**
     * Set compression on or off
     *
     * Default is on.
     *
     * @param bool $switch
     * @return Slot\HttpBundle\Client
     */
    public function compression($switch = true)
    {
        $this->compress($switch);

        return $this;
    }

    /**
     * Perform a get request
     *
     * @param $url
     * @return Client
     */
    public function get($url, $contentType='')
    {
        $this->cleanup();
        $this->method = self::HTTP_METHOD_GET;
        $this->url = $url;

        $this->call();

        return $this;
    }

    /**
     * Perform a post request
     *
     * @param $url
     * @param $data
     * @param string $contentType
     * @return Client
     */
    public function post($url, $data, $contentType = 'application/x-www-form-urlencoded')
    {
        $this->cleanup();
        $this->postData = $data;
        $this->method = self::HTTP_METHOD_POST;

        $this->addHeader('Content-Type', $contentType);

        if (!is_array($data)) {
            $length = $this->setBody($this->postData);
        } else {
            switch ($contentType)
            {
                case 'application/json':
                case 'text/json':
                    $length = $this->setBody(json_encode($this->postData));
                default:
                    $length = $this->setBody(http_build_query($this->postData));
            }
        }
        $this->addHeader('Content-Length', $length);

        $this->url = $url;

        $this->call();

        return $this;
    }

    /**
     * Perform the actual call to host
     *
     * @return Client
     */
    protected function call()
    {
        $this->prepareUrl();
        $this->connect();
        $this->fetch();
        $this->handleResponse();

        return $this;
    }

    /**
     * Connect to socket
     *
     * @return Client
     * @throws Exception
     */
    protected function connect()
    {
        $this->connection = @fsockopen(
            (($this->ssl) ? 'ssl://' : '') . $this->host,
            $this->port,
            $errno,
            $errstr,
            $this->timeout
        );

        if ($errno != 0) {
            $this->logger->crit($errstr);
            throw new Exception($errstr);
        }

        /**
         * If the value returned in errno is 0 and the function returned FALSE, it is an indication that
         * the error occurred before the connect() call. This is most likely due to a problem initializing the socket.
         *
         * @see http://de.php.net/manual/en/function.fsockopen.php
         */
        if ($this->connection === false)
        {
            if ($this->totalRetries >= $this->maxRetries)
            {
                $msg = sprintf('Socket initialization failed after %s retries.', $this->maxRetries);
                $this->logger->crit($msg);
                throw new Exception($msg);
            }

            // sleep for a while and retry
            usleep(500);
            $this->totalRetries++;

            $this->connect();
        }

        return $this;
    }

    /**
     * Get Response header
     *
     * Provide a name to retrieve specific header. If header
     * does not exist method will return null.
     *
     * If you don't provide a name it will return all headers as array.
     *
     * @param null $name
     * @return array|string|null
     */
    public function getResponseHeader($name = null)
    {
        if (is_null($name)) {
            return $this->responseHeaders;
        }
        $name = strtolower($name);

        if (!isset($this->responseHeaders[$name])) {
            return null;
        }

        return $this->responseHeaders[$name];
    }

    /**
     * Add a request header
     *
     * @param $key header name
     * @param $value header value
     */
    public function addHeader($key, $value)
    {
        $this->headers[$key] = $value;

        return $this;
    }

    /**
     * Parse and seperate URL elements and apply settings for call
     *
     * @throws HttpClientException
     * @return Client
     */
    protected function prepareUrl()
    {
        $url = trim($this->url);

        if (empty($url)) {
            throw new Exception('URL is empty.');
        }

        $this->host = parse_url($url, PHP_URL_HOST);
        $this->user = parse_url($url, PHP_URL_USER);
        $this->password = parse_url($url, PHP_URL_PASS);

        $query = parse_url($url, PHP_URL_QUERY);

        if (!empty($query))
        {
            $this->query = $query;
        }

        $path = parse_url($url, PHP_URL_PATH);

        if (empty($path)) {
            $this->path = '/';
        } else {
            $this->path = $path;
        }

        $port = parse_url($url, PHP_URL_PORT);
        $scheme = parse_url($url, PHP_URL_SCHEME);

        if (empty($port)) {
            switch ($scheme) {
                case self::HTTP_SCHEME:
                    $this->port = self::HTTP_DEFAULT_PORT;
                    break;
                case self::HTTP_SCHEME_SSL:
                    $this->port = self::HTTP_DEFAULT_SSL_PORT;
                    $this->ssl = true;
                    break;
                default:
                    throw new Exception(sprintf('Scheme "%s" is not supported.', $scheme));

            }
        } else {
            $this->port = $port;
        }

        return $this;
    }

    /**
     * Perform the HTTP call
     *
     * @return Client
     */
    protected function fetch()
    {
        $msg = sprintf(
            "%s %s%s HTTP/1.1\r\n",
            $this->method,
            $this->path,
            ($this->query) ? '?' . $this->query : ''
        );
        $msg .= sprintf("Host: %s\r\n", $this->host);
        $msg .= sprintf("User-Agent: %s\r\n", 'Slot HTTP Client for Symfony');

        if (!empty($this->user)) {
            $msg .= sprintf("Authorization: Basic %s\r\n",
                base64_encode(
                    $this->user . ':' . $this->password
                )
            );
        }

        if ($this->compression) {
            $this->addHeader('Accept-Encoding', 'gzip, deflate');
        }

        foreach ($this->headers as $key => $value)
        {
            $msg .= sprintf("%s: %s\r\n", $key, $value);
        }

        $msg .= "Connection: close\r\n\r\n";

        $msg .= $this->getBody();
        $response = '';

        fwrite($this->connection, $msg);
        while (!feof($this->connection)) {
            $response .= fgets($this->connection, 128);
        }
        fclose($this->connection);
        /* separate header and body */
        $neck = strpos($response, "\r\n\r\n");
        $head = substr($response, 0, $neck);

        $this->processHeader($head);

        $this->setResponseBody(substr($response, $neck + 4));

        return $this;

    }

    /**
     * Parse response headers
     *
     * @param $head
     * @return Client
     */
    protected function processHeader($head)
    {
        $headers = explode("\r\n", $head);

        $status = array_shift($headers);
        $status = explode(' ', substr($status, 9));
        $this->responseStatusCode = array_shift($status);
        $this->responseStatusMessage = implode(' ',$status);

        foreach ($headers as $header)
        {
            $keyvaluepair = explode(':', $header, 2);

            $this->responseHeaders[strtolower($keyvaluepair[0])] = trim($keyvaluepair[1]);
        }

        return $this;
    }

    /**
     * Check if http request was successful
     *
     * @return Client
     * @throws Exception
     */
    protected function handleResponse()
    {
        if (in_array($this->responseStatusCode, array(301,302)))
        {
            $location = $this->getResponseHeader('Location');

            if (is_null($location))
            {
                throw new Exception(sprintf('Got a HTTP status %s with no Location.', $this->getResponseStatusCode()));
            }

            $this->totalRedirects++;

            if ($this->totalRedirects >= $this->maxRedirects)
            {
                throw new Exception(sprintf('Reached number of maximum redirects (%s)', $this->maxRedirects));
            }

            $this->url = $location;
            $this->call();
        }

        if ($this->responseStatusCode >= 400) {
            $msg = sprintf('Call failed with HTTP status %s: %s',
                $this->responseStatusCode, $this->responseStatusMessage);

            $this->logger->warn($msg);
            throw new Exception($msg);
        }

        return $this;
    }

    /**
     * Resets clients attributes
     *
     * @return Client
     */
    protected function cleanup()
    {
        if(!empty($this->body)) {
            $this->headers = array();
        }
        $this->body = '';
        $this->responseBody = '';
        $this->responseHeaders = array();
        $this->ssl = false;
    }

    /**
     * Set Timeout in seconds
     *
     * @param $timeout
     */
    public function setTimeout($timeout)
    {
        $this->timeout = $timeout;

        return $this;
    }

    /**
     * Get Response Body
     *
     * @return String
     */
    public function getResponseBody()
    {
        return $this->responseBody;
    }

    /**
     * Get Response Status Code
     *
     * @return string
     */
    public function getResponseStatusCode()
    {
        return $this->responseStatusCode;
    }

    /**
     * Get Response Status Message
     *
     * @return string
     */
    public function getResponseStatusMessage()
    {
        return $this->responseStatusMessage;
    }

    /**
     * Set request body and return length
     *
     * @param $data
     * @return int length
     */
    protected function setBody($data)
    {
        $this->body = $data;
        return strlen($this->body);
    }

    /**
     * Get request body
     *
     * @return string
     */
    protected function getBody()
    {
        return $this->body;
    }

    /**
     * Set Response body and inflate if gzip-compressed
     *
     * @param $data
     * @return Client
     */
    protected function setResponseBody($data)
    {
        if ($this->getResponseHeader('Content-Encoding') == 'gzip') {
            $this->responseBody = @gzinflate(substr($data,10));
            return $this;
        }

        if ($this->getResponseHeader('Content-Encoding') == 'deflate') {
            $this->responseBody = @gzuncompress($data);
            return $this;
        }

        $this->responseBody = $data;
        return $this;
    }

    /**
     * Set max follow redirects.
     *
     * @param int $maxRedirects default 3
     */
    public function setMaxRedirects($maxRedirects)
    {
        $this->maxRedirects = $maxRedirects;
    }

    /**
     * Set max retries on connection error.
     *
     * @param int $maxRetries default 3
     */
    public function setMaxRetries($maxRetries)
    {
        $this->maxRetries = $maxRetries;
    }

    /**
     * Download file to file system
     *
     *
     * @param String $source file url
     * @param String $targetFolder default /tmp
     * @param String $targetFileName optional, if non is given a random filename will be generated
     * @throws Exception
     * @return String location of downloaded file
     */
    public function download($source, $targetFolder = '/tmp', $targetFileName = null){

        if (is_null($targetFileName))
        {
            $targetFileName = md5($source . microtime(true) . rand());
        }

        if(!is_dir($targetFolder)){
            if (!$this->filesystem->mkdir($targetFolder)) {
                $message = 'Download Error: Failed to create directory ' . $targetFolder;
                $this->logger->addCritical($message);
                throw new Exception($message);
            }
        }

        $response = $this->get($source)->getResponseBody();

        $savePath = $targetFolder . $targetFileName;

        if(file_put_contents($savePath, $response) === false) {
            $message = 'Download Error: failed to write file ' . $savePath;
            $this->logger->addCritical($message);
            throw new Exception($message);
        }

        return $savePath;
    }
}
