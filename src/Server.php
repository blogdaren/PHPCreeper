<?php
/**
 * @script   Server.php
 * @brief    This file is part of PHPCreeper
 * @author   blogdaren<blogdaren@163.com>
 * @link     http://www.phpcreeper.com
 * @create   2021-09-20
 */

namespace PHPCreeper;

use PHPCreeper\Kernel\PHPCreeper;

class Server extends PHPCreeper
{
    /**
     * @brief   run worker instance
     *
     * @return  void
     */
    public function run()
    {
        $this->onWorkerStart  = array($this, 'onWorkerStart');
        $this->onWorkerStop   = array($this, 'onWorkerStop');
        $this->onWorkerReload = array($this, 'onWorkerReload');
        $this->onConnect      = array($this, 'onConnect');
        $this->onClose        = array($this, 'onClose');
        $this->onMessage      = array($this, 'onMessage');
        $this->onBufferFull   = array($this, 'onBufferFull');
        $this->onBufferClose  = array($this, 'onBufferClose');
        $this->onError        = array($this, 'onError');

        parent::run();
    }

    /**
     * @brief    onWorkerStart  
     *
     * @param    object  $worker
     *
     * @return   boolean|void
     */
    public function onWorkerStart($worker)
    {
        //global init 
        $this->initMiddleware()->initLogger();

        //trigger user callback
        $returning = $this->triggerUserCallback('onServerStart', $this);
        if(false === $returning) return false;
    }

    /**
     * @brief    onWorkerStop   
     *
     * @return   void
     */
    public function onWorkerStop($worker)
    {
        //trigger user callback
        $returning = $this->triggerUserCallback('onServerStop', $this);
        if(false === $returning) return false;
    }

    /**
     * @brief    onWorkerReload     
     *
     * @param    object $worker
     *
     * @return   boolean|void
     */
    public function onWorkerReload($worker)
    {
        //trigger user callback
        $returning = $this->triggerUserCallback('onServerReload', $this);
        if(false === $returning) return false;
    }

    /**
     * @brief    onConnect  
     *
     * @param    object $connection
     *
     * @return   boolean|void
     */
    public function onConnect($connection)
    {
        //trigger user callback
        $returning = $this->triggerUserCallback('onServerConnect', $connection);
        if(false === $returning) return false;
    }

    /**
     * @brief    onClose
     *
     * @param    object $connection
     *
     * @return   boolean|void
     */
    public function onClose($connection)
    {
        //trigger user callback
        $returning = $this->triggerUserCallback('onServerClose', $connection);
        if(false === $returning) return false;
    }

    /**
     * @brief    onMessage
     *
     * @param    object $connection
     * @param    string $data
     *
     * @return   boolean|void
     */
    public function onMessage($connection, $data)
    {
        //trigger user callback
        $returning = $this->triggerUserCallback('onServerMessage', $connection, $data);
        if(false === $returning) return false;
    }

    /**
     * @brief    onBufferFull
     *
     * @param    object $connection
     *
     * @return   boolean|void
     */
    public function onBufferFull($connection)
    {
        //trigger user callback
        $returning = $this->triggerUserCallback('onServerBufferFull', $connection);
        if(false === $returning) return false;
    }

    /**
     * @brief    onBufferDrain
     *
     * @param    object $connection
     *
     * @return   boolean|void
     */
    public function onBufferDrain($connection)
    {
        //trigger user callback
        $returning = $this->triggerUserCallback('onServerBufferDrain', $connection);
        if(false === $returning) return false;
    }

    /**
     * @brief    onError    
     *
     * @param    object  $connection
     * @param    string  $code
     * @param    string  $msg
     *
     * @return   boolean|void
     */
    public function onError($connection, $code, $msg)
    {
        //trigger user callback
        $returning = $this->triggerUserCallback('onServerError', $connection, $code, $msg);
        if(false === $returning) return false;
    }



}


