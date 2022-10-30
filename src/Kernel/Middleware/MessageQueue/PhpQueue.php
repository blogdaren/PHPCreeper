<?php
/**
 * @script   PhpQueue.php
 * @brief    This file is part of PHPCreeper
 * @author   blogdaren<blogdaren@163.com>
 * @link     http://www.phpcreeper.com
 * @create   2022-10-30
 */

namespace PHPCreeper\Kernel\Middleware\MessageQueue;

use PHPCreeper\Kernel\Slot\BrokerInterface;

class PhpQueue implements BrokerInterface
{
    /**
     * php queue object
     *
     * @var object
     */
    protected $_phpQueueObject = NULL;

    /**
     * @brief    __construct    
     *
     * @param    array  $config
     *
     * @return   void
     */
    public function __construct($config = []) 
    {
        if(empty($this->_phpQueueObject))
        {
            $this->_phpQueueObject = new \SplQueue();
        }

        $this->_phpQueueObject->setIteratorMode(\SplQueue::IT_MODE_DELETE);
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

        return $this->_phpQueueObject->enqueue($text);
    }

    /**
     * @brief    pop data from queue   
     *
     * @param    string     $queue_name
     * @param    boolean    $wait
     *
     * @return   string
     */
    public function pop($queue_name = '', $wait = false)
    {
        $message = '';

        if(!$this->_phpQueueObject->isEmpty())
        {
            $message = $this->_phpQueueObject->dequeue();
            $message = json_decode($message, true);
        }

        return $message;
    }

    /**
     * @brief    the queue length
     *
     * @param    string  $queue_name
     *
     * @return   int
     */
    public function llen($queue_name = '')
    {
        return $this->_phpQueueObject->count();
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


