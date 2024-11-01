<?php
/**
 * @script   en.php
 * @brief    English Language Package - support placeholder, like: %item%
 * @author   blogdaren<blogdaren@163.com>
 * @link     http://www.phpcreeper.com
 * @create   2019-09-20
 */

return [
    'queue_start_url_invalid'       => "Task Init: detect initial task url invalid, please make sure it configured correctly.",
    'queue_url_invalid'             => "Task Make: detect the task url invalid, please make sure it configured correctly",
    'queue_full'                    => "Task Monitor: task queue length has exceeded the max number given, the total backlog of task is %task_number%",
    'queue_empty'                   => "Task Monitor: task queue is empty, continue to consume task in %crawl_interval% seconds at intervals.",
    'queue_push_task'               => "Task Make: detect there comes a new task and push it into the task queue: %task_url%.",
    'queue_push_exception_task'     => "Task Make: detect there comes the task abnormal and drop it: %task_url%.",
    'queue_duplicate_task'          => "Task Make: detect there comes the task repeated and drop it: %task_url%.",
    'queue_inactive_task'           => "Task Make: detect there comes the task inactive and drop it: %task_url%.",
    'downloader_connect_success'    => "Network Inspect: detect that one asynchronous connection from downloader to parser is established successfully.",
    'downloader_connect_failed'     => "Network Anomaly: detect that one asynchronous connection from downloader to parser is closed by force, try to reconnect in %reconnect_time% seconds.",
    'downloader_task_args_invalid'  => "Params Check: detect that the task params is empty when read cache, it's likely that you forget to give a valid task_url.", 
    'downloader_cache_disabled'     => "Cache Check: detect that the task cache id disabled, task_id: %task_id%", 
    'downloader_cache_enabled'      => "Cache Check: detect that the task cache id enabled, task_id: %task_id%", 
    'downloader_create_cache_failed'=> "Access Check: detect that the cache direcory couldn't be written, please verify write permissions are granted.", 
    'downloader_read_from_cache'    => "Task Download: the downloader hit the task cache successfully, task_id: %task_id%【cache_path: %cache_path%】", 
    'downloader_write_into_cache'   => "Task Download: the downloader write the task cache successfully, task_id: %task_id%【cache_path: %cache_path%】", 
    'downloader_rebuild_task_null'  => "Params Check: detect that the task params is empty when download data, it's likely you forget to give a valid task_url.", 
    'downloader_get_one_task'       => "Task Consume: the downloader successfully get one task: %task_url%.", 
    'downloader_download_task_yes'  => "Task Download: detect that the task is successfully downloaded.", 
    'downloader_download_task_no'   => "Task Download: detect that the task is failed to download.", 
    'downloader_forward_args'       => "Params Check: detect that the param with forward(\$task) is invalid, the correct usage is: \$task = ['task' => [], 'download_data' => '']", 
    'downloader_buffer_full'        => "Netflow Control: detect that the consumption capacity of the opposite end has been weaker than the production capacity of this side, it is about to limit the traffic until the strong consumption capacity of the opposite end is improved.",
    'downloader_buffer_drain'       => "Netflow Control: detect that the send buffer space is rich, ehn continue to send data to the opposite end.",
    'downloader_forward_data'       => "Async Dispatch: the downloader has successfully distributed the downloaded source data to the remote parser server.",
    'downloader_close_connection'   => "Network Inspect: detect that the request number of current connection has exceeded the maximum number of requests.",
    'downloader_connect_error'      => "Network Inspect: failed to establish one asynchronous connection between the Downloader and Parser, please verify that the Parser address is configured correctly.",
    'downloader_got_replay_null'    => "Receive Feedback: detect that the parser response is empty, it seems to be a mismatch between the socket protocol settings of the Downloader and Parser.",
    'downloader_lost_connections'   => "Network Inspect: detect that the downloader does not get the task connection object available.",
    'downloader_lost_channel'       => "Network Inspect: detect that the downloader does not get the task connection channel available.",
    'downloader_download_filesize_exceed'  => "Task Download: detect that the file size to be downloaded (%file size%Byte) exceeds the preset maximum (%default max file size%Byte), discard task.",
    'http_transfer_exception'       => "Request Anomaly: %exception_msg% (exception_code: %exception_code%) (task_url: %url%)",
    'http_transfer_compress'        => "Compress Transfer: detect that the compression algorithm is enabled: %algorithm%",
    'http_assemble_method'          => "Assemble Package: detect that the assemble package method is enabled: %assemble_method%",
    'parser_close_connection'       => "Network Inspect: detect that one asynchronous connection was closed by force, because the downloader failed to send any requests within the specified time, try to reconnect in %reconnect_time% seconds.",
    'parser_connected_success'      => "Network Inspect: detect that one asynchronous connection from the remote downloader to the local machine was established successfully .",
    'parser_task_success'           => "Parse Package：the connection id is %connection_id%: parse the task package successfully.",
    'parser_task_report'            => "Receive Feedback: the connection id is %connection_id%: process the task package successfully.",
    'parser_detect_url'             => "New Subtask：detect that the parser extract one new subtask and then push into task queue successfully.",
    'task_exceed_max_depth'         => "Crawl Depth：detect that the crawl depth has exceeded the maximum depth, won't push into task queue any more.",
    'ping_from_downloader'          => "Heartbeat Monitor：detect the heartbeat from downloader: the heartbeat interval is %interval% seconds.", 
    'ext_msgpack_not_install'       => "Assemble Package：detect the assemble package method `msgpack` enabled, but the `ext-msgpack` is not installed, so it will switch the assemble method to `json`",
    'invalid_httpclient_object'     => "Danger Action: detect `httpClient`  object invalid, must implement the interface with `HttpClientInterface`",
    'invalid_queueclient_object'    => "Danger Action: detect `queueClient` object invalid, must implement the interface with `BrokerInterface`",
    'work_as_single_worker_mode'    => "Worker Mode：note it works as single-worker mode currently, only Downloader instances allowed to be run",
    'logger_prefix_producer'        => "Producer   | %worker_name% | PID:%process_id% | Process %worker_id%",
    'logger_prefix_downloader'      => "Downloader | %worker_name% | PID:%process_id% | Process %worker_id%",
    'logger_prefix_parser'          => "Parser     | %worker_name% | PID:%process_id% | Process %worker_id%",
    'logger_prefix_server'          => "Server     | %worker_name% | PID:%process_id% | Process %worker_id%",
    'track_request_args'            => "Request Arguments：%request_args%",
    'track_task_package'            => "Task Package：%task_package%",
    'redis_server_error'            => "Service Inspect：%error_msg%, it depends on the [redis-server] service when running as multi-worker mode, please check whether the [redis-server] service is started or whether the firewall permits the service port, the current process will exit automatically after 10 seconds of sleep and resume running after restart.",
    'ext_redis_class_not_found'     => "Server Inspect: the class Redis was not found because the redis extension was not found, please install the official PHP extension phpredis yourself, the current process will exit after 10 seconds of sleep and resume running after restart.",
    'http_method_head_error'        => "HTTP HEAD: %error_msg%",
    'headless_browser_enabled'            => "Headless Browser: headless browser【%bin%】is configured as enabled.",
    'headless_browser_disabled'           => "Headless Browser: headless browser is configured as disabled.",
    'headless_browser_binary_not_found'   => "Headless Browser：headless browser binary file (%bin%) not found, please install your own headless browser.",
    'headless_browser_exception'          => "Headless Browser：",

    //more fields to be configured......
];


