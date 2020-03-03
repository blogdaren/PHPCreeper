<?php
/**
 * @script   Context.php
 * @brief    
 * @author   blogdaren<blogdaren@163.com>
 * @version  1.0.0
 * @modify   2019-06-18
 */

namespace PHPCreeper\Kernel\Middleware\MessageQueue\Base;

class Context 
{
    /**
     * @brief    __construct    
     *
     * @return   null
     */
    public function __construct()
    {
    }

    /**
     * @brief    getMessageEntity   
     *
     * @param    string  $body
     * @param    string  $headers
     * @param    string  $properties
     *
     * @return   object
     */
    public function getMessageEntity($body = '', $headers = array(), $properties = [])
    {
        return new Message($body, $headers, $properties);
    }

    /**
     * @brief    getExchangeEntity  
     *
     * @param    string  $name
     *
     * @return   object
     */
    public function getExchangeEntity($name = '')
    {
        return new Exchange($name);
    }

    /**
     * @brief    getQueueEntity     
     *
     * @param    string  $name
     *
     * @return   object
     */
    public function getQueueEntity($name = '')
    {
        return new Queue($name);
    }

    /**
     * @brief    convertMessage     
     *
     * @param    string  $extEnvelope
     *
     * @return   object
     */
    public function convertMessage(\AMQPEnvelope $extEnvelope)
    {
        $message = new Message(
            $extEnvelope->getBody(),
            $extEnvelope->getHeaders(),
            [
                'message_id' => $extEnvelope->getMessageId(),
                'correlation_id' => $extEnvelope->getCorrelationId(),
                'app_id' => $extEnvelope->getAppId(),
                'type' => $extEnvelope->getType(),
                'content_encoding' => $extEnvelope->getContentEncoding(),
                'content_type' => $extEnvelope->getContentType(),
                'expiration' => $extEnvelope->getExpiration(),
                'priority' => $extEnvelope->getPriority(),
                'reply_to' => $extEnvelope->getReplyTo(),
                'timestamp' => $extEnvelope->getTimeStamp(),
                'user_id' => $extEnvelope->getUserId(),
            ]
        );
        $message->setRedelivered($extEnvelope->isRedelivery());
        $message->setDeliveryTag($extEnvelope->getDeliveryTag());
        $message->setRoutingKey($extEnvelope->getRoutingKey());

        return $message;
    }
}
