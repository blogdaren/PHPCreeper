<?php
/**
 * @script   Task.php
 * @brief    This file is part of PHPCreeper
 * @author   blogdaren<blogdaren@163.com>
 * @version  1.0.0
 * @modify   2019-09-04
 */

namespace PHPCreeper\Kernel;

use PHPCreeper\Kernel\PHPCreeper;
use PHPCreeper\Kernel\Library\Helper\Tool;
use Ramsey\Uuid\Uuid;
use Configurator\Configurator;
use Logger\Logger;

class Task 
{
    /**
     * task id
     *
     * @var string
     */
    public $id = 0;

    /**
     * task type 
     *
     * maybe: text|image|audio|video|...
     *
     * @var string
     */
    public $type = 'text';

    /**
     * task url 
     *
     * @var string
     */
    public $url = '';

    /**
     * request method 
     *
     * @var string
     */
    public $method = 'get';

    /**
     * request referer
     *
     * @var string
     */
    public $referer = '';

    /**
     * task rule name
     *
     * @var string
     */
    public $ruleName = '';

    /**
     * task rule
     *
     * @var array
     */
    public $rule = [];

    /**
     * task context
     *
     * @var array
     */
    public $context = [];

    /**
     * PHPCreeper instance
     *
     * @var object
     */
    public $phpcreeper = null;

    /**
     * task single instance
     *
     * @var string
     */
    static private $_instance = null;

    /**
     * @brief    __construct    
     *
     * @param    object  $phpcreeper
     * @param    array   $options
     *
     * @return   void
     */
    public function __construct($phpcreeper, $options = []) 
    {
        $this->phpcreeper = $phpcreeper;
        !is_array($options) && $options = array($options);
        array_walk($options, function($v, $k){
            $new_method = "set" . ucfirst(strtolower($k));
            if(method_exists(__CLASS__, $new_method))
            {
                $this->{$new_method}($v);
            }
        });
    }

    /**
     * @brief    get single instance
     *
     * @param    object  $phpcreeper
     * @param    array   $options
     *
     * @return   object
     */
    static public function getInstance($phpcreeper, $options = [])
    {
        if(empty(self::$_instance))
        {
            self::$_instance = self::newInstance($phpcreeper, $options);
        }

        return self::$_instance;
    }

    /**
     * @brief    get new instance
     *
     * @param    object  $phpcreeper
     * @param    array   $options
     *
     * @return   object
     */
    static public function newInstance($phpcreeper, $options = [])
    {
        return new self($phpcreeper, $options);
    }

