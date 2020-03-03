<?php
/**
 * @script   PluginService.php
 * @brief    This file is part of PHPCreeper
 * @author   blogdaren<blogdaren@163.com>
 * @version  1.0.0
 * @modify   2019-11-06
 */

namespace PHPCreeper\Kernel\Service\Wrapper;

use PHPCreeper\Kernel\PHPCreeper;

class PluginService
{
    /**
     * @brief    install plugin  
     *
     * @param    object          $phpcreeper
     * @param    string | array  $plugins
     * @param    mixed           $args
     *
     * @return   object
     */
    static public function install(PHPCreeper $phpcreeper, $plugins, ...$args)
    {
        if(is_array($plugins))
        {
            foreach($plugins as $plugin) 
            {
                $plugin::install($phpcreeper);
            }   
        }
        else
        {
            $plugins::install($phpcreeper, ...$args);
        }   

        return $phpcreeper;
    }
}


