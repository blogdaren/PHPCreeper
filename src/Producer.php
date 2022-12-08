<?php
/**
 * @script   Producer.php
 * @brief    This file is part of PHPCreeper
 * @author   blogdaren<blogdaren@163.com>
 * @link     http://www.phpcreeper.com
 * @create   2019-09-04
 */

namespace PHPCreeper;

use PHPCreeper\Kernel\PHPCreeper;
use PHPCreeper\Kernel\Library\Helper\Benchmark;
use PHPCreeper\Kernel\Library\Helper\Tool;
use PHPCreeper\Timer;
use Configurator\Configurator;
use Logger\Logger;
use Workerman\Worker;

class Producer extends PHPCreeper
{
    /**
     * producer timer id
     *
     * @var int
     */
    public $producerTimerId = 0;

    /**
     * timer interval
     *
     * @var float
     */
    public $interval = 0;

    /**
     * @brief    construct    
     *
     * @return   void
     */
    public function __construct($config = [])
    {
        parent::__construct($config);
    }

    /**
     * @brief   run worker instance
     *
     * @return  void
     */
    public function run()
    {
        $this->onWorkerStart  = array($this, 'onWorkerStart');
        $this->onWorkerStop   = array($this, 'onWorkerStop');
        $this->onWorkerReload = array($this, 'onWorkerReload');
        parent::run();
    }

    /**
     * @brief    onWorkerStart  
     *
     * @param    object  $worker
     *
     * @return   boolean | void
     */
    public function onWorkerStart($worker)
    {
        //global init 
        $this->initMiddleware()->initLogger();

        //trigger user callback
        $returning = $this->triggerUserCallback('onProducerStart', $this);
        if(false === $returning) return false;

        //trigger timer
        $this->installTimer();
    }

    /**
     * @brief    onWorkerStop   
     *
     * @return   void
     */
    public function onWorkerStop()
    {
        //trigger user callback
        $returning = $this->triggerUserCallback('onProducerStop', $this);
        if(false === $returning) return false;

        $this->removeTimer()->removeBucket();
    }

    /**
     * @brief    onWorkerReload     
     *
     * @param    object $worker
     *
     * @return   boolean | void
     */
    public function onWorkerReload($worker)
    {
        //trigger user callback
        $returning = $this->triggerUserCallback('onProducerReload', $this);
        if(false === $returning) return false;
    }

    /**
     * @brief   push initial task into queue
     *
     * @return  boolean
     */
    public function initTask()
    {
        $init_task = Configurator::get('globalConfig/main/task_init') ?? [];

        return $this->createMultiTask($init_task);
    }

    /**
     * @brief    get task rule 
     *
     * !!! deprecated already !!!
     *
     * @param    string  $rule_name
     *
     * @return   array
     */
    static public function getInitTaskRule($rule_name)
    {
        if(!is_string($rule_name) && !is_numeric($rule_name)) return [];

        $rules = Configurator::get("globalConfig/main/task/rule");
        if(empty($rules)) return [];

        $rule = [];
        if(array_key_exists($rule_name, $rules) && is_array($rules[$rule_name]))
        {
            $rule = $rules[$rule_name];
            $rule = is_array($rule) ? $rule : [];
        }

        return $rule;
    }

    /**
     * @brief    set time interval   
     *
     * @param    float  $interval
     *
     * @return   object
     */
    public function setInterval($interval = 1)
    {
        if(!$interval || Tool::bcCompareNumber($interval, '0.001', 3) < 0) 
        {
            $interval = 1;
        }

        $this->interval = $interval;

        return $this;
    }

    /**
     * @brief    get time interval   
     *
     * @return   float
     */
    public function getInterval()
    {
        $interval = $this->interval;

        if(Tool::bcCompareNumber($interval, '0.001', 3) < 0) 
        {
            $interval = $this->getAppWorkerConfig()['interval'] ?? 1;
        }

        if(!$interval || Tool::bcCompareNumber($interval, '0.001', 3) < 0) 
        {
            $interval = 1;
        }

        return $this->interval = $interval;
    }

    /**
     * @brief    install timer
     *
     * @return   object
     */
    public function installTimer()
    {
        $this->producerTimerId = Timer::add($this->getInterval(), [$this, 'initTask'], [], false);

        return $this;
    }

    /**
     * @brief    get timer id     
     *
     * @return   int
     */
    public function getTimerId()
    {
        return $this->producerTimerId;
    }

    /**
     * @brief    remove timer
     *
     * @param    int  $timer_id
     *
     * @return   void
     */
    public function removeTimer()
    {
        $this->getTimerId() > 0 && Timer::del($this->getTimerId());

        return $this;
    }

    /**
     * @brief    remove bucket   
     *
     * @return   object
     */
    public function removeBucket()
    {
        if(empty($this->dropDuplicateFilter) || !is_object($this->dropDuplicateFilter)) 
        {
            return $this;
        }

        //important: with sleep to avoid creating url repeated unexpectdly
        sleep(1);

        $this->dropDuplicateFilter->removeBucket();

        return $this;
    }

}


