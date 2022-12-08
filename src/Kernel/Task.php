<?php
/**
 * @script   Task.php
 * @brief    This file is part of PHPCreeper
 * @author   blogdaren<blogdaren@163.com>
 * @link     http://www.phpcreeper.com
 * @create   2019-09-04
 */

namespace PHPCreeper\Kernel;

require_once dirname(__FILE__, 1) . '/Library/Common/Functions.php';
        
use PHPCreeper\Kernel\Service\Service;
use PHPCreeper\Kernel\Service\Provider\SystemServiceProvider;
use PHPCreeper\Kernel\Service\Provider\HttpServiceProvider;
use PHPCreeper\Kernel\Service\Provider\PluginServiceProvider;
use PHPCreeper\Kernel\Service\Provider\QueueServiceProvider;
use PHPCreeper\Kernel\Service\Provider\LockServiceProvider;
use PHPCreeper\Kernel\Service\Provider\LanguageServiceProvider;
use PHPCreeper\Kernel\Service\Provider\ExtractorServiceProvider;
use PHPCreeper\Kernel\Service\Provider\DropDuplicateServiceProvider;
use PHPCreeper\Kernel\Slot\BrokerInterface;
use PHPCreeper\Kernel\Slot\DropDuplicateInterface;
use PHPCreeper\Kernel\Slot\HttpClientInterface;
use PHPCreeper\Kernel\Slot\LockInterface;
use PHPCreeper\Kernel\PHPCreeper;
use PHPCreeper\Kernel\Library\Helper\Tool;
use Configurator\Configurator;
use Logger\Logger;
use PHPCreeper\Kernel\Library\Polyfill\Uuid;

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
    public $type = '';

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
    public $method = '';

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
     * whether enable task or not
     *
     * @var boolean
     */
    public $active = true;

    /**
     * @brief    __construct    
     *
     * @param    object  $phpcreeper
     * @param    array   $options
     *
     * 
     * >> pay attention to the runtime priority of global task options:   
     * __construct() > setXXX() > default_global_config
     *
     * >> the priority only applies to the options listed as below:
     * type | url | method | context | rule | rule_name | refer
     *
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
     * @param    string | array  $input
     *
     * @return   boolean | int
     */
    public function createTask($input = [])
    {
        //not only support array, but also string
        if(is_string($input))
        {
            $tmp_url = $input;
            $input = [];
            $input['url'] = $tmp_url;
        }

        //check task params
        $check_result = self::checkTaskParams($input);
        if(true !== $check_result) 
        {
            Logger::error(Tool::replacePlaceHolder($this->phpcreeper->langConfig['queue_url_invalid']));
            return false;
        }

        //check whether task is active or not
        $check_result = $this->checkTaskActiveState($input);
        if(true !== $check_result) 
        {
            Logger::warn(Tool::replacePlaceHolder($this->phpcreeper->langConfig['queue_inactive_task'], [
                'task_url' => $input['url'],
            ]));
            return false;
        }

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

        //rewash params
        $url     = $input['url'];
        $type    = (empty($input['type']) || !is_string($input['type'])) ? $this->getType() : $input['type'];
        $method  = (empty($input['method']) || !is_string($input['method'])) ? $this->getMethod() : $input['method'];
        $referer = (empty($input['referer']) || !is_string($input['referer'])) ? $this->getReferer() : $input['referer'];
        $rule    = (empty($input['rule']) || !is_array($input['rule'])) ? $this->getRule() : $input['rule'];


        //important!!! ensure that the 4th argument of rule is a valid callback string
        foreach($rule as $k => &$v)
        {
            $v[3] = Tool::getCallbackString($v[3] ?? '');
        }
        //important!!! ensure that the 4th argument of rule is a valid callback string


        $depth     = $input['depth'];
        $context   = $this->getContext($input['context'] ?? []);
        $task_id   = $this->createTaskId();
        $rule_name = $this->getRuleName();

        if(empty($rule_name) || !is_string($rule_name)) 
        {
            if(isset($context['force_use_md5url_if_rulename_empty']) && true === $context['force_use_md5url_if_rulename_empty']){
                $rule_name = md5($url);
            }else{
                $rule_name = md5($task_id);
            }
        }

        //check task url allowed to repeat or not
        $allow_url_repeat = false;
        if(!empty($context['allow_url_repeat']) && true === $context['allow_url_repeat'])
        {
            $allow_url_repeat = true;
        }

        if(PHPCreeper::$isRunAsMultiWorker && !$allow_url_repeat)
        {
            if(true === $this->hasUrl($input['url'])) 
            {
                Logger::warn(Tool::replacePlaceHolder($this->phpcreeper->langConfig['queue_duplicate_task'], [
                    'task_url'  => $url,
                ]));

                //unlock
                $this->phpcreeper->count > 1 && $this->phpcreeper->lockHelper->unlock('pushtask', $gold_key);

                return false;
            }

            $this->phpcreeper->dropDuplicateFilter->add($url);
        }

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

        //try to push into queue
        $rs = $this->phpcreeper->queueClient->push('task', $task_data);

        //unlock
        $this->phpcreeper->count > 1 && $this->phpcreeper->lockHelper->unlock('pushtask', $gold_key);

        if(!empty($rs))
        {
            Logger::info(Tool::replacePlaceHolder($this->phpcreeper->langConfig['queue_push_task'], [
                'task_id'   => $task_id,
                'task_url'  => $url,
            ]));

            //try to track task package
            if(isset($context['track_task_package']) && true === $context['track_task_package'])
            {
                Logger::crazy(Tool::replacePlaceHolder($this->phpcreeper->langConfig['track_task_package'], [
                    'task_package' => str_replace("\\/", "/", json_encode($task_data)),
                ]));
            }
        }

        return !empty($rs) ? $task_id : 0;
    }

    /**
     * @brief   create multi task less than v1.6.0
     *
     * !!! Old-Style-API not recommended to use !!!
     *
     * @param   string | 1D-array | 2D-array    $task
     *
     * @return  boolean
     */
    public function createMultiTaskLessThanV160($task = [])
    {
        //important!!!
        if(is_string($task))
        {
            $tmp_url = $task;
            $task = [];
            $task['url'] = $tmp_url;
        }

        empty($task['url']) && $task['url'] = $this->getUrl();
        $urls    = !is_array($task['url']) ? array($task['url']) : $task['url'];
        $type    = (empty($task['type']) || !is_string($task['type'])) ? $this->getType() : $task['type'];
        $method  = (empty($task['method']) || !is_string($task['method'])) ? $this->getMethod() : $task['method'];
        $referer = (empty($task['referer']) || !is_string($task['referer'])) ? $this->getReferer() : $task['referer'];
        $context = $this->getContext($task['context'] ?? []);
        $rules   = (empty($task['rule']) || !is_array($task['rule'])) ? $this->getRule() : $task['rule'];
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
        }

        if(empty($urls)) 
        {
            Logger::error(Tool::replacePlaceHolder($this->phpcreeper->langConfig['queue_url_invalid']));
            return false;
        }

        return true;
    }

    /**
     * @brief   create multi task 
     *
     * !!! New-Style-API recommended to use !!!
     *
     * @param   string | 1D-array | 2D-array    $task
     *
     * @return  boolean
     */
    public function createMultiTask($task = [])
    {
        //backward compatible with API version less than( < v1.6.0)
        if(isset($task['context']['force_use_old_style_multitask_args']) 
            && true === $task['context']['force_use_old_style_multitask_args'])
        {
            return $this->createMultiTaskLessThanV160($task);
        }

        //new version implementation
        $new_task = [];
        if(is_string($task)){
            $tmp_url = $task;
            $task = [];
            $task['url'] = $tmp_url;
            $new_task[] = $task;
        }elseif(is_array($task) && isset($task['url'])){
            $new_task[] = $task;
        }elseif(is_array($task) && isset(array_values($task)[0]['url'])){
            $new_task = $task;
        }

        foreach($new_task as $k => $task) 
        {
            $result = self::rebuildMultiTaskParams($task);
            if(false == $result) 
            {
                Logger::error(Tool::replacePlaceHolder($this->phpcreeper->langConfig['queue_url_invalid']));
                continue;
            }

            @extract($task);
            $taskObject = self::newInstance($this->phpcreeper);
            $task_id = $taskObject->setUrl($url)
                            ->setActiveState($active)
                            ->setType($type)
                            ->setMethod($method)
                            ->setReferer($referer)
                            ->setRuleName($rule_name)
                            ->setRule($rule)
                            ->setContext($context)
                            ->createTask();
        }

        if(empty($new_task)) 
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
     * @brief    rebuild multi task params     
     *
     * @param    array  $task
     *
     * @return   boolean
     */
    static public function rebuildMultiTaskParams(&$task = [])
    {
        if(empty($task) || !is_array($task)) return false;

        if(true !== Tool::checkUrl($task['url'] ?? '')) return false;

        if(!isset($task['active']) || !is_bool($task['active'])) 
        {
            $task['active'] = true;
        }

        if(empty($task['type']) || !is_string($task['type'])) 
        {
            $task['type'] = 'unknown';
        }

        if(empty($task['method']) || !is_string($task['method'])) 
        {
            $task['method'] = 'get';
        }

        if(empty($task['referer']) || !is_string($task['referer'])) 
        {
            $task['referer'] = $task['url'];
        }

        if(empty($task['rule']) || !is_array($task['rule'])) 
        {
            $task['rule'] = [];
        }

        if(empty($task['rule_name']) || !is_string($task['rule_name'])) 
        {
            $task['rule_name'] = '';
        }

        if(empty($task['context']) || !is_array($task['context'])) 
        {
            $task['context'] = [];
        }

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
        $this->id = Uuid::uuid_create();

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
        !is_array($rule) && $rule = [];

        $this->rule = $rule;

        return $this;
    }

    /**
     * @brief    set request method 
     *
     * @param    string  $method
     *
     * @return   object
     */
    public function setMethod($method = '')
    {
        $this->method = $method;

        return $this;
    }

    /**
     * @brief    set request referer  
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
        !is_array($context) && $context = [];
        $this->context = array_merge($this->context, $context);

        return $this;
    }

    /**
     * @brief    get task type
     *
     * @return   string
     */
    public function getType()
    {
        if(!empty($this->type)) return $this->type;

        $this->type = Configurator::get('globalConfig/main/task/type');
        empty($this->type) && $this->type = 'text';

        return $this->type;
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
     * @brief   get request method 
     *
     * @return  string
     */
    public function getMethod()
    {
        if(!empty($this->method)) return $this->method;

        $this->method = Configurator::get('globalConfig/main/task/method');
        empty($this->method) && $this->method = 'get';

        return $this->method;
    }

    /**
     * @brief    get request referer 
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
    public function getContext($context = [])
    {
        $global_context = Configurator::get('globalConfig/main/task/context');
        !is_array($global_context) && $global_context = [];
        !is_array($context) && $context = [];
        !is_array($this->context) && $this->context = [];

        $this->context = array_merge($global_context, $context, $this->context);

        return $this->context;
    }

    /**
     * @brief    set task active state     
     *
     * @param    boolean    $active
     *
     * @return   object
     */
    public function setActiveState($active = true)
    {
        !is_bool($active) && $active = true;

        $this->active = $active;

        return $this;
    }

    /**
     * @brief    get task active state     
     *
     * @return   boolean
     */
    public function getActiveState()
    {
        return $this->active;
    }

    /**
     * @brief    check task active state   
     *
     * @param    array  $input
     *
     * @return   boolean
     */
    public function checkTaskActiveState($input)
    {
        if(!isset($input['active']))
        {
            $input['active'] = $this->getActiveState();
        }

        if(false === $input['active'])
        {
            return false;
        }

        return true;
    }
}
