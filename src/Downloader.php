<?php
/**
 * @script   Downloader.php
 * @brief    This file is part of PHPCreeper
 * @author   blogdaren<blogdaren@163.com>
 * @link     http://www.phpcreeper.com
 * @create   2019-09-04
 */

namespace PHPCreeper;

use PHPCreeper\Kernel\PHPCreeper;
use PHPCreeper\Kernel\Library\Helper\Tool;
use PHPCreeper\Timer;
use Configurator\Configurator;
use Logger\Logger;
use Workerman\Connection\AsyncTcpConnection;

class Downloader extends PHPCreeper
{
    /**
     * task timer id
     *
     * @var int
     */
    public $timerId = 0;

    /**
     * task crawl interval 
     *
     * @var float
     */
    public $taskCrawlInterval = 0;

    /**
     * async task connection object collections
     *
     * @var array
     */
    public $taskConnections = [];

    /**
     * router callback to parser server
     *
     * @var closure
     */
    private $_router = null;

    /**
     * flag indicates whether task send buffer is full or not 
     *
     * @var boolean
     */
    protected $_bufferFull = false;

    /**
     * the send buffer size of connection from downloader to parser
     *
     * @var int
     */
    private $_sendToParserBufferSize = 10240000;

    /** 
     * heartbeat: ping from downloader to parser
     *
     * @var int
     */
    const PING_PARSER_INTERVAL = 25;  

    /**
     * @brief    __construct    
     *
     * @return   void
     */
    public function __construct($config = [])
    {
        parent::__construct($config);
    }

    /**
     * @brief    run worker instance 
     *
     * @return   void
     */
    public function run()
    {
        $this->onWorkerStart  = array($this, 'onWorkerStart');
        $this->onWorkerStop   = array($this, 'onWorkerStop');
        $this->onWorkerReload = array($this, 'onWorkerReload');

        parent::run();
    }

    /**
     * @brief    onWorkerStart  
     *
     * @param    object $worker
     *
     * @return   boolean | void
     */
    public function onWorkerStart($worker)
    {
        //global init 
        $this->initMiddleware()->initLogger();

        //trigger user callback
        $returning = $this->triggerUserCallback('onDownloaderStart', $this);
        if(false === $returning) return false;

        //check middleware again to avoid unexpected things by user callback 
        $result = $this->checkWhetherMiddlewareIsValid();
        if(0 <> $result['error_code'])
        {
            Logger::error(Tool::replacePlaceHolder($result['error_msg'], $result['extra_msg']));
            return false;
        }

        //when run as single worker
        if(false === PHPCreeper::$isRunAsMultiWorker)
        {
            $this->installTimer();
            return false;
        }

        //try to connect to parser asynchronously then execute consumeOneTask by interval
        $this->connectToParser();
    }

    /**
     * @brief    onWorkerStop   
     *
     * @param    object $worker
     *
     * @return   void
     */
    public function onWorkerStop($worker)
    {
        $this->triggerUserCallback('onDownloaderStop', $this);
        $this->removeTimer();
    }

    /**
     * @brief    onWorkerReload     
     *
     * @param    object $worker
     *
     * @return   boolean | void
     */
    public function onWorkerReload($worker)
    {
        //trigger user callback
        $returning = $this->triggerUserCallback('onDownloaderReload', $this);
        if(false === $returning) return false;
    }

