<?php
/**
 * @script   RedisExtension.php
 * @brief    This file is part of PHPCreeper
 * @author   blogdaren<blogdaren@163.com>
 * @link     http://www.phpcreeper.com
 * @create   2019-09-07
 */

namespace PHPCreeper\Kernel\Middleware\MessageQueue;

use PHPCreeper\Kernel\Slot\BrokerInterface;
use PHPCreeper\Kernel\Library\Helper\Tool;
use PHPCreeper\Timer;

class RedisExtension implements BrokerInterface
{
    /**
     * \RedisConnection
     *
     * @var array
     */
    protected $_connection = [];

    /**
     * the number of redis-instance
     *
     * @var int
     */
    static public $serverCount = 0;

    /**
     * allowed policy
     *
     * @var string
     */
    static private $_allowedPolicy = ['hand', 'hash'];

    /**
     * route policy
     *
     * @var string
     */
    public $policy = "hash";

    /**
     * partion id
     *
     * @var int
     */
    public $partion_id = 0;

    /** 
     * hearbeat
     *
     * @var int
     */
    const PING_INTERVAL = 25;

    /**
     * @brief    __construct    
     *
     * @param    array  $connection_config
     *
     * @return   void
     */
    public function __construct($connection_config = array())
    {
        empty($connection_config) && $connection_config = []; 
        !is_array($connection_config) && $connection_config = [$connection_config];

        if(!empty($connection_config) && Tool::getArrayDepth($connection_config) <> 2)
        {   
            $connection_config = [$connection_config];
        }   

        $this->connectionConfig = empty($connection_config) ? $this->getDefaultConfig() : $connection_config;

        //remove the config items which have dirty config keys
        $this->connectionConfig = self::purifyConnectionConfig($this->connectionConfig);

        self::$serverCount = count($this->connectionConfig);
    }

    /**
     * @brief    purify connection config    
     *
     * @param    array  $config
     *
     * @return   array
     */
    static public function purifyConnectionConfig($config)
    {
        $max_key = 0;

        foreach($config as $k => $v)
        {
            if(!is_numeric($k)) 
            {
                unset($config[$k]);
            }
            else
            {
                $max_key = $k > $max_key ? $k : $max_key;
            }

        }

        if($max_key > (count($config) - 1))
        {
            return array_values($config);
        }

        return $config;
    }

    /**
     * @brief    get connection config    
     *
     * @return   array
     */
    public function getConnectionConfig()
    {
        return $this->connectionConfig;
    }

    /**
     * @brief    get default cofiguration
     *
     * @return   array
     */
    public function getDefaultConfig()
    {
        return [[
            'host'      =>  '127.0.0.1',
            'port'      =>  6379,
            'auth'      =>  false,
            'pass'      =>  'guest',
            'prefix'    =>  '',
            'database'  =>  '0',
            'connection_timeout' => 3,
            'read_write_timeout' => 3,
            'persisted' =>  false,
            'lazy'      =>  true,
            'ssl_on'    =>  false,
            'ssl_verify'=>  true,
            'ssl_cacert'=>  '',
            'ssl_cert'  =>  '',
            'ssl_key'   =>  '',
            'ssl_passphrase' => '',
        ]];
    }

    /**
     * @brief   get connection object
     *
     * @param   string  $k
     *
     * @return  object
     */
    public function getConnection($k = '')
    {
        $index = $this->getRoutePartion($k);

        if(empty($this->_connection[$index]) || !$this->_connection[$index]->isConnected()) 
        {
            $this->_connection[$index] = new \Redis();

            $method = 'connect';
            if(!empty($this->connectionConfig[$index]['persisted']) && true === $this->connectionConfig[$index]['persisted'])
            {
                $method = 'pconnect';
            }

            $rs = call_user_func(
                [$this->_connection[$index], $method],
                $this->connectionConfig[$index]['host'] ?? '',
                $this->connectionConfig[$index]['port'] ?? 0,
                $this->connectionConfig[$index]['connection_timeout'] ?? 0,
                $this->connectionConfig[$index]['persist_id'] ?? null,
                $this->connectionConfig[$index]['retry_interval'] ?? 0,
                $this->connectionConfig[$index]['read_write_timeout'] ?? 0
            );

            $host = $this->connectionConfig[$index]['host'];
            $port = $this->connectionConfig[$index]['port'];

            if(empty($rs)) throw new \RedisException("connect redis-server-{$index} failed($host:$port)");

            if(strpos($host, '127.0.0.1') !== 0 && $this->_connection[$index]->isConnected())
            {                                                                                   
                $this->_connection[$index]->ping_timer = Timer::add(self::PING_INTERVAL, function()use($index){
                    $this->_connection[$index]->ping();
                });
            }
        }

        if(!$this->_connection[$index]->isConnected())
        {
            if(!empty($this->_connection[$index]->ping_timer))
            {
                Timer::del($this->_connection[$index]->ping_timer);
            }
            unset($this->_connection[$index]);
        }

        if(!empty($this->connectionConfig[$index]['auth']) && true == $this->connectionConfig[$index]['auth'])
        {
            $this->_connection[$index]->auth($this->connectionConfig[$index]['pass'] ?? '');
        }

        !empty($this->connectionConfig[$index]['database']) && $this->_connection[$index]->select($this->connectionConfig[$index]['database']);

        return $this->_connection[$index];
    }

