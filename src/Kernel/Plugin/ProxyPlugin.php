<?php
/**
 * @script   ProxyPlugin.php
 * @brief    This file is part of PHPCreeper
 * @author   blogdaren<blogdaren@163.com>
 * @version  1.0.0
 * @modify   2020-03-02
 */

namespace PHPCreeper\Kernel\Plugin;

use PHPCreeper\Kernel\PHPCreeper;

class ProxyPlugin
{
    /**
     * @brief    __construct    
     *
     * @param    object  $phpcreeper
     *
     * @return   void
     */
    public function __construct($phpcreeper)
    {
        $this->phpcreeper = $phpcreeper;
    }
        
    /**
     * @brief    install plugin   
     *
     * @param    object  $phpcreeper
     * @param    mixed   $args
     *
     * @return   void
     */
    static public function install(PHPCreeper $phpcreeper, ...$args)
    {
        $phpcreeper->getService()->inject('getSystemTime', function(){
            return (new ProxyPlugin($this))->getSystemTime();
        });
    }

    /**
     * @brief    get system time
     *
     * @return   int
     */
    public function getSystemTime()
    {
        return  time();
    }

}
