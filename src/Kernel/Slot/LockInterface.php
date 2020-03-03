<?php
/**
 * @script   LockInterface.php
 * @brief    This file is part of PHPCreeper
 * @author   blogdaren<blogdaren@163.com>
 * @version  1.0.0
 * @modify   2019-11-06
 */

namespace PHPCreeper\Kernel\Slot;

interface LockInterface 
{
    /**
     * @brief    lock   
     *
     * @param    string  $key
     *
     * @return   int
     */
    public function lock($key);

    /**
     * @brief    unlock     
     *
     * @param    string  $key
     * @param    int     $timeout
     *
     * @return   boolean
     */
    public function unlock($key, $timeout = 0);
}



