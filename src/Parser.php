<?php
/**
 * @script   Parser.php
 * @brief    This file is part of PHPCreeper
 * @author   blogdaren<blogdaren@163.com>
 * @link     http://www.phpcreeper.com
 * @create   2019-09-04
 */

namespace PHPCreeper;

use PHPCreeper\Kernel\PHPCreeper;
use PHPCreeper\Kernel\Library\Helper\Tool;
use PHPCreeper\Timer;
use Configurator\Configurator;
use Logger\Logger;


class Parser extends PHPCreeper
{
    /**
     * parser timer id
     *
     * @var int
     */
    public $parserTimerId = 0;

    /**
     * task
     *
     * @var array
     */
    public $task = null;

    /**
     * the send buffer size of connection from parser to downloader 
     *
     * @var int
     */
    private $_sendToDownloaderBufferSize = 10240000;

    /**
     * force to close the connection if any request not received
     * after the time specified with MESSAGE_ALIVE_TIMEOUT 
     *
     * @var int
     */
    public const MESSAGE_ALIVE_TIMEOUT = 300;

    /**
     * @brief    __construct    
     *
     * @return   void
     */
    public function __construct($config = [])
    {
        parent::__construct($config);
    }

    /**
     * @brief    run current worker   
     *
     * @return   void
     */
    public function run()
    {
        $this->onWorkerStart  = array($this, 'onWorkerStart');
        $this->onWorkerStop   = array($this, 'onWorkerStop');
        $this->onWorkerReload = array($this, 'onWorkerReload');
        $this->onConnect      = array($this, 'onConnect');
        $this->onMessage      = array($this, 'onMessage');
        parent::run();
    }

    /**
     * @brief    onWorkerStart  
     *
     * @param    object $worker
     *
     * @return   void
     */
    public function onWorkerStart($worker)
    {
        //global init 
        $this->initMiddleware()->initLogger();

        //trigger user callback
        $this->triggerUserCallback('onParserStart', $this);
    }

    /**
     * @brief    onWorkerStop
     *
     * @param    object $worker
     *
     * @return   void
     */
    public function onWorkerStop()
    {
    }

    /**
     * @brief    onWorkerReload
     *
     * @param    object $worker
     *
     * @return   boolean | void
     */
    public function onWorkerReload($worker)
    {
        //trigger user callback
        $returning = $this->triggerUserCallback('onParserReload', $this);
        if(false === $returning) return false;
    }

    /**
     * @brief    onConnect  
     *
     * @param    object  $connection
     *
     * @return   void
     */
    public function onConnect($connection)
    {
        Logger::debug(Tool::replacePlaceHolder($this->langConfig['parser_connected_success'], [
            'parser_server_address'   =>   $connection->worker->getSocketName(),
        ]));

        $connection->maxSendBufferSize = $this->getSendBufferSize();
        $connection->maxPackageSize = PHPCreeper::getDefaultMaxFileSizeToDownload() > 0 ?: (20 * (1<<20));
        empty($connection->lastMessageAliveTime) && $connection->lastMessageAliveTime = time();

        //trigger user callback
        $this->triggerUserCallback('onParserConnect', $connection);

        $connection->timerId = Timer::add(1, function()use($connection){
            if(time() - $connection->lastMessageAliveTime > self::MESSAGE_ALIVE_TIMEOUT)
            {
                $connection->close('0x05');
                Timer::del($connection->timerId);
            }
        }, [], true);
    }

    /**
     * @brief    onMessage  
     *
     * @param    object  $connection
     * @param    string  $message
     *
     * @return   void
     */
    public function onMessage($connection, $message)
    {
        //update the alive time when got any message
        $connection->lastMessageAliveTime = time();

        //try to disassemble data
        $message = $this->disassemblePackage($message);

        //gettype($message) === [string|array]
        if(empty($message)) return false;

        //check ping from downloader
        $this->checkPingFromDownloader($message);

        //pprint(memory_get_usage(false)/1024/1024);
        $worker_id     = $connection->worker->id;
        $connection_id = $connection->id;
        $task_id       = $message['task']['id']    ?? 0;
        $download_data = $message['download_data'] ?? '';

        //check task_id + download_data
        if(empty($task_id) || empty($download_data)) return false;

        //if type of $download_data is resource, then base64_decode it
        /*
         *if('text' <> $message['task']['type'])
         *{
         *    $download_data = base64_decode($download_data);
         *}
         */

        //set task + increase task depth
        $this->setTask($message['task'])->increaseTaskDepth();

        //trigger user callback: only return false can skip the rest execution in this session
        $returning = $this->triggerUserCallback('onParserMessage', $this, $connection, $download_data);
        if(false === $returning) return false;
        if(!empty($returning) && is_string($returning)) $download_data = $returning;

        //extract field
        $fields = $this->extractField($download_data);
        $returning = $this->triggerUserCallback('onParserExtractField', $this, $download_data, $fields);
        if(false === $returning) return false;
        if(!empty($returning) && is_string($returning)) $download_data = $returning;

        //extract sub url
        $sub_urls = $this->extractSubUrl($download_data);
        if(empty($sub_urls)) return false;

        //trigger user callback: only return false can skip the rest execution in this session
        foreach($sub_urls as $sub_url)
        {
            $returning = $this->triggerUserCallback('onParserFindUrl', $this, $sub_url);
            if(false === $returning) continue;
            if(!empty($returning) && is_string($returning)) $sub_url = $returning;

            //if task depth > max_depth then discard the current task
            $check_result = $this->checkWhetherTaskDepthExceedMaxDepth($sub_url);
            if(true === $check_result) continue;

            //contine to add sub task
            $sub_task_id = $this->addSubTask($sub_url);
            if(!empty($sub_task_id))
            {
                Logger::info(Tool::replacePlaceHolder($this->langConfig['parser_find_url'], [
                    'sub_url'   => $sub_url,
                ]));
            }
        }

        Logger::debug(Tool::replacePlaceHolder($this->langConfig['parser_task_success'], [
            'connection_id' => $connection_id,
            'task_id'       => $this->task['id'],
        ]));

        $reply = Tool::replacePlaceHolder($this->langConfig['parser_task_report'], [
            'connection_id' => $connection_id,
            'task_id'       => $this->task['id'],
        ]);
        $reply = $this->assemblePackage($reply);

        $connection->send($reply);
    }

