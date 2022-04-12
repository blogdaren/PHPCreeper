<?php
/**
 * @script   HttpFactoryService.php
 * @brief    This file is part of PHPCreeper
 * @author   blogdaren<blogdaren@163.com>
 * @version  1.0.0
 * @modify   2019-11-06
 */

namespace PHPCreeper\Kernel\Service\Wrapper;

use PHPCreeper\Kernel\Middleware\MessageQueue\BrokeInterface;
use PHPCreeper\Kernel\Middleware\MessageQueue\RedisExtension;
use PHPCreeper\Kernel\Middleware\MessageQueue\AmqpExtension;
use PHPCreeper\Kernel\Middleware\MessageQueue\PhpQueue;

class QueueFactoryService
{
    /**
     * queue client instance
     *
     * @var array
     */
    static private $_client = null;

    /**
     * @brief    create queue client   
     *
     * @param    string|callback    $type
     * @param    mixed              $args
     *
     * @return   object
     */
    static public function createQueueClient($type = 'redis', ...$args)
    {
        $hashKey = md5(json_encode($type));

        if(empty(self::$_client[$hashKey])) 
        {
            if(empty($type) || $type == 'redis'){
                $client = new RedisExtension(...$args);
            }elseif($type == 'amqp'){
                $client = new AmqpExtension(...$args);
            }elseif($type == 'php'){
                $client = new PhpQueue(...$args);
            }elseif(is_callable($type)){
                $client = call_user_func($type, ...$args);
            }else{
                $client = new RedisExtension(...$args);
            }

            self::$_client[$hashKey] = $client;
        }

        return self::$_client[$hashKey];
    }
}