    /**
     * @brief    create one task
     *
     * @param    array  $input
     *
     * @return   string | int
     */
    public function createTask($input = [])
    {
        //check task params
        $check_result = self::checkTaskParams($input);
        if(true !== $check_result) return $check_result;

        //check task number
        if(true !== $check_result = $this->checkTaskNumber())
        {
            Logger::warn(Tool::replacePlaceHolder($this->phpcreeper->langConfig['queue_full'], [
                'task_number' => $check_result['task_number'],
                'max_number'  => $check_result['max_number'],
            ]));
            usleep(500000);
            return false;
        };

        if($this->phpcreeper->count > 1)
        {
            $gold_key = $this->phpcreeper->lockHelper->lock('pushtask');
            if(!$gold_key) return false;
        }

        //check task number once again
        if(true !== $check_result = $this->checkTaskNumber())
        {
            Logger::warn(Tool::replacePlaceHolder($this->phpcreeper->langConfig['queue_full'], [
                'task_number' => $check_result['task_number'],
                'max_number'  => $check_result['max_number'],
            ]));

            //unlock
            $this->phpcreeper->count > 1 && $this->phpcreeper->lockHelper->unlock('pushtask', $gold_key);

            sleep(1);
            return false;
        };

        $allow_url_repeat = false;
        if(!empty($input['context']['allow_url_repeat']) && true === $input['context']['allow_url_repeat'])
        {
            $allow_url_repeat = true;
        }

        if(PHPCreeper::$isRunAsMultiWorker && !$allow_url_repeat)
        {
            if(true === $this->hasUrl($input['url'])) 
            {
                Logger::warn(Tool::replacePlaceHolder($this->phpcreeper->langConfig['queue_duplicate_task'], [
                    'task_url'  => $input['url'],
                ]));

                //unlock
                $this->phpcreeper->count > 1 && $this->phpcreeper->lockHelper->unlock('pushtask', $gold_key);

                return false;
            }

            $this->phpcreeper->dropDuplicateFilter->add($input['url']);
        }

        if(isset($input['rule_name'])) $this->setRuleName($input['rule_name']);
        $rule_name = $this->getRuleName();
        if(empty($rule_name) || !is_string($rule_name)) $rule_name = md5($input['url']);

        $type       = $input['type']      ?? $this->getType();
        $url        = $input['url'];
        $method     = $input['method']    ?? $this->getMethod();
        $referer    = $input['referer']   ?? $this->getReferer();
        $rule_name  = $rule_name;
        $rule       = $input['rule']      ?? $this->getRule();
        $rule_depth = Tool::getArrayDepth($rule);
        2 <> $rule_depth && $rule = [];
        $depth      = $input['depth'];
        $context    = $input['context']   ?? $this->getContext();
        $task_id    = $this->createTaskId();

        $task_data = [
            'id'          => $task_id,
            'type'        => $type,
            'url'         => $url,
            'method'      => $method,
            'referer'     => $referer,
            'rule_name'   => $rule_name,
            'rule'        => $rule,
            'depth'       => $depth,
            'context'     => $context,
            'create_time' => Tool::getNowTime(true),
        ];

        $rs = $this->phpcreeper->queueClient->push('task', $task_data);

        //unlock
        $this->phpcreeper->count > 1 && $this->phpcreeper->lockHelper->unlock('pushtask', $gold_key);

        return !empty($rs) ? $task_id : 0;
    }

    /**
     * @brief   create multi task 
     *
     * @return  boolean
     */
    public function createMultiTask($task = [])
    {
        //important!!!
        if(is_string($task))
        {
            $tmp_url = $task;
            $task = [];
            $task['url'] = $tmp_url;
        }

        empty($task['url']) && $task['url'] = $this->getUrl();
        $urls = !is_array($task['url']) ? array($task['url']) : $task['url'];
        $type = (empty($task['type']) || !is_string($task['type'])) ? $this->getType() : $task['type'];
        $method  = $task['method']  ?? $this->getMethod();
        $referer = $task['referer'] ?? $this->getReferer();
        $context = $task['context'] ?? $this->getContext();
        $rules   = $task['rule']    ?? $this->getRule();
        $rule_depth = Tool::getArrayDepth($rules);
        3 <> $rule_depth && $rules = [];

        foreach($urls as $rule_name => $url) 
        {
            if(empty(Tool::checkUrl($url))) 
            {
                unset($urls[$rule_name]);
                continue;
            }

            $_rule_name = !is_string($rule_name) ? md5($url) : $rule_name;
            $rule = $rules[$rule_name] ?? [];
            $taskObject = self::newInstance($this->phpcreeper);
            $task_id = $taskObject->setUrl($url)
                            ->setType($type)
                            ->setMethod($method)
                            ->setReferer($referer)
                            ->setRuleName($_rule_name)
                            ->setRule($rule)
                            ->setContext($context)
                            ->createTask();

            if(!empty($task_id))
            {
                Logger::info(Tool::replacePlaceHolder($this->phpcreeper->langConfig['queue_push_task'], [
                    'task_id'   => $task_id,
                    'task_url'  => $url,
                ]));
            }
        }

        if(empty($urls)) 
        {
            Logger::error(Tool::replacePlaceHolder($this->phpcreeper->langConfig['queue_url_invalid']));
            return false;
        }

        return true;
    }

    /**
     * @brief    check task params
     *
     * @param    array  $args
     *
     * @return   boolean
     */
    public function checkTaskParams(&$args)
    {
        if(empty($this->phpcreeper) || !is_object($this->phpcreeper)) 
        {
            return false;
        }

        $args['url'] = $args['url'] ?? $this->getUrl();

        if(true !== Tool::checkUrl($args['url'])) 
        {
            return false;
        }

        empty($args['depth']) && $args['depth'] = 0;

        return true;
    }

