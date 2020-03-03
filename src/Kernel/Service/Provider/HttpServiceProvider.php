<?php
/**
 * @script   HttpServiceProvider.php
 * @brief    This file is part of PHPCreeper
 * @author   blogdaren<blogdaren@163.com>
 * @version  1.0.0
 * @modify   2019-11-06
 */

namespace PHPCreeper\Kernel\Service\Provider;

use PHPCreeper\Kernel\Service\Service;
use PHPCreeper\Kernel\Service\Wrapper\HttpFactoryService;

class HttpServiceProvider
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
        $service->inject('bindHttpClient', function($type = '', ...$args){
            return $this->httpClient = HttpFactoryService::createHttpClient($type, ...$args);
        });

        /*
         *$service->inject('get', function($url, ...$args){
         *    return HttpService::get($url, ...$args);
         *});
         */

        /*
         *$service->inject('post', function($url, ...$args){
         *    return HttpService::post($url, ...$args);
         *});
         */
    }
}
