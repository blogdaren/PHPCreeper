
## Usage: Depend On The PHPCreeper Application Framework
Now, let's do the same job based on the PHPCreeper Application Framework:    


#### *Step-1：Download PHPCreeper-Application Framework*
```
git clone https://github.com/blogdaren/PHPCreeper-Application /path/to/myproject
```

#### *Step-2：Load the PHPCreeper Engine*

1、Switch to the application base directory:
```
cd /path/to/myproject
```

2、Load the PHPCreeper Engine:
```
composer require blogdaren/phpcreeper
```

#### *Step-3：Run PHPCreeper-Application Assistant*

1、Run PHPCreeper-Application assistant:
```
php  Application/Sbin/Creeper
```
2、The terminal output looks like this:    

![AppAssistant](/Image/AppAssistantEnglish.png)

#### *Step-4：Create One Application*

1、Create one spider application named **weather**:
```
php Application/Sbin/Creeper make weather --en
```

2、The complete execution process looks like this:   

![AppWeather2English](/Image/AppWeather2English.png)

In fact, we have done all the jobs at this point,
now you just need to run `php weather.php start` to see what has happened, 
but you still need to finish the rest step of the work if you want to 
do some elaborate work or jobs.

#### *Step-5：Business Configuration*
1、Switch to the application config direcory:
```
cd Application/Spider/Weather/Config/
```
2、Edit the global config file named **global.php**:   
```
Basically, there is no need to change this file unless you want to create a new global sub-config file
```
3、Edit the global sub-config file named **database.php**:
```php
<?php
return [
    'redis' => [
        'host'   => '127.0.0.1',
        'port'   => 6379,
        'database' => 0,
        'prefix' => 'Weather',
    ],
];
```
or 
```php
<?php
return [
    'redis' => [[
        'host'   => '127.0.0.1',
        'port'   => 6379,
        'database' => 0,
        'prefix' => 'Weather',
    ]],
];
```
4、Edit the global sub-config file named **main.php**:
```php
<?php
return array(
    //set the locale, currently support Chinese and English (optional, default `zh`)
    'language' => 'en',

    //PHPCreeper has two modes to work: single worker mode and multi workers mode,   
    //the former is seldomly to use, the only advantage is that you can play it   
    //without redis-server, because it uses the PHP built-in queue service, so it    
    //only applys to some simple jobs ; the latter is frequently used to handle    
    //many more complex jobs, especially for distributed or separated work or jobs,    
    //in this way, you must enable the redis server (optional, default `true`)
    'multi_worker'  => true,

    //whether to start the given worker(s) instance or not(optional, default `true`)
    'start' => array(
        'AppProducer'      => true,
        'AppDownloader'    => true,
        'AppParser'        => true,
    ),

    //global task config
    'task' => array(
        //set the task crawl interval, the minimum 0.001 second (optional, default `1`)
        'crawl_interval'  => 1,

        //set the max number of the task queue, 0 indicates no limit (optional, default `0`)
        'max_number'      => 1000,

        //specifies the max number of connections each downloader process can connect to the parser
        //(optional, default `1`, minimum value 1, maximum value 1000)
        'max_connections' => 1,

        //set the max number of the request for each socket connection,  
        //if the cumulative number of socket requests exceeds the max number of requests,
        //the parser will close the connection and try to reconect automatically.
        //0 indicates no limit (optional, default `0`)
        'max_request'     => 1000,

        //compression algorithm
        'compress'  => array(
            //whether to enable the data compress method (optional, default `true`)
            'enabled'   =>  true,

            //compress algorithm, support `gzip` and `deflate` (optional, default `gzip`)
            'algorithm' => 'gzip',
        ),

        //limit domains which are allowed to crawl, no limit if leave empty
        'limit_domains' => [],

        //automatically compute the optimal bitmap size and hash functions count by given expected insertions and falseratio
        'bloomfilter' => [
            'expected_insertions' => 10000,  //expected insertions
            'expected_falseratio' => 0.01,   //expected falseratio
        ],

        //SPECIAL NOTE: the context member configured here is a global context,
        //we can also set a private context for each task individually,
        //it gives us a lot of flexibility to indirectly affect dependent services,
        //for example, various context parameters of HTTP requests can be affected 
        //by setting those context members (optional, default `null`).
        //the default HTTP engine is the Guzzle client, which supports all request 
        //parameters for Guzzle. See the Guzzle Manual for details.
        //SPECIAL NOTE: there are very few members which is not inconsistent with
        //Guzzle Official, so the inconsistencies will be annotated specifically.
        'context' => array(
            //whether to enable the download cache or not (optional, default `false`)
            'cache_enabled'   => false,                               

            //set the download cache directory (optional, default `sys_get_temp_dir()`)
            'cache_directory' => sys_get_temp_dir() . '/DownloadCache4PHPCreeper/',

            //whether to allow capture the same URL resource repeatedly within a particular life cycle
            'allow_url_repeat' => true,

            //whether to trace the full HTTP request parameters not,  
            //the terminal will display the full request parameters if enabled
            'track_request_args' => true,

            //whether to trace the full task packet or not,  
            //the terminal will display the full task packet if enabled
            'track_task_package' => true,

            //before v1.6.0, it use md5($task_url) as rule_name if leave empty  
            //since v1.6.0, it will use md5($task_id) as rule_name if leave empty  
            //so this configuration parameter is only for backward compatibility, 
            //but it is not recommended because of the potential pitfalls.
            //in other words, if you are using an earlier version than v1.6.0, 
            //then you may need to enable this parameter. 
            'force_use_md5url_if_rulename_empty' => false,

            //force to use the older version of the multi-task creation API parameter style 
            //to maintain backward compatibility, but it is not recommendeded to use.
            'force_use_old_style_multitask_args' => false,

            //set http request header: engine will automatically disguise itself as a variety of common random User-Agents
            'headers' => [
                //'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
                //'Accept'     => 'text/html,*/*',
            ],

            //the format of cookies member is not different with the Guzzle Official, 
            //we shield up the cookieJar, the value maybe [false | array]
            'cookies' => [
                //'domain' => 'domain.com',
                //'k1' => 'v1',
                //'k2' => 'v2',
            ],  

            //set guzzle config membere here  
            //..............................

            //use headless browser to crawl dynamic pages
            'headless_browser' => [
                'headless' => false, 
                /* more browser options for chrome to see: */
                /* https://github.com/chrome-php/chrome?tab=readme-ov-file#available-options */
            ],

            //whether to extract sub url or not, note that engine just extract the sub url,
            //but it won't be pushed into the task queue, so you can use the callback API 
            //`onParserFindUrl` to do this job as you expected. (optional, default `true`)
            'extract_sub_url'  => true,

            //in addition to the built-in parameters, you can also configure user-defined parameters,
            //which are very useful in the upstream and downstream service chain application scenarios.
            'user_define_key1' => 'user_define_value1',
            'user_define_key2' => 'user_define_value2',
        ),
    ),

    //set the initialized task, support both Single-Task and Multi-Task
    'task_init' => array(
        'url'  => "https://forecast.weather.gov/MapClick.php?lat=47.4113&lon=-120.5563",

        //please refer to the "How to set extractor rule" section for details
        "rule" => array(
            'period'      => ['#seven-day-forecast-container ul li p.period-name', 'text'],
            'weather'     => ['#seven-day-forecast-container ul li p.short-desc', 'text'],
            'temperature' => ['#seven-day-forecast-container ul li p.temp', 'text'],
        ),  

        //set rule name which will be set to `md5($task_id)` if leave it empty
        'rule_name' => 'r1',

        //set task private context
        'context'   => [],
    ),
);
```
5、Edit the business worker config file named **AppProducer.php**：
```php
<?php
return array(
    'name' => 'producer1',
    'count' => 1,
    'interval' => 1,
);
```

