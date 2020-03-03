<?php
/**
 * @script   DropDuplicateService.php
 * @brief    This file is part of PHPCreeper
 * @author   blogdaren<blogdaren@163.com>
 * @version  1.0.0
 * @modify   2019-11-01
 */

namespace PHPCreeper\Kernel\Service\Wrapper;

use PHPCreeper\Kernel\Slot\LockInterface;
use PHPCreeper\Kernel\Middleware\LockManager\RedisLock;
use PHPCreeper\Kernel\Middleware\LockManager\FileLock;

class LockFactoryService
{
    /**
     * lock helper instance
     *
     * @var object
     */
    static private $_helper = null;

    /**
     * @brief    create lock helper
     *
     * @param    string  $type
     * @param    mixed   $args
     *
     * @return   object
     */
    static public function createLockHelper($type = 'redis', ...$args)
    {
        $hashKey = md5(json_encode($type));

        if(empty(self::$_helper[$hashKey])) 
        {
            if(empty($type) || 'redis' == $type){
                $helper = new RedisLock(...$args);
            }elseif('file' == $type){
                $helper = new FileLock(...$args);
            }elseif(is_callable($type)){
                $helper = call_user_func($type, ...$args);
            }else{
                $helper = new FileLock(...$args);
            }

            self::$_helper[$hashKey] = $helper;
        }

        return self::$_helper[$hashKey];
    }
}


