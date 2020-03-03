<?php
/**
 * @script   Exchange.php
 * @brief    
 * @author   blogdaren<blogdaren@163.com>
 * @version  1.0.0
 * @modify   2019-06-18
 */

namespace PHPCreeper\Middleware\MessageQueue\Base;

class Exchange extends Entity
{
    const TYPE_DIRECT   = 'direct';
    const TYPE_FANOUT   = 'fanout';
    const TYPE_TOPIC    = 'topic';
    const TYPE_HEADERS  = 'headers';
    const FLAG_INTERNAL = 2048;
    const FLAG_NOPARAM  = 0;
    const FLAG_DURABLE  = 1;

    /**
     * constructor
     *
     * @param   string  $name
     * @return  null
     */
    public function __construct(string $name)
    {
        $this->type = self::TYPE_DIRECT;
        parent::__construct($name);
    }

    /**
     * remove flags
     *
     * @return  null
     */
    public function removeFlags()
    {
        $this->flags = self::FLAG_NOPARAM;
    }
}
