<?php
/**
 * @script   RedisLock.php
 * @brief    This file is part of PHPCreeper
 * @author   blogdaren<blogdaren@163.com>
 * @link     http://www.phpcreeper.com
 * @create   2022-12-10
 */

namespace PHPCreeper\Kernel\Middleware\LockManager;

use PHPCreeper\Kernel\Slot\LockInterface;
use PHPCreeper\Kernel\Middleware\MessageQueue\RedisExtension;
use PHPCreeper\Kernel\Library\Helper\Tool;

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
     * whether to use red lock or not
     *
     * @var boolean
     */
    private $_useRedLock = false;

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
        }elseif($entity instanceof RedisExtension) {
            $this->_redis = $entity;
        }elseif(is_array($entity)){
            //check whether to use red lock or not
            $this->_useRedLock = self::whetherToUseRedLock($entity);
            $this->_redis = $this->_useRedLock ? new DistributedRedLock($entity, 'redis') : new RedisExtension($entity);
        }else{
            throw new \Exception("invalid redis instance provided with \$entity = " . var_export($entity, true));
        }

        //upgrade to use red lock and keep compatible
        if($this->_useRedLock) return;
        //upgrade to use red lock and keep compatible


        //force to route to 0 partion
        $this->_redis->setPartionId(0);
        $this->setExpiryTime(1)->setSleepTime(1);
    }

    /**
     * @brief    whether to use red lock 
     *
     * @param    array  $config
     *
     * @return   boolean
     */
    static public function whetherToUseRedLock(&$config)
    {
        $orig_config = $config;
        empty($config) && $config = []; 
        !is_array($config) && $config = [$config];

        //如果是一维数组，那么强制转换为二维数组
        if(empty($config) || Tool::getArrayDepth($config) == 1)
        {   
            $config = [$config];
        }   

        //如果是多维数组，筛选出只支持使用red lock的redis实例
        foreach($config as $k => $v)
        {
            if(isset($v['use_red_lock']) && $v['use_red_lock'] == false)
            {
                unset($config[$k]);
            }
        }

        //如果没有筛选到有效redis实例，则回滚为旧有的锁机制
        if(empty($config)) {
            $config = Tool::getArrayDepth($orig_config) === 1 ? [$orig_config] : $orig_config;
            $use = false;
        }else{
            $use = true;
        }

        return $use;
    }

    /**
     * @brief    set expiry time  
     *
     * @param    int  $time (单位：秒)
     *
     * @return   object
     */
    public function setExpiryTime($time = 1)
    {
        //upgrade to use red lock and keep compatible
        if($this->_useRedLock) return $this->_redis->setExpiryTime($time);
        //upgrade to use red lock and keep compatible


        $this->expiryTime = $time;

        return $this;
    }

    /**
     * @brief    set sleep time   
     *
     * @param    int  $time (单位：毫秒)
     *
     * @return   object
     */
    public function setSleepTime($time = 10)
    {
        //upgrade to use red lock and keep compatible
        if($this->_useRedLock) return $this->_redis->setSleepTime($time);
        //upgrade to use red lock and keep compatible


        $this->sleepTime = $time;

        return $this;
    }

    /**
     * @brief    set retry times
     *
     * @param    int    $times
     *
     * @return   object
     */
    public function setRetryTimes($times)
    {
        //upgrade to use red lock and keep compatible
        if($this->_useRedLock) return $this->_redis->setRetryTimes($time);
        //upgrade to use red lock and keep compatible
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
        //upgrade to use red lock and keep compatible
        if($this->_useRedLock) return $this->_redis->lock($this->getLockKey($key));
        //upgrade to use red lock and keep compatible


        $lock_key = $this->getLockKey($key);
        $timeout = time() + $this->expiryTime;
        $result = (bool)$this->_redis->setnx($lock_key, $timeout);
        if($result) return $timeout;

        while(true) 
        {
            usleep($this->sleepTime * 1000);
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
        //upgrade to use red lock and keep compatible
        if($this->_useRedLock) return $this->_redis->unlock($this->getLockKey($key));
        //upgrade to use red lock and keep compatible


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
        return $key;
    }
}


