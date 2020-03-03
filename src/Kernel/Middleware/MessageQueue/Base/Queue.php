<?php
/**
 * @script   Queue.php
 * @brief    this file is part of PHPCreeper
 * @author   blogdaren<blogdaren@163.com>
 * @version  1.0.0
 * @modify   2019-06-17
 */

namespace PHPCreeper\Middleware\MessageQueue\Base;

class Queue extends Entity
{
    const FLAG_EXCLUSIVE = 2097152;
    const FLAG_IFEMPTY = 4194304;
    const FLAG_DURABLE = 2;

    /**
     * the consumerTag
     *
     * @var string
     */
    private $consumerTag;

    /**
     * constructor
     *
     * @return  null
     */
    public function __construct(string $name)
    {
        parent::__construct($name);
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
}
