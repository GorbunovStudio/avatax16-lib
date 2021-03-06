<?php
/**
 * OnePica
 * NOTICE OF LICENSE
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to codemaster@onepica.com so we can send you a copy immediately.
 *
 * @category  OnePica
 * @package   OnePica_AvaTax16
 * @copyright Copyright (c) 2016 One Pica, Inc. (http://www.onepica.com)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
namespace OnePica\AvaTax16\IO;

/**
 * Class \OnePica\AvaTax16\IO\Curl
 */
class Curl
{
    /**
     * Version
     */
    const VERSION = '1.0.0';

    /**
     * Default timeout
     */
    const DEFAULT_TIMEOUT = 30;

    /**
     * cUrl resource.
     *
     * @var resource
     */
    protected $curl;

    /**
     * Id
     *
     * @var string
     */
    protected $id = null;

    /**
     * Error
     *
     * @var bool
     */
    protected $error = false;

    /**
     * Error code
     *
     * @var int
     */
    protected $errorCode = 0;

    /**
     * Error message
     *
     * @var string
     */
    protected $errorMessage = null;

    /**
     * Curl error
     *
     * @var bool
     */
    protected $curlError = false;

    /**
     * Curl error code
     *
     * @var int
     */
    protected $curlErrorCode = 0;

    /**
     * Curl error message
     *
     * @var string
     */
    protected $curlErrorMessage = null;

    /**
     * Http error
     *
     * @var bool
     */
    protected $httpError = false;

    /**
     * Http status code
     *
     * @var int
     */
    protected $httpStatusCode = 0;

    /**
     * Http error message
     *
     * @var string
     */
    protected $httpErrorMessage = null;

    /**
     * Base url
     *
     * @var string
     */
    protected $baseUrl = null;

    /**
     * Url
     *
     * @var string
     */
    protected $url = null;

    /**
     * Request headers
     *
     * @var array
     */
    protected $requestHeaders = null;

    /**
     * Response headers
     *
     * @var array
     */
    protected $responseHeaders = null;

    /**
     * Raw response headers
     *
     * @var string
     */
    protected $rawResponseHeaders = '';

    /**
     * Response
     *
     * @var mixed
     */
    protected $response = null;

    /**
     * Raw response
     *
     * @var string
     */
    protected $rawResponse = null;

    /**
     * Before send function
     *
     * @var string
     */
    protected $beforeSendFunction = null;

    /**
     * Download complete function
     *
     * @var string
     */
    protected $downloadCompleteFunction = null;

    /**
     * Success function
     *
     * @var string
     */
    protected $successFunction = null;

    /**
     * Error function
     *
     * @var string
     */
    protected $errorFunction = null;

    /**
     * Complete function
     *
     * @var string
     */
    protected $completeFunction = null;

    /**
     * Cookies
     *
     * @var array
     */
    protected $cookies = array();

    /**
     * Response cookies
     *
     * @var array
     */
    protected $responseCookies = array();

    /**
     * Headers
     *
     * @var array
     */
    protected $headers = array();

    /**
     * Options
     *
     * @var array
     */
    protected $options = array();

    /**
     * Options
     *
     * @var function
     */
    protected $jsonDecoder = null;

    /**
     * Json pattern
     *
     * @var string
     */
    protected $jsonPattern = '/^(?:application|text)\/(?:[a-z]+(?:[\.-][0-9a-z]+){0,}[\+\.]|x-)?json(?:-[a-z]+)?/i';

    /**
     * XML pattern
     *
     * @var string
     */
    protected $xmlPattern = '~^(?:text/|application/(?:atom\+|rss\+)?)xml~i';

    /**
     * Construct
     *
     * @access public
     * @param  $base_url
     * @throws \ErrorException
     */
    public function __construct($base_url = null)
    {
        if (!extension_loaded('curl')) {
            throw new \ErrorException('cURL library is not loaded');
        }

        $this->curl = curl_init();
        $this->id = 1;
        $this->setDefaultUserAgent();
        $this->setDefaultJsonDecoder();
        $this->setDefaultTimeout();
        $this->setOpt(CURLINFO_HEADER_OUT, true);
        $this->setOpt(CURLOPT_HEADERFUNCTION, array($this, 'headerCallback'));
        $this->setOpt(CURLOPT_RETURNTRANSFER, true);
        $this->setOpt(CURLOPT_SSL_VERIFYPEER, false);
        $this->headers = new CaseInsensitiveArray();
        $this->setURL($base_url);
    }

