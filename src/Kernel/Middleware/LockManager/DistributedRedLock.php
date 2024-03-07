<?php
/**
 * @script   DistributedRedLock.php
 * @brief    This file is part of PHPCreeper
 *           注意：本类是一个特殊的独立类，无需实现 LockInterface 接口
 * @author   blogdaren<blogdaren@163.com>
 * @link     http://www.phpcreeper.com
 * @create   2022-12-01
 */

namespace PHPCreeper\Kernel\Middleware\LockManager;

use PHPCreeper\PHPCreeper;
use PHPCreeper\Kernel\Library\Polyfill\Uuid;
use PHPCreeper\Kernel\Library\Helper\Tool;
use Logger\Logger;


class DistributedRedLock
{
    /**
     * redis config
     *
     * @var array
     */
    public $config = array();

    /**
     * expiry time (单位：秒)
     *
     * @var int
     */
    public $expiryTime = 1 * 1000;

    /**
     * clock offset
     *
     * @var int
     */
    private $_clockOffset = 0.01;

    /**
     * redis instances
     *
     * @var string
     */
    public $instances = array();

    /**
     * random token      
     *
     * @var string
     */
    private $_token = '';

    /**
     * retry times
     *
     * @var string
     */
    public $retryTimes = 3;

    /**
     * sleep time (单位：毫秒)
     *
     * @var string
     */
    public $sleepTime = 10;

    /**
     * client type
     *
     * @var string
     */
    private $_clientType = 'predis';

    /**
     * default prefix
     *
     * @var string
     */
    static private $_defaultPrefix = 'PHPCreeper';

    /**
     * prefix
     *
     * @var string
     */
    static private $_prefix = '';

    /**
     * @brief    __construct    
     *
     * @param    array  $config
     *
     * @return   void
     */
    public function __construct(array $config, $type = "predis")
    {
        $this->config = $config;
        $this->setClientType($type)
            ->setExpiryTime(1)      //单位：秒
            ->setSleepTime(100)     //单位：毫秒
            ->setRetryTimes(3)      //单位：次
            ->init();
    }

    /**
     * @brief    set client type  
     *
     * @param    string  $type
     *
     * @return   object
     */
    public function setClientType($type)
    {
        if(!is_string($type) || !in_array($type, ['predis', 'redis']))
        {
            $type = 'predis';
        }

        $this->_clientType = $type;

        return $this;
    }

    /**
     * @brief   set random token
     *
     * @return  object 
     */
    private function _setToken()
    {
        $this->_token = Uuid::uuid_create(1);

        return $this;
    }

    /**
     * @brief    set expiry time (单位：秒)
     *
     * @param    int  $time 
     *
     * @return   object
     */
    public function setExpiryTime($time)
    {
        if($time >= 0)
        {
            $this->expiryTime = $time * 1000;
        }

        return $this;
    }

    /**
     * @brief    set sleep time (单位：毫秒)  
     *
     * @param    int  $time
     *
     * @return   object
     */
    public function setSleepTime($time = 100)
    {
        if((is_int($time) || is_float($time)) && $time >= 0)
        {
            $this->sleepTime = $time;
        }

        return $this;
    }

    /**
     * @brief    set retry times
     *
     * @param    int    $times  (单位：次)
     *
     * @return   object
     */
    public function setRetryTimes($times)
    {
        if(!is_int($times) || $times <= 0)
        {
            $times = 3;
        }

        $this->retryTimes = $times;

        return $this;
    }

    /**
     * @brief    get client type  
     *
     * @return   string
     */
    public function getClientType()
    {
        return $this->_clientType;
    }

    /**
     * @brief    get random token
     *
     * @return   string
     */
    private function _getToken()
    {
        return $this->_token;
    }

    /**
     * @brief    get expiry time  
     *
     * @return   int
     */
    public function getExpiryTime()
    {
        return $this->expiryTime;
    }

    /**
     * @brief    get sleep time  
     *
     * @return   int
     */
    public function getSleepTime()
    {
        return $this->sleepTime;
    }

    /**
     * @brief    get retry times  
     *
     * @return   int
     */
    public function getRetryTimes()
    {
        return $this->retryTimes;
    }

    /**
     * @brief    get clock offset     
     *
     * @return   float
     */
    public function getClockOffset()
    {
        return $this->_clockOffset;
    }

