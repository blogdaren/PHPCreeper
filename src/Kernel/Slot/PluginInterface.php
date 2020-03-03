<?php
/**
 * @script   PluginInterface.php
 * @brief    This file is part of PHPCreeper
 * @author   blogdaren<blogdaren@163.com>
 * @version  1.0.0
 * @modify   2019-09-04
 */

namespace PHPCreeper\Kernel\Slot;

use PHPCreeper\Kernel\PHPCreeper;

interface PluginInterface
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
    static public function install(PHPCreeper $phpcreeper, ...$args);
}
