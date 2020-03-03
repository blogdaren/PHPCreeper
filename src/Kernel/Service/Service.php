<?php
/**
 * @script   Service.php
 * @brief    This file is part of PHPCreeper
 * @author   blogdaren<blogdaren@163.com>
 * @version  1.0.0
 * @modify   2019-09-04
 */

namespace PHPCreeper\Kernel\Service;

use PHPCreeper\Kernel\PHPCreeper;
use \Closure;

class Service
{
    /**
     * record used to store service
     *
     * @var array
     */
    public $record = array();

    /**
     * @brief    __construct    
     *
     * @param    array   $providers
     * @param    object  $phpcreeper
     *
     * @return   void
     */
    public function __construct($providers, PHPCreeper $phpcreeper)
    {
        array_walk($providers, function($provider){
            (new $provider())->render($this);
        });
    }

    /**
     * @brief    get service by name
     *
     * @param    string  $name
     *
     * @return   closure
     */
    public function getName($name)
    {
        if(!isset($this->record[$name])) 
        {
            pprint("method [$name] not inject yet", array_keys($this->record));
            return;
        }

        return $this->record[$name];
    }

    /**
     * @brief    inject service    
     *
     * @param    string  $name
     * @param    closure $callback
     *
     * @return   void
     */
    public function inject($name, Closure $callback)
    {
        !array_key_exists($name, $this->record) && $this->record[$name] = $callback;
    }

    /**
     * @brief    get record  
     *
     * @return   array
     */
    public function getRecord()
    {
        return $this->record;
    }

}




