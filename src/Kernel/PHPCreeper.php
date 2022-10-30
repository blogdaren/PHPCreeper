<?php
/**
 * @script   PHPCreeper.php
 * @brief    This file is part of PHPCreeper
 * @author   blogdaren<blogdaren@163.com>
 * @link     http://www.phpcreeper.com
 * @create   2019-09-04
 */

namespace PHPCreeper\Kernel;

require_once __DIR__ . '/Library/Common/Functions.php';
        
use PHPCreeper\Kernel\Service\Service;
use PHPCreeper\Kernel\Service\Provider\SystemServiceProvider;
use PHPCreeper\Kernel\Service\Provider\HttpServiceProvider;
use PHPCreeper\Kernel\Service\Provider\PluginServiceProvider;
use PHPCreeper\Kernel\Service\Provider\QueueServiceProvider;
use PHPCreeper\Kernel\Service\Provider\LockServiceProvider;
use PHPCreeper\Kernel\Service\Provider\LanguageServiceProvider;
use PHPCreeper\Kernel\Service\Provider\ExtractorServiceProvider;
use PHPCreeper\Kernel\Service\Provider\DropDuplicateServiceProvider;
use PHPCreeper\Kernel\Slot\BrokerInterface;
use PHPCreeper\Kernel\Slot\DropDuplicateInterface;
use PHPCreeper\Kernel\Slot\HttpClientInterface;
use PHPCreeper\Kernel\Slot\LockInterface;
use PHPCreeper\Kernel\Slot\PluginInterface;
use PHPCreeper\Kernel\Library\Helper\Tool;
use CustomTerminalColor\Color;
use Configurator\Configurator;
use Logger\Logger;
use Workerman\Worker;


class PHPCreeper extends Worker
{
    /**
     * current version
     *
     * @var string
     */
    const  CURRENT_VERSION = '1.5.3';

    /**
     * valid assemble package methods
     *
     * @var array
     */
    const ALLOWED_ASSEMBLE_PACKAGE_METHODS = ['json', 'serialize', 'msgpack'];

    /**
     * built-in middle classes 
     *
     * @var array
     */
    const PHPCREEPER_BUILTIN_MIDDLE_CLASSES = [
        'producer'   => 'PHPCreeper\Producer',
        'downloader' => 'PHPCreeper\Downloader',
        'parser'     => 'PHPCreeper\Parser',
        'server'     => 'PHPCreeper\Server',
    ];

    /**
     * pseudo worker
     *
     * @var string
     */
    const PSEUDO_WORKER = 'phpcreeper';

    /**
     * valid log level
     *
     * @var array 
     */
    const ALLOWED_LOG_LEVEL = ['info', 'debug', 'warn', 'error', 'crazy'];

    /**
     * http client
     *
     * @var HttpClientInterface|null
     */
    public $httpClient = null;

    /**
     * queue client
     *
     * @var object
     */
    public $queueClient = null;

    /**
     * redis client
     *
     * @var object
     */
    public $redisClient = null;

    /**
     * lock helper
     *
     * @var object
     */
    public $lockHelper = null;

    /**
     * data extractor
     *
     * @var object
     */
    public $extractor = null;

    /**
     * drop duplicate filter
     *
     * @var object
     */
    public $dropDuplicateFilter = null;

    /**
     * language config
     *
     * @var array
     */
    public $langConfig = [];

    /**
     * PHPCreeper config
     *
     * @var array
     */
    private $_config = [];

    /**
     * flag indicates whether has checked environment or not 
     *
     * @var string
     */
    static public $hasCheckedEnvironment= false;

    /**
     * flag indicates whether has shown GUI or not 
     *
     * @var string
     */
    static public $hasShownGui = false;

    /**
     * start time
     *
     * @var int
     */
    static protected $_startTime = 0;

    /**
     * phpcreeper instance 
     *
     * @var object
     */
    static private  $_instance = null;

    /**
     * basic service
     *
     * @var object
     */
    static protected $_service = null;

    /**
     * whether run as multi worker or not 
     *
     * @var object
     */
    static public $isRunAsMultiWorker = true;

    /**
     * client socket address
     *
     * [
     *   'key1' => ['127.0.0.1:5555', '127.0.0.1:6666', ..., '']
     *   'key2' => ['127.0.0.1:5555', '127.0.0.1:6666', ..., '']
     *   .......................................................
     *   'keyN' => ['127.0.0.1:5555', '127.0.0.1:6666', ..., '']
     * ]
     *
     * @var array
     */
    protected   $_clientSocketAddress = [];

    /**
     * server socket address
     *
     * @var string
     */
    protected   $_serverSocketAddress = '';

    /**
     * server socket context
     *
     * @var array
     */
    protected   $_serverSocketContext = [];

    /**
     * redeclare worker status
     *
     * @var string
     */
    public $status = '';

    /**
     * redeclare worker socket 
     *
     * @var string
     */
    public $socket = '';

    /**
     * redeclare workerId
     *
     * @var string
     */
    public $workerId = '';

    /**
     * phpcreeper instances
     *
     * @var string
     */
    static private $_phpcreeperInstances = [];

    /**
     * indicates whether has logged as single worker mode
     *
     * @var boolean
     */
    static private $_hasLoggedAsSingleWorker = false;