    /**
     * Build Post Data
     *
     * @access public
     * @param  $data
     *
     * @return array|string
     */
    public function buildPostData($data)
    {
        if (is_array($data)) {
            if (self::is_array_multidim($data)) {
                if (isset($this->headers['Content-Type']) &&
                    preg_match($this->jsonPattern, $this->headers['Content-Type'])) {
                    $json_str = json_encode($data);
                    if (!($json_str === false)) {
                        $data = $json_str;
                    }
                } else {
                    $data = self::http_build_multi_query($data);
                }
            } else {
                $binary_data = false;
                foreach ($data as $key => $value) {
                    // Fix "Notice: Array to string conversion" when $value in
                    // curl_setopt($ch, CURLOPT_POSTFIELDS, $value) is an array
                    // that contains an empty array.
                    if (is_array($value) && empty($value)) {
                        $data[$key] = '';
                        // Fix "curl_setopt(): The usage of the @filename API for
                        // file uploading is deprecated. Please use the CURLFile
                        // class instead".
                    } elseif (is_string($value) && strpos($value, '@') === 0) {
                        $binary_data = true;
                        if (class_exists('CURLFile')) {
                            $data[$key] = new \CURLFile(substr($value, 1));
                        }
                    } elseif ($value instanceof \CURLFile) {
                        $binary_data = true;
                    }
                }

                if (!$binary_data) {
                    if (isset($this->headers['Content-Type']) &&
                        preg_match($this->jsonPattern, $this->headers['Content-Type'])) {
                        $json_str = json_encode($data);
                        if (!($json_str === false)) {
                            $data = $json_str;
                        }
                    } else {
                        $data = http_build_query($data, '', '&');
                    }
                }
            }
        }

        return $data;
    }

    /**
     * Call
     *
     * @access public
     */
    public function call()
    {
        $args = func_get_args();
        $function = array_shift($args);
        if (is_callable($function)) {
            array_unshift($args, $this);
            call_user_func_array($function, $args);
        }
    }

    /**
     * Close
     *
     * @access public
     */
    public function close()
    {
        if (is_resource($this->curl)) {
            curl_close($this->curl);
        }
        $this->options = null;
        $this->jsonDecoder = null;
    }

    /**
     * Complete
     *
     * @access public
     * @param  $callback
     */
    public function complete($callback)
    {
        $this->completeFunction = $callback;
    }

    /**
     * Progress
     *
     * @access public
     * @param  $callback
     */
    public function progress($callback)
    {
        $this->setOpt(CURLOPT_PROGRESSFUNCTION, $callback);
        $this->setOpt(CURLOPT_NOPROGRESS, false);
    }

    /**
     * Delete
     *
     * @access public
     * @param  $url
     * @param  $query_parameters
     * @param  $data
     *
     * @return string
     */
    public function delete($url, $query_parameters = array(), $data = array())
    {
        if (is_array($url)) {
            $data = $query_parameters;
            $query_parameters = $url;
            $url = $this->baseUrl;
        }

        $this->setURL($url, $query_parameters);
        $this->setOpt(CURLOPT_CUSTOMREQUEST, 'DELETE');
        $this->setOpt(CURLOPT_POSTFIELDS, $this->buildPostData($data));
        return $this->exec();
    }

    /**
     * Download Complete
     *
     * @access public
     * @param  $fh
     */
    public function downloadComplete($fh)
    {
        if (!$this->error && $this->downloadCompleteFunction) {
            rewind($fh);
            $this->call($this->downloadCompleteFunction, $fh);
            $this->downloadCompleteFunction = null;
        }

        if (is_resource($fh)) {
            fclose($fh);
        }

        // Fix "PHP Notice: Use of undefined constant STDOUT" when reading the
        // PHP script from stdin. Using null causes "Warning: curl_setopt():
        // supplied argument is not a valid File-Handle resource".
        if (!defined('STDOUT')) {
            define('STDOUT', fopen('php://stdout', 'w'));
        }

        // Reset CURLOPT_FILE with STDOUT to avoid: "curl_exec(): CURLOPT_FILE
        // resource has gone away, resetting to default".
        $this->setOpt(CURLOPT_FILE, STDOUT);

        // Reset CURLOPT_RETURNTRANSFER to tell cURL to return subsequent
        // responses as the return value of curl_exec(). Without this,
        // curl_exec() will revert to returning boolean values.
        $this->setOpt(CURLOPT_RETURNTRANSFER, true);
    }