    /**
     * @brief    get one task    
     *
     * @return   string | array
     */
    public function getTask()
    {
        $task = $this->phpcreeper->queueClient->pop('task');

        return  !empty($task) ? $task : [];
    }

    /**
     * @brief    create unique task id 
     *
     * @return   string
     */
    public function createTaskId()
    {
        $this->id = Uuid::uuid4()->toString();

        return $this->id;
    }

    /**
     * @brief    get task length
     *
     * @return   int
     */
    public function getTaskNumber()
    {
        $task_number = $this->phpcreeper->queueClient->llen('task');

        return empty($task_number) ? 0 : $task_number;
    }

    /**
     * @brief    check whether task number exceed max number or not 
     *
     * @return   boolean | array
     */
    public function checkTaskNumber()
    {
        $max_number = Configurator::get('globalConfig/main/task/max_number');
        !Tool::checkIsInt($max_number) && $max_number = 0;
        $task_number = $this->getTaskNumber();

        if($max_number > 0 && $task_number >= $max_number)
        {
            return [
                'task_number' => $task_number,
                'max_number'  => $max_number,
            ];
        }

        return true;
    }

    /**
     * @brief    check whether url exists in collections or not 
     *
     * @param    string  $url
     *
     * @return   boolean
     */
    public function hasUrl($url)
    {
        if(true !== Tool::checkUrl($url)) return false;

        $rs = $this->phpcreeper->dropDuplicateFilter->has($url);

        return !empty($rs) ? true : false;
    }

    /**
     * @brief    set task url
     *
     * @param    string  $url
     *
     * @return   object
     */
    public function setUrl($url = '')
    {
        $this->url = $url;

        return $this;
    }

    /**
     * @brief    set task type
     *
     * @param    string  $type
     *
     * @return   object
     */
    public function setType($type = '')
    {
        $this->type = $type;

        return $this;
    }


    /**
     * @brief    set task rule name
     *
     * @param    string  $name
     *
     * @return   object
     */
    public function setRuleName($name = '')
    {
        $this->ruleName = (!empty($name) && is_string($name)) ? $name : '';

        return $this;
    }

    /**
     * @brief    set task rule    
     *
     * @param    array  $rule
     *
     * @return   object
     */
    public function setRule($rule = [])
    {
        $this->rule = $rule;

        return $this;
    }

    /**
     * @brief    set request method for task
     *
     * @param    string  $method
     *
     * @return   object
     */
    public function setMethod($method = 'get')
    {
        $this->method = $method;

        return $this;
    }

    /**
     * @brief    set request referer for task 
     *
     * @param    string  $referer
     *
     * @return   object
     */
    public function setReferer($referer = '')
    {
        empty($referer) && $this->referer = $this->getUrl();
        $this->referer = $referer;

        return $this;
    }

    /**
     * @brief    set task context 
     *
     * @param    array  $context
     *
     * @return   object
     */
    public function setContext($context = [])
    {
        $this->context = $context;

        return $this;
    }

    /**
     * @brief    get task type
     *
     * @return   string
     */
    public function getType()
    {
        return !empty($this->type) ? $this->type : 'text';
    }

    /**
     * @brief    get task url
     *
     * @return   string
     */
    public function getUrl()
    {
        return $this->url;
    }

    /**
     * @brief    get task rule name
     *
     * @return   string
     */
    public function getRuleName()
    {
        return $this->ruleName;
    }

    /**
     * @brief    get task rule
     *
     * @return   array
     */
    public function getRule()
    {
        return $this->rule;
    }

    /**
     * @brief    get request method for task
     *
     * @return   string
     */
    public function getMethod()
    {
        return !empty($this->method) ? $this->method : 'get';
    }

    /**
     * @brief    get request referer for task
     *
     * @return   string
     */
    public function getReferer()
    {
        return $this->referer;
    }

    /**
     * @brief    get task context     
     *
     * @return   array
     */
    public function getContext()
    {
        return $this->context;
    }

}
