<?php
/**
 * @script   zh.php
 * @brief    中文版语言包 - 支持占位符, 格式如: %item%
 * @author   blogdaren<blogdaren@163.com>
 * @link     http://www.phpcreeper.com
 * @create   2019-09-20
 */

return [
    'queue_start_url_invalid'       => "初始任务: 检测到初始任务URL配置无效, 请检查配置确保正确设置初始任务URL.......",
    'queue_url_invalid'             => "生产任务: 检测到任务URL配置无效, 请确认是否已经正确设置任务URL.......",
    'queue_full'                    => "队列监控: Task 队列任务已经达到阀值%max_number%, 当前积压任务总量为: %task_number%",
    'queue_empty'                   => "队列监控: Task 队列任务已被消费一空, 间隔 %crawl_interval% 秒后继续消费任务.................",
    'queue_push_task'               => "生产任务: 检测到新建任务并压入队: %task_url%",
    'queue_push_exception_task'     => "生产任务: 检测到异常任务并压入队: %task_url%",
    'queue_duplicate_task'          => "生产任务: 检测到重复任务直接丢弃: %task_url%",
    'queue_inactive_task'           => "生产任务: 检测到冻结任务直接丢弃: %task_url%",
    //'network_connect_success'       => "网络巡检：检测到本机至远程Parser的异步连接建立成功, 继续从 Task 队列提取任务.",
    'downloader_connect_success'    => "网络巡检：检测到本机至远程解析器的异步连接建立成功: %downloader_client_address%",
    'downloader_connect_failed'     => "网络异常：检测到本机至远程解析器异步连接已断开: %parser_socket%, %reconnect_time%秒后尝试重连",
    'downloader_task_args_invalid'  => "参数校验: 检测到读取缓存时任务参数为空, 也许是你疏忽忘记了提供有效的 task_url", 
    'downloader_cache_disabled'     => "缓存校验: 检测到已禁用任务缓存, 任务ID: %task_id%", 
    'downloader_cache_enabled'      => "缓存校验: 检测到已启用任务缓存, 任务ID: %task_id%", 
    'downloader_create_cache_failed'=> "权限校验: 检测到下载任务时缓存目录禁写, 请确认是否是否赋予了足够的写权限", 
    'downloader_read_from_cache'    => "下载任务: 下载器命中到任务缓存, 任务ID: %task_id% 【缓存文件: %cache_path%】",
    'downloader_write_into_cache'   => "下载任务: 下载器硬更新任务缓存, 任务ID: %task_id% 【缓存文件: %cache_path%】",
    'downloader_rebuild_task_null'  => "参数校验: 检测到下载任务时任务参数为空, 也许是你疏忽忘记了提供有效的 task_url", 
    'downloader_get_one_task'       => "消费任务: 下载器成功获得一条任务: %task_url%", 
    'downloader_download_task_yes'  => "下载任务: 检测到任务下载成功", 
    'downloader_download_task_no'   => "下载任务: 检测到任务下载失败", 
    'downloader_forward_args'       => "参数校验: 检测到 forward(\$task) 参数无效, 正确姿势：\$task = ['task' => [], 'download_data' => '']", 
    'downloader_buffer_full'        => "流量控制: 检测到对端的消费能力已经弱于本端的生产能力, 即将限流直至对端提高强消费能力.",
    'downloader_buffer_drain'       => "流量控制: 检测到本端发送缓冲区空间富裕，继续向对端推送数据...................",
    'downloader_forward_data'       => "异步分发: 下载器已将下载源数据成功分发至远程 Parser 服务器...................",
    'downloader_close_connection'   => "网络巡检: 检测到当前连接请求数已经超过最大请求%max_request%, 关闭连接并%reconnect_time%秒后尝试重连.",
    'downloader_connect_error'      => "网络巡检: 检测到下载器与解析器建立异步连接失败：请检查Parser地址配置是否正确.",
    'downloader_got_replay_null'    => "收到反馈: 检测到解析器响应为空：原因很可能是下载器和解析器socket通信协议设定不一致.",
    'downloader_lost_connections'   => "网络巡检：检测到下载器获取不到可用的任务连接对象.............................",
    'downloader_lost_channel'       => "网络巡检：检测到下载器获取不到可用的任务连接通道.............................",
    'http_transfer_exception'       => "请求异常: %exception_msg% (exception_code: %exception_code%) (task_url: %url%)",
    'http_transfer_compress'        => "压缩传输: 检测到已启用压缩方式传输数据: 压缩算法%algorithm%...................",
    'http_assemble_method'          => "打包传输: 检测到已启用打包方式传输数据: 打包方式%assemble_method%...................",
    'parser_close_connection'       => "网络巡检: 检测到下载器在指定时间内未发送任何请求, 连接被关闭, %reconnect_time%秒后尝试重连..",
    'parser_connected_success'      => "网络巡检：检测到远程下载器至本机的异步连接建立成功: %parser_server_address%",
    'parser_task_success'           => "解析封包：第%connection_id%号连接:封包解析成功 (%task_id%)",
    'parser_task_report'            => "收到反馈：第%connection_id%号连接:封包处理成功 (%task_id%)",
    'parser_find_url'               => "新子任务：解析器成功提取到新子任务并压入任务队列: %sub_url%",
    'task_exceed_max_depth'         => "采集深度：检测到采集深度已经超过最大深度%max_depth%, 不再入队: %sub_url%",
    'ping_from_downloader'          => "心跳监控：检测到来自下载器的心跳数据包：心跳间隔为 %interval% 秒.......................",
    'ext_msgpack_not_install'       => "数据打包：检测到数据打包方式为msgpack, 但是msgpack扩展并未安装, 尝试切换为json",
    'invalid_httpclient_object'     => "危险行为：检测到非法的 httpClient 对象, 要求必须实现 HttpClientInterface 接口",
    'invalid_queueclient_object'    => "危险行为：检测到非法的 queueClient 对象, 要求必须实现 BrokerInterface 接口",
    'work_as_single_worker_mode'    => "工作模式：当前为单worker工作模式，注意该模式下只能运行下载器实例",
    'logger_prefix_producer'        => "Producer   | %worker_name% | %worker_id%号生产器进程",
    'logger_prefix_downloader'      => "Downloader | %worker_name% | %worker_id%号下载器进程",
    'logger_prefix_parser'          => "Parser     | %worker_name% | %worker_id%号解析器进程",
    'logger_prefix_server'          => "Server     | %worker_name% | %worker_id%号服务器进程",
    'track_request_args'            => "请求参数：%request_args%",
    'track_task_package'            => "任务数据：%task_package%",

    //若干待配字段......
];