    /**
     * @brief    try to connect to parser asynchronously
     *
     * @return   void
     */
    public function connectToParser()
    {
        //get all task connections
        $task_connections = $this->getAsyncTaskConnection();
        if(empty($task_connections))
        {
            Logger::error(Tool::replacePlaceHolder($this->langConfig['downloader_lost_connections']));
            return;
        }

        //try to connect all parser
        foreach($task_connections as $target_server => $connections)
        {
            foreach($connections as $k => $connection)
            {
                $connection->channel = $channel = $target_server . "|" . $connection->id;
                $this->taskConnections[$channel] = $connection;
                $this->taskConnections[$channel]->counter    = 0;
                $this->taskConnections[$channel]->closeFlag  = '0x00';
                $this->taskConnections[$channel]->bufferFull = $this->_bufferFull;
                $this->taskConnections[$channel]->maxSendBufferSize = $this->getSendBufferSize();

                $this->taskConnections[$channel]->onConnect = [$this, 'onConnectToParser'];
                $this->taskConnections[$channel]->onMessage = [$this, 'onReceiveParserMessage'];

                $this->taskConnections[$channel]->onBufferFull = function($connection){
                    $connection->bufferFull = true;
                    Logger::crazy(Tool::replacePlaceHolder($this->langConfig['downloader_buffer_full']));
                };

                $this->taskConnections[$channel]->onBufferDrain = function($connection){
                    $connection->bufferFull = false;
                    Logger::crazy(Tool::replacePlaceHolder($this->langConfig['downloader_buffer_drain']));
                };

                $this->taskConnections[$channel]->onClose = function($connection){
                    $reconnect_time = 1;
                    !empty($connection->taskTimerId) && Timer::del($connection->taskTimerId);
                    !empty($connection->pingTimerId) && Timer::del($connection->pingTimerId);
                    $connection->reconnect($reconnect_time);

                    $msgKey = '';
                    '0x00' == $connection->closeFlag && $msgKey = 'downloader_connect_failed';
                    '0x01' == $connection->closeFlag && $msgKey = 'downloader_close_connection';
                    '0x05' == $connection->closeFlag && $msgKey = 'parser_close_connection';
                    if(empty($msgKey)) return false;

                    Logger::error(Tool::replacePlaceHolder($this->langConfig[$msgKey], [
                        'reconnect_time' => $reconnect_time,
                        'max_request'    => $this->getMaxRequest(),
                        'parser_socket'  => $connection->getRemoteAddress(),
                    ]));
                };

                $this->taskConnections[$channel]->connect();
            }
        }
    }

    /**
     * @brief    emitted when connect to parser successfully
     *
     * @param    object $connection
     *
     * @return   void
     */
    public function onConnectToParser($connection)
    {
        Logger::debug(Tool::replacePlaceHolder($this->langConfig['downloader_connect_success'], [
            'downloader_client_address' => 'tcp://' . $connection->getLocalAddress(),
        ]));

        //install task timer
        $connection->taskTimerId = Timer::add($this->getTaskCrawlInterval(), [$this, 'consumeOneTask'], [$connection->channel], 1);

        //if the parser's server ip is not 127.0.0.1, then we need to keep heartbeat.
        $to_parser_address = $connection->getRemoteAddress();
        if(false === strpos($to_parser_address, '127.0.0.1')) 
        {
            $connection->pingTimerId = Timer::add(self::PING_PARSER_INTERVAL, function()use($connection){
                $ping_data = [
                    'event'     => 'ping',
                    'interval'  => self::PING_PARSER_INTERVAL,
                ];
                $ping_data = $this->assemblePackage($ping_data);
                $connection->send($ping_data);
            }); 
        }  
    }

    /**
     * @brief    emitted when receive message from parser successfully
     *
     * @param    object  $connection
     * @param    string  $data
     *
     * @return   boolean
     */
    public function onReceiveParserMessage($connection, $data)
    {
        if('0x05' == $data) 
        {
            $connection->closeFlag = $data;
            return false;
        }

        $parser_reply = $this->disassemblePackage($data);
        if(empty($parser_reply))
        {
            Logger::error(Tool::replacePlaceHolder($this->langConfig['downloader_got_replay_null']));
            return false;
        }

        Logger::debug($parser_reply);

        //trigger callback
        $returning = $this->triggerUserCallback('onDownloaderMessage', $this, $parser_reply);

        //check middleware again to avoid unexpected things by user callback 
        $result = $this->checkWhetherMiddlewareIsValid();
        if(0 <> $result['error_code'])
        {
            Logger::error(Tool::replacePlaceHolder($result['error_msg'], $result['extra_msg']));
            return false;
        }

        if(false === $returning) return false;
        if(!empty($returning) && is_string($returning)) $parser_reply = $returning;

        $connection->counter++;
        if($this->getMaxRequest() > 0 && $connection->counter > $this->getMaxRequest())
        {
            $connection->closeFlag = '0x01';
            $connection->close();
        }

        return true;
    }

    /**
     * @brief    get max request for per task connection
     *
     * @return   int
     */
    public function getMaxRequest()
    {
        $max_request = Configurator::get('globalConfig/main/task/max_request');

        !Tool::checkIsInt($max_request) && $max_request = 0;

        return $max_request;
    }

