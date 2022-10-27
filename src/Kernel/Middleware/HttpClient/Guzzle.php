<?php
/**
 * @script   Guzzle.php
 * @brief    This file is part of PHPCreeper
 * @author   blogdaren<blogdaren@163.com>
 * @version  1.0.0
 * @modify   2019-11-06
 */

namespace PHPCreeper\Kernel\Middleware\HttpClient;

use PHPCreeper\Kernel\PHPCreeper;
use PHPCreeper\Kernel\Library\Helper\Tool;
use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use PHPCreeper\Kernel\Slot\HttpClientInterface;
use Logger\Logger;

class Guzzle implements HttpClientInterface
{
    /**
     * single instance
     *
     * @var object
     */
    static private $_instance = null;

    /**
     * cookiejar
     *
     * @var object
     */
    static private $_cookieJar = null;

    /**
     * config
     *
     * @var array
     */
    static private $_config = [];

    /**
     * response object
     *
     * @var object
     */
    static private $_response = null;

    /**
     * worker object
     *
     * @var object
     */
    static private $_worker = null;

    /**
     * user agent
     *
     * @var array
     */
    static public $userAgent = [
        'pc' => [
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_11_1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/51.0.2704.103 Safari/537.36',
            'Mozilla/5.0 (X11; Linux x86_64; rv:29.0) Gecko/20100101 Firefox/29.0',
            'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/34.0.1847.137 Safari/537.36',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.9; rv:29.0) Gecko/20100101 Firefox/29.0',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_9_3) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/34.0.1847.137 Safari/537.36',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_9_3) AppleWebKit/537.75.14 (KHTML, like Gecko) Version/7.0.3 Safari/537.75.14',
            'Mozilla/5.0 (Windows NT 6.1; WOW64; rv:29.0) Gecko/20100101 Firefox/29.0',
            'Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/34.0.1847.137 Safari/537.36',
            'Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1; SV1)',
            'Mozilla/4.0 (compatible; MSIE 7.0; Windows NT 5.1)',
            'Mozilla/4.0 (compatible; MSIE 8.0; Windows NT 6.1; WOW64; Trident/4.0)',
            'Mozilla/5.0 (compatible; MSIE 9.0; Windows NT 6.1; WOW64; Trident/5.0)',
            'Mozilla/5.0 (compatible; MSIE 10.0; Windows NT 6.1; WOW64; Trident/6.0)',
            'Mozilla/5.0 (Windows NT 6.1; WOW64; Trident/7.0; rv:11.0) like Gecko',
        ],
        'android' => [
            'Mozilla/5.0 (Android; Mobile; rv:29.0) Gecko/29.0 Firefox/29.0',
            'Mozilla/5.0 (Linux; Android 4.4.2; Nexus 4 Build/KOT49H) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/34.0.1847.114 Mobile Safari/537.36',
        ],
        'ios' => [
            'Mozilla/5.0 (iPad; CPU OS 7_0_4 like Mac OS X) AppleWebKit/537.51.1 (KHTML, like Gecko) CriOS/34.0.1847.18 Mobile/11B554a Safari/9537.53',
            'Mozilla/5.0 (iPad; CPU OS 7_0_4 like Mac OS X) AppleWebKit/537.51.1 (KHTML, like Gecko) Version/7.0 Mobile/11B554a Safari/9537.53',
            'Mozilla/5.0 (iPhone; CPU iPhone OS 8_0_2 like Mac OS X) AppleWebKit/600.1.4 (KHTML, like Gecko) Version/8.0 Mobile/12A366 Safari/600.1.4',
            'Mozilla/5.0 (iPhone; CPU iPhone OS 8_0 like Mac OS X) AppleWebKit/600.1.4 (KHTML, like Gecko) Version/8.0 Mobile/12A366 Safari/600.1.4',
        ],
    ];

    /**
     * @brief    __construct    
     *
     * @param    array  $config
     *
     * @return   void
     */
    public function __construct($config)
    {
        self::$_config = $config;
    }

    /**
     * @brief    __call     
     *
     * @param    string  $name
     * @param    mixed   $args
     *
     * @return   mixed
     */
    public function __call($name, $args)
    {   
        if(empty(self::$_response)) return '';

        return call_user_func(array(self::$_response, $name), ...$args);
    }   

    /**
     * @brief    get config
     *
     * @return   array
     */
    public function getConfig()
    {
        return self::$_config;
    }

    /**
     * @brief    get single instance
     *
     * @return   object
     */
    public function getInstance()
    {
        self::$_instance || self::$_instance = new Client();

        return self::$_instance;
    }

    /**
     * @brief    get default arguments    
     *
     * @return   array
     */
    static public function getDefaultArguments()
    {
        return [
            'verify'  => false,
            'cookies' => self::getCookieJar(),
        ];
    }

    /**
     * @brief    get cookiejar   
     *
     * @return   object
     */
    static public function getCookieJar()
    {
        self::$_cookieJar || self::$_cookieJar = new CookieJar();

        return self::$_cookieJar;
    }

    /**
     * @brief    http get method
     *
     * @param    string  $url
     * @param    array   $args
     *
     * @return   string
     */
    public function get($url, $args = [])
    {
        $response = self::request('GET', $url, $args);

        return (string)$response->getBody();
    }

    /**
     * @brief    http post method
     *
     * @param    string  $url
     * @param    array   $args
     *
     * @return   string
     */
    public function post($url, $args = [])
    {
        $response = self::request('POST', $url, $args);

        return (string)$response->getBody();
    }

    /**
     * @brief    trigger http request    
     *
     * @param    string  $method
     * @param    string  $url
     * @param    array   $args
     *
     * @return   object
     */
    public function request($method, $url, $args = [])
    {
        $options = array_merge(self::getDefaultArguments(), $args, self::$_config);

        if(!isset($options['headers']['User-Agent']))
        {
            $options['headers']['User-Agent'] = self::getRandUserAgent($args['type'] ?? 'pc');
        }

        if(!isset($options['headers']['referer']))
        {
            $options['headers']['referer'] = self::$_config['referer'] ?? '';
        }

        //try to trace request args 
        if(isset($options['trace_request_args']) && true === $options['trace_request_args'] && is_object($this->getWorker()))
        {
            $full_args = ['url' => $url, 'method' => $method] + $options;
            Logger::info(Tool::replacePlaceHolder($this->getWorker()->langConfig['trace_request_args'], [
                'request_args' => json_encode($full_args),
            ]));
        }

        self::$_response = self::getInstance()->request($method, $url, $options);

        return $this;
    }

    /**
     * @brief    trigger http request asynchorously 
     *
     * @param    string  $method
     * @param    string  $url
     * @param    array   $args
     *
     * @return   promise
     */
    public function requestAsync($method, $url, $args = [])
    {
        $options = array_merge($args, self::$_config);

        return self::getInstance()->requestAsync($method, $url, $options);
    }

    /**
     * @brief    getResponseStatusCode  
     *
     * @return   int
     */
    public function getResponseStatusCode()
    {
        if(empty(self::$_response)) return 'NaN';

        return self::$_response->getStatusCode();
    }

    /**
     * @brief    getResponseStatusMessage
     *
     * @return   string
     */
    public function getResponseStatusMessage()
    {
        if(empty(self::$_response)) return 'NaN';

        return self::$_response->getReasonPhrase();
    }

    /**
     * @brief    getResponseBody
     *
     * @return   string
     */
    public function getResponseBody()
    {
        if(empty(self::$_response)) return '';

        return self::$_response->getBody()->getContents();
    }

    /**
     * @brief    set request options     
     *
     * @param    array  $options
     *
     * @return   object
     */
    public function setOptions($options = [])
    {
        if(!is_array($options)) return $this;

        foreach($options as $k => $v)
        {
            self::$_config[$k] = $v;
        }

        return $this;
    }

    /**
     * @brief    set http header
     *
     * @param    array  $headers
     *
     * @return   object
     */
    public function setHeaders($headers = [])
    {
        !empty($headers) && self::$_config['headers'] = $headers;

        return $this;
    }

    /**
     * @brief    set http referer
     *
     * @param    string  $referer
     *
     * @return   object
     */
    public function setReferer($referer = '')
    {
        !is_string($referer) && $referer = '';
        self::$_config['referer'] = $referer;

        return $this;
    }

    /**
     * @brief    set base uri     
     *
     * @param    string  $uri
     *
     * @return   object
     */
    public function setBaseUri($uri = '')
    {
        !is_string($uri) && $uri = '';
        self::$_config['base_uri'] = $uri;

        return $this;
    }

    /**
     * @brief    set http redirect    
     *
     * @param    boolean | array    $allow_redirect
     *
     * @return   object
     */
    public function setRedirect($allow_redirect = true)
    {
        self::$_config['allow_redirects'] = $allow_redirect;

        return $this;
    }

    /**
     * @brief    set connect timeout  
     *
     * @param    int    $timeout
     *
     * @return   object
     */
    public function setConnectTimeout($timeout = 0)
    {
        self::$_config['connect_timeout'] = $timeout;

        return $this;
    }

    /**
     * @brief    set transfer timeout     
     *
     * @param    int    $timeout
     *
     * @return   object
     */
    public function setTransferTimeout($timeout = 0)
    {
        self::$_config['timeout'] = $timeout;

        return $this;
    }

    /**
     * @brief    set http proxy   
     *
     * @param    string | array  $proxy
     *
     * @return   object
     */
    public function setProxy($proxy = [])
    {
        self::$_config['proxy'] = $proxy;

        return $this;
    }

    /**
     * @brief   disable SSL verify
     *
     * @return  object 
     */
    public function disableSSL()
    {
        self::$_config['verify'] = false;

        return $this;
    }

    /**
     * @brief   enable SSL verify
     *
     * @return  object 
     */
    public function enableSSL()
    {
        self::$_config['verify'] = true;

        return $this;
    }

    /**
     * @brief   set SSL certificate 
     *
     * @return  object 
     */
    public function setSSLCertificate($cafile = '')
    {
        if(empty($cafile) || !is_string($cafile)) return $this;

        self::$_config['verify'] = $cafile;

        return $this;
    }

    /**
     * @brief    get rand user agent   
     *
     * @param    string  $type
     *
     * @return   string
     */
    static public function getRandUserAgent($type = 'pc')
    {
        $type = strtolower($type);
        $limit_types = array_keys(self::$userAgent);
        !in_array($type, $limit_types) && $type = 'pc';
        $rand_key = array_rand(self::$userAgent[$type]); 
        $user_agent = self::$userAgent[$type][$rand_key] . rand(0, 10000);

        return $user_agent;
    }

    /**
     * @brief    set downloader worker
     *
     * @return   object
     */
    public function setWorker($worker)
    {
        $downloader_class = PHPCreeper::PHPCREEPER_BUILTIN_MIDDLE_CLASSES['downloader'];

        if(empty(self::$_worker) && $worker instanceof $downloader_class)
        {
            self::$_worker = $worker;
        }

        return $this;
    }

    /**
     * @brief    get downloader worker
     *
     * @return   object
     */
    public function getWorker()
    {
        return self::$_worker;
    }
}




