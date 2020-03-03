<?php
/**
 * @script   SplQueue.php
 * @brief    This file is part of PHPCreeper
 * @author   blogdaren<blogdaren@163.com>
 * @version  1.0.0
 * @modify   2019-11-14
 */

namespace PHPCreeper\Kernel\Middleware\MessageQueue;

use PHPCreeper\Kernel\Slot\BrokerInterface;

class PhpQueue extends \SplQueue implements BrokerInterface
{
    /**
     * @brief    __construct    
     *
     * @param    array  $config
     *
     * @return   void
     */
    public function __construct($config = []) 
    {
        parent::setIteratorMode(\SplQueue::IT_MODE_DELETE);
    }

    /**
     * @brief    push data into queue
     *
     * @param    string  $queue_name
     * @param    string  $text
     *
     * @return   boolean
     */
    public function push($queue_name = '', $text = '')
    {
        $text = json_encode($text);

        return parent::enqueue($text);
    }

    /**
     * @brief    pop data from queue   
     *
     * @param    string  $queue_name
     * @param    string  $wait
     *
     * @return   string
     */
    public function pop($queue_name = '', $wait = false)
    {
        $message = '';

        if(!parent::isEmpty())
        {
            $message = parent::dequeue();
            $message = json_decode($message, true);
        }

        return $message;
    }

    /**
     * @brief    the queue length
     *
     * @param    string  $queue_name
     *
     * @return   init
     */
    public function llen($queue_name = '')
    {
        return parent::count();
    }

    /**
     * @brief    close  
     *
     * @return   void
     */
    public function close()
    {
    }

    /**
     * @brief    acknowledge    
     *
     * @param    string  $queue_name
     * @param    string  $delivery_tag
     *
     * @return   void
     */
    public function acknowledge($queue_name, $delivery_tag)
    {
    }

    /**
     * @brief    purge  
     *
     * @param    string  $queue_name
     *
     * @return   void
     */
    public function purge($queue_name)
    {
    }
}