    /**
     * @brief    set task crawl interval   
     *
     * @param    float  $interval
     *
     * @return   object
     */
    public function setTaskCrawlInterval($interval = 1)
    {
        if(!$interval || Tool::bcCompareNumber($interval, '0.001', 3) < 0) 
        {
            $interval = 1;
        }

        $this->taskCrawlInterval = $interval;

        return $this;
    }

    /**
     * @brief    get task crawl interval   
     *
     * @return   float
     */
    public function getTaskCrawlInterval()
    {
        $interval = $this->taskCrawlInterval;

        if(Tool::bcCompareNumber($interval, '0.001', 3) < 0) 
        {
            $interval = Configurator::get('globalConfig/main/task/crawl_interval');
        }

        if(!$interval || Tool::bcCompareNumber($interval, '0.001', 3) < 0) 
        {
            $interval = 1;
        }

        return $this->taskCrawlInterval = $interval;
    }

    /**
     * @brief    consume one task     
     *
     * @param    string  $channel
     *
     * @return   void
     */
    public function consumeOneTask($channel = '')
    {
        //connection channel
        (!empty($channel) && !is_string($channel)) && $channel = (string)$channel;

        //netflow control switch 
        if(!empty($channel) && isset($this->taskConnections[$channel])
            && $this->taskConnections[$channel] instanceof AsyncTcpConnection
            && true === $this->taskConnections[$channel]->bufferFull)
        {
            Logger::crazy(Tool::replacePlaceHolder($this->langConfig['downloader_buffer_full']));
            return false;
        }

        //get one task
        $task = $this->getOneTask();

        //check task 
        if(empty($task)) 
        {
            Logger::crazy(Tool::replacePlaceHolder($this->langConfig['queue_empty'],[
                'crawl_interval' => $this->getTaskCrawlInterval(),
            ]));
            return false;
        }

        //logger
        Logger::info(Tool::replacePlaceHolder($this->langConfig['downloader_get_one_task'], [
            'task_url'   => $task['url'],
        ]));

        //trigger user callback
        $returning = $this->triggerUserCallback('onBeforeDownload', $this, $task);

        //check middleware again to avoid unexpected things by user callback 
        $result = $this->checkWhetherMiddlewareIsValid();
        if(0 <> $result['error_code'])
        {
            Logger::error(Tool::replacePlaceHolder($result['error_msg'], $result['extra_msg']));
            return false;
        }

        if(false === $returning) return false;
        if(!empty($returning) && is_array($returning)) $task = $returning;

        //reject connection channel into $task
        $task['channel'] = $channel;

        //start download
        $this->_startDownload($task);
    }

    /**
     * @brief    get one task to download
     *
     * @return   void
     */
    public function getOneTask()
    {
        //lock
        /*
         *if($this->count > 1)
         *{
         *    $gold_key = $this->lockHelper->lock('getonetask');
         *    if(!$gold_key) return false;
         *}
         */

        $task = $this->getTaskMan()->getTask();

        //unlock
        /*
         *$this->count > 1 && $this->lockHelper->unlock('getonetask', $gold_key);
         */

        return $task;
    }

    /**
     * @brief    start download  
     *
     * @param    array  $task
     *
     * @return   void
     */
    protected function _startDownload(array $task = [])
    {
        //trigger user callback
        $returning = $this->triggerUserCallback('onStartDownload', $this, $task);

        //check middleware again to avoid unexpected things by user callback 
        $result = $this->checkWhetherMiddlewareIsValid();
        if(0 <> $result['error_code'])
        {
            Logger::error(Tool::replacePlaceHolder($result['error_msg'], $result['extra_msg']));
            return false;
        }

        if(false === $returning) return false;
        if(!empty($returning) && is_array($returning)) $task = $returning;

        //try to get download data and maybe from cache
        $download_data = $this->readDownloadData($task);

        //trigger user callback: only return false can stop executing in this session
        $returning = $this->triggerUserCallback('onAfterDownload', $this, $download_data, $task);

        //check middleware again to avoid unexpected things by user callback 
        $result = $this->checkWhetherMiddlewareIsValid();
        if(0 <> $result['error_code'])
        {
            Logger::error(Tool::replacePlaceHolder($result['error_msg'], $result['extra_msg']));
            return false;
        }

        //disable forwarding if run as single worker mode
        if(false === $returning || false === PHPCreeper::$isRunAsMultiWorker) return false;

        //important!!
        if(!empty($returning) && is_string($returning)) $download_data = $returning;
        if(empty($download_data)) return false;

        //forward download result
        //'text' <> $task['type'] && $download_data = base64_encode($download_data);
        $result = [
            'task'          => $task,
            'download_data' => $download_data,
            'binary_type'   => PHPCreeper::BINARY_TYPE_ARRAYBUFFER, //indicates to pack|unpack the input data
        ];
        $this->_forward($result);
    }

