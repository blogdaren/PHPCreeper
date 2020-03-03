<?php
/**
 * @script   BrokerInterface.php
 * @brief    This file is part of PHPCreeper
 * @author   blogdaren<blogdaren@163.com>
 * @version  1.0.0
 * @modify   2019-06-17
 */

namespace PHPCreeper\Kernel\Slot;

interface BrokerInterface
{
    /**
     * @brief    __construct    
     *
     * @param    array  $config
     *
     * @return   null
     */
    public function __construct($config = array());

    /**
     * @brief    close connection
     *
     * @return   boolean
     */
    public function close();

    /**
     * @brief    push data info queue
     *
     * @param    string  $text
     *
     * @return bool|string 
     */
    public function push($queue_name = '', $text = '');

    /**
     * @brief    pop data from queue
     *
     * @param    string  $queue_name
     * @param    string  $wait
     *
     * @return   boolean|string
     */
    public function pop($queue_name, $wait = false);

    /**
     * @brief    message acknowledge    
     *
     * @param    string  $queue_name
     * @param    int     $delivery_tag
     *
     * @return   boolean
     */
    public function acknowledge($queue_name, $delivery_tag);

    /**
     * @brief    purge queue
     *
     * @param    string  $queue_name
     *
     * @return   boolean
     */
    public function purge($queue_name);
} 
