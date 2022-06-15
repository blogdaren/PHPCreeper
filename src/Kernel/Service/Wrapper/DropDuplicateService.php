<?php
/**
 * @script   DropDuplicateService.php
 * @brief    This file is part of PHPCreeper
 * @author   blogdaren<blogdaren@163.com>
 * @version  1.0.0
 * @modify   2019-11-01
 */

namespace PHPCreeper\Kernel\Service\Wrapper;

use PHPCreeper\Kernel\Slot\DropDuplicateInterface;
use PHPCreeper\Kernel\Middleware\DropDuplicate\BloomFilterLocal;
use PHPCreeper\Kernel\Middleware\DropDuplicate\BloomFilterRedis;
use PHPCreeper\Kernel\Middleware\DropDuplicate\BloomFilterPredis;

class DropDuplicateService
{
    /**
     * drop duplicate filter
     *
     * @var array
     */
    static private $_dropDuplicateFilter = null;

    /**
     * @brief    create one instance     
     *
     * @param    string|callback    $type
     * @param    mixed              $args
     *
     * @return   object
     */
    static public function create($type = 'redis', ...$args)
    {
        if(empty($type) || $type == 'predis'){
            $filter = new BloomFilterPredis(...$args);
        }elseif($type == 'redis'){
            $filter = new BloomFilterRedis(...$args);
        }elseif($type == 'local'){
            $filter = new BloomFilterLocal(...$args);
        }elseif(is_callable($type)){
            $filter = call_user_func($type, ...$args);
        }else{
            $filter = new BloomFilterLocal(...$args);
        }

        return $filter;
    }
}


