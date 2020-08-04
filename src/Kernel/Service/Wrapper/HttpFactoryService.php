<?php
/**
 * @script   HttpFactoryService.php
 * @brief    This file is part of PHPCreeper
 * @author   blogdaren<blogdaren@163.com>
 * @version  1.0.0
 * @modify   2019-11-06
 */

namespace PHPCreeper\Kernel\Service\Wrapper;

use PHPCreeper\Kernel\PHPCreeper;
use PHPCreeper\Kernel\Middleware\HttpClient\Guzzle;
use PHPCreeper\Kernel\Middleware\HttpClient\Curl;

class HttpFactoryService
{
    /**
     * http client instance
     *
     * @var object
     */
    static private $_client = null;

    /**
     * @brief    create http client   
     *
     * @param    string|callback    $type
     * @param    mixed              $args
     *
     * @return   object
     */
    static public function createHttpClient($type = 'guzzle', ...$args)
    {
        $hashKey = md5(json_encode($type));

        if(empty(self::$_client[$hashKey])) 
        {
            if(empty($type) || $type == 'guzzle'){
                $client = new Guzzle(...$args);
            }elseif($type == 'curl'){
                $client = new Guzzle(...$args);
            }elseif(is_callable($type)){
                $client = call_user_func($type, ...$args);
            }else{
                $client = new Guzzle(...$args);
            }

            self::$_client[$hashKey] = $client;
        }

        return self::$_client[$hashKey];
    }
}


