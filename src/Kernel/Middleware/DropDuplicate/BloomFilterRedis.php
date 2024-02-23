<?php
/**
 * @script   BloomFilterRedis.php
 * @brief    This file is part of PHPCreeper
 * @author   blogdaren<blogdaren@163.com>
 * @version  1.0.0
 * @modify   2019-11-01
 */

namespace PHPCreeper\Kernel\Middleware\DropDuplicate;

use PHPCreeper\Kernel\PHPCreeper;
use PHPCreeper\Kernel\Slot\DropDuplicateInterface;
use PHPCreeper\Kernel\Middleware\MessageQueue\RedisExtension;

class BloomFilterRedis implements DropDuplicateInterface
{
    /**
     * redis instance
     *
     * @var object
     */
    private $_redis = null;

    /**
     * bit array size (单位：bit)
     *
     * @var object
     */
    public $bitSize = 10000;

    /**
     * hash times
     *
     * @var int
     */
    public $hashTimes = 3;

    /**
     * bucket name
     *
     * @var string
     */
    public $bucket = 'bucket';

    /**
     * expected insertions 
     *
     * @var int
     */
    public $expected_insertions  = 10000;

    /**
     * expected falseratio
     *
     * @var float
     */
    public $expected_falseratio = 0.01;

    /**
     * @brief    __construct    
     *
     * @param    mixed  $entity
     * @param    array  $bloom_filter_config
     *
     * @return   void 
     */
    public function __construct($entity, $bloom_filter_config = array()) 
    {
        if($entity instanceof \Redis) {
            $this->_redis = $entity;
        }elseif($entity instanceof RedisExtension) {
            $this->_redis = $entity;
        }elseif(is_array($entity)){
            $this->_redis = new RedisExtension($entity);
        }else{
            throw new \Exception("invalid redis instance provided with \$entity = " . var_export($entity, true));
        }

        //force to route to 0 partion
        $this->_redis->setPartionId(0);

        //set expected insertions and probablity
        $this->initExpectedInsertionsAndProbablity($bloom_filter_config)->computeOptimalBitSizeAndHashTimes();

        //$this->setBucket($bucket);
        //$this->setBitSize($bit_size);
        //$this->setHashTimes($hash_times);
    }

    /**
     * @brief    init expected insertions and falseratio
     *
     * @param    array  $config
     *
     * @return   object
     */
    public function initExpectedInsertionsAndProbablity($config)
    {
        if(empty($config) 
            || !is_array($config) 
            || !isset($config['expected_insertions']) 
            || !isset($config['expected_falseratio'])) return $this;

        if(!is_int($config['expected_insertions']) || $config['expected_insertions'] < 0) return $this;
        if(!is_float($config['expected_falseratio'])) return;
        if($config['expected_falseratio'] <= 0.0 || $config['expected_falseratio'] >= 1.0)  return $this;

        $this->expected_insertions = $config['expected_insertions'];
        $this->expected_falseratio = $config['expected_falseratio'];

        return $this;
    }

    /**
     * @brief    compute the optimal bitmap size and hash times
     *
     * @return   object
     */
    public function computeOptimalBitSizeAndHashTimes()
    {
        $this->bitSize = -(($this->expected_insertions * log($this->expected_falseratio)) / (log(2) * log(2)));
        $this->bitSize = ceil($this->bitSize);

        $this->hashTimes = ($this->bitSize / $this->expected_insertions) * log(2);
        $this->hashTimes = ceil($this->hashTimes);

        return $this;
    }

    /**
     * @brief    compute expected false ratio  
     *
     * @return   float
     */
    public function computeExpectedFalseRatio() 
    {
        $exp = (-1 * $this->hashTimes * $this->expected_insertions) / $this->bitSize;
        $false_ratio = pow(1 - exp($exp), $this->hashTimes);

        return round($false_ratio, 3);
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
        if(!is_int($size) || $size <= 0) return $this;

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
        if(!is_int($times) || $times <= 0) return $this;

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
        $pipe = $this->_redis->pipeline();

        $skey = $this->_redis->getStandardKey($this->getBucket());
        while($index < $this->getHashCount()) 
        {
            $crc = $this->hash($element, $index);
            $pipe->setbit($skey, $crc, 1);
            $index++;
        }

        $pipe->exec();
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
        $pipe = $this->_redis->pipeline();
        $skey = $this->_redis->getStandardKey($this->getBucket());

        while($index < $this->hashTimes) 
        {
            $crc = $this->hash($element, $index);
            $pipe->getbit($skey, $crc);
            $index++;
        }

        $result = $pipe->exec();

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
        $this->_redis->del($this->getBucket());
    }

}