6、Edit the business worker config file named **AppDownloader.php**：
```php
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
);
```
7、Edit the business worker config file named **AppParser.php**：
```php
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
#### *Step-6：Write Business Callback*
1、Write business callback for AppProducer:
```php
<?php
public function onProducerStart($producer)
{
    //here we can add more new task(s) 
    //$producer->createTask($task);
    //$producer->createMultiTask($task);
}

public function onProducerStop($producer)
{
}

public function onProducerReload($producer)
{
}
``` 
2、Write business callback for AppDownloader:
```php
<?php
public function onDownloaderStart($downloader)
{
}

public function onDownloaderStop($downloader)
{
}

public function onDownloaderReload($downloader)
{
}

public function onDownloaderConnectToParser($connection)
{   
}  

public function onDownloaderMessage($downloader, $parser_reply)
{
}

public function onDownloadBefore($downloader, $task)
{
    //here we can reset the $task and then return it
    //return $task;

    //here we can change the context parameters when creating a http request
    //$downloader->httpClient->setConnectTimeout(5);
    //$downloader->httpClient->setTransferTimeout(10);
    //$downloader->httpClient->disableSSL();
}

public function onDownloadStart($downloader, $task)
{
}

public function onDownloadAfter($downloader, $download_data, $task)
{
    //here we can save the downloaded source data to a file
    //file_put_contents("/path/to/DownloadData.txt", $download_data);
}

public function onDownloadFail($downloader, $error, $task)
{
}

public function onTaskEmpty($downloader)
{
}
```
3、Write business callback for AppParser:
```php
<?php
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
    //here we can view the current task
    //pprint($parser->task);
}

public function onParserFindUrl($parser, $sub_url)
{
    //here we can create new task by sub_url
    //$parser->createTask($sub_url);
}

public function onParserExtractField($parser, $download_data, $fields)
{
    //here we got the expected data successfully extracted by rule
    //!empty($fields) && pprint($fields, __METHOD__);
    pprint($fields['r1']);

    //here we can save the business data into database like mysql、redis and so on
    //DB::save($fields);
}
```
#### *Step-7：Start Application Instance*
There are two ways to start an application instance, one is `Global Startup`, 
and the other is `Single Startup`, we just need to choose one of them.
`Global Startup` means that all workers run in the same group of processes under the same application,
it can be deployed in a distributed way, but it can't be deployed separately,
`Single Startup` means that different workers run in different groups of processes under the same application,
it can be distributed or deployed separately.

1、Or Global Startup:
```
php weather.php start
```

2、Or Single Startup:
```
php Application/Spider/Weather/Start/AppProducer.php    start
php Application/Spider/Weather/Start/AppDownloader.php  start
php Application/Spider/Weather/Start/AppParser.php      start
```

