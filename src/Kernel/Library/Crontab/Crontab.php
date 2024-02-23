<?php
/**
 * 特别说明:
 * 
 * 1. 本实现是将walkor大大的原始实现完全照搬而来: https://github.com/walkor/crontab
 * 2. 考虑到业务广泛使用，所以决定脱离本库之composer依赖，即采取揉进爬山虎内核策略
 * 3. 注意：本脚本含有两个类: class Crontab 和 class Parser
 *
 * This file is part of workerman.
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the MIT-LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @author    walkor<walkor@workerman.net>
 * @copyright walkor<walkor@workerman.net>
 * @link      http://www.workerman.net/
 * @license   http://www.opensource.org/licenses/mit-license.php MIT License
 */

namespace PHPCreeper\Kernel\Library\Crontab;

use PHPCreeper\Timer;

/**
 * Class Crontab
 * @package Workerman\Crontab
 */
class Crontab
{
    /**
     * @var string
     */
    protected $_rule;

    /**
     * @var callable
     */
    protected $_callback;

    /**
     * @var string
     */
    protected $_name;

    /**
     * @var int
     */
    protected $_id;

    /**
     * @var array
     */
    protected static $_instances = [];

    /**
     * Crontab constructor.
     * @param   string    $rule
     * @param   callable  $callback
     * @param   string    $name
     */
    public function __construct($rule, $callback, $name = '')
    {
        $this->_rule = $rule;
        $this->_callback = $callback;
        $this->_name = $name;
        $this->_id = static::createId();
        static::$_instances[$this->_id] = $this;
        static::tryInit();
    }

    /**
     * @return string
     */
    public function getRule()
    {
        return $this->_rule;
    }

    /**
     * @return callable
     */
    public function getCallback()
    {
        return $this->_callback;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->_name;
    }

    /**
     * @return int
     */
    public function getId()
    {
        return $this->_id;
    }

    /**
     * @return bool
     */
    public function destroy()
    {
        return static::remove($this->_id);
    }

    /**
     * @return array
     */
    public static function getAll()
    {
        return static::$_instances;
    }

    /**
     * @param $id
     * @return bool
     */
    public static function remove($id)
    {
        if ($id instanceof Crontab) {
            $id = $id->getId();
        }
        if (!isset(static::$_instances[$id])) {
            return false;
        }
        unset(static::$_instances[$id]);
        return true;
    }

    /**
     * @return int
     */
    protected static function createId()
    {
        static $id = 0;
        return ++$id;
    }

    /**
     * tryInit
     */
    protected static function tryInit()
    {
        static $inited = false;
        if ($inited) {
            return;
        }
        $inited = true;
        $parser = new Parser();
        $callback = function () use ($parser, &$callback) {
            foreach (static::$_instances as $crontab) {
                $rule = $crontab->getRule();
                $cb = $crontab->getCallback();
                if (!$cb || !$rule) {
                    continue;
                }
                $times = $parser->parse($rule);
                $now = time();
                foreach ($times as $time) {
                    $t = $time-$now;
                    if ($t <= 0) {
                        $t = 0.000001;
                    }
                    Timer::add($t, $cb, null, false);
                }
            }
            Timer::add(60 - time()%60, $callback, null, false);
        };

        $next_time = time()%60;
        if ($next_time == 0) {
            $next_time = 0.00001;
        } else {
            $next_time = 60 - $next_time;
        }
        Timer::add($next_time, $callback, null, false);
    }

}


//crontab parser listed as below
//crontab parser listed as below
//crontab parser listed as below

/**
 * @author:  Jan Konieczny <jkonieczny@gmail.com>, group@hyperf.io
 * @license: http://www.gnu.org/licenses/
 * @license: https://github.com/hyperf/hyperf/blob/master/LICENSE
 *
 *  This is a simple script to parse crontab syntax to get the execution time
 *
 *  Eg.:   $timestamp = Crontab::parse('12 * * * 1-5');
 *
 *
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation, either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  You should have received a copy of the GNU General Public License
 *  along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

/**
 * Provides basic cron syntax parsing functionality
 *
 * @author:  Jan Konieczny <jkonieczny@gmail.com>, group@hyperf.io
 * @license: http://www.gnu.org/licenses/
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */


/**
 * Class Parser
 * @package Workerman\Crontab
 */
