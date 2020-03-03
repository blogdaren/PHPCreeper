<?php
/**
 * @script   RedisExtension.php
 * @brief    This file is part of PHPCreeper
 * @author   blogdaren<blogdaren@163.com>
 * @version  1.0.0
 * @modify   2019-09-07
 */

namespace PHPCreeper\Kernel\Middleware\MessageQueue;

use PHPCreeper\Kernel\Slot\BrokerInterface;

class RedisExtension implements BrokerInterface
{
    /**
     * \RedisConnection
     *
     * @var object
     */
    protected $_connection = null;

    /**
     * @brief    __construct    
     *
     * @param    array  $connection_config
     *
     * @return   null
     */
    public function __construct($connection_config = array())
    {
        empty($connection_config) && $connection_config = [];
        $this->connectionConfig = array_merge($this->getDefaultConfig(), $connection_config);
        $this->getConnection();
        //$this->_context = new Context();
    }

    /**
     * @brief    get default cofiguration
     *
     * @return   array
     */
    public function getDefaultConfig()
    {
        return array(
            'host'      =>  '127.0.0.1',
            'port'      =>  6379,
            'auth'      =>  false,
            'pass'      =>  'guest',
            'prefix'    =>  '',
            'database'  =>  '0',
            'connection_timeout' => 3.,
            'read_write_timeout' => 3.,
            'heartbeat' =>  0,
            'persisted' =>  false,
            'lazy'      =>  true,
            'ssl_on'    =>  false,
            'ssl_verify'=>  true,
            'ssl_cacert'=>  '',
            'ssl_cert'  =>  '',
            'ssl_key'   =>  '',
            'ssl_passphrase' => '',
        );
    }

    /**
     * @brief    get connection object
     *
     * @return   object
     */
    public function getConnection()
    {
        if(empty($this->_connection) || !$this->_connection->isConnected()) 
        {
            $this->_connection = new \Redis();

            $method = true === $this->connectionConfig['persisted'] ? 'pconnect' : 'connect';

            $rs = call_user_func(
                array($this->_connection, $method),
                $this->connectionConfig['host'],
                $this->connectionConfig['port'],
                $this->connectionConfig['connection_timeout'],
                $this->connectionConfig['persist_id'] ?? null,
                $this->connectionConfig['retry_interval'] ?? 0,
                $this->connectionConfig['read_write_timeout'] ?? 0
            );

            if(empty($rs)) throw new \RedisException('redis connect failed...');
        }

        true === $this->connectionConfig['auth'] && $this->_connection->auth($this->connectionConfig['pass']);
        !empty($this->connectionConfig['database']) && $this->_connection->select($this->connectionConfig['database']);

        return $this->_connection;
    }

    /**
     * @brief    get queue key
     *
     * @param    string  $queue_name
     *
     * @return   string
     */
    public function getQueueKey($queue_name)
    {
        $prefix = $this->connectionConfig['prefix'] ? $this->connectionConfig['prefix'] : 'default';

        return "{$prefix}:queue_{$queue_name}";
    }

    /**
     * @brief   push data into queue
     *
     * @param   string  $queue_name
     * @param   string  $text
     *
     * @return  int
     */
    public function push($queue_name = '', $text = '')
    {
        $text = json_encode($text);
        $rs = $this->_connection->lpush($this->getQueueKey($queue_name), $text);

        return $rs;
    }

    /**
     * @brief   pop data from queue
     *
     * @param   string  $queue_name
     * @param   bool    $wait 
     *
     * @return  array
     */
    public function pop($queue_name, $wait = false)
    {
        $message = $this->_connection->rpop($this->getQueueKey($queue_name));

        if(!$message) return false;

        $task = json_decode($message, true);

        return $task;
    }

    /**
     * @brief    get the queue length
     *
     * @param    string  $queue_name
     *
     * @return   int
     */
    public function llen($queue_name)
    {
        return $this->_connection->llen($this->getQueueKey($queue_name));
    }

    /**
     * @brief    message acknowledge    
     *
     * @param    string  $queue_name
     * @param    string  $delivery_tag
     *
     * @return   boolean
     */
    public function acknowledge($queue_name, $delivery_tag)
    {
    }

    /**
     * @brief    purge the queue
     *
     * @param    string  $queue_name
     *
     * @return   boolean
     */
    public function purge($queue_name)
    {
        $this->_connection->del($this->getQueueKey($queue_name));

        return true;
    }

    /**
     * close the connection and channel
     *
     * @return void
     */
    public function close()
    {
        if(!empty($this->_connection) || $this->_connection->isConnected()) 
        {
            $this->_connection->close();
            $this->_connection = null;
        }
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
        return $this->getConnection()->{$function_name}(...$args);
    }

}
