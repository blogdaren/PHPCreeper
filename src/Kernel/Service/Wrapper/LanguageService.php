<?php
/**
 * @script   LanguageService.php
 * @brief    This file is part of PHPCreeper
 * @author   blogdaren<blogdaren@163.com>
 * @version  1.0.0
 * @modify   2019-11-06
 */

namespace PHPCreeper\Kernel\Service\Wrapper;

class LanguageService
{
    /**
     * language config
     *
     * @var array
     */
    static private $_lang_config = [];

    /**
     * @brief    load  language 
     *
     * @param    string  $type
     *
     * @return   array
     */
    static public function load($type = 'zh')
    {
        if(empty(self::$_lang_config))
        {
            (empty($type) || !is_string($type)) && $type = 'zh';
            $config_file = dirname(dirname(dirname(__FILE__))) . '/Language/' . $type . '.php';

            if(!is_file($config_file) || !file_exists($config_file)) 
            {
                $config_file = dirname(dirname(dirname(__FILE__))) . '/Language/zh.php';
            }

            self::$_lang_config = include_once($config_file);
        }

        return self::$_lang_config;
    }

}
