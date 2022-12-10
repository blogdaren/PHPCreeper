<?php
/**
 * @script   PredisClient.php
 * @brief    This file is part of PHPCreeper
 * @author   blogdaren<blogdaren@163.com>
 * @link     http://www.phpcreeper.com
 * @create   2022-06-14
 */

namespace PHPCreeper\Kernel\Middleware\MessageQueue;

use PHPCreeper\Kernel\Slot\BrokerInterface;
use PHPCreeper\Kernel\Library\Helper\Tool;
use PHPCreeper\Timer;

class PredisClient implements BrokerInterface
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
     * behavior args
     *
     * @var array
     */
    const BEHAVIOR_ARGS = [
        'exceptions',
        'connections',
        'cluster',
        'replication',
        'aggregate',
        'parameters',
        'commands',
    ];

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
            'scheme'    =>  'tcp',
            'host'      =>  '127.0.0.1',
            'port'      =>  6379,
            'auth'      =>  false,
            'pass'      =>  'guest',
            'database'  =>  '0',
            'connection_timeout' => 3.,
            'read_write_timeout' => 3.,
            'persisted' =>  false,
            'ssl'       =>  [],
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
            $output = $this->rebuildConnectionParamsAndOptions($index);
            if(empty($output['params'])) throw new \Exception("predis connection params invalid");

            $this->_connection[$index] = new \Predis\Client($output['params'], $output['options']);

            $scheme = $output['params']['scheme'];
            $host = $output['params']['host'];
            $port = $output['params']['port'];
            $this->_connection[$index]->connect();

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

        return $this->_connection[$index];
    }

    /**
     * @brief    rebuild connection params and options
     *
     * @param    int  $index
     *
     * @return   array
     */
    public function rebuildConnectionParamsAndOptions($index = 0)
    {
        $params = $options = [];
        $output = [
            'params' => $params,
            'options' => $options,
        ];

        if(!is_int($index) || empty($this->connectionConfig[$index]) || !is_array($this->connectionConfig[$index])) 
        {
            return $output;
        }

        $config = $this->connectionConfig[$index];

        $params['scheme'] = $config['scheme'] ?? 'tcp';
        $params['host']   = $config['host'] ?? '127.0.0.1';
        $params['port']   = $config['port'] ?? 6379;
        $params['persistent'] = !empty($config['persisted']) ? true : null;

        $params['timeout'] = 5;
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

        $common_args = $this->getDefaultConfig()[0];
        foreach($config as $k => $v)
        {
            if(!array_key_exists($k, $common_args))
            {
                $options[$k] = $v;
            }
        }

        foreach(self::BEHAVIOR_ARGS as $k => $v)
        {
            if(isset($params[$v]))
            {
                $options[$v] = $params[$v];
                unset($params[$v]);
            }
        }

        //force to unset $options['prefix']
        if(isset($options['prefix'])) unset($options['prefix']);

        $output = [
            'params'  => $params,
            'options' => $options,
        ];

        return $output;
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
        $rs = $this->getConnection($skey)->lpush($skey, $text);

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
        $message = $this->getConnection($skey)->rpop($skey);

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
        return $this->getConnection($skey)->llen($skey);
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
        $this->getConnection($skey)->del($skey);

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

        if(!empty($this->getConnection($skey)) && $this->getConnection($skey)->isConnected()) 
        {
            $this->getConnection($skey)->close();
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
     * @param    string  $function_name
     * @param    mixed   $args
     *
     * @return   void
     */
    public function __call($function_name, $args)
    {
        $key = $args[0] ?? '';
        $skey = $this->getStandardKey($key);

        //important: rewrite $args[0];
        $skey && $args[0] = $skey;

        return $this->getConnection($skey)->{$function_name}(...$args);
    }

}