    /**
     * Download
     *
     * @access public
     * @param  $url
     * @param  $mixed_filename
     *
     * @return boolean
     */
    public function download($url, $mixed_filename)
    {
        if (is_callable($mixed_filename)) {
            $this->downloadCompleteFunction = $mixed_filename;
            $fh = tmpfile();
        } else {
            $filename = $mixed_filename;
            $fh = fopen($filename, 'wb');
        }

        $this->setOpt(CURLOPT_FILE, $fh);
        $this->get($url);
        $this->downloadComplete($fh);

        return !$this->error;
    }

    /**
     * Error
     *
     * @access public
     * @param  $callback
     */
    public function error($callback)
    {
        $this->errorFunction = $callback;
    }

    /**
     * Exec
     *
     * @access public
     * @param  $ch
     *
     * @return string
     */
    public function exec($ch = null)
    {
        $this->responseCookies = array();
        if (!($ch === null)) {
            $this->rawResponse = curl_multi_getcontent($ch);
        } else {
            $this->call($this->beforeSendFunction);
            $this->rawResponse = curl_exec($this->curl);
            $this->curlErrorCode = curl_errno($this->curl);
        }
        $this->curlErrorMessage = curl_error($this->curl);
        $this->curlError = !($this->curlErrorCode === 0);
        $this->httpStatusCode = curl_getinfo($this->curl, CURLINFO_HTTP_CODE);
        $this->httpError = in_array(floor($this->httpStatusCode / 100), array(4, 5));
        $this->error = $this->curlError || $this->httpError;
        $this->errorCode = $this->error ? ($this->curlError ? $this->curlErrorCode : $this->httpStatusCode) : 0;

        // NOTE: CURLINFO_HEADER_OUT set to true is required for requestHeaders
        // to not be empty (e.g. $curl->setOpt(CURLINFO_HEADER_OUT, true);).
        if ($this->getOpt(CURLINFO_HEADER_OUT) === true) {
            $this->requestHeaders = $this->parseRequestHeaders(curl_getinfo($this->curl, CURLINFO_HEADER_OUT));
        }
        $this->responseHeaders = $this->parseResponseHeaders($this->rawResponseHeaders);
        list($this->response, $this->rawResponse) = $this->parseResponse($this->responseHeaders, $this->rawResponse);

        $this->httpErrorMessage = '';
        if ($this->error) {
            if (isset($this->responseHeaders['Status-Line'])) {
                $this->httpErrorMessage = $this->responseHeaders['Status-Line'];
            }
        }
        $this->errorMessage = $this->curlError ? $this->curlErrorMessage : $this->httpErrorMessage;

        if (!$this->error) {
            $this->call($this->successFunction);
        } else {
            $this->call($this->errorFunction);
        }

        $this->call($this->completeFunction);

        return $this->response;
    }

    /**
     * Get
     *
     * @access public
     * @param  $url
     * @param  $data
     *
     * @return string
     */
    public function get($url, $data = array())
    {
        if (is_array($url)) {
            $data = $url;
            $url = $this->baseUrl;
        }
        $this->setURL($url, $data);
        $this->setOpt(CURLOPT_CUSTOMREQUEST, 'GET');
        $this->setOpt(CURLOPT_HTTPGET, true);
        return $this->exec();
    }

    /**
     * Get Opt
     *
     * @access public
     * @param  $option
     *
     * @return mixed
     */
    public function getOpt($option)
    {
        return $this->options[$option];
    }

