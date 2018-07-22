<?php
/**
 * Suda FrameWork
 *
 * An open source application development framework for PHP 7.0.0 or newer
 *
 * Copyright (c)  2017 DXkite
 *
 * @category   PHP FrameWork
 * @package    Suda
 * @copyright  Copyright (c) DXkite
 * @license    MIT
 * @link       https://github.com/DXkite/suda
 * @version    since 1.2.4
 */
namespace suda\core;

/**
 * 自动加载控制器
 * 控制按照一定的规则自动加载文件或者类
 */
class Autoloader
{
    /**
     * 默认命名空间
     *
     * @var array
     */
    protected static $namespace=['suda\\core'];
    protected static $include_path=[];

    /**
     * 将JAVA，路径分割转换为PHP分割符
     *
     * @param string $name 类名
     * @return string 真实分隔符
     */
    public static function realName(string $name)
    {
        return preg_replace('/[.\/\\\\]+/', '\\', $name);
    }
    
    public static function realPath(string $name)
    {
        return preg_replace('/[\\\\\/]+/', DIRECTORY_SEPARATOR, $name);
    }

    public static function register()
    {
        spl_autoload_register(array('suda\\core\\Autoloader', 'classLoader'));
        self::addIncludePath(dirname(dirname(__DIR__)));
    }

    public static function import(string $filename)
    {
        if (self::file_exists($filename)) {
            require_once $filename;
            return $filename;
        } else {
            foreach (self::$include_path as $include_path) {
                if (self::file_exists($path=$include_path.DIRECTORY_SEPARATOR.$filename)) {
                    require_once $path;
                    return $path;
                }
            }
        }
    }

    public static function classLoader(string $classname)
    {
        if ($path=static::getClassPath($classname)) {
            if (!class_exists($classname, false)) {
                require_once $path;
            }
        }
    }

    public static function getClassPath(string $className)
    {
        $namepath=self::realPath($className);
        $classname=self::realName($className);
        // 搜索路径
        foreach (self::$include_path as $include_namespace => $include_path) {
            if (!is_numeric($include_namespace) && preg_match('/^'.preg_quote(static::realName($include_namespace), '/').'(.+)$/', $classname, $match)) {
                $path=preg_replace('/[\\\\\\/]+/', DIRECTORY_SEPARATOR, $include_path.DIRECTORY_SEPARATOR.static::realPath($match[1]).'.php');
            } else {
                $path=preg_replace('/[\\\\\\/]+/', DIRECTORY_SEPARATOR, $include_path.DIRECTORY_SEPARATOR.$namepath.'.php');
            }
            if (self::file_exists($path)) {
                return $path;
            } else {
                // 添加命名空间
                foreach (self::$namespace as $namespace) {
                    $path=preg_replace('/[\\\\]+/', DIRECTORY_SEPARATOR, $include_path.DIRECTORY_SEPARATOR.$namespace.DIRECTORY_SEPARATOR.$namepath.'.php');
                    if (self::file_exists($path)) {
                        // 精简类名
                        if (!class_exists($classname, false)) {
                            class_alias($namespace.'\\'.$classname, $classname);
                        }
                        return $path;
                    }
                }
            }
        }
        return false;
    }

    public static function addIncludePath(string $path, string $namespace=null)
    {
        if (realpath($path)) {
            if (empty($namespace) && !in_array($path, self::$include_path)) {
                self::$include_path[]=$path;
            } elseif (array_search($path, self::$include_path) != $namespace) {
                self::$include_path[$namespace]=$path;
            }
        }
    }

    public static function getIncludePath()
    {
        return self::$include_path;
    }

    public static function getNamespace()
    {
        return self::$namespace;
    }

    public static function setNamespace(string $namespace)
    {
        if (!in_array($namespace, self::$namespace)) {
            self::$namespace[]=$namespace;
        }
    }

    private static function file_exists($name):bool
    {
        if (file_exists($name) && is_file($name) && $real=realpath($name)) {
            if (basename($real) === basename($name)) {
                return true;
            }
        }
        return false;
    }
}
