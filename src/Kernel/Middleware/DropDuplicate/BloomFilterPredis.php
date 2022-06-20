<?php
/**
 * @script   BloomFilterPredis.php
 * @brief    This file is part of PHPCreeper
 * @author   blogdaren<blogdaren@163.com>
 * @link     http://www.phpcreeper.com
 * @create   2022-06-14
 */

namespace PHPCreeper\Kernel\Middleware\DropDuplicate;

use PHPCreeper\Kernel\PHPCreeper;
use PHPCreeper\Kernel\Slot\DropDuplicateInterface;
use PHPCreeper\Kernel\Middleware\MessageQueue\PredisClient;

class BloomFilterPredis implements DropDuplicateInterface
{
    /**
     * predis instance
     *
     * @var object
     */
    private $_predis = null;

    /**
     * bit array size 
     *
     * @var object
     */
    public $bitSize = 10000;

    /**
     * hash times
     *
     * @var int
     */
    public $hashTimes = 1;

    /**
     * bucket name
     *
     * @var string
     */
    public $bucket = 'default';

    /**
     * @brief    __construct    
     *
     * @param    object  $entity
     * @param    string  $bucket
     * @param    int     $bit_size
     * @param    int     $hash_times
     *
     * @return   void 
     */
    public function __construct($entity, $bucket = 'bucket', $bit_size = 10000, $hash_times = 3) 
    {
        if($entity instanceof \Predis) {
            $this->_predis = $entity;
        }elseif($entity instanceof PredisClient) {
            $this->_predis = $entity;
        }elseif(is_array($entity)){
            $this->_predis = new PredisClient($entity);
        }else{
            throw new \Exception("invalid predis client instance provided with \$entity = " . var_export($entity, true));
        }

        //force to route to 0 partion
        $this->_predis->setPartionId(0);

        $this->setBucket($bucket);
        $this->setBitSize($bit_size);
        $this->setHashTimes($hash_times);
    }

    /**
     * @brief    setBucket  
     *
     * @param    string  $bucket
     *
     * @return   object 
     */
    public function setBucket($bucket)
    {
        empty($bucket) && $bucket = 'bucket';
        $this->bucket = $bucket;

        return $this;
    }

    /**
     * @brief    setBitSize     
     *
     * @param    int  $size
     *
     * @return   object
     */
    public function setBitSize($size = 10000)
    {
        $this->bitSize = $size;

        return $this;
    }

    /**
     * @brief    setHashTimes   
     *
     * @param    int  $count
     *
     * @return   object
     */
    public function setHashTimes($times = 3)
    {
        $this->hashTimes = $times;

        return $this;
    }

    /**
     * @brief    getBucket  
     *
     * @return   string
     */
    public function getBucket()
    {
        return $this->bucket;
    }

    /**
     * @brief    getBitSize     
     *
     * @return   int
     */
    public function getBitSize()
    {
        return $this->bitSize;
    }

    /**
     * @brief    getHashCount   
     *
     * @return   int
     */
    public function getHashCount()
    {
        $this->hashTimes <= 0 && $this->hashTimes = 3;

        return $this->hashTimes;
    }

    /**
     * @brief    add element to bit array, called collections.
     *
     * @param    string  $element
     *
     * @return   void
     */
    public function add($element) 
    {
        $index = 0;
        $pipe = $this->_predis->pipeline();

        $skey = $this->_predis->getStandardKey($this->getBucket());
        while($index < $this->getHashCount()) 
        {
            $crc = $this->hash($element, $index);
            $pipe->setbit($skey, $crc, 1);
            $index++;
        }

        $pipe->execute();
    }

    /**
     * @brief    check whether element exists bit array, called collections.   
     *
     * @param    string  $element
     *
     * @return   boolean
     */
    public function has($element) 
    {
        $index = 0;
        $pipe = $this->_predis->pipeline();
        $skey = $this->_predis->getStandardKey($this->getBucket());

        while($index < $this->hashTimes) 
        {
            $crc = $this->hash($element, $index);
            $pipe->getbit($skey, $crc);
            $index++;
        }

        $result = $pipe->execute();

        return !in_array(0, $result);
    }

    /**
     * @brief    hash  algorithm 
     *
     * @param    string  $element
     * @param    int     $index
     *
     * @return   string
     */
    public function hash($element, $index) 
    {
        return abs(crc32(md5('m' . $index . $element))) % $this->getBitSize();
    }

    /**
     * @brief    removeBucket   
     *
     * @return   void
     */
    public function removeBucket()
    {
        $this->_predis->del($this->getBucket());
    }

}