    /**
     * @brief    forward  download data to remote parser server  
     *
     * @param    array  $data
     *
     * @return   mixed
     */
    protected function _forward($data)
    {
        if(empty($data) || !isset($data['task']) || !isset($data['download_data'])) 
        {
            Logger::warn(Tool::replacePlaceHolder($this->langConfig['downloader_forward_args'], []));
            return false;
        }

        $channel = $data['task']['channel'] ?? '';
        if(empty($channel) || !array_key_exists($channel, $this->taskConnections))
        {
            Logger::warn(Tool::replacePlaceHolder($this->langConfig['downloader_lost_channel'], []));
            return false;
        }

        if(empty($this->taskConnections[$channel]))
        {
            $this->taskConnections[$channel] = $this->getOneAsyncTaskConnection();

            if(empty($this->taskConnections[$channel])) 
            {
                Logger::error(Tool::replacePlaceHolder($this->langConfig['downloader_connect_error']));
                return false;
            }

            $this->taskConnections[$channel]->connect();
        }

        $data = $this->assemblePackage($data);
        $result = $this->taskConnections[$channel]->send($data);

        if(false !== $result)
        {
            Logger::info(Tool::replacePlaceHolder($this->langConfig['downloader_forward_data'], []));
        }

        return $result;
    }

    /**
     * @brief    get one async task connection
     *
     * @return   object | boolean
     */
    public function getOneAsyncTaskConnection()
    {
        $connections = $this->getAsyncTaskConnection();

        if(empty($connections) || !is_array($connections)) return false;

        $key1 = array_rand($connections);

        if(empty($connections[$key1]) || !is_array($connections[$key1])) return false;

        $key2 = array_rand($connections[$key1]);   

        return $connections[$key1][$key2];
    }

    /**
     * @brief    get all available async task connections
     *
     * @return   array
     */
    public function getAsyncTaskConnection()
    {
        static $connections = [];

        //get max connections
        $max_connections = self::getHowManyConnectionsCouldBeCreatedByDownloader();

        //remember to check
        $addresses = $this->getClientSocketAddress('parser');
        if(empty($addresses)) return false;

        foreach($addresses as $k => $v)
        {
            $target_server  = $v['target'];
            $socket_address = $v['socket'];
            $socket_context = $v['context'];

            while(empty($connections[$target_server]) || count($connections[$target_server]) < $max_connections)
            {
                $c = new AsyncTcpConnection($socket_address, $socket_context);
                $connections[$target_server][] = $c;
            }
        }

        //support setting router callback: router target parser server
        if(!empty($this->getRouter()) && is_callable($this->getRouter()))
        {
            $router_result = call_user_func($this->getRouter(), $this, $connections);
            if(is_array($router_result))
            {
                $router_task_connections = [];
                foreach($router_result as $k => $v){
                    foreach($v as $k1 => $v1){
                        if(array_key_exists($k, $connections) && $v1 instanceof AsyncTcpConnection){
                            $router_task_connections[$k][] = $v1;
                        }
                    }
                }
                !empty($router_task_connections) && $connections = $router_task_connections;
            }
        }

        return $connections;
    }

    /**
     * @brief    indicates how many connections which could be created by downloader to parser,
     *           this is for per downloader process separately. default 1 if nothing configured 
     *           and at most 1000 if more than the number, especially, you must must give the 
     *           appropriate value according to machine memory, or it will lead to unexpected 
     *           problems at your own disk.
     *
     * @return   int
     */
    static public function getHowManyConnectionsCouldBeCreatedByDownloader()
    {
        $max_connections = Configurator::get('globalConfig/main/task/max_connections');
        $max_connections <= 0 && $max_connections = 1;
        $max_connections >= 1000 && $max_connections = 1000;
        !Tool::checkIsInt($max_connections) && $max_connections = 1;

        return $max_connections;
    }

