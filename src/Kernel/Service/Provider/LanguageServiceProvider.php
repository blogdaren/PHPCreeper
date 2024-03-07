<?php
/**
 * @script   LanguageServiceProvider.php
 * @brief    This file is Part of PHPCreeper
 * @author   blogdaren<blogdaren@163.com>
 * @version  1.0.0
 * @modify   2019-09-20
 */

namespace PHPCreeper\Kernel\Service\Provider;

use PHPCreeper\PHPCreeper;
use PHPCreeper\Kernel\Service\Service;
use PHPCreeper\Kernel\Service\Wrapper\LanguageService;

class LanguageServiceProvider
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
        $service->inject('bindLangConfig', function($type = 'zh'){
            return $this->langConfig = PHPCreeper::$langConfigBackup = LanguageService::load($type);
        });
    }
}