    /**
     * runtime language
     *
     * @var string
     */
    static private $_lang = 'zh';

    /**
     * runtime log file
     *
     * @var array
     */
    static private $_logFile = [];

    /**
     * runtime log level
     *
     * @var array
     */
    static private $_logLevel = [];

    /**
     * default redis client
     *
     * @var string
     */
    static private $_defaultRedisClient = 'predis';

    /**
     * default timezone 
     *
     * @var string
     */
    static private $_defaultTimezone = 'Asia/Shanghai';

    /**
     * user callbacks
     *
     * @var array
     */
    static public $callbacks = array(
        'onProducerStart'      => null,
        'onProducerStop'       => null,
        'onProducerReload'     => null,
        'onDownloaderStart'    => null,
        'onDownloaderStop'     => null,
        'onDownloaderReload'   => null,
        'onDownloaderMessage'  => null,
        'onBeforeDownload'     => null,
        'onStartDownload'      => null,
        'onAfterDownload'      => null,
        'onParserStart'        => null,
        'onParserStop'         => null,
        'onParserReload'       => null,
        'onParserMessage'      => null,
        'onParserFindUrl'      => null,
        'onParserExtractField' => null,
        'onServerStart'        => null,
        'onServerStop'         => null,
        'onServerReload'       => null,
        'onServerConnect'      => null,
        'onServerClose'        => null,
        'onServerMessage'      => null,
        'onServerBufferFull'   => null,
        'onServerBufferDrain'  => null,
        'onServerError'        => null,
    );

    /**
     * @brief    __construct    
     *
     * @return   void
     */
    public function __construct($config = [])
    {
        //check environment
        self::checkEnvironment();

        //set service
        self::setService();

        //set config
        !empty($config) && $this->setConfig($config);

        //important: reset 0 to keep setCount() have higher priority
        $this->count = 0;

        //save phpcreeper instances
        self::$_phpcreeperInstances[] = $this;
    }

    /**
     * @brief    attention!! boot() must be called after app worker initialized
     *
     * @return   void
     */
    public function boot()
    {
        //check app worker
        defined('USE_PHPCREEPER_APPLICATION_FRAMEWORK') && self::checkAppWorker();

        //only allow to run downloader workers in single worker mode
        if(false === self::$isRunAsMultiWorker) 
        {
            if(empty(self::$_hasLoggedAsSingleWorker)) 
            {
                $this->bindLangConfig(self::getLang());
                self::showSplitLine("WorkerMode");
                Logger::warn(Tool::replacePlaceHolder($this->langConfig['work_as_single_worker_mode'], [
                ]));
                self::$_hasLoggedAsSingleWorker = true;
            }

            $downloader_class = self::PHPCREEPER_BUILTIN_MIDDLE_CLASSES['downloader'];
            if(!$this instanceof $downloader_class) return;

            //keep [count === 1] for the unique downloader worker in single worker mode
            $this->count = 1;
        }

        //init socket
        $socket_address = $this->getServerSocketAddress();
        $socket_context = $this->getServerSocketContext();
        parent::__construct($socket_address, $socket_context);

        //display gui
        self::displayGui();
    }

    /**
     * @brief    get instance
     *
     * @return   object
     */
    static public function getInstance()
    {
        if(!self::$_instance instanceof self)
        {
            self::$_instance = new self();
        }

        return self::$_instance;
    }

    /**
     * @brief    set app worker name
     *
     * @param    string  $name
     *
     * @return   object
     */
    public function setName($name = 'none')
    {
        $this->name = (empty($name) || !is_string($name)) ? 'none' : $name;

        return $this;
    }

    /**
     * @brief    set app worker count
     *
     * @param    int        $count
     * @param    boolean    $prefer_by_cpu_cores
     *
     * @return   object
     */
    public function setCount($count = 1, $prefer_by_cpu_cores = false)
    {
        $this->count = ($count <= 0 || !is_int($count)) ? 1 : $count;

        if(true === $prefer_by_cpu_cores)
        {
            $this->count = Tool::getCpuCoreCount() * 2;
        }

        return $this;
    }

    /**
     * @brief    enable or disable multi worker mode
     *
     * @param    boolean $mode
     *
     * @return   null
     */
    static public function enableMultiWorkerMode($mode = true)
    {
        !is_bool($mode) && $mode = true;

        self::$isRunAsMultiWorker = (true !== $mode) ? false : true;
    }

    /**
     * @brief    get worker run mode
     *
     * @return   string
     */
    static public function getWorkerMode()
    {
        return true === self::$isRunAsMultiWorker ? 'Multi' : 'Single';
    }

    /**
     * @brief    init middleware     
     *
     * @return   object
     */
    public function initMiddleware()
    {
        $this->bindLangConfig(self::getLang());
        $this->bindHttpClient('guzzle', []);
        $this->bindQueueClient('php');
        $this->bindExtractor();

        if(self::$isRunAsMultiWorker)
        {
            $rdbclient = self::getDefaultRedisClient();
            $rdbconfig = (array)Configurator::get('globalConfig/database/redis');
            $this->bindQueueClient($rdbclient, $rdbconfig);
            $this->bindRedisClient($rdbclient, $rdbconfig);
            $this->bindLockHelper($rdbclient,  $rdbconfig);
            $this->bindDropDuplicateFilter($rdbclient, $rdbconfig);
        }

        return $this;
    }