    /**
     * @brief    set router
     *
     * @param    Closure    $router
     *
     * @return   object
     */
    public function setRouter($router)
    {
        if($router instanceof \Closure)
        {
            $this->_router = $router;
        }

        return $this;
    }

    /**
     * @brief    get router
     *
     * @return   closure
     */
    public function getRouter()
    {
        return $this->_router;
    }

    /**
     * @brief    router target parser server
     *
     * $conn = [
     *      '127.0.0.1:8888' => [$connection1, $connection2, ...., $connectionN];
     *      '127.0.0.1:9999' => [$connection1, $connection2, ...., $connectionN];
     *      ......
     * ];
     *
     * @param    array      $conn   
     * @param    string     $algorithm 
     *
     * @return   string
     */
    private function _routerTargetServer($conn, $algorithm = 'modula')
    {
        if(empty($conn)) return '';

        if(1 == $this->count) return array_rand($conn);

        $target_servers = array_keys($conn);

        switch($algorithm) 
        {
            case 'modula':
                $total_server_number = count($target_servers);
                $target_server_key = abs($this->id % $total_server_number);
                break;
            default:
                $target_server_key = array_rand($conn);
                break;
        }

        $target_server = $target_servers[$target_server_key] ?? '';

        return $target_server;
    }

    /**
     * @brief    execute download   
     *
     * @param    array  $task
     *
     * @return   array
     */
    public function download($task)
    {
        $extra = [];
        $args = $this->rebuildTaskArguments($task);

        if(empty($args)) 
        {
            return Tool::throwback('-200', $this->langConfig['downloader_rebuild_task_null'], $extra);
        }

        try{
            list($method, $url) = [$args['method'], $args['url']];
            unset($args['method'], $args['url']);

            //try to set download worker only when need to track request args 
            if(isset($args['track_request_args']) && true === $args['track_request_args'])
            {
                method_exists($this->httpClient, 'setWorker') && $this->httpClient->setWorker($this);
            }

            //check whether large files are to be downloaded and the size exceeds the default max file size or not
            $content_length = ceil($this->prefetchRemoteFileSizeToBeDownloaded($url, $args) / 1024 / 1024);
            $defaut_max_file_size = ceil(PHPCreeper::getDefaultMaxFileSizeToDownload() / 1024 / 1024);
            if($content_length > $defaut_max_file_size)
            {
                $extra = [
                    'file_size'             => $content_length,
                    'default_max_file_size' => $defaut_max_file_size,
                ];
                return Tool::throwback('-202', $this->langConfig['downloader_download_filesize_exceed'], $extra);
            }

            $code = $this->httpClient->request($method, $url, $args)->getResponseStatusCode();

            if(in_array($code, [301, 302])){
                $args['allow_redirects'] = true;
                $client = $this->httpClient->request($method, $url, $args);
                $code = $this->httpClient->getResponseStatusCode();
                $content = 200 == $code ? $this->httpClient->getResponseBody(): false;
                $extra = ['content' => $content];
                return Tool::throwback('0', $this->langConfig['downloader_download_task_yes'], $extra);
            }elseif(200 == $code){
                $content = $this->httpClient->getResponseBody();
                $extra = ['content' => $content];
                return Tool::throwback('0', $this->langConfig['downloader_download_task_yes'], $extra);
            }else{
                $input = array_merge($task, $args);
                $task_id = $this->newTaskMan()->createTask($input);

                if(empty($task_id))
                {
                    $extra = array(
                        'task_id'   => $task_id,
                        'task_url'  => $input['url'],
                    );

                    return Tool::throwback('-201', $this->langConfig['queue_push_exception_task'], $extra);
                }
            }
        }catch(\Throwable $e){
            $extra = array(
                'url'            => $task['url'],
                'exception_code' => $e->getCode(),
                'exception_msg'  => $e->getMessage(),
            );

            return Tool::throwback('-205', $this->langConfig['http_transfer_exception'], $extra);
        }
    }

