<?php 
/**
 * @script   start.php
 * @brief    这是脱离爬山虎应用框架的自定义启动脚本:
 *
 * 1. 本示例脚本是脱离爬山虎应用框架的自定义启动脚本;
 * 2. 本示例脚本用于模拟抓取未来7天内的天气预报数据;
 * 3. 如果希望使用爬山虎应用框架开发，请参考开发手册：
 *
 *    >> http://www.phpcreeper.com/docs/
 *    >> http://www.blogdaren.com/docs/
 *
 * @author   blogdaren<blogdaren@163.com>
 * @link     http://www.phpcreeper.com
 * @create   2022-09-08
 */


//自行设定自动加载器路径
require dirname(__FILE__, 2) . "/vendor/autoload.php";


//只是临时为了兼容工具函数库在低版本工作正常以及演示需要，实际并不需要这行代码
require_once dirname(__FILE__, 2) . "/src/Kernel/Library/Common/Functions.php";


use PHPCreeper\Kernel\PHPCreeper;
use PHPCreeper\Producer;
use PHPCreeper\Downloader;
use PHPCreeper\Parser;
use PHPCreeper\Server;
use PHPCreeper\Tool;
use PHPCreeper\Timer;
use PHPCreeper\Crontab;
use Logger\Logger;


/**
 * just leave redis config alone when run as Single-Worker mode
 * 仅单worker运作模式下不依赖redis，所以此时redis的配置可以忽略不管
 */
$config['redis'] = [
    [
        'host'      =>  '192.168.1.234',
        'port'      =>  6379,
        'auth'      =>  false,
        'pass'      =>  'guest',
        'prefix'    =>  'PHPCreeper', 
        'database'  =>  '0',
        'connection_timeout' => 5,
    ],
    /*[
        'host'      =>  '192.168.1.234',
        'port'      =>  6380,
        'auth'      =>  false,
        'pass'      =>  'guest',
        'prefix'    =>  'PHPCreeper', 
        'database'  =>  '0',
        'connection_timeout' => 5,
    ],*/
];


//启动生产器组件
startAppProducer();

//启动下载器组件
startAppDownloader();

//启动解析器组件
startAppParser();

/**
 * 启动通用型服务器组件，项目较少使用，可以按需自由定制一些服务，完全独立于 [Producer|Downloader|Parser] 组件
 * start the general server component，seldom to use in project，can customize some services freely as needed，
 * and fully indepent on those components like [Producer|Downloader|Parser]
 */
startAppServer();


/**
 * enable the single worker mode so that we can run without redis, however, you should note 
 * it will be limited to run only all the downloader workers in this case【version >= 1.3.2】
 * and the default is Multi-Worker run mode.
 *
 * 多worker运作模式开关，默认是多worker运作模式，支持两种运作模式【version >= 1.3.2】：
 *
 * 1、单worker运作模式：限定只能编写若干特定的downloader实例，即可完成所有的爬虫需求，
 *    好处是开箱即用，不依赖redis服务，使用PHP内置队列，缺点是只能对付简单的爬虫需求;
 * 2、多worker运作模式：支持自由编写任意多个业务worker实例，这是爬山虎默认的运作模式;
 */
//PHPCreeper::enableMultiWorkerMode(false);


/**
 * switch runtime language between `zh` and `en`, default is `zh`【version >= 1.3.7】
 * 多语言运行时环境开关：暂支持中文和英文，默认是中文【version >= 1.3.7】
 */
//PHPCreeper::setLang('en');


/**
 * set the corresponding app log according to the component, 
 * and can also mask the log of the corresponding log level.
 * 根据组件保存相应的应用日志，也可以屏蔽掉相应日志级别的日志。
 */
//PHPCreeper::setLogFile('/tmp/runtime.log');
//PHPCreeper::setLogFile('/tmp/runtime.log', 'producer');
//PHPCreeper::setLogFile('/tmp/runtime.log', 'downloader');
//PHPCreeper::setLogFile('/tmp/runtime.log', 'parser');
//PHPCreeper::disableLogLevel(['crazy','debug','info']);
//PHPCreeper::disableLogLevel(['crazy','debug','info'], 'producer');
//PHPCreeper::disableLogLevel(['crazy','debug','info', 'warn'], 'downloader');


/**
 * set master pid file manually as needed【version >= 1.3.8】
 * 设置主进程PID文件【version >= 1.3.8】
 */
//PHPCreeper::setMasterPidFile('master.pid');


/**
 * note that `predis` will be the default redis client since【version >= 1.4.2】
 * but you could still switch it to be `redis` if you prefer to use ext-redis
 * 设置默认的redis客户端，默认为predis，也可切换为基于ext-redis的redis【version >= 1.4.2】
 */
//PHPCreeper::setDefaultRedisClient('redis');


/**
 * set default timezone, default is `Asia/Shanghai`【version >= 1.5.4】
 * 设置默认时区，默认为 Asia/Shanghai
 */
//PHPCreeper::setDefaultTimezone('Asia/Shanghai');


/**
 * all components support distributed or separated deployment
 * 所有组件支持分布式或分离式部署
 */