    /**
     * @brief    init logger     
     *
     * @return   object
     */
    public function initLogger()
    {
        foreach(SELF::PHPCREEPER_BUILTIN_MIDDLE_CLASSES as $k => $class_name)
        {
            if($this instanceof $class_name)
            {
                $worker = $k;
                break;
            }
        }

        if(empty($worker)) return $this;

        //app worker
        $worker_name = str_pad($this->name, 15, " ", STR_PAD_RIGHT);  

        //set log message prefix
        $length = strlen($this->count) <= 2 ? 2 : strlen($this->count);
        Logger::setMessagePrefix(
            Tool::replacePlaceHolder($this->langConfig["logger_prefix_{$worker}"], [
                'worker_id'   => str_pad($this->id + 1, $length, 0, STR_PAD_LEFT),
                'worker_name' => $worker_name,
            ])
        );

        //decide which kind of log level data should not be shown under debug mode
        $log_level = self::getLogLevel($worker);
        !empty($log_level) && Logger::disableLogShowWithLevel($log_level);

        //decide the log path where to store log message by worker
        $log_file = self::getLogFile($worker);
        !empty($log_file) && Logger::setLogFile($log_file);

        return $this;
    }

    /**
     * @brief    set log file for worker
     *
     * @param    string  $path
     * @param    string  $worker
     *
     * @return   void
     */
    static public function setLogFile($path, $worker = '')
    {
        $workers = array_keys(self::PHPCREEPER_BUILTIN_MIDDLE_CLASSES);

        if(!is_string($worker) || !in_array($worker, $workers))
        {
            $worker = self::PSEUDO_WORKER;
        } 

        self::$_logFile[strtoupper($worker)] = $path;
    }

    /**
     * @brief    get log file for worker
     *
     * @param    string  $worker
     *
     * @return   string
     */
    static public function getLogFile($worker = '')
    {    
        if(!empty(self::$_logFile[strtoupper(self::PSEUDO_WORKER)]))
        {
            $worker = strtoupper(self::PSEUDO_WORKER);
            $path = self::$_logFile[strtoupper(self::PSEUDO_WORKER)];
        }
        else
        {
            $worker = strtoupper($worker);
            $path = self::$_logFile[$worker] ?? '';
        }

        if(empty($path))
        {
            $path = Configurator::get("globalConfig/main/logger/{$worker}/log_file_path");
        }

        if(empty($path) || is_dir($path)) return '';

        $path = preg_replace("/(\/|\\\)+/is", DIRECTORY_SEPARATOR, $path);
        $path_info = pathinfo($path);
        $dir = $path_info['dirname'];
        $basename = $path_info['basename'];

        Tool::createMultiDirectory($dir);

        $_worker = strtolower($worker);
        $fullfile = $dir . DIRECTORY_SEPARATOR . $basename . ".{$_worker}" ;

        return $fullfile;
    } 

    /**
     * @brief    disable log level for worker
     *
     * @param    array   $level     [info|debug|warn|error|crazy]
     * @param    string  $worker
     *
     * @return   void
     */
    static public function disableLogLevel($level = [], $worker = '')
    {
        if(empty($level) || !is_array($level))  return;

        $workers = array_keys(self::PHPCREEPER_BUILTIN_MIDDLE_CLASSES);

        if(!is_string($worker) || !in_array($worker, $workers))
        {
            $worker = self::PSEUDO_WORKER;
        } 

        $valid_log_level = array_intersect($level, self::ALLOWED_LOG_LEVEL);

        self::$_logLevel[strtoupper($worker)] = $valid_log_level;
    }

    /**
     * @brief    get log level by worker
     *
     * @param    string  $worker
     *
     * @return   array
     */
    static public function getLogLevel($worker = '')
    {    
        $log_level = [];

        if(!empty(self::$_logLevel[strtoupper(self::PSEUDO_WORKER)]))
        {
            $worker = strtoupper(self::PSEUDO_WORKER);
            $log_level = self::$_logLevel[strtoupper(self::PSEUDO_WORKER)];
        }
        else
        {
            $worker = strtoupper($worker);
            $log_level = self::$_logLevel[$worker] ?? '';
        }

        if(empty($log_level))
        {
            $log_level = Configurator::get("globalConfig/main/logger/{$worker}/log_disable_level");
        }

        return $log_level;
    } 

    /**
     * @brief    reset master pid file
     *
     * @param    string  $pid_file
     *
     * @return   void
     */
    static public function setMasterPidFile($pid_file = '')
    {
        if(!is_string($pid_file)) return;

        if(empty($pid_file)) 
        {
            $backtrace  = debug_backtrace();
            $start_file = $backtrace[\count($backtrace) - 1]['file'];
            $unique_prefix = str_replace('/', '_', $start_file);
            $pid_file = dirname(__FILE__, 3) . DIRECTORY_SEPARATOR . "$unique_prefix.pid";
        }

        Worker::$pidFile = $pid_file;
    }