    /**
     * @brief    prefetch remote file size to be downloaded
     *
     * @param    string  $url
     * @param    array   $args
     *
     * @return   int
     */
    public function prefetchRemoteFileSizeToBeDownloaded($url, $args)
    {
        $args['allow_redirects'] = true;
        $args['headers']['Cache-Control'] = 'no-cache';
        $code = $this->httpClient->request('head', $url, $args)->getResponseStatusCode();

        if(in_array($code, [301, 302])){
            $client = $this->httpClient->request($method, $url, $args);
            $code = $this->httpClient->getResponseStatusCode();
            $headers = 200 == $code ? $this->httpClient->getHeaders(): [];
        }elseif(200 == $code){
            $headers = $this->httpClient->getHeaders();
        }else{
            $headers = [];
        }

        $len = $headers['Content-Length'][0] ?? 0;
        
        return $len;
    }


    /**
     * @brief    rebuild task arguments   
     *
     * @param    array|string   $args 
     *
     * @return   array
     */
    public function rebuildTaskArguments($args = [])
    {
        //also support $args as url string
        is_string($args) && $args = ['url' => $args];

        if(empty($args) || !is_array($args) || empty($args['url'])) return [];

        //check url
        if(true !== Tool::checkUrl($args['url'])) return [];

        //method
        (!isset($args['method']) || !is_string($args['method'])) && $args['method'] = 'get';
        $args['method'] = 'get' != strtolower($args['method']) ? strtolower($args['method']) : 'get';

        //header
        $headers = (isset($args['headers']) && is_array($args['headers'])) ? $args['headers'] : [];
        array_walk($headers, function($v, $k)use(&$headers){
            if(!is_string($k)) unset($headers[$k]);
        });
        !empty($headers) && $args['headers'] = $headers;

        //cookies
        (!isset($args['cookies']) || !is_array($args['cookies'])) && $args['cookies'] = NULL;

        //redirect
        if(!isset($args['allow_redirects'])){
            $args['allow_redirects'] = true;
        }elseif(is_bool($args['allow_redirects'])){
            $args['allow_redirects'] = !empty($args['allow_redirects']) ? true : false;
        }elseif(is_array($args['allow_redirects'])){
            $redirects = $args['allow_redirects'];
            array_walk($redirects, function($v, $k)use(&$redirects){
                if(!is_string($k)) unset($redirects[$k]);
            });
            $redirects['referer'] = !empty($redirects['referer']) ? true : false;
            $args['allow_redirects'] = $redirects;
        }else{
            $args['allow_redirects'] = false;
        }

        //connect timeout 
        $args['connect_timeout'] = $args['connect_timeout'] ?? Configurator::get('globalConfig/main/task/context/connect_timeout');
        $args['connect_timeout'] <= 0 && $args['connect_timeout'] = 2;

        //transfer timeout
        $args['timeout'] = $args['timeout'] ?? Configurator::get('globalConfig/main/task/context/transfer_timeout');
        $args['timeout'] <= 0 && $args['timeout'] = 5;

        //task context
        if(isset($args['context']) && is_array($args['context']))
        {
            array_walk($args['context'], function($v, $k)use(&$args){
                $args[$k] = $v;
            });
        }

        //unset ditry data
        $unset_array = ['id', 'rule_name', 'rule', 'depth', 'create_time', 'referer', 'context'];
        array_walk($unset_array, function($v, $k)use(&$args){
            unset($args[$v]);
        });

        return $args;
    }

