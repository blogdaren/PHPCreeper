<?php
/**
 * @script  Autoloader.php
 * @brief   This file is part of PHPCreeper
 * @author  blogdaren<blogdaren@163.com>
 * @version 1.0.0
 * @modify  2019-11-04
 */

namespace PHPCreeper;

class Autoloader
{
    /**
     * autoload root path
     *
     * @var string
     */
    protected static $_autoloadRootPath = '';

    /**
     * set autoload root path
     *
     * @param   string  $root_path
     *
     * @return  void
     */
    public static function setRootPath($root_path)
    {
        self::$_autoloadRootPath = $root_path;
    }

    /**
     * load files by namespace
     *
     * @param   string  $name
     *
     * @return  boolean
     */
    public static function loadByNamespace($name)
    {
        $class_path = str_replace('\\', DIRECTORY_SEPARATOR, $name);

        if(strpos($name, 'PHPCreeper\\') === 0) 
        {
            $class_file = __DIR__ . substr($class_path, strlen('PHPCreeper')) . '.php';
        } 
        else 
        {
            if(self::$_autoloadRootPath) 
            {
                $class_file = self::$_autoloadRootPath . DIRECTORY_SEPARATOR . $class_path . '.php';
            }

            if(empty($class_file) || !is_file($class_file)) 
            {
                $class_file = __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . "$class_path.php";
            }
        }

        defined('AUTOLOADER_DEBUG') && pprint([
            '__CLASS__'  => __CLASS__, 
            'namespace'  => $name, 
            'class_file' => $class_file,
        ]);

        if(is_file($class_file)) 
        {
            require_once($class_file);
            if(class_exists($name, false)) return true;
        }

        return false;
    }
}

spl_autoload_register('\PHPCreeper\Autoloader::loadByNamespace');