    /**
     * @brief    get master pid file
     *
     * @return   string
     */
    static public function getMasterPidFile()
    {
        if(empty(Worker::$pidFile)) return '';

        if(Worker::$pidFile[0] <> '/')
        {
            $backtrace  = \debug_backtrace();
            $start_file = $backtrace[\count($backtrace) - 1]['file'];
            $pid_file = dirname($start_file) . DIRECTORY_SEPARATOR .  Worker::$pidFile;
            $pid_file = Tool::convertToAbsolutePath($pid_file);
        }
        else
        {
            $pid_file = Worker::$pidFile;
        }

        return $pid_file;
    }

    /**
     * @brief    check whether middleware is valid  
     *
     * @return   array
     */
    public function checkWhetherMiddlewareIsValid()
    {
        $extra = [];

        if(false === $this->httpClient instanceof HttpClientInterface)
        {
            return Tool::throwback('-300', $this->langConfig['invalid_httpclient_object'], $extra);
        }

        if(false === $this->queueClient instanceof BrokerInterface)
        {
            return Tool::throwback('-301', $this->langConfig['invalid_queueclient_object'], $extra);
        }

        return Tool::throwback('0', 'success', $extra);
    }

    /**
     * @brief    set config
     *
     * @param    array  $config
     *
     * @return   object
     */
    public function setConfig(array $config = [])
    {
        $this->_config = $config = array_merge($this->_config, $config);

        $appworker = $this->_config['main']['appworker'] ?? '';
        if(array_key_exists($appworker, $config)) unset($config[$appworker]);

        array_map(function($key)use(&$config){
            if(isset($config[$key])) unset($config[$key]);
        }, ['redis', 'mysql', 'lang']);

        Configurator::reset('globalConfig', $config);

        if(!empty($this->_config['redis']))
        {
            Configurator::set('globalConfig/database/redis', $this->_config['redis']);
        }

        if(!empty($this->_config['mysql']))
        {
            Configurator::set('globalConfig/database/mysql', $this->_config['mysql']);
        }

        if(!empty($this->_config['lang']))
        {
            Configurator::set('globalConfig/main/language', $this->_config['lang']);
        }

        return $this;
    }

    /**
     * @brief    get global config  
     *
     * @return   array
     */
    public function getGlobalConfig()
    {
        $config = Configurator::get("globalConfig");

        return $config;
    }

    /**
     * @brief    get app worker config  
     *
     * @return   array
     */
    public function getAppWorkerConfig()
    {
        if(empty($this->_config['main']['appworker'])) return [];

        $appworker = $this->_config['main']['appworker'];

        return $this->_config[$appworker] ?? [];
    }

    /**
     * @brief    set server socket address     
     *
     * @param    string  $address
     *
     * @return   object
     */
    public function setServerSocketAddress($address = '')
    {
        !empty($address) && $this->_serverSocketAddress = $address;

        return $this;
    }

    /**
     * @brief    set server socket context     
     *
     * @param    array  $context
     *
     * @return   object
     */
    public function setServerSocketContext($context = [])
    {
        !empty($context) && $this->_serverSocketContext = $context;

        return $this;
    }

    /**
     * @brief    get server socket address   
     *
     * @return   string
     */
    public function getServerSocketAddress()
    {
        if(!empty($this->_serverSocketAddress)) return $this->_serverSocketAddress;

        $scheme = $this->getAppWorkerConfig()['socket']['server']['scheme'] ?? 'text';
        $host   = $this->getAppWorkerConfig()['socket']['server']['host']   ?? '';
        $port   = $this->getAppWorkerConfig()['socket']['server']['port']   ?? '';

        $socket_address = '';
        ($host && $port) && $socket_address = $scheme . "://" . $host . ":" . $port;

        return $socket_address;
    }

    /**
     * @brief    get server socket context   
     *
     * @return   array
     */
    public function getServerSocketContext()
    {
        if(!empty($this->_serverSocketContext) && is_array($this->_serverSocketContext)) 
        {
            return $this->_serverSocketContext;
        }

        $context = $this->getAppWorkerConfig()['socket']['server']['context'] ?? [];

        return  is_array($context) ? $context : [];
    }

    /**
     * @brief    setClientSocketAddress     
     *
     * @param    string|array   $address
     * @param    string         $key
     *
     * @return   object
     */
    public function setClientSocketAddress($address = [], $key = 'parser')
    {
        if(empty($key) || !is_string($key) || empty($address)) return $this;

        !is_array($address) && $address = [$address];

        $this->_clientSocketAddress[$key] = $address;

        return $this;
    }

    /**
     * @brief    appendClientSocketAddress     
     *
     * @param    string|array   $address
     * @param    string         $key
     *
     * @return   object
     */
    public function appendClientSocketAddress($address = [], $key = 'parser' )
    {
        if(empty($key) || !is_string($key) || empty($address)) return $this;

        !is_array($address) && $address = [$address];

        if(!isset($this->_clientSocketAddress[$key]))
        {
            $this->_clientSocketAddress[$key] = [];
        }

        $this->_clientSocketAddress[$key] = array_merge($this->_clientSocketAddress[$key], $address);

        return $this;
    }