    /**
     * Head
     *
     * @access public
     * @param  $url
     * @param  $data
     *
     * @return string
     */
    public function head($url, $data = array())
    {
        if (is_array($url)) {
            $data = $url;
            $url = $this->baseUrl;
        }
        $this->setURL($url, $data);
        $this->setOpt(CURLOPT_CUSTOMREQUEST, 'HEAD');
        $this->setOpt(CURLOPT_NOBODY, true);
        return $this->exec();
    }

    /**
     * Header Callback
     *
     * @access public
     * @param  $ch
     * @param  $header
     *
     * @return integer
     */
    public function headerCallback($ch, $header)
    {
        if (preg_match('/^Set-Cookie:\s*([^=]+)=([^;]+)/mi', $header, $cookie) == 1) {
            $this->responseCookies[$cookie[1]] = $cookie[2];
        }
        $this->rawResponseHeaders .= $header;
        return strlen($header);
    }

    /**
     * Options
     *
     * @access public
     * @param  $url
     * @param  $data
     *
     * @return string
     */
    public function options($url, $data = array())
    {
        if (is_array($url)) {
            $data = $url;
            $url = $this->baseUrl;
        }
        $this->setURL($url, $data);
        $this->unsetHeader('Content-Length');
        $this->setOpt(CURLOPT_CUSTOMREQUEST, 'OPTIONS');
        return $this->exec();
    }

    /**
     * Patch
     *
     * @access public
     * @param  $url
     * @param  $data
     *
     * @return string
     */
    public function patch($url, $data = array())
    {
        if (is_array($url)) {
            $data = $url;
            $url = $this->baseUrl;
        }

        if (is_array($data) && empty($data)) {
            $this->unsetHeader('Content-Length');
        }

        $this->setURL($url);
        $this->setOpt(CURLOPT_CUSTOMREQUEST, 'PATCH');
        $this->setOpt(CURLOPT_POSTFIELDS, $this->buildPostData($data));
        return $this->exec();
    }

    /**
     * Post
     *
     * @access public
     * @param  $url
     * @param  $data
     *
     * @return string
     */
    public function post($url, $data = array())
    {
        if (is_array($url)) {
            $data = $url;
            $url = $this->baseUrl;
        }

        $this->setURL($url);
        $this->setOpt(CURLOPT_CUSTOMREQUEST, 'POST');
        $this->setOpt(CURLOPT_POST, true);
        $this->setOpt(CURLOPT_POSTFIELDS, $this->buildPostData($data));
        return $this->exec();
    }

    /**
     * Put
     *
     * @access public
     * @param  $url
     * @param  $data
     *
     * @return string
     */
    public function put($url, $data = array())
    {
        if (is_array($url)) {
            $data = $url;
            $url = $this->baseUrl;
        }
        $this->setURL($url);
        $this->setOpt(CURLOPT_CUSTOMREQUEST, 'PUT');
        $put_data = $this->buildPostData($data);
        if (empty($this->options[CURLOPT_INFILE]) && empty($this->options[CURLOPT_INFILESIZE])) {
            $this->setHeader('Content-Length', strlen($put_data));
        }
        $this->setOpt(CURLOPT_POSTFIELDS, $put_data);
        return $this->exec();
    }

