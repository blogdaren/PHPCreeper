<?php
/**
 * @script   Message.php
 * @brief    this file is part of PHPCreeper
 * @author   blogdaren<blogdaren@163.com>
 * @version  1.0.0
 * @modify   2019-06-17
 */

namespace PHPCreeper\Middleware\MessageQueue\Base;

class Message extends Entity
{
    const DELIVERY_MODE_NON_PERSISTENT = 1;
    const DELIVERY_MODE_PERSISTENT = 2;
    const FLAG_NOPARAM = 0;
    const FLAG_MANDATORY = 1;
    const FLAG_IMMEDIATE = 2;

    /**
     * the delivertag
     *
     * @var int|null
     */
    private $deliveryTag = '';

    /**
     * the consumerTag
     *
     * @var string|null
     */
    private $consumerTag = '';

    /**
     * redelivered or not
     * 
     * @var bool
     */
    private $redelivered = false;

    /**
     * the routing key
     *
     * @var string
     */
    private $routingKey = '';

    /**
     * construtor
     *
     * @return  null 
     */
    public function __construct($body = '', $properties = array(), $headers = array())
    {
        $this->redelivered = false;
        parent::__construct(uniqid('msg_'), $headers, $body, $properties);
    }

    /**
     * set redelivered status
     *
     * @return  null 
     */
    public function setRedelivered(bool $redelivered)
    {
        $this->redelivered = (bool)$redelivered;
    }

    /**
     * get redelivered status
     *
     * @return  boolean
     */
    public function getRedelivered()
    {
        return $this->redelivered;
    }

    /**
     * set the deliveryTag 
     *
     * @return  null
     */
    public function setDeliveryTag(int $deliveryTag = null)
    {
        $this->deliveryTag = $deliveryTag;
    }

    /**
     * get the deliveryTag 
     *
     * @return  string
     */
    public function getDeliveryTag()
    {
        return $this->deliveryTag;
    }

    /**
     * set the consumerTag
     *
     * @return  null
     */
    public function setConsumerTag(string $consumerTag = null)
    {
        $this->consumerTag = $consumerTag;
    }

    /**
     * get the consumerTag
     *
     * @return  string
     */
    public function getConsumerTag()
    {
        return $this->consumerTag;
    }

    /**
     * set the routing key
     *
     * @return  null
     */
    public function setRoutingKey(string $routingKey = null)
    {
        $this->routingKey = $routingKey;
    }

    /**
     * get the routing key
     *
     * @return  string
     */
    public function getRoutingKey()
    {
        return $this->routingKey;
    }
}
