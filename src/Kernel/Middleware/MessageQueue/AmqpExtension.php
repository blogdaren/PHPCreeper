<?php
/**
 * @script   AmqpExtension.php
 * @brief    This file is part of PHPCreeper
 * @author   blogdaren<blogdaren@163.com>
 * @version  1.0.0
 * @modify   2019-06-17
 */

namespace PHPCreeper\Kernel\Middleware\MessageQueue;

use PHPCreeper\Kernel\Slot\BrokerInterface;
use PHPCreeper\Kernel\Middleware\MessageQueue\Base\Context;
use PHPCreeper\Kernel\Middleware\MessageQueue\Base\Exchange;
use PHPCreeper\Kernel\Middleware\MessageQueue\Base\Queue;
use PHPCreeper\Kernel\Middleware\MessageQueue\Base\Message;

#[\AllowDynamicProperties]
class AmqpExtension implements BrokerInterface
{
    /**
     * \AMQPConnection
     *
     * @var object
     */
    protected $_connection = null;

    /**
     * \AMQPChannel
     *
     * @var object
     */
    protected $_channel = null;

    /**
     * \AMQPExchange
     *
     * @var object
     */
    protected $_exchange = null;

    /**
     * \AMQPQueue
     *
     * @var object
     */
    protected $_queue = null;

    /**
     * The name of Exchange
     *
     * @var string
     */
    public $exchangeName = 'exchange:default';

    /**
     * The name of Queue
     *
     * @var string
     */
    public $queueName = 'queue:default';

    /**
     * The routing key 
     *
     * @var string
     */
    protected $routingKey = 'routingkey:default';

    /**
     * The context
     *
     * @var string
     */
    protected $_context = null;

    /**
     * @brief    __construct    
     *
     * @param    array  $connection_config
     *
     * @return   null
     */
    public function __construct($connection_config = array())
    {
        $this->connectionConfig = array_merge($this->getDefaultConfig(), $connection_config);
        $this->getConnection();
        $this->_context = new Context();
    }

    /**
     * @brief    __destruct     
     *
     * @return   null
     */
    public function __destruct()
    {
        $this->close();
    }

    /**
     * @brief    get default configuration
     *
     * @return   array
     */
    public function getDefaultConfig()
    {
        return array(
            'host' => 'localhost',
            'port' => 5672,
            'user' => 'guest',
            'pass' => 'guest',
            'vhost' => '/',
            'read_timeout' => 3.,
            'write_timeout' => 3.,
            'connection_timeout' => 3.,
            'heartbeat' => 0,
            'persisted' => false,
            'lazy' => true,
            'qos_global' => false,
            'qos_prefetch_size' => 0,
            'qos_prefetch_count' => 1,
            'ssl_on' => false,
            'ssl_verify' => true,
            'ssl_cacert' => '',
            'ssl_cert' => '',
            'ssl_key' => '',
            'ssl_passphrase' => '',
        );
    }

    /**
     * @brief    get connection object
     *
     * @return   object
     */
    public function getConnection()
    {
        if(empty($this->_connection) || !$this->_connection->isConnected()) 
        {
            if(true !== $this->connectionConfig['ssl_on']) 
            {
                foreach(array('ssl_verify', 'ssl_cacert', 'ssl_cert', 'ssl_key', 'ssl_passphrase') as $k => $v)
                {
                    unset($this->connectionConfig[$k]);
                }
            }

            $this->_connection = new \AMQPConnection($this->connectionConfig);

            true === $this->connectionConfig['persisted'] ? $this->_connection->pconnect() 
                                                          : $this->_connection->connect();
        }

        return $this->_connection;
    }

    /**
     * @brief    get connection channel
     *
     * @return   object
     */
    public function getChannel()
    {
        if(empty($this->_channel) || !$this->_channel->isConnected()) 
        {
            $this->_channel = new \AMQPChannel($this->getConnection());
        }

        return $this->_channel;
    }

    /**
     * @brief    setQos     
     *
     * @param    int  $prefetch_size
     * @param    int  $prefetch_count
     *
     * @return   object 
     */
    public function setQos($prefetch_size, $prefetch_count)
    {   
        $this->getChannel()->qos($prefetch_size, $prefetch_count);

        return $this;
    }   

    /**
     * @brief    setExchangeName    
     *
     * @param    string  $name
     *
     * @return   object
     */
    public function setExchangeName($name = '')
    {
        $this->exchangeName = $name;

        return $this;
    }

    /**
     * @brief    setQueueName   
     *
     * @param    string  $name
     *
     * @return   object
     */
    public function setQueueName($name = '')
    {
        $this->queueName = $name;

        return $this;
    }

    /**
     * @brief    setRoutingKey  
     *
     * @param    string  $routing_key
     *
     * @return   object
     */
    public function setRoutingKey($routing_key = '')
    {
        $this->routingKey = $routing_key;

        return $this;
    }