    /**
     * @brief    set route policy
     *
     * @param    string  $policy
     *
     * @return   object
     */
    public function setPolicy($policy)
    {
        if(!in_array($policy, self::$_allowedPolicy))
        {
            return $this;
        }

        $this->policy = $policy;

        return $this;
    }

    /**
     * @brief    get route policy  
     *
     * @return   string
     */
    public function getPolicy()
    {
        return $this->policy;
    }

    /**
     * @brief    set partion id 
     *
     * @param    int|NULL $index
     *
     * @return   object 
     */
    public function setPartionId($index = 0)
    {
        if(is_null($index))
        {
            $this->setPolicy('hash');
            return $this;
        }

        $this->setPolicy('hand');
        $valid_partion_ids = range(0, self::$serverCount - 1);
        !is_int($index) && $index = 0;
        $this->partion_id = $index;

        if(!in_array($index, $valid_partion_ids))
        {
            $this->partion_id = 0;
        }

        return $this;
    }

    /**
     * @brief    get partion id 
     *
     * @return   int
     */
    public function getPartionId()
    {
        return $this->partion_id;
    }

    /**
     * @brief    get route partion 
     *
     * @param    string  $k
     *
     * @return   int
     */
    public function getRoutePartion($k = '')
    {
        $index = 0;

        switch($this->getPolicy())
        {
            case 'hand':
                $index = self::getPartionId();
                break;
            case 'hash':
                $index = self::getHashIndex($k);
                break;
            default:
                break;
        }

        return $index;
    }

    /**
     * @brief    get queue key
     *
     * @param    string  $key
     *
     * @return   string
     */
    public function getStandardKey($key)
    {
        if(empty($key)) return '';

        $index = self::getHashIndex($key);
        $prefix = !empty($this->connectionConfig[$index]['prefix']) ? $this->connectionConfig[$index]['prefix'] : 'PHPCreeper';

        return "{$prefix}:{$key}";
    }

    /**
     * @brief   push data into queue
     *
     * @param   string  $key
     * @param   string  $text
     *
     * @return  int
     */
    public function push($key = '', $text = '')
    {
        $text = json_encode($text);
        $skey = $this->getStandardKey($key);
        $rs = $this->getConnection($key)->lpush($skey, $text);

        return $rs;
    }

    /**
     * @brief   pop data from queue
     *
     * @param   string  $key
     * @param   bool    $wait 
     *
     * @return  array
     */
    public function pop($key, $wait = false)
    {
        $skey = $this->getStandardKey($key);
        $message = $this->getConnection($key)->rpop($skey);

        if(!$message) return false;

        $msg = json_decode($message, true);

        return $msg;
    }

    /**
     * @brief    get the queue length
     *
     * @param    string  $key
     *
     * @return   int
     */
    public function llen($key)
    {
        $skey = $this->getStandardKey($key);
        return $this->getConnection($key)->llen($skey);
    }

    /**
     * @brief    message acknowledge    
     *
     * @param    string  $key
     * @param    string  $delivery_tag
     *
     * @return   boolean
     */
    public function acknowledge($key, $delivery_tag)
    {
    }

    /**
     * @brief    purge the queue
     *
     * @param    string  $key
     *
     * @return   boolean
     */
    public function purge($key)
    {
        $skey = $this->getStandardKey($key);
        $this->getConnection($key)->del($skey);

        return true;
    }

    /**
     * with client to close the connection and channel
     *
     * @return void
     */
    public function close($key = '')
    {
        if(!is_string($key)) return;

        $skey = $this->getStandardKey($key);

        if(!empty($this->getConnection($key)) && $this->getConnection($key)->isConnected()) 
        {
            $this->getConnection($key)->close();
        }
    }

    /**
     * @brief    get hash value
     *
     * @param    string  $key
     *
     * @return   int
     */
    static public function getHash($key)
    {
        return abs(crc32($key));
    }

    /**
     * @brief    get hash index
     *
     * @param    string  $key
     *
     * @return   int
     */
    static public function getHashIndex($key)
    {
        return self::getHash($key) % self::$serverCount;
    }

    /**
     * @brief    get config
     *
     * @param    string  $key
     *
     * @return   array
     */
    public function getConfig($key = '')
    {
        $index = self::getHashIndex($key);

        return $this->connectionConfig[$index] ?? [];
    }

    /**
     * @brief    __call     
     *
     * @param    string  $func
     * @param    mixed   $args
     *
     * @return   void
     */
    public function __call($func, $args)
    {
        $key = $args[0] ?? '';
        $skey = $this->getStandardKey($key);

        //important: rewrite $args[0];
        $skey && $args[0] = $skey;

        return $this->getConnection($key)->{$func}(...$args);
    }

}
