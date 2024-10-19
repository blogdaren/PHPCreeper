Well, this is an full demo example to show how to `capture the weather in Washington in 7 days`：
```php
<?php 
require "./vendor/autoload.php";

use PHPCreeper\PHPCreeper;
use PHPCreeper\Producer;
use PHPCreeper\Downloader;
use PHPCreeper\Parser;
use PHPCreeper\Server;
use PHPCreeper\Crontab;
use PHPCreeper\Timer;

//switch runtime language between `zh` and `en`, default is `zh`【version >= 1.3.7】
PHPCreeper::setLang('en');

//enable the single worker mode so that we can run without redis, however, you should note 
//it will be limited to run only all the downloader workers in this case【version >= 1.3.2】
//PHPCreeper::enableMultiWorkerMode(false);

//set master pid file manually as needed【version >= 1.3.8】
//PHPCreeper::setMasterPidFile('/path/to/master.pid');

//set worker log file when start as daemon mode as needed【version >= 1.3.8】
//PHPCreeper::setLogFile('/path/to/phpcreeper.log');

//note that `predis` will be the default redis client since【version >= 1.4.2】
//but you could still switch it to be `redis` if you prefer to use ext-redis
//PHPCreeper::setDefaultRedisClient('redis');

//set default timezone, default is `Asia/Shanghai`【version >= 1.5.4】
//PHPCreeper::setDefaultTimezone('Asia/Shanghai');

//redirect all stdandard out to file when run as daemonize【version >= 1.7.0】
//PHPCreeper::setStdoutFile("/path/to/stdout.log");

//set default headless browser, default is `chrome`【version >= 1.8.7】
//PHPCreeper::setDefaultHeadlessBrowser('chrome');

//Global-Redis-Config: support array value with One-Dimension or Two-Dimension, 
//NOTE: since v1.6.4, it's been upgraded to use a more secure and officially
//recommended distributed red lock mechanism by default, but it will use the
//old version of the lock mechanism degenerate only when all the redis instances 
//are explicitly configured with the option [use_red_lock === false] as below.
//for details on how to configure the value, refer to the Follow-Up sections.
$config['redis'] = [
    [
        'host'      =>  '127.0.0.1',
        'port'      =>  6379,
        'database'  =>  '0',
        'auth'      =>  false,
        'pass'      =>  'guest',
        'prefix'    =>  'PHPCreeper', 
        'connection_timeout' => 5,
        'read_write_timeout' => 0,
        'use_red_lock'       => true,   //default to true since v1.6.4
    ],
];

//Global-Task-Config: the context member configured here is a global context,
//we can also set a private context for each task, finally the global context 
//and private task context will adopt the strategy of merging and covering.
//you can free to customize various context settings, including user-defined,
//for details on how to configure it, please refer to the Follow-Up sections.
$config['task'] = [ 
    //'crawl_interval'  => 1,
    //'max_number'      => 1000,
    //'max_connections' => 1,
    //'max_request'     => 1000,
    'context' => [
        'cache_enabled'    => true,
        'cache_directory'  => sys_get_temp_dir() . '/DownloadCache4PHPCreeper/',
        'allow_url_repeat' => true,
        'headless_browser' => ['headless' => false, /*more browser options*/],
    ],
]; 

function startAppProducer()
{
    global $config;
    $producer = new Producer($config);

    $producer->setName('AppProducer')->setCount(1);
    $producer->onProducerStart = function($producer){
        //private task context which will be merged with global context
        $private_task_context = [];

        //【version <  1.6.0】: we mainly use an OOP style API to create task     
        //$producer->newTaskMan()->setXXX()->setXXX()->createTask()
        //$producer->newTaskMan()->setXXX()->setXXX()->createTask($task)
        //$producer->newTaskMan()->setXXX()->setXXX()->createMultiTask()
        //$producer->newTaskMan()->setXXX()->setXXX()->createMultiTask($task)

        //【version >= 1.6.0】: we provide a shorter and easier API to create task    
        //with more rich parameter types, and the old OOP style API can still be used,    
        //and extension jobs are promoted just to maintain backward compatibility
        //1. Single-Task-API: $task parameter types supported: [string | 1D-array]    
        //2. Single-Task-API：$producer->createTask($task);   
        //3. Multi-Task-API:  $task parameter types supported: [string | 1D-array | 2D-array]   
        //4. Multi-Task-API： $producer->createMultiTask($task);

        //use string: not recommended to use because the configuration is limited.    
        //so the question is that you need to process the fetching result by yourself     
        //$task = "https://github.com/search?q=stars:%3E1&s=stars&type=Repositories";
        //$producer->createTask($task);
        //$producer->createMultiTask($task);

        //use 1D-array：we can use either `createTask()` or `createMultiTask()` API
        $task = array(
            'url'  => "https://forecast.weather.gov/MapClick.php?lat=47.4113&lon=-120.5563",
            'rule' => [       
                'period'      => ['#seven-day-forecast-container ul li p.period-name', 'text'],
                'weather'     => ['#seven-day-forecast-container ul li p.short-desc', 'text'],
                'temperature' => ['#seven-day-forecast-container ul li p.temp', 'text'],
            ],
            'rule_name' =>  '',       //md5($task_id) will be the rule_name if leave empty  
            'refer'     =>  '',
            'type'      =>  'text',   //it has lost the original concept setting, feel free to use 
            'method'    =>  'get',
            'parser'    =>  '',       //router to one random target parser if leave empty [ip:port]  
            "context"   =>  $private_task_context, 
        );
        $producer->createTask($task);
        $producer->createMultiTask($task);

        //use 2D-array: since it is multitasking, we can only use the `createMultiTask()` API 
        $task = array(
            array(
                'url'  => "https://forecast.weather.gov/MapClick.php?lat=47.4113&lon=-120.5563",
                'rule' => [       
                    'period'      => ['#seven-day-forecast-container ul li p.period-name', 'text'],
                    'weather'     => ['#seven-day-forecast-container ul li p.short-desc', 'text'],
                    'temperature' => ['#seven-day-forecast-container ul li p.temp', 'text'],
                ],
                'rule_name' => 'r1',
                "context"   => $private_task_context,
            ),
            array(
                'url'  => "https://forecast.weather.gov/MapClick.php?lat=47.4113&lon=-120.5563",
                'rule' => [       
                    'period'      => ['#seven-day-forecast-container ul li p.period-name', 'text'],
                    'weather'     => ['#seven-day-forecast-container ul li p.short-desc', 'text'],
                    'temperature' => ['#seven-day-forecast-container ul li p.temp', 'text'],
                ],
                'rule_name' => 'r2', 
                "context"   => $private_task_context,
            ),
        );
        $producer->createMultiTask($task);

        //use headless browser to crawl dynamic page rendered by javascript
        $private_task_context['headless_browser']['headless'] = true;
        $dynamic_task = array(
            'url' => 'https://www.toutiao.com',
            'rule' => array(
                'title' => ['div.show-monitor ol li a', 'aria-label'],
                'link'  => ['div.show-monitor ol li a', 'href'],
            ), 
            'context' => $private_task_context,
        );
        $producer->createTask($dynamic_task);
    };
}

function startAppDownloader()
{
    global $config;
    $downloader = new Downloader($config);

    //set the client socket address based on the listening parser server 
    $downloader->setName('AppDownloader')->setCount(2)->setClientSocketAddress([
        'ws://127.0.0.1:8888',
    ]);

    $downloader->onDownloadBefore = function($downloader, $task){
        //disable http ssl verify in any of the following two ways 
        //$downloader->httpClient->disableSSL();
        //$downloader->httpClient->setOptions(['verify' => false]);
    }; 

    //use headless browser by user callback or API directly
    $downloader->onHeadlessBrowserOpenPage = function($downloader, $browser, $page, $url){
        //Note: keeping flexible types of return values helps to deal with various complex app scenarios.
        //1. Returning false  will trigger the interruption of subsequent business logic.
        //2. Returning string will trigger the interruption of subsequent business logic, 
        //   it is often used to return the HTML of the web page.
        //3. Returning array  will continue to execute subsequent business logic, 
        //   it is often used to return headless browser options.
        //4. Returning others will continue to execute subsequent business logic, 
        //   which is equivalent to do nothing.

        //Note: Generally, there is no need to call the following lines of code, because 
        //Note: PHPCreeper will automatically call the headless API by default to do the same work.
        //$page->navigate($url)->waitForNavigation('firstMeaningfulPaint');
        //$html = $page->getHtml();
        //return $html;
    };

    //more downloader or download callbacks frequently used
    //$downloader->onDownloaderStart = function($downloader){};
    //$downloader->onDownloaderStop  = function($downloader){};
    //$downloader->onDownloaderMessage = function($downloader, $parser_reply){};
    //$downloader->onDownloaderConnectToParser = function($connection){};
    //$downloader->onDownloadStart = function($downloader, $task){};
    //$downloader->onDownloadAfter = function($downloader, $download_data, $task){};
    //$downloader->onDownloadFail  = function($downloader, $error, $task){};
    //$downloader->onDownloadTaskEmpty = function($downloader){};
}

function startAppParser()
{
    $parser = new Parser();
    $parser->setName('AppParser')->setCount(1)->setServerSocketAddress('websocket://0.0.0.0:8888');
    $parser->onParserExtractField = function($parser, $download_data, $fields){
        pprint($fields);
    };

    //more parser callbacks frequently used
    //$parser->onParserStart = function($parser){};
    //$parser->onParserStop  = function($parser){};
    //$parser->onParserMessage = function($parser, $connection, $download_data){};
    //$parser->onParserFindUrl = function($parser, $sub_url){};
}

function startAppServer()
{
    $server = new Server();
    $server->onServerStart = function(){
        //execute the task every 1 second
        new Crontab('*/1 * * * * *', function(){
            pprint("print the current time every 1 second: " . time());
        });

        //execute the task every 2 minutes 
        new Crontab('*/2 * * * *', function(){
            pprint("print the current time every 2 minutes: " . time());
        });
    };
}

//start producer component
startAppProducer();

//start downloader component
startAppDownloader();

//start parser component 
startAppParser();

//start server component
startAppServer();

//start phpcreeper engine
PHPCreeper::start();
```

Now, save the example code above to a file and name it to be `github.php` as a startup script, then run it like this:
```
/path/to/php github.php start
```