    /**
     * Set Basic Authentication
     *
     * @access public
     * @param  $username
     * @param  $password
     */
    public function setBasicAuthentication($username, $password = '')
    {
        $this->setOpt(CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        $this->setOpt(CURLOPT_USERPWD, $username . ':' . $password);
    }

    /**
     * Set Digest Authentication
     *
     * @access public
     * @param  $username
     * @param  $password
     */
    public function setDigestAuthentication($username, $password = '')
    {
        $this->setOpt(CURLOPT_HTTPAUTH, CURLAUTH_DIGEST);
        $this->setOpt(CURLOPT_USERPWD, $username . ':' . $password);
    }

    /**
     * Set Cookie
     *
     * @access public
     * @param  $key
     * @param  $value
     */
    public function setCookie($key, $value)
    {
        $this->cookies[$key] = $value;
        $this->setOpt(CURLOPT_COOKIE, str_replace(' ', '%20', urldecode(http_build_query($this->cookies, '', '; '))));
    }

    /**
     * Get cookie.
     *
     * @access public
     * @param  $key
     */
    public function getCookie($key)
    {
        return $this->getResponseCookie($key);
    }

    /**
     * Get response cookie.
     *
     * @access public
     * @param  $key
     */
    public function getResponseCookie($key)
    {
        return isset($this->responseCookies[$key]) ? $this->responseCookies[$key] : null;
    }

    /**
     * Set Port
     *
     * @access public
     * @param  $port
     */
    public function setPort($port)
    {
        $this->setOpt(CURLOPT_PORT, intval($port));
    }

    /**
     * Set Connect Timeout
     *
     * @access public
     * @param  $seconds
     */
    public function setConnectTimeout($seconds)
    {
        $this->setOpt(CURLOPT_CONNECTTIMEOUT, $seconds);
    }

    /**
     * Set Cookie File
     *
     * @access public
     * @param  $cookie_file
     */
    public function setCookieFile($cookie_file)
    {
        $this->setOpt(CURLOPT_COOKIEFILE, $cookie_file);
    }

    /**
     * Set Cookie Jar
     *
     * @access public
     * @param  $cookie_jar
     */
    public function setCookieJar($cookie_jar)
    {
        $this->setOpt(CURLOPT_COOKIEJAR, $cookie_jar);
    }

    /**
     * Set Default JSON Decoder
     *
     * @access public
     */
    public function setDefaultJsonDecoder()
    {
        $this->jsonDecoder = function($response) {
            $json_obj = json_decode($response, false);
            if (!($json_obj === null)) {
                $response = $json_obj;
            }
            return $response;
        };
    }

    /**
     * Set Default Timeout
     *
     * @access public
     */
    public function setDefaultTimeout()
    {
        $this->setTimeout(self::DEFAULT_TIMEOUT);
    }

    /**
     * Set Default User Agent
     *
     * @access public
     */
    public function setDefaultUserAgent()
    {
        $user_agent = 'PHP-Curl-Class/' . self::VERSION;
        $user_agent .= ' PHP/' . PHP_VERSION;
        $curl_version = curl_version();
        $user_agent .= ' curl/' . $curl_version['version'];
        $this->setUserAgent($user_agent);
    }

    /**
     * Set Header
     *
     * @access public
     * @param  $key
     * @param  $value
     *
     * @return string
     */
    public function setHeader($key, $value)
    {
        $this->headers[$key] = $value;
        $headers = array();
        foreach ($this->headers as $key => $value) {
            $headers[] = $key . ': ' . $value;
        }
        $this->setOpt(CURLOPT_HTTPHEADER, $headers);
    }

    /**
     * Set JSON Decoder
     *
     * @access public
     * @param  $function
     */
    public function setJsonDecoder($function)
    {
        if (is_callable($function)) {
            $this->jsonDecoder = $function;
        }
    }

    /**
     * Set Opt
     *
     * @access public
     * @param  $option
     * @param  $value
     *
     * @return boolean
     */
    public function setOpt($option, $value)
    {
        $required_options = array(
            CURLOPT_RETURNTRANSFER => 'CURLOPT_RETURNTRANSFER',
        );

        if (in_array($option, array_keys($required_options), true) && !($value === true)) {
            trigger_error($required_options[$option] . ' is a required option', E_USER_WARNING);
        }

        $this->options[$option] = $value;
        return curl_setopt($this->curl, $option, $value);
    }

    /**
     * Set Referer
     *
     * @access public
     * @param  $referer
     */
    public function setReferer($referer)
    {
        $this->setReferrer($referer);
    }

    /**
     * Set Referrer
     *
     * @access public
     * @param  $referrer
     */
    public function setReferrer($referrer)
    {
        $this->setOpt(CURLOPT_REFERER, $referrer);
    }

    /**
     * Set Timeout
     *
     * @access public
     * @param  $seconds
     */
    public function setTimeout($seconds)
    {
        $this->setOpt(CURLOPT_TIMEOUT, $seconds);
    }

    /**
     * Set Url
     *
     * @access public
     * @param  $url
     * @param  $data
     */
    public function setURL($url, $data = array())
    {
        $this->baseUrl = $url;
        $this->url = $this->buildURL($url, $data);
        $this->setOpt(CURLOPT_URL, $this->url);
    }

    /**
     * Set User Agent
     *
     * @access public
     * @param  $user_agent
     */
    public function setUserAgent($user_agent)
    {
        $this->setOpt(CURLOPT_USERAGENT, $user_agent);
    }

    /**
     * Success
     *
     * @access public
     * @param  $callback
     */
    public function success($callback)
    {
        $this->successFunction = $callback;
    }

    /**
     * Unset Header
     *
     * @access public
     * @param  $key
     */
    public function unsetHeader($key)
    {
        $this->setHeader($key, '');
        unset($this->headers[$key]);
    }

    /**
     * Verbose
     *
     * @access public
     * @param  $on
     */
    public function verbose($on = true)
    {
        $this->setOpt(CURLOPT_VERBOSE, $on);
    }

    /**
     * Destruct
     *
     * @access public
     */
    public function __destruct()
    {
        $this->close();
    }

    /**
     * Build Url
     *
     * @access protected
     * @param  $url
     * @param  $data
     *
     * @return string
     */
    protected function buildURL($url, $data = array())
    {
        return $url . (empty($data) ? '' : '?' . http_build_query($data));
    }

    /**
     * Parse Headers
     *
     * @access protected
     * @param  $raw_headers
     *
     * @return array
     */
    protected function parseHeaders($raw_headers)
    {
        $raw_headers = preg_split('/\r\n/', $raw_headers, null, PREG_SPLIT_NO_EMPTY);
        $http_headers = new CaseInsensitiveArray();

        $raw_headers_count = count($raw_headers);
        for ($i = 1; $i < $raw_headers_count; $i++) {
            list($key, $value) = explode(':', $raw_headers[$i], 2);
            $key = trim($key);
            $value = trim($value);
            // Use isset() as array_key_exists() and ArrayAccess are not compatible.
            if (isset($http_headers[$key])) {
                $http_headers[$key] .= ',' . $value;
            } else {
                $http_headers[$key] = $value;
            }
        }

        return array(isset($raw_headers['0']) ? $raw_headers['0'] : '', $http_headers);
    }

    /**
     * Parse Request Headers
     *
     * @access protected
     * @param  $raw_headers
     *
     * @return array
     */
    protected function parseRequestHeaders($raw_headers)
    {
        $request_headers = new CaseInsensitiveArray();
        list($first_line, $headers) = $this->parseHeaders($raw_headers);
        $request_headers['Request-Line'] = $first_line;
        foreach ($headers as $key => $value) {
            $request_headers[$key] = $value;
        }
        return $request_headers;
    }

    /**
     * Parse Response
     *
     * @access protected
     * @param  $response_headers
     * @param  $raw_response
     *
     * @return array
     */
    protected function parseResponse($response_headers, $raw_response)
    {
        $response = $raw_response;
        if (isset($response_headers['Content-Type'])) {
            if (preg_match($this->jsonPattern, $response_headers['Content-Type'])) {
                $json_decoder = $this->jsonDecoder;
                if (is_callable($json_decoder)) {
                    $response = $json_decoder($response);
                }
            } elseif (preg_match($this->xmlPattern, $response_headers['Content-Type'])) {
                $xml_obj = @simplexml_load_string($response);
                if (!($xml_obj === false)) {
                    $response = $xml_obj;
                }
            }
        }

        return array($response, $raw_response);
    }

    /**
     * Parse Response Headers
     *
     * @access protected
     * @param  $raw_response_headers
     *
     * @return array
     */
    protected function parseResponseHeaders($raw_response_headers)
    {
        $response_header_array = explode("\r\n\r\n", $raw_response_headers);
        $response_header  = '';
        for ($i = count($response_header_array) - 1; $i >= 0; $i--) {
            if (stripos($response_header_array[$i], 'HTTP/') === 0) {
                $response_header = $response_header_array[$i];
                break;
            }
        }

        $response_headers = new CaseInsensitiveArray();
        list($first_line, $headers) = $this->parseHeaders($response_header);
        $response_headers['Status-Line'] = $first_line;
        foreach ($headers as $key => $value) {
            $response_headers[$key] = $value;
        }
        return $response_headers;
    }

    /**
     * Http Build Multi Query
     *
     * @access public
     * @param  $data
     * @param  $key
     *
     * @return string
     */
    public static function http_build_multi_query($data, $key = null)
    {
        $query = array();

        if (empty($data)) {
            return $key . '=';
        }

        $is_array_assoc = self::is_array_assoc($data);

        foreach ($data as $k => $value) {
            if (is_string($value) || is_numeric($value)) {
                $brackets = $is_array_assoc ? '[' . $k . ']' : '[]';
                $query[] = urlencode($key === null ? $k : $key . $brackets) . '=' . rawurlencode($value);
            } elseif (is_array($value)) {
                $nested = $key === null ? $k : $key . '[' . $k . ']';
                $query[] = self::http_build_multi_query($value, $nested);
            }
        }

        return implode('&', $query);
    }

    /**
     * Is Array Assoc
     *
     * @access public
     * @param  $array
     *
     * @return boolean
     */
    public static function is_array_assoc($array)
    {
        return (bool)count(array_filter(array_keys($array), 'is_string'));
    }

    /**
     * Is Array Multidim
     *
     * @access public
     * @param  $array
     *
     * @return boolean
     */
    public static function is_array_multidim($array)
    {
        if (!is_array($array)) {
            return false;
        }

        return (bool)count(array_filter($array, 'is_array'));
    }

    /**
     * Get Error
     *
     * @return bool
     */
    public function getError()
    {
        return $this->error;
    }

    /**
     * Get Error Code
     *
     * @return int
     */
    public function getErrorCode()
    {
        return $this->errorCode;
    }

    /**
     * Get Error Message
     *
     * @return string
     */
    public function getErrorMessage()
    {
        return $this->errorMessage;
    }

    /**
     * Get Curl Error
     *
     * @return bool
     */
    public function getCurlError()
    {
        return $this->curlError;
    }

    /**
     * Get Curl Error Code
     *
     * @return int
     */
    public function getCurlErrorCode()
    {
        return $this->curlErrorCode;
    }

    /**
     * Get Curl Error Message
     *
     * @return string
     */
    public function getCurlErrorMessage()
    {
        return $this->curlErrorMessage;
    }

    /**
     * Get Http Error
     *
     * @return bool
     */
    public function getHttpError()
    {
        return $this->httpError;
    }

    /**
     * Get http Status Code
     *
     * @return int
     */
    public function getHttpStatusCode()
    {
        return $this->httpStatusCode;
    }

    /**
     * Get Http Error Message
     *
     * @return string
     */
    public function getHttpErrorMessage()
    {
        return $this->httpErrorMessage;
    }

    /**
     * Get Base Url
     *
     * @return string
     */
    public function getBaseUrl()
    {
        return $this->baseUrl;
    }

    /**
     * Get Url
     *
     * @return string
     */
    public function getUrl()
    {
        return $this->url;
    }

    /**
     * Get Request Headers
     *
     * @return array
     */
    public function getRequestHeaders()
    {
        return $this->requestHeaders;
    }

    /**
     * Get Response Headers
     *
     * @return array
     */
    public function getResponseHeaders()
    {
        return $this->responseHeaders;
    }

    /**
     * Get Raw Response Headers
     *
     * @return string
     */
    public function getRawResponseHeaders()
    {
        return $this->rawResponseHeaders;
    }

    /**
     * Get Response
     *
     * @return mixed
     */
    public function getResponse()
    {
        return $this->response;
    }

    /**
     * Get Raw Response
     *
     * @return string
     */
    public function getRawResponse()
    {
        return $this->rawResponse;
    }

    /**
     * Get Before Send Function
     *
     * @return string
     */
    public function getBeforeSendFunction()
    {
        return $this->beforeSendFunction;
    }

    /**
     * Set Before Send Function
     *
     * @param string $function
     * @return $this
     */
    public function setBeforeSendFunction($function)
    {
        $this->beforeSendFunction = $function;
        return $this;
    }

    /**
     * Get Download Complete Function
     *
     * @return string
     */
    public function getDownloadCompleteFunction()
    {
        return $this->downloadCompleteFunction;
    }

    /**
     * Get Download Complete Function
     *
     * @param string $function
     * @return $this
     */
    public function setDownloadCompleteFunction($function)
    {
        $this->downloadCompleteFunction = $function;
        return $this;
    }
}