    /**
     * @brief    get client socket address to target  
     *
     * @param    string  $key
     *
     * @return   array
     */
    public function getClientSocketAddress($key = 'parser')
    {
        $parser_address = [];

        if(isset($this->_clientSocketAddress[$key]))
        {
            $address = $this->_clientSocketAddress[$key];
            foreach($address as $k => $v)
            {
                $data = parse_url($v);
                if(empty($data)) continue;
                $parser_address[$k] = $data;
            }
        }

        if(empty($parser_address))
        {
            $parser_address = $this->getAppWorkerConfig()['socket']['client'][$key] ?? [];
        }

        if(empty($parser_address) || !is_array($parser_address)) return false;

        $array_length = Tool::getArrayDepth($parser_address);
        1 === $array_length && $parser_address = [$parser_address];

        $addresses = [];

        foreach($parser_address as $k => $address)
        {
            if(empty($address['host']) || empty($address['port'])) continue;

            if(empty($address['scheme']) || !is_string($address['scheme']))
            {
                $address['scheme'] = 'text';
            }

            $address['target'] = $address['host'] . ":" . $address['port'];
            $address['socket'] = $address['scheme']. "://" . $address['host']. ":" . $address['port'];

            if(empty($address['context']) || !is_array($address['context']))
            {
                $address['context'] = [];
            }

            $addresses[$k] = $address;
        }

        return $addresses;
    }

    /**
     * @brief    __call     
     *
     * @param    string  $function_name
     * @param    mixed   $args
     *
     * @return   closure 
     */
    public function __call($function_name, $args)
    {
        return $this->getService()->getName($function_name)->call($this, ...$args);
    }

    /**
     * @brief    __callStatic   
     *
     * @param    string  $function_name
     * @param    mixed   $args
     *
     * @return   closure
     */
    static public function __callStatic($function_name, $args)
    {
        if('showHelpByeBye' == $function_name) 
        {
            return self::{$function_name}(...$args);
        }

        return self::getInstance()->{$function_name}(...$args);
    }

    /**
     * @brief    __set  
     *
     * @param    string  $k
     * @param    mixed   $v
     *
     * @return   boolean
     */
    public function __set($k, $v)
    {
        //$k == 'whenStart' && $k = 'onStart'; 
        if(array_key_exists($k, self::$callbacks) && is_callable($v))
        {
            self::$callbacks[$k][] = $v;
        }
        else
        {
            $this->$k = $v;
        }

        return false;
    }

    /**
     * @brief    triggerUserCallback    
     *
     * @param    string  $name
     * @param    mixed   $args
     *
     * @return   mixed
     */
    public function triggerUserCallback($name, ...$args)
    {
        $callbacks = self::$callbacks[$name];

        if(empty($callbacks)) return;

        $map = array_map(function($callback)use($args){
            return call_user_func($callback, ...$args);
        }, $callbacks);

        if(!isset($map[0])) return;

        return $map[0];
    }

    /**
     * @brief    proxy service inject 
     *
     * @param    string     $name
     * @param    closure    $provider
     *
     * @return   
     */
    public function inject(string $name, \Closure $provider)
    {   
        $this->getService()->inject($name, $provider);

        return $this;
    }   

    /**
     * @brief    set service     
     *
     * @return   object
     */
    public function setService()
    {
        $service_providers = self::getServiceProviders();

        if(empty(self::$_service))
        {
            self::$_service = new Service($service_providers, $this);
        }

        return $this;
    }

    /**
     * @brief    get service     
     *
     * @return   object
     */
    public function getService()
    {
        return self::$_service;
    }

    /**
     * @brief    get service providers    
     *
     * @return   array
     */
    static public function getServiceProviders()
    {
        return [
            SystemServiceProvider::class,
            HttpServiceProvider::class,
            PluginServiceProvider::class,
            QueueServiceProvider::class,
            LockServiceProvider::class,
            LanguageServiceProvider::class,
            ExtractorServiceProvider::class,
            DropDuplicateServiceProvider::class,
        ];
    }

    /**
     * @brief    check whether data compress is enabled or not
     *
     * @return   boolean
     */
    static public function checkWhetherDataCompressIsEnabled()
    {
        $flag = Configurator::get('globalConfig/main/task/compress/enabled');

        return true === $flag ? true : false;
    }

    /**
     * @brief    assemble package 
     *
     * @param    mixed  $data
     * @param    int    $level
     *
     * @return   string
     */
    public function assemblePackage($data, $level = 6)
    {
        if(empty($data)) return '';

        //encode
        $content = Tool::encodeData($data, self::getAssemblePackageMethod());

        //compress
        if(true === self::checkWhetherDataCompressIsEnabled()) 
        {
            $method = Configurator::get('globalConfig/main/task/compress/algorithm');
            empty($method) && $method = 'gzip';
            $method = strtolower($method);
            Logger::debug(Tool::replacePlaceHolder($this->langConfig['http_transfer_compress'], [
                'algorithm' => $method,
            ]));
            $content = Tool::compressData($content, $level, $method);
        }

        return $content;
    }

    /**
     * @brief    disassemble package
     *
     * @param    minxed  $data
     * @param    int     $length
     *
     * @return   string
     */
    public function disassemblePackage($data, $length = 0)
    {
        if(empty($data)) return '';

        //uncompress
        if(true === self::checkWhetherDataCompressIsEnabled()) 
        {
            $length <= 0 && $length = 0;
            $method = Configurator::get('globalConfig/main/task/compress/algorithm');
            empty($method) && $method = 'gzip';
            $method = strtolower($method);
            $data = Tool::uncompressData($data, $length, $method);
        }

        //decode
        $content = Tool::decodeData($data, self::getAssemblePackageMethod());

        return $content;
    }

