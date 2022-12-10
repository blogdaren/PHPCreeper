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
//require_once dirname(__FILE__, 2) . "/src/Kernel/Library/Common/Functions.php";


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
 * Global-Redis-Config: just leave it alone when run as Single-Worker mode
 * 仅单worker运作模式下不依赖redis，所以此时redis的配置可以忽略不管.
 * 特别注意：自v1.6.4起，redis锁机制已升级并默认使用官方推荐的更安全的分布式红锁，
 * 只有当所有的redis实例都显式的配置[use_red_lock === false]才会退化为旧版的锁机制.
 */
$config['redis'] = [
    [
        'host'      =>  '127.0.0.1',
        'port'      =>  6379,
        'auth'      =>  false,
        'pass'      =>  'guest',
        'prefix'    =>  'PHPCreeper', 
        'database'  =>  '0',
        'connection_timeout' => 5,
        'read_write_timeout' => 0,
        //'use_red_lock'     => true,   //默认使用更安全的分布式红锁 
    ],
    /*[
        'host'      =>  '127.0.0.1',
        'port'      =>  6380,
        'auth'      =>  false,
        'pass'      =>  'guest',
        'prefix'    =>  'PHPCreeper', 
        'database'  =>  '0',
        'connection_timeout' => 5,
        'read_write_timeout' => 0,
        //'use_red_lock'     => true,   //默认使用更安全的分布式红锁 
    ],*/
];


/**
 * Global-Task-Config: the context member configured here is a global context,
 * we can also set a private context for each task, finally the global context 
 * and task private context will adopt the strategy of merging and covering.
 * free to customize various context settings, including user-defined,
 *
 * 注意: 此处配置的context是全局context上下文，我们也可以为每条任务设置私有context上下文，
 * 其上下文成员完全相同，全局context与任务私有context最终采用合并覆盖的策略，具体参考手册。
 * http://www.phpcreeper.com/docs/DevelopmentGuide/ApplicationConfig.html
 * context上下文成员主要是针对任务设置的，但同时拥有很大灵活性，可以间接影响依赖性服务，
 * 比如可以通过设置context上下文成员来影响HTTP请求时的各种上下文参数 (可选项，默认为空)
 * HTTP引擎默认采用Guzzle客户端，兼容支持Guzzle所有的请求参数选项，具体参考Guzzle手册。
 * 特别注意：个别上下文成员的用法是和Guzzle官方不一致的，一方面主要就是屏蔽其技术性概念，
 * 另一方面面向开发者来说，关注点主要是能进行简单的配置即可，所以不一致的会注释特别说明。
 */