    /**
     * @brief    lock  key 
     *
     * @param    string  $key
     *
     * @return   boolean|array
     */
    public function lock($key)
    {
        $random_token = $this->_setToken()->_getToken();
        $retry_times = $this->getRetryTimes();

        while($retry_times > 0)
        {
            $counter = 0;
            $start_time = microtime(true) * 1000;
            $candidate_number = floor(min(count($this->instances), (count($this->instances) / 2 + 1)));

            foreach($this->instances as $k => $instance) 
            {
                try{
                    $this->_lockInstance($instance, $key) && $counter++;
                }catch(\Exception $e){
                    unset($this->instances[$k]);
                }catch(\Throwable $e){
                    unset($this->instances[$k]);
                }
            }

            $clock_offset = $this->getExpiryTime() * $this->getClockOffset() + 2;
            $valid_time   = $this->getExpiryTime() - (microtime(true) * 1000 - $start_time) - $clock_offset;
            $instances_number = count($this->instances);

            if(!empty($this->instances) && $counter >= $candidate_number && $valid_time > 0) 
            {
                return [
                    'lock_key'          => self::$_prefix . ':' . $key,
                    'random_token'      => $random_token,
                    'instances_number'  => $instances_number,
                    'candidate_number'  => $candidate_number,
                    'clock_offset'      => $clock_offset,
                    'valid_time'        => $valid_time,
                ];
            } 

            $this->unlock($key);
            $sleep_time = mt_rand(floor($this->getSleepTime() / 2), $this->getSleepTime()) * 1000;
            usleep($sleep_time);
            $retry_times--;
        }

        return false;
    }

    /**
     * @brief    unlock     
     *
     * @param    string  $key
     *
     * @return   void 
     */
    public function unlock($key)
    {
        foreach($this->instances as $instance) 
        {
            $this->_unlockInstance($instance, $key, $this->_getToken());
        }
    }

    /**
     * @brief    init all redis instances  
     *
     * @return   object
     */
    public function init()
    {
        if(!empty($this->instances)) return $this;

        if('predis' == $this->getClientType()){
            $this->_initAllPredisInstances();
        }elseif('redis' == $this->getClientType()){
            $this->_initAllRedisInstances();
        }else{
            throw new \Exception('invalid redis client type');
        }

        return $this;
    }

    /**
     * @brief    init all predis instances   
     *
     * @return   void
     */
    private function _initAllPredisInstances()
    {
        $all_prefixs = [];
        $tmp_instance = '';

        //注意：$this->config要求是二维数组
        foreach($this->config as $config) 
        {
            $params = $options = [];
            $params['scheme'] = $config['scheme'] ?? 'tcp';
            $params['host']   = $config['host'] ?? '127.0.0.1';
            $params['port']   = $config['port'] ?? 6379;
            $params['database']  = $config['database'] ?? 0;
            $params['prefix'] = $config['prefix'] ?? self::$_defaultPrefix;
            $params['persistent'] = !empty($config['persisted']) ? true : null;
            $params['timeout'] = 5;

            //跳过相同的实例
            $current_instance = $params['host'] . '|' . $params['port'] . '|' . $params['database'];
            if($current_instance == $tmp_instance) continue;
            $tmp_instance = $current_instance;

            //临时收集所有的前缀
            $all_prefixs[] = $params['prefix'];

            if(isset($config['connection_timeout']) && is_int($config['connection_timeout']) && $config['connection_timeout'] > 0)
            {
                $params['timeout'] = $config['connection_timeout'];
            }

            $params['read_write_timeout'] = 0;
            if(isset($config['read_write_timeout']) && is_int($config['read_write_timeout']))
            {
                $params['read_write_timeout'] = $config['read_write_timeout'];
            }

            $params['ssl'] = [];
            if(isset($config['ssl']) && is_array($config['ssl']))
            {
                $params['ssl'] = $config['ssl'];
            }

            if(!empty($config['auth']) && true === $config['auth'])
            {
                $params['password'] = $config['pass'] ?? '';
            }
            if(isset($options['prefix'])) unset($options['prefix']);

            $client = new \Predis\Client($params, $options); 

            try{
                $client->connect();
            }catch(\Exception $e){
                Logger::error(Tool::replacePlaceHolder(PHPCreeper::$langConfigBackup['redis_server_error'], [
                    'error_msg'    => $e->getMessage(),
                    'sleep_time'   => PROCESS_SLEEP_TIME,
                ]));sleep(PROCESS_SLEEP_TIME);exit;
            }catch(\Throwable $e){
                Logger::error(Tool::replacePlaceHolder(PHPCreeper::$langConfigBackup['redis_server_error'], [
                    'error_msg'    => $e->getMessage(),
                    'sleep_time'   => PROCESS_SLEEP_TIME,
                ]));sleep(PROCESS_SLEEP_TIME);exit;
            }

            $this->instances[] = $client;
        }

        //所有实例的前缀必须一致，否则强制采用默认的前缀
        if(empty($all_prefixs) || 1 <> count(array_unique($all_prefixs))){
            self::$_prefix = self::$_defaultPrefix;
        }else{
            self::$_prefix = array_shift($all_prefixs);
        }
    }