    /**
     * @brief    get assemble package method   
     *
     * @return   string
     */
    public function getAssemblePackageMethod()
    {
        $assemble_method = Configurator::get('globalConfig/main/task/assemble_method');

        if(!in_array($assemble_method, self::ALLOWED_ASSEMBLE_PACKAGE_METHODS)) 
        {
            $assemble_method = 'json';
        }

        //necessary to check ext-msgpack
        if('msgpack' === $assemble_method && !Tool::checkWhetherPHPExtensionIsLoaded('msgpack', false)) 
        {
            Logger::error(Tool::replacePlaceHolder($this->langConfig['ext_msgpack_not_install']));
            $assemble_method = 'json';
        }

        Logger::debug(Tool::replacePlaceHolder($this->langConfig['http_assemble_method'],[
            'assemble_method' => $assemble_method,
        ]));

        return $assemble_method;
    }

    /**
     * @brief    check execute environment 
     *
     * @return   boolean | exit
     */
    static public function checkEnvironment()
    {
        //flag indicates whether has checked environment or not 
        if(self::$hasCheckedEnvironment) return;

        //check system platform
        !Tool::checkWhetherSystemPlatformIsLinuxLikeSystem() && self::showHelpByeBye('only allowed to run on Linux-Like System'); 

        //check sapi
        strtolower(PHP_SAPI) != 'cli'   && self::showHelpByeBye("only allowed to run on the command line");

        //check php minor version
        version_compare(PHP_VERSION, '7.0.0', 'lt') && self::showHelpByeBye("the PHP VERSION must greater than >= 7.0.0");

        //check posix extension
        !Tool::checkWhetherPHPExtensionIsLoaded('posix', false) && self::showHelpByeBye('plz make sure the POSIX extension is installed');

        //check pcntl extension
        !Tool::checkWhetherPHPExtensionIsLoaded('pcntl', false) && self::showHelpByeBye('plz make sure the PCNTL extension is installed');
        
        //check php safe function 
        $check_result = Tool::checkWhetherPHPSafeFunctionIsDisabled();
        if(false !== $check_result) 
        {
            $php_config = self::getPHPConfiguration();
            $msg = "`$check_result` function may be disabled, plz check disable_functions in {$php_config}";
            self::showHelpByeBye($msg);
        }

        //remember to change the flag bit
        self::$hasCheckedEnvironment = true;

        return true;
    }

    /**
     * @brief    check app worker    
     *
     * @return   void | exit
     */
    public function checkAppWorker()
    {
        //app worker
        $worker = $this->_config['main']['appworker'] ?? '';
        $class = get_class($this);
        empty($worker) && self::showHelpByeBye("you must pass the `\$config` param to the entity of `new {$class}(\$config)`");

        //when configure phpcreeper run as single worker
        if(isset($this->_config['main']['multi_worker']) && false === $this->_config['main']['multi_worker'])
        {
            self::$isRunAsMultiWorker = false;
            $this->_config['main']['start'] = [];
            $downloader_class = self::PHPCREEPER_BUILTIN_MIDDLE_CLASSES['downloader'];
            $this instanceof $downloader_class && $this->_config['main']['start'][$worker] = true;
            Configurator::reset('globalConfig', $this->_config);
        }

        //check redis extension in multi worker mode
        if(true === self::$isRunAsMultiWorker)
        {
            if(self::getDefaultRedisClient() == 'predis' && version_compare(PHP_VERSION, '7.2.0', 'lt'))
            {
                self::showHelpByeBye('predis client require PHP_VERSION >= 7.2.0, current PHP_VRESION:' . PHP_VERSION);
            }

            if(self::getDefaultRedisClient() == 'redis' && !Tool::checkWhetherPHPExtensionIsLoaded('redis', false)) 
            {
                self::showHelpByeBye('plz make sure the REDIS extension is installed in multi worker mode');
            }
        }

        //set worker name
        if('none' == $this->name && !empty($this->_config[$worker]['name']) && is_string($this->_config[$worker]['name'])) 
        {
            $this->name = $this->_config[$worker]['name'];
        }

        //check worker name
        if(false === self::checkSpiderName($this->name))
        {
            self::clearScreen();
            self::showHelpByeBye('worker name `' . $this->name . '` invalid, only alpha or number combined, and 30 characters at most.');
        }

        //set worker count 
        if(0 == $this->count && !empty($this->_config[$worker]['count']) && $this->_config[$worker]['count'] > 0) 
        {
            $this->count = $this->_config[$worker]['count'];
        }

        $this->count <= 0 && $this->count = 1;
    }

    /**
     * @brief    clear screen    
     *
     * @return   void
     */
    static public function clearScreen()
    {    
        if(!Tool::checkWhetherSystemPlatformIsLinuxLikeSystem()) return; 

        $input = array(27, 91, 72, 27, 91, 50, 74); 

        array_map(function($v){
            echo chr($v);
        }, $input);
    }    

    /**
     * @brief    check spider name    
     *
     * @param    string  $name
     *
     * @return   boolean
     */
    static public function checkSpiderName($name = '')
    {
        if(!preg_match("/^[a-zA-Z0-9]{1,30}$/is", $name)) return false;

        return true;
    }