$config['task'] = array( 
    //任务爬取间隔，单位秒，最小支持0.001秒 (可选项，默认1秒)
    //'crawl_interval'  => 1,
    //最大爬取深度, 0代表爬取深度无限制 (可选项，默认1)
    //'max_depth'       => 1,
    //任务队列最大task数量, 0代表无限制 (可选项，默认0)
    //'max_number'      => 1000,
    //当前Socket连接累计最大请求数，0代表无限制 (可选项，默认0)
    //如果当前Socket连接的累计请求数超过最大请求数时，
    //parser端会主动关闭连接，同时客户端会自动尝试重连
    //'max_request'     => 1000,
    //限定爬取站点域，留空表示不受限
    'limit_domains' => [],
    //全局任务context上下文
    'context' => [
        //要不要缓存下载文件 [默认false]
        'cache_enabled'   => true,
        'cache_directory' => '/tmp/DownloadCache4PHPCreeper/',
        //在特定的生命周期内是否允许重复抓取同一个URL资源 [默认false]
        'allow_url_repeat'   => true,
        //要不要跟踪完整的HTTP请求参数，开启后终端会显示完整的请求参数 [默认false]
        'track_request_args' => true,
        //要不要跟踪完整的TASK数据包，开启后终端会显示完整的任务数据包 [默认false]
        'track_task_package' => true,
        //在v1.6.0之前，如果rulename留空，默认会使用 md5($task_url)作为rulename
        //自v1.6.0开始，如果rulename留空，默认会使用 md5($task_id) 作为rulename
        //所以这个配置参数是仅仅为了保持向下兼容，但是不推荐使用，因为有潜在隐患
        //换句话如果使用的是v1.6.0之前旧版本，那么才有可能需要激活本参数 [默认false]
        'force_use_md5url_if_rulename_empty' => false,
        //强制使用多任务创建API的旧版本参数风格，保持向下兼容，不再推荐使用 [默认false]
        'force_use_old_style_multitask_args' => false,
        //cookies成员的配置格式和guzzle官方不大一样，屏蔽了cookieJar，取值[false|array]
        'cookies' => [
            //'domain' => 'domain.com',
            //'k1' => 'v1',
            //'k2' => 'v2',
        ],
        //除了内置参数之外，还可以自由配置自定义参数，在上下游业务链应用场景中十分有用
        'user_define_key1' => 'user_define_value1',
        'user_define_key2' => 'user_define_value2',
    ],
); 


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
//startAppServer();


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
    $producer = new Producer($config);
    $producer->setName('AppProducer')->setCount(1);

    //模拟抓取未来7天内北京的天气预报
    $producer->onProducerStart = function($producer){
        //任务私有context，其上下文成员与全局context完全相同，最终会采用合并覆盖策略
        $context = [];


        //在v1.6.0之前，爬山虎主要使用OOP风格的API来创建任务：
        //$producer->newTaskMan()->setXXX()->setXXX()->createTask()
        //$producer->newTaskMan()->setXXX()->setXXX()->createTask($task)
        //$producer->newTaskMan()->setXXX()->setXXX()->createMultiTask()
        //$producer->newTaskMan()->setXXX()->setXXX()->createMultiTask($task)


        //自v1.6.0开始，爬山虎提供了更加短小便捷的API来创建任务, 而且参数类型更加丰富：
        //注意：仅仅只是扩展，原有的API依然可以正常使用，提倡扩展就是为了保持向下兼容。
        //1. 单任务API：$task参数类型可支持：[字符串 | 一维数组]
        //1. 单任务API：$producer->createTask($task);
        //2. 多任务API：$task参数类型可支持：[字符串 | 一维数组 | 二维数组]
        //2. 多任务API：$producer->createMultiTask($task);


        //使用字符串：不推荐使用，配置受限，需要自行处理抓取结果
        //$task = "http://www.weather.com.cn/weather/101010100.shtml";
        //$producer->createTask($task);
        //$producer->createMultiTask($task);


        //使用一维数组：推荐使用，配置丰富，引擎内置处理抓取结果
        $task = $_task = array(
            'active' => true,       //是否激活当前任务，只有配置为false才会冻结任务，默认true
            'url' => "http://www.weather.com.cn/weather/101010100.shtml",
            "rule" => array(        //如果该字段留空默认将返回原始下载数据
                'time' => ['div#7d ul.t.clearfix h1',      'text', [], 'function($field_name, $data){
                    return "具体日子: " . $data;
                }'],                //关于回调字符串的用法务必详看官方手册
                'wea'  => ['div#7d ul.t.clearfix p.wea',   'text'],
                'tem'  => ['div#7d ul.t.clearfix p.tem',   'text'],
                'wind' => ['div#7d ul.t.clearfix p.win i', 'text'],
            ), 
            'rule_name' =>  '',     //如果留空将使用md5($task_id)作为规则名
            'refer'     =>  '',
            'type'      =>  'text', //可以自由设定类型
            'method'    =>  'get',
            'context'   =>  $context,
        );
        $producer->createTask($task);
        $producer->createMultiTask($task);


        //使用二维数组: 推荐使用，配置丰富，因为是多任务，所以只能调用createMultiTask()接口
        $task = array(
            array(
                "url" => "http://www.weather.com.cn/weather/101010100.shtml",
                "rule" => array(
                    'time' => ['div#7d ul.t.clearfix h1',      'text'],
                    'wea'  => ['div#7d ul.t.clearfix p.wea',   'text'],
                    'tem'  => ['div#7d ul.t.clearfix p.tem',   'text'],
                    'wind' => ['div#7d ul.t.clearfix p.win i', 'text'],
                ), 
                'rule_name' => 'r1', //如果留空将使用md5($task_id)作为规则名
                "context" => $context,
            ),
            array(
                "url" => "http://www.weather.com.cn/weather/201010100.shtml",
                "rule" => array(
                    'time' => ['div#7d ul.t.clearfix h1',      'text'],
                    'wea'  => ['div#7d ul.t.clearfix p.wea',   'text'],
                    'tem'  => ['div#7d ul.t.clearfix p.tem',   'text'],
                    'wind' => ['div#7d ul.t.clearfix p.win i', 'text'],
                ), 
                'rule_name' => 'r2', //如果留空将使用md5($task_id)作为规则名
                "context" => $context,
            ),
        );
        $producer->createMultiTask($task);

        //以下是旧版本OOP风格的单任务创建API：可继续使用
        $_task['url'] = "http://www.demo5.com";
        $producer->newTaskMan()->setUrl($_task['url'])->setRule($_task['rule'])
            ->setContext($context)->createTask();

        //以下是旧版本OOP风格的多任务创建API：不推荐使用
        $_task['url'] = "http://www.demo6.com";
        $producer->newTaskMan()->createMultiTask($_task);

        //也可以通过开启一个内部端口与第三方应用进行外部通信来创建任务
        /*
         *$server = new Server();
         *$server->setServerSocketAddress("text://0.0.0.0:3333");
         *$server->serve();
         *$server->onMessage = function($connection, $task)use($producer){
         *    $producer->createTask($task);
         *};
         */
    };
}


/**
 * all components support distributed or separated deployment
 * 所有组件支持分布式或分离式部署
 */
function startAppDownloader()
{
    global $config;
    $downloader = new Downloader($config);
    //$downloader->setTaskCrawlInterval(5);
    $downloader->setName('AppDownloader')->setCount(1)->setClientSocketAddress([
        'ws://127.0.0.1:8888',
    ]);

    $downloader->onBeforeDownload = function($downloader, $task){
        //disable http ssl verify in any of the following two ways 
        //$downloader->httpClient->disableSSL();
        //$downloader->httpClient->setOptions(['verify' => false]);
    }; 

    $downloader->onDownloaderStart = function($downloader){
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