    /**
     * @brief    init all redis instances   
     *
     * @return   void
     */
    private function _initAllRedisInstances()
    {
        $all_prefixs = [];

        //注意：$this->config要求是二维数组
        foreach($this->config as $config) 
        {
            $host = $config['host'] ?? '127.0.0.1';
            $port = $config['port'] ?? 6379;
            $prefix = $config['prefix'] ?? self::$_defaultPrefix;
            $persistent = !empty($config['persisted']) ? true : null;
            $timeout = 5;
            $read_write_timeout = 0;

            //临时收集所有的前缀
            $all_prefixs[] = $prefix;

            if(isset($config['connection_timeout']) && is_int($config['connection_timeout']) && $config['connection_timeout'] > 0)
            {
                $timeout = $config['connection_timeout'];
            }

            if(isset($config['read_write_timeout']) && is_int($config['read_write_timeout']) && $config['read_write_timeout'] > 0)
            {
                $read_write_timeout = $config['read_write_timeout'];
            }


            //it's sad to only support context in higer version, so leave it alone.
            $context = [];
            if(isset($config['ssl']) && is_array($config['ssl']))
            {
                $context['ssl'] = $config['ssl'];
            }
            //it's sad to only support context in higer version, so leave it alone.


            $client = new \Redis(); 

            try{
                $client->connect($host, $port, $timeout, '', 0, $read_write_timeout);
            }catch(\Exception $e){
                Logger::error(Tool::replacePlaceHolder(PHPCreeper::$langConfigBackup['redis_server_error'], [
                    'error_msg'    => $e->getMessage() . " > tcp://{$host}:{$port}",
                    'sleep_time'   => PROCESS_SLEEP_TIME,
                ]));sleep(PROCESS_SLEEP_TIME);exit;
            }catch(\Throwable $e){
                Logger::error(Tool::replacePlaceHolder(PHPCreeper::$langConfigBackup['redis_server_error'], [
                    'error_msg'    => $e->getMessage() . " > tcp://{$host}:{$port}",
                    'sleep_time'   => PROCESS_SLEEP_TIME,
                ]));sleep(PROCESS_SLEEP_TIME);exit;
            }

            if(!empty($config['auth']) && true === $config['auth'])
            {
                $client->auth($config['pass'] ?? '');
            }

            $this->instances[] = $client;
        }

        //所有实例的前缀必须一致，否则强制采用默认的前缀
        if(empty($all_prefixs) || 1 <> count(array_unique($all_prefixs))){
            self::$_prefix = self::$_defaultPrefix;
        }else{
            self::$_prefix = array_shift($all_prefixs);
        }
    }

    /**
     * @brief    lock one instance
     *
     * @param    object  $instance
     * @param    string  $key
     *
     * @return   boolean
     */
    private function _lockInstance($instance, $key)
    {
        $token = $this->_getToken();
        $ttl = $this->getExpiryTime();
        $key = self::$_prefix . ':' . $key;

        if('predis' == $this->getClientType()){
            return $instance->set($key, $token, 'PX', $ttl, 'NX');
        }elseif('redis' == $this->getClientType()){
            return $instance->set($key, $token, ['NX', 'PX' => $ttl]);
        }else{
            throw new \Exception('invalid redis client type');
        }
    }

    /**
     * @brief    unlock one instance
     *
     * @param    object  $instance
     * @param    string  $key
     *
     * @return   boolean
     */
    private function _unlockInstance($instance, $key)
    {
        $script = '
            if redis.call("GET", KEYS[1]) == ARGV[1] then
                return redis.call("DEL", KEYS[1])
            else
                return 0
            end
        ';

        $token = $this->_getToken();
        $key = self::$_prefix . ':' . $key;

        if('predis' == $this->getClientType()){
            return $instance->eval($script, 1, $key, $token);
        }elseif('redis' == $this->getClientType()){
            return $instance->eval($script, [$key, $token], 1);
        }else{
            throw new \Exception('invalid redis client type');
        }
    }
}
