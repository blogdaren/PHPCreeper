# PHPCreeper
[![language](https://img.shields.io/badge/language-php-ff69b4.svg)]()
[![base](https://img.shields.io/badge/base-workerman-519dd9.svg)]()
[![php](https://img.shields.io/badge/php->=7.0.0-519dd9.svg)]()
[![posix](https://img.shields.io/badge/ext_posix-required-red.svg)]()
[![pcntl](https://img.shields.io/badge/ext_pcntl-required-red.svg)]()
[![event](https://img.shields.io/badge/ext_event-suggest-green.svg)]()
[![redis](https://img.shields.io/badge/ext_redis-suggest-green.svg)]()
[![license](http://img.shields.io/badge/license-Apache%202.0-000000.svg)]()

## What is it

[PHPCreeper](http://www.phpcreeper.com) is a new generation of multi-process 
asynchronous event-driven spider engine based on [Workerman](https://www.workerman.net)

## Documentation
The document of chinese version is relatively complete, and the full english version will be published as soon as possile.   
**注意：** 爬山虎中文开发文档相对比较完善，中国朋友直接点击下方链接阅读即可，另详版英文文档也会尽可能快的释放.

* 爬山虎中文官方网站：[http://www.phpcreeper.com](http://www.phpcreeper.com)
* 中文开发文档主节点：[http://www.phpcreeper.com/docs/](http://www.phpcreeper.com/docs/)
* 中文开发文档备节点：[http://www.blogdaren.com/docs/](http://www.blogadren.com/docs/)

## Todo List
- [x] 轻量级关系型数据库：Lightweight relational database like Medoo style
- [ ] 反爬之IP生态代理池： IP ecological agent pool of Anti-Spider strategy
- [ ] 图片验证码识别技术：Image verification code recognition technology
- [ ] 智能化识别网页数据：Intelligent recognition of the web page content
- [ ] 爬虫项目管理可视化：The crawler application management visualization

## Motivation
Nowdays, it has already existed in all kinds of language version of the spider framework, such as: 
`Spiderman based on Java`、`Scrapy based on Python`、`go-colly based on Go` etc. 
However, we also need to realize that：when faced with business scenarios where any language is appropriate,
no matter whether you are a novice or the PHP preconceived driver who is not so familiar 
with other programming languages, if you want to develop a crawler business 
at this point, we strongly recommend that you should give priority to an excellent spider 
engine written in PHP. So why? Because PHP is absolutely optimal for agility, because 
you can play PHP with ease, because it could be much more expensive to use some other languages, 
because it can reduce the development costs for you or your company in a straight line and so on.

Besides, almost all of the PHP spider frameworks work as either single-process or synchronous mode, 
neither distributed nor separate deployment is supported, so crawler performance couldn't be maximized. 
Today `PHPCreeper` makes everything possible.


So the final goals of `PHPCreeper` are:     

* Focus on efficient agile development, and make the crawling job becomes so easy.   
* Solve the performance and extension problems of traditional PHP crawler frameworks.    


## Features
* Inherit all features from workerman.
* Free to customize various plugins and callback.
* Free to customize the third-party middleware.
* Support for netflow traffic limitaion.
* Support for distributed deployment.
* Support for separated deployment.
* Support for socket programming.
* Support multi-language environment.
* Use PHPQuery as the elegant content extractor.
* Support for agile development with PHPCreeper-Application
* With high performance and strong scalability.


## Prerequisites
* PHP_VERSION \>= 7.0.0     
* A POSIX compatible operating system (Linux, OSX, BSD)  
* POSIX extension for PHP (**required**)
* PCNTL extension for PHP (**required**)
* REDIS extension for PHP (optional, better to install)
* EVENT extension for PHP (optional, better to install)

## Installation
The recommended way to install PHPCreeper is through [Composer](https://getcomposer.org/).
```
composer require blogdaren/phpcreeper
```

## Usage: not depend on the application framework
First of all, we should know that there is another official matched application framework 
named `PHPCreeper-Application` which is also published simultaneously for your development convenience,
although this framework is not necessary, we strongly recommend that you use it for 
business development, thus it's no doubt that it will greatly improve your job efficiency.
however, somebody still wish to write the code which not depends on the framework, it is 
also easy to play.   
Assume our demand is to capture the weather forecasts for the next 7 days, here let's take an example to illustrate the usage:
```
<?php 
require "./vendor/autoload.php";

use PHPCreeper\Kernel\PHPCreeper;
use PHPCreeper\Producer;
use PHPCreeper\Downloader;
use PHPCreeper\Parser;

//producer instance
$producer = new Producer;
$producer->setName('AppProducer')->setCount(1);
$producer->onProducerStart = function($producer){
    $task = array(
        'url' => array(
            "r1" => "http://www.weather.com.cn/weather/101010100.shtml",
        ),
        'rule' => array(
            "r1" => array(
                'time' => ['div#7d ul.t.clearfix h1',      'text'],
                'wea'  => ['div#7d ul.t.clearfix p.wea',   'text'],
                'tem'  => ['div#7d ul.t.clearfix p.tem',   'text'],
                'wind' => ['div#7d ul.t.clearfix p.win i', 'text'],
            ), 
        ),
    );
    $context = [
        //'cache_enabled'   => true,                              
        //'cache_directory' => '/tmp/task/download/' . date('Ymd'), 
    ];
    $producer->newTaskMan()->setContext($context)->createMultiTask($task);
};

//downloader instance
$downloader = new Downloader();
$downloader->setName('AppDownloader')->setCount(2)->setClientSocketAddress([
    'ws://127.0.0.1:8888',
]);

//parser instance
$parser = new Parser();
$parser->setName('AppParser')->setCount(1)->setServerSocketAddress('websocket://0.0.0.0:8888');
$parser->onParserExtractField = function($parser, $download_data, $fields){
    pprint($fields);
};

PHPCreeper::runAll();
```

## Usage: depend on the application framework
Next, let's use the official application framework to complete the same task above efficiently:    


#### *Step-1：Download PHPCreeper-Application Framework*
```php
git clone https://github.com/blogdaren/PHPCreeper-Application
```

#### *Step-2：Load the PHPCreeper Core Engine*

1、Switch to the PHPCreeper-Application base directory:
```php
cd /path/to/PHPCreeper-Application
```

2、Load the PHPCreeper core engine:
```php
composer require blogdaren/phpcreeper
```

#### *Step-3：Run PHPCreeper-Application Assistant*

1、Run PHPCreeper-Application assistant:
```php
php  Application/Sbin/Creeper

```
2、The terminal output will look like this:    

![AppAssistant](./Image/AppAssistantEnglish.png)

 #### *Step-4：Create One Application*
1、Create one spider application named **weather**:
```
php Application/Sbin/Creeper make weather --en
```
2、The full process of building looks like this:   

![AppAssistant](./Image/AppWeatherEnglish.png)

As matter of fact, we have accomplished all the jobs at this point,
you just need to run `php weather.php start` to see what has happened, 
but you still need to finish the rest step of the work if you wanna
do some elaborate work or jobs.

#### *Step-5：Business Configuration*
1、Switch to the application config direcory:
```
cd Application/Spider/Weather/Config/
```
2、Edit the global config file named **global.php**:   
```
attention: this file don't need to be changed unless you want to introduce a new global sub-config file
```
3、Edit the global sub-config file named **database.php** like this:
```
<?php
return array(
    'redis' => array(
        'prefix' => 'Weather',
        'host'   => '127.0.0.1',
        'port'   => 6379,
        'database' => 0,
    ),
);
```
4、Edit the global sub-config file named **main.php** like this:
```
return array(
    'language' => 'en',
    'multi_worker'  => true,
    'start' => array(
        'WeatherProducer'      => true,
        'WeatherDownloader'    => true,
        'WeatherParser'        => true,
    ),
    'task' => array(
        'method'          => 'get',
        'crawl_interval'  => 1,
        'max_depth'       => 1,
        'max_number'      => 1000,
        'max_request'     => 1000,
        'compress'  => array(
            'enabled'   =>  true,
            'algorithm' => 'gzip',
        ),
        'limit_domains' => array(
        ),
        'url' => array(
            "r1" => "http://www.weather.com.cn/weather/101010100.shtml",
        ),
        'context' => array(
        ),
   ),
);
```
In fact, most of the configuration parameters are not used frequently, it will automatically read 
the default value from engine, so the configuration can be simplified like this:
```
return array(
    'task' => array(
        'url' => array(
            "r1" => "http://www.weather.com.cn/weather/101010100.shtml",
        ),
    ),
);
```
5、Edit the business worker config file named **AppProducer.php** like this：
```
<?php
return array(
    'name' => 'producer1',
    'count' => 1,
    'interval' => 1,
);
```
6、Edit the business worker config file named **AppDownloader.php** like this：
```
<?php
return array(
    'name' => 'downloader1',
    'count' => 2,
    'socket' => array(
        'client' => array(
            'parser' => array(
                'scheme' => 'ws',
                'host' => '127.0.0.1',
                'port' => 8888,
            ),
        ),
    ),
    'cache' => array(
        'enabled'   => false,
        'directory' => '/tmp/logs/data/' . date('Ymd'),
    ),
);
```
7、Edit the business worker config file named **AppParser.php** like this：
```
<?php
return array(
    'name'  => 'parser1',
    'count' => 3,
    'socket' => array(
        'server' => array(
            'scheme' => 'websocket',
            'host' => '0.0.0.0',
            'port' => 8888,
        ),
    ),
);
```
#### *Step-6：Set Business Rule*
1、Switch to the PHPCreeper-Application base directory again:
```
cd Application/Spider/Weather/Config/
```
2、Go back to Edit **main.php** again:
```
return array(
    'task' => array(
        'url' => array(
            "r1" => "http://www.weather.com.cn/weather/101010100.shtml",
        ),
        'rule' => array(
            "r1" => array(
                'time' => ['div#7d ul.t.clearfix h1',      'text'],
                'wea'  => ['div#7d ul.t.clearfix p.wea',   'text'],
                'tem'  => ['div#7d ul.t.clearfix p.tem',   'text'],
                'wind' => ['div#7d ul.t.clearfix p.win i', 'text'],
            ), 
        ),
   ),
);
```
#### *Step-7：Write Business Callback*
1、Write business callback for AppProducer:
```
public function onProducerStart($producer)
{
    //here you can add one new task here
    /*$task = array(
         'url' => array(
             'r1' => 'https://baike.baidu.com/item/%E5%8C%97%E4%BA%AC/128981?fr=aladdin',
         ),
         'rule' => array(
             'r1' => array(
                 'airport' => ['dl.basicInfo-right dd.basicInfo-item.value:eq(5)', 'text'],
             ),
         ),
    );
    $producer->newTaskMan()->createMultiTask($task);*/
}

public function onProducerStop($producer)
{
}

public function onProducerReload($producer)
{
}
``` 
2、Write business callback for AppDownloader:
```
public function onDownloaderStart($downloader)
{
}

public function onDownloaderStop($downloader)
{
}

public function onDownloaderReload($downloader)
{
}

public function onDownloaderMessage($downloader, $parser_reply)
{
}

public function onBeforeDownload($downloader, $task)
{
    //here you can reset the $task array here and be sure to return it
    //$task = [...];
    //return $task;

    //here you can change the context parameters when making a http request
    //$downloader->httpClient->setConnectTimeout(3);
    //$downloader->httpClient->setTransferTimeout(10);
    //$downloader->httpClient->setProxy('http://180.153.144.138:8800');
}

public function onStartDownload($downloader, $task)
{
}

public function onAfterDownload($downloader, $download_data, $task)
{
    //here you can save the downloaded source data to a file
    //file_put_contents("/path/to/downloadData.txt", $download_data);
}
```
3、Write business callback for AppParser:
```
public function onParserStart($parser)
{
}

public function onParserStop($parser)
{
}

public function onParerReload($parser)
{
}

public function onParerMessage($parser, $connection, $download_data)
{
}

public function onParserFindUrl($parser, $url)
{
    //here you can check whether the sub url is valid or not
    //if(!Tool::checkUrl($url)) return false;
}

public function onParserExtractField($parser, $download_data, $fields)
{
    //here you can print out the business data extracted by rule
    //!empty($fields) && var_dump($fields, __METHOD__);

    //here you can save the business data into database like mysql、redis and so on
    //DB::save($fields);
}
```
#### *Step-8：Start Application Instance*
There are two ways to start an application instance, one is `Global Startup`, 
and the other is `Single Startup`, we just need to choose one of them.
`Global Startup` means that all workers run in the same group of processes under the same application,
it can be deployed in a distributed way, but it cannot be deployed separately,
`Single Startup` means that different workers run in different groups of processes under the same application,
it can be distributed or deployed separately.

1、Or Global Startup:
```
php weather.php start
```

2、Or Single Startup:
```
php Application/Spider/Weather/AppProducer.php start
php Application/Spider/Weather/AppDownloader.php start
php Application/Spider/Weather/AppParser.php start
```

## Available commands:  
We have to remind you again that all the commands in PHPCreeper run at the command line, 
and whatever the application is, you must write an entry startup script whose name
assumed to be `AppWorker.php` before you start PHPCreeper, but if you use the 
PHPCreeper-Application framework for development, the framework will automatically 
generate the application entry startup script.

1、Start as debug mode:   
```
php AppWorker.php start
```

2、Start as daemon mode:   
```
php AppWorker.php start -d
```

3、Stop:
```
php AppWorker.php stop
```

4、Restart:
```
php AppWorker.php restart
```

5、Reload gracefully:
```
php AppWorker.php reload
```

6、Show runtime status:
```
php AppWorker.php status
```

7、Show connections status:
```
php AppWorker.php connections
```

## Use Database
PHPCreeper wrappers a lightweight database like Medoo style, 
please visit the [Medoo official site](https://medoo.lvtao.net/) 
if you wanna know more usage. now we just need to find out 
how to get the DBO, as a matter of fact, it is very simple:   

First configure the `database.php` then add the code belowed:
```
<?php
return array(
    'dbo' => array(
        'test' => array(
            'database_type' => 'mysql',
            'database_name' => 'test',
            'server'        => '127.0.0.1',
            'username'      => 'root',
            'password'      => 'root',
            'charset'       => 'utf8'
        ),
    ),
);
```

Now we can get DBO and start the query or the other operation as you like: 
```
$downloader->onAfterDownloader = function($downloader){
    //dbo single instance and we can pass the DSN string `test`
    $downloader->getDbo('test')->select('user', '*');
    
    //dbo single instance and we can pass the configuration array
    $config = Configurator::get('globalConfig/database/dbo/test')
    $downloader->getDbo($config)->select('user', '*');

    //dbo new instance and we can pass the DSN string `test`
    $downloader->newDbo('test')->select('user', '*');

    //dbo new instance and we can pass the configuration array
    $config = Configurator::get('globalConfig/database/dbo/test')
    $downloader->newDbo($config)->select('user', '*');
};
```

## Screenshot
![EnglishVersion1](./Image/EnglishVersion1.png)
![EnglishVersion2](./Image/EnglishVersion2.png)

## Related links and thanks

* [http://www.phpcreeper.com](http://www.phpcreeper.com)
* [http://www.blogdaren.com](http://www.blogdaren.com)
* [https://www.workerman.net](https://www.workerman.net)

## Donate
If you agree with the author's work and benefit from PHPCreeper, 
i'm willing to accept donations from all sides. 
The donation will continue to be used for the follow-up research, 
development and maintenance of PHPCreeper as well as the maintenance of the server.
Thanks a lot.

* By PayPal.me：[PHPCcreeper.paypal.me](https://paypal.me/phpcreeper)
* By Alipay or Wechat：    
![alipay](./Image/alipay.png)
![wechat](./Image/wechat.png)

## LICENSE
PHPCreeper is released under the [Apache 2.0 License](http://www.apache.org/licenses/LICENSE-2.0).