    /**
     * @brief    show banner2 - Font Name: Slant
     *
     * @return   void
     */
    static public function showBanner()
    {
        self::showSplitLine('PHPCreeper');

        print <<<EOT
    ____  __  ______  ______                              
   / __ \/ / / / __ \/ ____/_______  ___  ____  ___  _____      An Async Event Driven Spider Engine
  / /_/ / /_/ / /_/ / /   / ___/ _ \/ _ \/ __ \/ _ \/ ___/
 / ____/ __  / ____/ /___/ /  /  __/  __/ /_/ /  __/ /          @link  http://www.phpcreeper.com
/_/   /_/ /_/_/    \____/_/   \___/\___/ .___/\___/_/     
                                      /_/                       @link  http://www.blogdaren.com


EOT;
    }

    /**
     * @brief    show help before exiting
     *
     * @param    string  $msg
     *
     * @return   exit
     */
    static protected function showHelpByeBye($msg = "")
    {
        if(!empty($msg))
        {
            self::showBanner();
            !is_string($msg) && $msg = json_encode($msg);
            self::showSplitLine('Error Report');
            $error_msg = PHP_EOL . "Runtime Error: ";
            //$error_msg = PHP_EOL;
            $error_msg .= wordwrap($msg, 110, "\n        ") . PHP_EOL . PHP_EOL;
            Color::showError($error_msg);
        }

        $usage = self::showEnvironment() . PHP_EOL . self::showSplitLine();

        exit($usage);
    }

    /**
     * @brief    show environment
     *
     * @return   void
     */
    static public function showEnvironment()
    {
        self::showSplitLine('Environment');

        $total_length = self::getSingleLineTotalLength();
        $php_config   = self::getPHPConfiguration();
        $line_version = 'PHPCreeper  Version:   ' . self::CURRENT_VERSION;
        $line_version .= str_pad('PHP     Version:   ', 48, ' ', STR_PAD_LEFT) . PHP_VERSION . PHP_EOL;
        @extract(self::checkPHPExtensions());
        $line_version .= str_pad('POSIX       Extension:', 8, ' ', STR_PAD_LEFT) .' '.Color::getColorfulText($posix_text, $posix_color, 'black');
        $line_version .= " <Required>";
        $line_version .= str_pad('PCNTL   Extension:', 34, ' ', STR_PAD_LEFT) .' '.Color::getColorfulText($pcntl_text, $pcntl_color, 'black');
        $line_version .= " <Required>" . PHP_EOL;
        $line_version .= str_pad('REDIS       Extension:', 8, ' ', STR_PAD_LEFT) .' '.Color::getColorfulText($redis_text, $redis_color, 'black');
        $line_version .= " [Optional]";
        $line_version .= str_pad('EVENT   Extension:', 34, ' ', STR_PAD_LEFT) .' '.Color::getColorfulText($event_text, $event_color, 'black');
        $line_version .= " [Optional]" . PHP_EOL;
        $line_version .= 'System      Platform:  ' . PHP_OS . str_pad('PHP     Configure: ', 48, ' ', STR_PAD_LEFT) . $php_config . PHP_EOL;
        $line_version .= 'Master      PID File:  ' . self::getMasterPidFile() . PHP_EOL;
        !defined('LINE_VERSIOIN_LENGTH') && define('LINE_VERSIOIN_LENGTH', strlen($line_version));

        self::safeEcho($line_version);
    }

    /**
     * @brief    check PHP extensions 
     *
     * @return   array
     */
    static public function checkPHPExtensions()
    {
        $extensions = ['posix', 'pcntl', 'redis', 'event'];

        $ouput = array();
        foreach($extensions as $extension)
        {
            $text_key = $extension . '_text';
            $color_key = $extension . '_color';
            $ouput[$text_key]  =  Tool::checkWhetherPHPExtensionIsLoaded($extension);
            $ouput[$color_key] = 'enabled' == $ouput[$text_key] ? 'light_green' : 'yellow';
        }

        return $ouput;
    }

    /**
     * @brief    safe echo
     *
     * @param    string  $msg
     * @param    string  $show_colorful
     *
     * @return   boolean
     */
    static public function safeEcho($msg, $show_colorful = true)
    {
        $stream = self::setOutputStream();
        if(!$stream) return false;

        if($show_colorful) 
        {
            $line = $white = $yellow = $red = $green = $blue = $skyblue = $ry = $ul = $end = '';
            if(self::$_outputDecorated) 
            {
                $line    =  "\033[1A\n\033[K";
                $white   =  "\033[47;30m";
                $yellow  =  "\033[1m\033[33m";
                $red     =  "\033[1m\033[31m";
                $green   =  "\033[1m\033[32m";
                $blue    =  "\033[1m\033[34m";
                $skyblue =  "\033[1m\033[36m";
                $ry      =  "\033[1m\033[41;33m";
                $ul      =  "\033[1m\033[4m\033[36m";
                $end     =  "\033[0m";
            }

            $color = array($line, $white, $green, $yellow, $skyblue, $red, $blue, $ry, $ul);
            $msg = str_replace(array('<n>', '<w>', '<g>', '<y>', '<s>', '<r>', '<b>', '<t>', '<u>'), $color, $msg);
            $msg = str_replace(array('</n>', '</w>', '</g>', '</y>', '</s>', '</r>', '</b>', '</t>', '</u>'), $end, $msg);
        } 
        elseif(!self::$_outputDecorated) 
        {
            return false;
        }

        fwrite($stream, $msg);
        fflush($stream);

        return true;
    }

