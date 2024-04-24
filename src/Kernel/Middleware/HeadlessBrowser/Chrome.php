<?php
/**
 * @script   Chrome.php
 * @brief    This file is part of PHPCreeper
 * @author   blogdaren<blogdaren@163.com>
 * @link     http://www.phpcreeper.com
 * @create   2024-04-21
 */

namespace PHPCreeper\Kernel\Middleware\HeadlessBrowser;

use PHPCreeper\Kernel\PHPCreeper;
use PHPCreeper\Kernel\Library\Helper\Tool;
use PHPCreeper\Kernel\Slot\HttpClientInterface;
use Logger\Logger;
use HeadlessChromium\BrowserFactory;
use HeadlessChromium\Page;
use HeadlessChromium\Exception\OperationTimedOut;
use HeadlessChromium\Exception\NavigationExpired;

class Chrome
{
    /**
     * single instance
     *
     * @var object
     */
    static private $_browser_factory = null;

    /**
     * single instance
     *
     * @var object
     */
    static private $_browser = null;

    /**
     * config
     *
     * @var array
     */
    static private $_config = [];

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
     * page event
     *
     * @var array
     */
    public const PAGE_EVENT = [
        'DOMContentLoaded',
        'firstContentfulPaint',
        'firstImagePaint',
        'firstMeaningfulPaint',
        'firstPaint',
        'init',
        'InteractiveTime',
        'load',
        'networkIdle',
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
     * @brief    get merged options   
     *
     * @param    array  $args
     *
     * @return   array
     */
    public function getMergedOptions($args = [])
    {
        !is_array($args) && $args = [];
        $merged_options = array_merge(self::getDefaultArguments(), self::$_config, $args);

        return $merged_options;
    }


    /**
     * @brief    get single instance for browser factory 
     *
     * @return   object
     */
    public function getBrowserFactoryInstance()
    {
        self::$_browser_factory || self::$_browser_factory = new BrowserFactory();

        return self::$_browser_factory;
    }

    /**
     * @brief    get single instance for browser 
     *
     * @return   object
     */
    public function getBrowserInstance()
    {
        self::$_browser || self::$_browser = self::getBrowserFactoryInstance()->createBrowser();

        return self::$_browser;
    }

    /**
     * @brief    get default arguments    
     *
     * @return   array
     */
    static public function getDefaultArguments()
    {
        return [
            'headless'  => true,
            'noSandbox' => true,
            'keepAlive' => true,
            'ignoreCertificateErrors' => true,
            'sendSyncDefaultTimeout'  => 10000,
        ];
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
        $options = $this->getMergedOptions($args);

        if(!isset($options['headers']['User-Agent']))
        {
            $options['headers']['User-Agent'] = self::getRandUserAgent($args['type'] ?? 'pc');
        }

        if(!empty(self::$_config['referer'])){ 
            $referer = self::$_config['referer'];
        }elseif(!empty($options['headers']['referer'])){
            $referer = $options['headers']['referer'];
        }else{
            $referer = $url;
        }   

        //set referer
        $options['headers']['referer'] = $referer;

        //set browser options
        self::getBrowserFactoryInstance()->setOptions($options);

        //get page event
        $page_event = 'firstMeaningfulPaint';
        if(!empty($options['pageEvent']) && in_array($options['pageEvent'], self::PAGE_EVENT))
        {
            $page_event = $options['pageEvent'];
        }

        //get navigation timeout
        $navigate_timeout = 30000;
        if(isset($options['navigateTimeout']) && $options['navigateTimeout'] > 0) 
        {
            $navigate_timeout = (int)$options['navigateTimeout'];
        }

        //try to track request args 
        if(isset($options['track_request_args']) && true === $options['track_request_args'] && is_object($this->getWorker()))
        {
            $full_args = [
                'url' => $url, 
                'method' => $method,
                'pageEvent' => $page_event,
                'navigateTimeout' => $navigate_timeout,
            ] + $options;
            Logger::crazy(Tool::replacePlaceHolder($this->getWorker()->langConfig['track_request_args'], [
                'request_args' => str_replace("\\/", "/", json_encode($full_args)),
            ]));
        }

        //issue http request
        $page = self::getPage();
        $page->navigate($url)->waitForNavigation($page_event, $navigate_timeout);
        $html = $page->getHtml();
        $page->close();

        return $html;
    }

    /**
     * @brief   get page 
     *
     * @return  object 
     */
    public function getPage()
    {
        return self::getBrowserInstance()->createPage();
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




