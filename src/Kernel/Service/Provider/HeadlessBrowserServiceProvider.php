<?php
/**
 * @script   HeadlessBrowserServiceProvider
 * @brief    This file is part of PHPCreeper
 * @author   blogdaren<blogdaren@163.com>
 * @link     http://www.phpcreeper.com
 * @create   2024-04-19
 */

namespace PHPCreeper\Kernel\Service\Provider;

use PHPCreeper\Kernel\Service\Service;
use PHPCreeper\Kernel\Service\Wrapper\HeadlessBrowserFactoryService;

class HeadlessBrowserServiceProvider
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
        $service->inject('bindHeadlessBrowser', function($type = '', ...$args){
            return $this->headlessBrowser = HeadlessBrowserFactoryService::create($type, ...$args);
        });
    }
}