    /**
     * @brief    set output stream
     *
     * @param    null  $stream
     *
     * @return   boolean
     */
    static public function setOutputStream($stream = null)
    {
        if(!$stream) 
        {
            $stream = self::$_outputStream ? self::$_outputStream : STDOUT;
        }

        if(!$stream || !is_resource($stream) || 'stream' !== get_resource_type($stream)) 
        {
            return false;
        }

        $stat = fstat($stream);

        if(($stat['mode'] & 0170000) === 0100000) {
            self::$_outputDecorated = false;
        } else {
            self::$_outputDecorated = function_exists('posix_isatty') && posix_isatty($stream);
        }

        return self::$_outputStream = $stream;
    }

    /**
     * @brief    show split line
     *
     * @param    string  $msg
     *
     * @return   void
     */
    static public function showSplitLine($msg = '')
    {
        $label_length = 0;
        !empty($msg) && $msg = "<t>  $msg  </t>";
        !empty($msg) && $label_length = strlen('<t></t>');
        $total_length = self::getSingleLineTotalLength() + $label_length + 27;
        $split_line = '<n>' . str_pad($msg, $total_length, '-', STR_PAD_BOTH) . '</n>'. PHP_EOL;
        self::safeEcho($split_line);
    }

    /**
     * @brief    get php configuration    
     *
     * @return   string
     */
    static public function getPHPConfiguration()
    {
        $cmd = !empty($_SERVER['_']) ? $_SERVER['_'] : "php";
        @exec("$cmd --ini", $buffer, $status);

        if(empty($buffer) || $status <> 0) 
        {
            ob_start(); 
            @phpinfo(); 
            $buffer = ob_get_contents(); 
            ob_end_clean();
            $buffer = explode(PHP_EOL, $buffer);
        }

        $match_line = '';
        foreach($buffer as $k => $v)
        {
            preg_match_all("/Loaded Configuration File/is", $v, $matches);
            if(!empty($matches[0])) {
                $match_line = $v;
                unset($buffer);
                break;
            }
        }

        $result = explode(" ", $match_line);
        $config = !empty($result) ?  array_pop($result) : 'none';

        return $config;
    }

    /**
     * @brief    display gui interface
     *
     * @return   void | boolean
     */
    static public function displayGui()
    {
        //indicates whether has shown GUI or not 
        if(self::$hasShownGui) return;

        //show banner
        self::showBanner();

        //show environment
        self::showEnvironment();

        //show workerman
        self::showSplitLine('Workerman');

        //remember to change the flag bit
        self::$hasShownGui = true;
    }

    /**
     * @brief    rewrite listen     
     *
     * @return   void
     */
    public function serve()
    {
        $this->boot();
        $this->listen();
    }

    /**
     * @brief    set runtime language
     *
     * @param    string  $lang
     *
     * @return   void
     */
    static public function setLang($lang = "zh")
    {
        if(!is_string($lang) || !in_array($lang, ['zh', 'en']))
        {
            return;
        }

        self::$_lang = $lang;
    }

    /**
     * @brief    get runtime language
     *
     * @return   string
     */
    static public function getLang()
    {
        if(!empty(self::$_lang)) return self::$_lang;

        self::$_lang = Configurator::get('globalConfig/main/language') ?? "zh";

        return self::$_lang;
    }

    /**
     * @brief    set default redis client  
     *
     * @param    string  $client
     *
     * @return   void
     */
    static public function setDefaultRedisClient($client = '')
    {
        if(empty($client) || !is_string($client) || !in_array($client, ['redis', 'predis']))
        {
            $client = 'predis';
        }

        self::$_defaultRedisClient = $client;
    }

    /**
     * @brief    get default redis client  
     *
     * @return   string
     */
    static public function getDefaultRedisClient()
    {
        return self::$_defaultRedisClient;
    }

    /**
     * @brief    set default timezone     
     *
     * @param    string  $timezone
     *
     * @return   void
     */
    static public function setDefaultTimezone($timezone = '')
    {
        self::$_defaultTimezone = $default_timezone = "Asia/Shanghai";

        if(empty($timezone) || !is_string($timezone))
        {
            $timezone = $default_timezone;
        }

        if(true === date_default_timezone_set($timezone))
        {
            self::$_defaultTimezone = $timezone;
            return;
        }

        date_default_timezone_set(self::$_defaultTimezone);
    }

    /**
     * @brief    rewrite method runAll
     *
     * @return   void
     */
    static public function runAll()
    {
        //set master pid file
        empty(Worker::$pidFile) && self::setMasterPidFile();

        //set default timezone
        self::setDefaultTimezone(self::$_defaultTimezone);

        foreach(self::$_phpcreeperInstances as $creeper)
        {
            $creeper->boot();
        }

        Worker::runAll();
    }

    /**
     * @brief    alias runAll()
     *
     * @return   void
     */
    static public function start()
    {
        self::runAll();
    }

}



