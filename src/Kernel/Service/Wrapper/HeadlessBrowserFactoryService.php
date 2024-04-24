<?php
/**
 * @script   HeadlessBrowserFactoryService.php
 * @brief    This file is part of PHPCreeper
 * @author   blogdaren<blogdaren@163.com>
 * @link     http://www.phpcreeper.com
 * @create   2024-04-21
 */

namespace PHPCreeper\Kernel\Service\Wrapper;

use PHPCreeper\Kernel\PHPCreeper;
use PHPCreeper\Kernel\Middleware\HeadlessBrowser\Chrome;

class HeadlessBrowserFactoryService
{
    /**
     * @brief    create headless browser instance   
     *
     * @param    string|callback    $type
     * @param    mixed              $args
     *
     * @return   object
     */
    static public function create($type = 'chrome', ...$args)
    {
        if(empty($type) || $type == 'chrome'){
            $browser = new Chrome(...$args);
        }elseif(is_callable($type)){
            $browser = call_user_func($type, ...$args);
        }else{
            $browser = new Chrome(...$args);
        }

        return $browser;
    }
}