function startAppProducer()
{
    global $config;
    $producer = new Producer;
    $producer->setName('AppProducer')->setCount(1)->setConfig($config);

    //模拟抓取未来7天内北京的天气预报
    $producer->onProducerStart = function($producer){
        //context上下文成员主要是针对任务设置的，但同时拥有很大灵活性，可以间接影响依赖性服务，
        //比如可以通过设置context上下文成员来影响HTTP请求时的各种上下文参数 (可选项，默认为空)
        //HTTP引擎默认采用Guzzle客户端，兼容支持Guzzle所有的请求参数选项，具体参考Guzzle手册。
        //特别注意：个别上下文成员的用法是和Guzzle官方不一致的，一方面主要就是屏蔽其技术性概念，
        //另一方面就是面向开发者来说，要的就是能进行简单的配置即可，所以不一致的会注释特别说明。
        $context = [
            'cache_enabled'   => true,
            'cache_directory' => '/tmp/task/download/' . date('Ymd'), 
            //在特定的生命周期内是否允许重复抓取同一个URL资源 [默认false]
            'allow_url_repeat' => false,
            //要不要跟踪完整的HTTP请求参数，开启后终端会显示完整的请求参数 [默认false]
            'track_request_args' => true,
            //cookies成员的配置格式和guzzle官方不大一样，屏蔽了cookieJar，取值[false|array]
            /*
             *'cookies' => [
             *    'domain' => 'domain.com',
             *    'k1' => 'v1',
             *    'k2' => 'v2',
             *],
             */
        ];

        //批量任务接口
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
        $producer->newTaskMan()->setContext($context)->createMultiTask($task);

        //单个任务接口
        $task = [
            'url' => "http://www.weather.com.cn/weather/101010100.shtml",
            "rule" => array(
                'time' => ['div#7d ul.t.clearfix h1',      'text'],
                'wea'  => ['div#7d ul.t.clearfix p.wea',   'text'],
                'tem'  => ['div#7d ul.t.clearfix p.tem',   'text'],
                'wind' => ['div#7d ul.t.clearfix p.win i', 'text'],
            ), 
        ];
        $producer->newTaskMan()->setUrl($task['url'])->setRule($task['rule'])->setContext($context)->createTask();
        $producer->newTaskMan()->setContext($context)->createTask($task);
    };
}


/**
 * all components support distributed or separated deployment
 * 所有组件支持分布式或分离式部署
 */
function startAppDownloader()
{
    global $config;
    $downloader = new Downloader();
    $downloader->setConfig($config);
    //$downloader->setTaskCrawlInterval(7);
    $downloader->setName('AppDownloader')->setCount(1)->setClientSocketAddress([
        'ws://192.168.1.234:8888',
    ]);

    $downloader->onDownloaderStart = function($downloader){
        //$worker = new Downloader();
        //$worker->setServerSocketAddress("text://0.0.0.0:3333");
        //$worker->serve();
    };

    $downloader->onAfterDownload = function($downloader, $data, $task){
        //Tool::debug($content, $json = true, $append = true, $filename = "debug", $base_dir = "/tmp/")
        //Tool::debug($task);
    };
}


/**
 * all components support distributed or separated deployment
 * 所有组件支持分布式或分离式部署
 */
function startAppParser()
{
    $parser = new Parser();
    $parser->setName('AppParser')->setCount(1);
    $parser->setServerSocketAddress('websocket://0.0.0.0:8888');
    $parser->onParserExtractField = function($parser, $download_data, $fields){
        pprint($fields);
    };
}

/**
 * General Server independ on [Producer|Downloader|Parser]
 * 通用型服务器组件，完全独立于[Producer|Downloader|Parser]
 */
function startAppServer()
{
    $server = new Server();
    $server->onServerStart = function(){
        /*
         * just show how to use Linux-Style Crontab: 
         *
         * (1) the only difference is that support the second-level;
         * (2) the minimum time granularity is minutes if the second bit is omitted;
         *
         * the formatter looks like as below:
         * 
         *  0   1   2   3   4   5
         *  |   |   |   |   |   |
         *  |   |   |   |   |   +------ day of week (0 - 6) (Sunday=0)
         *  |   |   |   |   +------ month (1 - 12)
         *  |   |   |   +-------- day of month (1 - 31)
         *  |   |   +---------- hour (0 - 23)
         *  |   +------------ min (0 - 59)
         *  +-------------- sec (0-59)[可省略，如果没有0位,则最小时间粒度是分钟]
         *
         * 防止重复造轮子且图省事完全照搬了walkor大大的workerman-crontab而来, 
         * 很小巧且为了方便所以将此库脱离了composer库并揉进了PHPCreeper内核，
         * 高仿Linux风格的Crontab，语法层面除了支持秒级以外，其余用法基本一致，
         * 所以平时crontab怎么用现在就怎么用，具体用法请参照workerman官方手册：
         * https://www.workerman.net/doc/webman/components/crontab.html
         */

        //每隔1秒执行一次任务
        new Crontab('*/1 * * * * *', function(){
            pprint("模拟每隔1秒打印一下当前时间：" . Tool::getHumanTime());
        });

        //每隔2分钟执行一次任务
        new Crontab('*/2 * * * *', function(){
            pprint("模拟每隔2分钟打印一下当前时间：" . Tool::getHumanTime());
        });
    };
}

PHPCreeper::runAll();