    /**
     * @brief    check ping from downloader    
     *
     * @param    array  $input
     *
     * @return   boolean
     */
    public function checkPingFromDownloader($input = [])
    {
        if(empty($input) || empty($input['event']) || empty($input['interval']))
        {
            return false;
        }

        Logger::debug(Tool::replacePlaceHolder($this->langConfig['ping_from_downloader'], [
            'interval' => $input['interval'],
        ]));

        return true;
    }

    /**
     * @brief    set the send buffer size of connection from parser to downloader 
     *
     * @param    int    $size
     *
     * @return   object
     */
    public function setSendBufferSize($size = 10240000)
    {
        !Tool::checkIsInt($size) && $size = $this->_sendToDownloaderBufferSize;

        $this->_sendToDownloaderBufferSize = $size;

        return $this;
    }

    /**
     * @brief    get the send buffer size of connection from parser to downloader 
     *
     * @return   int
     */
    public function getSendBufferSize()
    {
        return $this->_sendToDownloaderBufferSize;
    }

    /**
     * @brief    set task
     *
     * @param    array  $task
     *
     * @return   object
     */
    public function setTask($task)
    {
        $this->task = $task;

        return $this;
    }

    /**
     * @brief    increase task depth
     *
     * @param    int    $step 
     *
     * @return   object
     */
    public function increaseTaskDepth($step = 1)
    {
        !is_int($step) && $step = 1;
        empty($this->task['depth']) && $this->task['depth'] = 0;
        $this->task['depth'] += $step;

        return $this;
    }

    /**
     * @brief    check whether task depth exceed max depth or not 
     *
     * @param    string  $sub_url
     *
     * @return   boolean
     */
    public function checkWhetherTaskDepthExceedMaxDepth($sub_url)
    {
        $max_depth = Configurator::get('globalConfig/main/task/max_depth');
        !Tool::checkIsIntOrZero($max_depth) && $max_depth = 1;
        empty($this->task['depth']) && $this->task['depth'] = 0;

        if($max_depth > 0 && $this->task['depth'] >= $max_depth) 
        {
            /*
             *Logger::error(Tool::replacePlaceHolder($this->langConfig['task_exceed_max_depth'], [
             *    'max_depth' => $max_depth,
             *    'sub_url'   => $sub_url,
             *]));
             */

            return true; 
        }

        return false;
    }

    /**
     * @brief    extract target fields according to task rule
     *
     * @param    string     $source_data
     * @param    array      $rule
     * @param    string     $rule_name
     *
     * @return   array
     */
    public function extractField($source_data = '', $rule = [], $rule_name = '')
    {
        if(empty($source_data) || !is_string($source_data)) return [];

        if(empty($rule) || !is_array($rule))
        {
            $rule = !empty($this->task['rule']) ? $this->task['rule'] : [];
        }

        if(empty($rule_name) || !is_string($rule_name))
        {
            if(!empty($this->task['rule_name'])){
                $rule_name = $this->task['rule_name'];
            }elseif(!empty($this->task['url'])){
                //just to keep back compatible
                $context = $this->task['context'] ?? [];
                if(isset($context['force_use_md5url_if_rulename_empty']) && true === $context['force_use_md5url_if_rulename_empty']){
                    $rule_name = md5($this->task['url']);
                }else{
                    $rule_name = md5($this->task['id']);
                }
            }else{
                $rule_name = 'default';
            }
        }

        $fields = $this->extractor->setHtml($source_data)->setRule($rule)->extract();

        return [$rule_name => $fields];
    }

