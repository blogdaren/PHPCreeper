<?php
/**
 * @script   FileLock.php
 * @brief    This file is part of PHPCreeper
 * @author   blogdaren<blogdaren@163.com>
 * @version  1.0.0
 * @modify   2019-11-06
 */

namespace PHPCreeper\Kernel\Middleware\LockManager;

use PHPCreeper\Kernel\Slot\LockInterface;

class FileLock implements LockInterface
{
    //file path where to store lock
    private $path = null;

    //file handler
    private $handler = null;

    //lock particle size: the smaller particle size the bigger hash number
    private $hashNumber = 100;

    /**
     * @brief    __construct    
     *
     * @return   void
     */
    public function __construct()
    {
    }

    /**
     * @brief    init the lock key and directory  
     *
     * @param    string  $key
     * @param    string  $directory
     *
     * @return   void
     */
    public function init($key, $directory = '/tmp')
    {
        if(empty($directory) || !is_writeable($directory)) 
        {
            $directory = sys_get_temp_dir();
        }

        $hash = $this->hash($key) % $this->hashNumber;
        $this->path = $directory . DIRECTORY_SEPARATOR . $hash . '.txt';
    }
     
    /**
     * hash string
     *
     * @param   string  $string
     *
     * @return  int
     */
    private function hash($string)
    {
        $crc = abs(crc32($string));

        if($crc & 0x80000000) 
        {
            $crc ^= 0xffffffff;
            $crc += 1;
        }

        return $crc;
    }

    /**
     * @brief    lock   
     *
     * @param    string  $key
     * @param    string  $directory
     *
     * @return   boolean
     */
    public function lock($key = '', $directory = '')
    {
        self::init($key, $directory);

        $this->handler = fopen($this->path, 'w+');

        if(false === $this->handler) return false;

        return flock($this->handler, LOCK_EX);
    }

    /**
     * @brief    unlock     
     *
     * @param    string  $key
     *
     * @return   boolean
     */
    public function unlock($key = '', $timeout = 0)
    {
        if(false !== $this->handler)
        {
            flock($this->handler, LOCK_UN);
            clearstatcache();
        }

        return fclose($this->handler);
    }
}



