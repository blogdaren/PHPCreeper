<?php
/**
 * @script   RedisLock.php
 * @brief    This file is part of PHPCreeper
 * @author   blogdaren<blogdaren@163.com>
 * @version  1.0.0
 * @modify   2019-11-06
 */

namespace PHPCreeper\Kernel\Middleware\LockManager;

use PHPCreeper\Kernel\Slot\LockInterface;
use \Redis;

class RedisLock implements LockInterface
{
    /**
     * redis object
     *
     * @var object
     */
    private $_redis = null;

    /**
     * expiry time
     *
     * @var int
     */
    public $expiryTime = 0;

    /**
     * sleep time
     *
     * @var int
     */
    protected $sleepTime = 10;

    /**
     * lock prefix
     *
     * @var string
     */
    public $lockPrefix = '';


    /**
     * @brief    __construct    
     *
     * @param    object $entity
     *
     * @return   void
     */
    public function __construct($entity)
    {
        if($entity instanceof \Redis) {
            $this->_redis = $entity;
        }elseif($entity->queueClient){
            $this->_redis = $entity->queueClient;
        }else{
            throw new \Exception('invalid redis instance provided');
        }

        if(method_exists($entity, 'getConfig'))
        {
            $entity_config = $entity->getConfig();
            !empty($entity_config['redis']['prefix']) && $this->lockPrefix = $entity_config['redis']['prefix'] . ':';
        }

        $this->setExpiryTime()->setSleepTime();
    }

    /**
     * @brief    set expiry time  
     *
     * @param    int  $time
     *
     * @return   object
     */
    public function setExpiryTime($time = 1)
    {
        $this->expiryTime = $time;

        return $this;
    }

    /**
     * @brief    set sleep time   
     *
     * @param    int  $time
     *
     * @return   object
     */
    public function setSleepTime($time = 10)
    {
        $this->sleepTime = $time;

        return $this;
    }

    /**
     * @brief    lock   
     *
     * @param    string  $key
     *
     * @return   int
     */
    public function lock($key)
    {
        $lock_key = $this->getLockKey($key);
        $timeout = time() + $this->expiryTime;
        $result = (bool)$this->_redis->setnx($lock_key, $timeout);
        if($result) return $timeout;

        while(true) 
        {
            usleep($this->sleepTime);
            $now_time = time();
            $old_timeout = $this->_redis->get($lock_key);
            if($now_time <= $old_timeout) continue;

            $new_timeout = $now_time + $this->expiryTime;
            $old_timeout2 = $this->_redis->getset($lock_key, $new_timeout);
            if($old_timeout <> $old_timeout2) continue;

            break;
        }

        return $new_timeout;
    }

    /**
     * @brief    unlock     
     *
     * @param    string  $key
     * @param    int     $timeout
     *
     * @return   boolean
     */
    public function unlock($key, $timeout = 0)
    {
        $lock_key = $this->getLockKey($key);

        if($timeout >= time()) 
        {
            return $this->_redis->del($lock_key);
        }

        return true;
    }

    /**
     * @brief    get lock key     
     *
     * @param    string  $key
     *
     * @return   string
     */
    public function getLockKey($key)
    {
        return $this->lockPrefix . "lock_{$key}";
    }
}