class Parser
{
    /**
     *  Finds next execution time(stamp) parsin crontab syntax.
     *
     * @param string $crontab_string :
     *   0    1    2    3    4    5
     *   *    *    *    *    *    *
     *   -    -    -    -    -    -
     *   |    |    |    |    |    |
     *   |    |    |    |    |    +----- day of week (0 - 6) (Sunday=0)
     *   |    |    |    |    +----- month (1 - 12)
     *   |    |    |    +------- day of month (1 - 31)
     *   |    |    +--------- hour (0 - 23)
     *   |    +----------- min (0 - 59)
     *   +------------- sec (0-59)
     *
     * @param null|int $start_time
     * @throws \InvalidArgumentException
     * @return int[]
     */
    public function parse($crontab_string, $start_time = null)
    {
        if (! $this->isValid($crontab_string)) {
            throw new \InvalidArgumentException('Invalid cron string: ' . $crontab_string);
        }
        $start_time = $start_time ? $start_time : time();
        $date = $this->parseDate($crontab_string);
        if (in_array((int) date('i', $start_time), $date['minutes'])
            && in_array((int) date('G', $start_time), $date['hours'])
            && in_array((int) date('j', $start_time), $date['day'])
            && in_array((int) date('w', $start_time), $date['week'])
            && in_array((int) date('n', $start_time), $date['month'])
        ) {
            $result = [];
            foreach ($date['second'] as $second) {
                $result[] = $start_time + $second;
            }
            return $result;
        }
        return [];
    }

    public function isValid(string $crontab_string): bool
    {
        if (! preg_match('/^((\*(\/[0-9]+)?)|[0-9\-\,\/]+)\s+((\*(\/[0-9]+)?)|[0-9\-\,\/]+)\s+((\*(\/[0-9]+)?)|[0-9\-\,\/]+)\s+((\*(\/[0-9]+)?)|[0-9\-\,\/]+)\s+((\*(\/[0-9]+)?)|[0-9\-\,\/]+)\s+((\*(\/[0-9]+)?)|[0-9\-\,\/]+)$/i', trim($crontab_string))) {
            if (! preg_match('/^((\*(\/[0-9]+)?)|[0-9\-\,\/]+)\s+((\*(\/[0-9]+)?)|[0-9\-\,\/]+)\s+((\*(\/[0-9]+)?)|[0-9\-\,\/]+)\s+((\*(\/[0-9]+)?)|[0-9\-\,\/]+)\s+((\*(\/[0-9]+)?)|[0-9\-\,\/]+)$/i', trim($crontab_string))) {
                return false;
            }
        }
        return true;
    }

    /**
     * Parse each segment of crontab string.
     */
    protected function parseSegment(string $string, int $min, int $max, int $start = null)
    {
        if ($start === null || $start < $min) {
            $start = $min;
        }
        $result = [];
        if ($string === '*') {
            for ($i = $start; $i <= $max; ++$i) {
                $result[] = $i;
            }
        } elseif (strpos($string, ',') !== false) {
            $exploded = explode(',', $string);
            foreach ($exploded as $value) {
                if (strpos($value, '/') !== false || strpos($string, '-') !== false) {
                    $result = array_merge($result, $this->parseSegment($value, $min, $max, $start));
                    continue;
                }
                if (trim($value) === '' || ! $this->between((int) $value, (int) ($min > $start ? $min : $start), (int) $max)) {
                    continue;
                }
                $result[] = (int) $value;
            }
        } elseif (strpos($string, '/') !== false) {
            $exploded = explode('/', $string);
            if (strpos($exploded[0], '-') !== false) {
                [$nMin, $nMax] = explode('-', $exploded[0]);
                $nMin > $min && $min = (int) $nMin;
                $nMax < $max && $max = (int) $nMax;
            }
            $start < $min && $start = $min;
            for ($i = $start; $i <= $max;) {
                $result[] = $i;
                $i += $exploded[1];
            }
        } elseif (strpos($string, '-') !== false) {
            $result = array_merge($result, $this->parseSegment($string . '/1', $min, $max, $start));
        } elseif ($this->between((int) $string, $min > $start ? $min : $start, $max)) {
            $result[] = (int) $string;
        }
        return $result;
    }

    /**
     * Determire if the $value is between in $min and $max ?
     */
    private function between(int $value, int $min, int $max): bool
    {
        return $value >= $min && $value <= $max;
    }


    private function parseDate(string $crontab_string): array
    {
        $cron = preg_split('/[\\s]+/i', trim($crontab_string));
        if (count($cron) == 6) {
            $date = [
                'second'  => $this->parseSegment($cron[0], 0, 59),
                'minutes' => $this->parseSegment($cron[1], 0, 59),
                'hours'   => $this->parseSegment($cron[2], 0, 23),
                'day'     => $this->parseSegment($cron[3], 1, 31),
                'month'   => $this->parseSegment($cron[4], 1, 12),
                'week'    => $this->parseSegment($cron[5], 0, 6),
            ];
        } else {
            $date = [
                'second'  => [1 => 0],
                'minutes' => $this->parseSegment($cron[0], 0, 59),
                'hours'   => $this->parseSegment($cron[1], 0, 23),
                'day'     => $this->parseSegment($cron[2], 1, 31),
                'month'   => $this->parseSegment($cron[3], 1, 12),
                'week'    => $this->parseSegment($cron[4], 0, 6),
            ];
        }
        return $date;
    }
}
