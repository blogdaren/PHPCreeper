<?php
/**
 * @script   Crontab.php
 * @brief    This file is part of PHPCreeper
 * @author   blogdaren<blogdaren@163.com>
 * @link     http://www.phpcreeper.com
 * @create   2022-08-16
 */

namespace PHPCreeper;

use PHPCreeper\Kernel\Library\Crontab\Crontab as Crontable;

class Crontab extends Crontable
{
    /**
     *
     * 秒  分  时  日  月  周
     * 0   1   2   3   4   5
     * |   |   |   |   |   |
     * |   |   |   |   |   +------ day of week (0 - 6) (Sunday=0)
     * |   |   |   |   +------ month (1 - 12)
     * |   |   |   +-------- day of month (1 - 31)
     * |   |   +---------- hour (0 - 23)
     * |   +------------ min (0 - 59)
     * +-------------- sec (0-59)[可省略，如果没有0位,则最小时间粒度是分钟]
     *
     */
}


