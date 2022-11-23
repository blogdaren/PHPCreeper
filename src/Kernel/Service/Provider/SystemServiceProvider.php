<?php
/**
 * @script   SystemServiceProvider.php
 * @brief    This file is part of PHPCreeper
 * @author   blogdaren<blogdaren@163.com>
 * @version  1.0.0
 * @modify   2019-11-06
 */

namespace PHPCreeper\Kernel\Service\Provider;

use PHPCreeper\Kernel\Service\Service;
use PHPCreeper\Kernel\Service\Wrapper\QueueFactoryService;
use PHPCreeper\Kernel\Task;
use PHPCreeper\Kernel\Middleware\Database\Database;

class SystemServiceProvider
{
    /**
     * @brief    闭包式服务注射器
     *
     * 注 意:    闭包里的 $this 指向的是PHPCreeper对象, 内部机制参考核心PHPCreeper类.
     *
     * @param    object $service
     *
     * @return   array
     */
    public function render(Service $service)
    {
        $service->inject('bindRedisClient', function($type, ...$args){
            return $this->redisClient = QueueFactoryService::createQueueClient($type, ...$args);
        });

        $service->inject('getTaskMan', function($task_options = []){
            return Task::getInstance($this, $task_options);
        });

        $service->inject('newTaskMan', function($task_options = []){
            return Task::newInstance($this, $task_options);
        });

        $service->inject('getDbo', function($options = []){
            return Database::getInstance($options);
        });

        $service->inject('newDbo', function($options = []){
            return Database::newInstance($options);
        });

        $service->inject('createTask', function($options = []){
            return Task::newInstance($this, [])->createTask($options);
        });

        $service->inject('createMultiTask', function($options = []){
            return Task::newInstance($this, [])->createMultiTask($options);
        });
    }
}