    /**
     * @brief    _declareExchange   
     *
     * @param    object  $ex
     *
     * @return   object
     */
    private function _declareExchange(Object $ex)
    {
        $innerExchange = new \AMQPExchange($this->getChannel());
        $innerExchange->setType($ex->getType());
        $innerExchange->setName($ex->getName());
        $innerExchange->setFlags($ex->getFlags());
        $innerExchange->setArguments($ex->getArguments());
        $innerExchange->declareExchange();

        return $innerExchange;
    }

    /**
     * @brief    _declareQueue  
     *
     * @param    object  $qe
     *
     * @return   object
     */
    private function _declareQueue(Object $qe)
    {
        $innerQueue = new \AMQPQueue($this->getChannel());
        $innerQueue->setName($qe->getName());
        $innerQueue->setFlags($qe->getFlags());
        $innerQueue->setArguments($qe->getArguments());
        $innerQueue->bind($this->exchangeName, $this->routingKey);
        $innerQueue->declareQueue();

        return $innerQueue;
    }

    /**
     * close the connection and channel
     *
     * @return void
     */
    public function close()
    {
        if(!$this->_connection->disconnect()) 
        {
            throw new Exception('Could not disconnect!');
        }

        $this->_connection = $this->_channel = null;
    }

    /**
     * @brief   push data into queue
     *
     * @param   string  $queue_name
     * @param   string  $text
     *
     * @return  int
     */
    public function push($queue_name = '', $text = '')
    {
        //install exchange + queue
        $this->installExchangeAndQueue($queue_name);

        //get message entity
        $message = $this->_context->getMessageEntity($text);
        $attributes = $headers = array(
            'delivery_mode' => Message::DELIVERY_MODE_PERSISTENT,
            'message_id'    => uniqid('', true),
            'timestamp'     => time(),
        );
        $message->setHeaders($headers);
        $message->setRoutingKey($this->routingKey);
        //$message->getProperties() && $attributes['headers'] = $message->getProperties();

        //publish message to exchange
        $result = $this->_exchange->publish(
            $message->getBody(),
            $message->getRoutingKey(),
            $message->getFlags(),
            $attributes
        );

        if(!$result) 
        {
            throw new Exception("Error: Message '" . $text. "' was not sent." . PHP_EOL);
        }

        return $attributes['message_id'];
    }

    /**
     * @brief    installExchangeAndQueue
     *
     * @return   object
     */
    public function installExchangeAndQueue($queue_name = '')
    {
        !empty($queue_name) && $this->setQueueName($queue_name);

        $this->installExchange();
        $this->installQueue();

        return $this;
    }

    /**
     * @brief    installExchange    
     *
     * @return   object
     */
    public function installExchange()
    {
        if(empty($this->_exchange)) 
        {
            $ex = $this->_context->getExchangeEntity($this->exchangeName);
            $ex->setType(Exchange::TYPE_DIRECT);
            $ex->addFlag(Exchange::FLAG_DURABLE);
            $this->_exchange = $this->_declareExchange($ex);
        }

        return $this->_exchange;
    }

    /**
     * @brief    installQueue   
     *
     * @param    string  $queue_name
     *
     * @return   object
     */
    public function installQueue($queue_name = '')
    {
        if(empty($this->_queue)) 
        {
            !empty($queue_name) && $this->setQueueName($queue_name);
            $qe = $this->_context->getQueueEntity($this->queueName);
            $qe->addFlag(Queue::FLAG_DURABLE);
            /*
             *$qe->setArguments(array(
             *    'x-max-priority' => $this->maxPriority,
             *));
             */
            $this->_queue = $this->_declareQueue($qe);
        }

        return  $this->_queue;
    }

    /**
     * @brief    pop data from queue
     *
     * @param    string     $queue_name
     * @param    boolean    $wait
     *
     * @return   boolean|string
     */
    public function pop($queue_name = '', $wait = false)
    {
        $this->installExchange();

        $message = $this->installQueue($queue_name)->get();

        if($message) 
        {
            $this->acknowledge($queue_name, $message->getDeliveryTag());
            return $message->getBody();
        }

        return false;
    }

    /**
     * @brief    message acknowledge    
     *
     * @param    string  $queue_name
     * @param    int     $delivery_tag
     *
     * @return   boolean
     */
    public function acknowledge($queue_name = '', $delivery_tag = 0)
    {
        $this->installExchange();

        return $this->installQueue($queue_name)->ack($delivery_tag);
    }

    /**
     * @brief    purge the queue
     *
     * @param    string  $queue_name
     *
     * @return   boolean
     */
    public function purge($queue_name = '')
    {
        $this->installExchange();

        return $this->installQueue($queueName)->purge();
    }
} 