    /**
     * @brief    extract sub url
     *
     * @param    string  $source_data
     *
     * @return   array
     */
    public function extractSubUrl($source_data)
    {
        if(empty($source_data)) return [];

        $rule = ['sub_url' => array('a', 'href')];
        $option = [
            'html'  => $source_data,
            'rule'  => $rule,
        ];
        $urls = $this->extractor->set($option)->extract();
        $urls = Tool::rebuildArrayByOneField($urls, 'sub_url');

        if(empty($urls)) return [];

        array_walk($urls, function($sub_url, $k)use(&$urls){
            $new_sub_url = $this->rebuildSubUrl($sub_url);
            if(empty($new_sub_url))  unset($urls[$k]);
            if(!empty($new_sub_url)) $urls[$k] = $new_sub_url;
        });

        if(empty($urls)) return [];

        $urls = array_unique($urls);

        return $urls;
    }

    /**
     * @brief    rebuild sub url  
     *
     * @param    string  $url
     *
     * @return   string | boolean
     */
    public function rebuildSubUrl($url)
    {
        if(empty($url)) return false;

        $url = str_replace(array('"', "'", '&amp;'), array('', '', '&'), trim($url));
        $task_url = trim($this->task['url']);

        if(preg_match("@^(mailto|javascript:|#|'|\")@i", $url))
        {
            return false;
        }

        //exlcude tags which parse as failed
        if(substr($url, 0, 3) == '<%=' || substr($url, 0, 1) == '{' || substr($url, 0, 2) == ' {')
        {
            return false;
        }

        $parse_url = @parse_url($task_url);
        if(empty($parse_url['scheme']) || empty($parse_url['host'])) 
        {
            return false;
        }

        //exclude all protocols except for http + https
        if(!in_array($parse_url['scheme'], array('http', 'https')))
        {
            return false;
        }

        $scheme        = $parse_url['scheme'];
        $domain        = $parse_url['host'];
        $path          = empty($parse_url['path']) ? '' : $parse_url['path'];
        $base_url_path = $domain . $path;
        $base_url_path = preg_replace("/\/([^\/]*)\.(.*)$/", '/', $base_url_path);
        $base_url_path = preg_replace("/\/$/", '', $base_url_path);
        $i             = $path_step = 0;
        $dstr          = $pstr      = '';
        $pos           = strpos($url, '#');

        //drop all the other string follow #, and including #.
        $pos > 0 && $url = substr($url, 0, $pos);

        if(substr($url, 0, 2) == '//') {
            $url = preg_replace('/^\/\//iu', '', $url);
        } elseif($url[0] == '/') {
            $url = $domain . $url;
        } elseif($url[0] == '.') {
            if(!isset($url[2])) return false;
            $urls = explode('/',$url);
            foreach($urls as $u)
            {
                if($u == '..') {
                    $path_step++;
                } elseif($i < count($urls) - 1) {
                    $dstr .= $urls[$i] . '/';
                } else {
                    $dstr .= $urls[$i];
                }
                $i++;
            }
            $urls = explode('/',$base_url_path);

            if(count($urls) <= $path_step) return false;

            $pstr = '';
            for($i = 0; $i < count($urls) - $path_step; $i++)
            { 
                $pstr .= $urls[$i].'/'; 
            }
            $url = $pstr . $dstr;
        }
        else 
        {
            if(strtolower(substr($url, 0, 7)) == 'http://'){
                $url    = preg_replace('#^http://#i', '', $url);
                $scheme = 'http';
            } elseif( strtolower(substr($url, 0, 8)) == 'https://'){
                $url = preg_replace('#^https://#i','',$url);
                $scheme = "https";
            } else {
                $arr = array_filter(explode("/", $base_url_path));
                $base_url_path = implode("/", $arr);
                $url = $base_url_path . '/' . $url;
            }
        }

        $url = preg_replace('/\/{1,}/i', '/', $url);
        $url = $scheme . '://' . $url;
        $parse_url = @parse_url($url);
        $domain    = empty($parse_url['host']) ? $domain : $parse_url['host'];

        //match the given domains
        if(!empty($parse_url['host']))
        {
            $domains = Configurator::get('globalConfig/main/task/limit_domains');

            if(empty($domains) || $domains[0] == '*')   return $url;

            if(!in_array($parse_url['host'], $domains)) return false;
        }

        return $url;
    }

    /**
     * @brief    add sub task     
     *
     * @param    string  $sub_url
     *
     * @return   string
     */
    public function addSubTask($sub_url)
    {
        //check whether url is invald 
        if(empty(Tool::checkUrl($sub_url))) return 0;

        //unset redundant fields
        unset($this->task['rule_name'], $this->task['rule']);

        //create sub task
        $task_id = $this->newTaskMan()->createTask([
            'url'     => $sub_url,
            'method'  => $this->task['method'],
            'refer'   => $this->task['url'],
            'context' => $this->task['context'],
            'depth'   => $this->task['depth'],
        ]);

        return $task_id;
    }

}