    /**
     * @brief    read download data which maybe from cache  
     *
     * @param    array|string   $task
     * @param    boolean|null   $from_cache
     *
     * @return   string | false
     */
    public function readDownloadData($task, $from_cache = null)
    {
        //also support $args as url string
        is_string($task) && $task = ['url' => $task];

        if(empty($task) || empty($task['url']) || true !== Tool::checkUrl($task['url'])) 
        {
            Logger::warn($this->langConfig['downloader_task_args_invalid']);
            return false;
        }

        //$from_cache have the highest priority, then task setting, then global config
        if(is_bool($from_cache)){
            $enabled = $from_cache;
        } else {
            if(isset($task['context']['cache_enabled'])){
                $enabled = true === $task['context']['cache_enabled'] ? true : false;
            } else {
                $enabled = Configurator::get('globalConfig/main/task/context/cache_enabled') ?? false;
            }
        }

        if(true === $enabled){
            Logger::warn(Tool::replacePlaceHolder($this->langConfig['downloader_cache_enabled'], [
                'task_id' => $task['id'],
            ]));
        }else{
            Logger::warn(Tool::replacePlaceHolder($this->langConfig['downloader_cache_disabled'], [
                'task_id' => $task['id'],
            ]));
        } 

        $download_data = '';

        //cache directory
        if(!empty($task['context']['cache_directory']) && is_string($task['context']['cache_directory'])){
            $cache_dir = $task['context']['cache_directory'];
        }else{
            $cache_dir = Configurator::get('globalConfig/main/task/context/cache_directory') ?? sys_get_temp_dir();
        }

        //cache filename
        $cache_file = md5($task['url']);

        //full cache path
        $cache_path = $cache_dir . DIRECTORY_SEPARATOR . $cache_file;
        $cache_path = preg_replace("/\/*\//is", DIRECTORY_SEPARATOR, $cache_path);

        if(true === $enabled && is_file($cache_path) && file_exists($cache_path))
        {
            Logger::warn(Tool::replacePlaceHolder($this->langConfig['downloader_read_from_cache'], [
                'task_id' => $task['id'],
                'cache_path' => $cache_path,
            ]));
            $download_data = file_get_contents($cache_path);
            if(!empty($download_data)) return $download_data;
        }

        //execute download
        $result = $this->download($task);
        if(0 <> $result['error_code'])
        {
            $error_msg = Tool::replacePlaceHolder($result['error_msg'], $result['extra_msg']);
            Logger::error($error_msg);

            //trigger user callback
            $error = [
                'error_code' => $result['error_code'],
                'error_msg'  => $error_msg,
                'extra_msg'  => $result['extra_msg'],
            ];
            $returning = $this->triggerUserCallback('onFailDownload', $this, $error, $task);
            if(false === $returning) return false;

            return false;
        }

        //now we have got download data successfully
        $download_data = $result['extra_msg']['content'];

        //try to cache download data when both of the following conditions are met simultaneously
        //1. cache is enabled 
        //2. sizeof($old_download_data) <> sizeof($new_download_data) 
        $cache_by_size = false;
        if(!file_exists($cache_path) || strlen($download_data) <> strlen(file_get_contents($cache_path)))
        {
            $cache_by_size = true;
        }

        if(true === $enabled && true === $cache_by_size)
        {
            $rs = Tool::createMultiDirectory($cache_dir);

            if(true !== $rs){
                Logger::warn(Tool::replacePlaceHolder($this->langConfig['downloader_create_cache_failed'], [
                    'task_id' => $task['id'],
                    'cache_path'  => $cache_dir,
                ]));
                return false;
            } 

            file_put_contents($cache_path, $download_data, LOCK_EX);
            Logger::warn(Tool::replacePlaceHolder($this->langConfig['downloader_write_into_cache'], [
                'task_id' => $task['id'],
                'cache_path'  => $cache_path,
            ]));
        }

        return $download_data;
    }

    /**
     * @brief    set the send buffer size of connection from downloader to parser
     *
     * @param    int    $size
     *
     * @return   object
     */
    public function setSendBufferSize($size = 10240000)
    {
        !Tool::checkIsInt($size) && $size = $this->_sendToParserBufferSize;

        $this->_sendToParserBufferSize = $size;

        return $this;
    }

    /**
     * @brief    get the send buffer size of connection from downloader to parser 
     *
     * @return   int
     */
    public function getSendBufferSize()
    {
        return $this->_sendToParserBufferSize;
    }

    /**
     * @brief    install timer
     *
     * @return   object
     */
    public function installTimer()
    {
        $this->timerId = Timer::add($this->getTaskCrawlInterval(), array($this, 'consumeOneTask'), [], true);

        return $this;
    }

    /**
     * @brief    remove task timer
     *
     * @return   object
     */
    public function removeTimer()
    {
        $this->getTimerId() > 0 && Timer::del($this->getTimerId());

        return $this;
    }

    /**
     * @brief    get timer id     
     *
     * @return   int
     */
    public function getTimerId()
    {
        return $this->timerId;
    }

}



