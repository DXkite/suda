<?php
namespace suda\component\loader;

require_once __DIR__ .'/Path.php';
require_once __DIR__ .'/PathTrait.php';
require_once __DIR__ .'/PathInterface.php';
require_once __DIR__ .'/IncludeManager.php';

use suda\component\loader\IncludeManager;


/**
 * 类自动加载器
 */
class Loader extends IncludeManager
{
    /**
     * 注册加载器
     *
     * @return void
     */
    public function register()
    {
        // 注册加载器
        spl_autoload_register(array($this, 'classLoader'));
    }


    /**
     * 自动类加载器
     *
     * @param string $className
     * @return void
     */
    public function classLoader(string $className)
    {
        if ($path = $this->getClassPath($className)) {
            if (!class_exists($className, false)) {
                @require_once $path;
            }
        }
    }

    /**
     * 获取类路径
     *
     * @param string $className
     * @return string|null
     */
    public  function getClassPath(string $className):?string
    {
        // 搜索路径
        foreach ($this->includePath as $includeNamespace => $includePaths) {
            foreach ($includePaths as $includePath) {
                if ($path = $this->getClassPathByName($includeNamespace, $includePath, $className)) {
                    return $path;
                } elseif ($path = $this->getClassPathByAlias($includePath, $className)) {
                    return $path;
                }
            }
        }
        return null;
    }

    /**
     * 根据类别名获取路径
     *
     * @param string $includePath
     * @param string $className
     * @return string|null
     */
    protected  function getClassPathByAlias(string $includePath, string $className):?string
    {
        $namepath=static::formatSeparator($className);
        $className=static::realName($className);
        foreach ($this->namespace as $namespace) {
            $path = $includePath.DIRECTORY_SEPARATOR.$namespace.DIRECTORY_SEPARATOR.$namepath.'.php';
            if ($path = static::realPath($path)) {
                // 精简类名
                if (!class_exists($className, false)) {
                    class_alias($namespace.'\\'.$className, $className);
                }
                return $path;
            }
        }
        return null;
    }

    /**
     * 根据类名获取路径
     *
     * @param string $includeNamespace
     * @param string $includePath
     * @param string $className
     * @return string|null
     */
    protected  function getClassPathByName(string $includeNamespace, string $includePath, string $className):?string
    {
        if (is_numeric($includeNamespace)) {
            $path = $includePath.DIRECTORY_SEPARATOR.static::formatSeparator($className).'.php';
        } else {
            $nl = strlen($includeNamespace);
            if (substr(static::realName($className), 0, $nl) === $includeNamespace) {
                $path=$includePath.DIRECTORY_SEPARATOR.static::formatSeparator(substr($className, $nl)).'.php';
            } else {
                $path=$includePath.DIRECTORY_SEPARATOR.static::formatSeparator($className).'.php';
            }
        }
        if ($path = static::realPath($path)) {
            return $path;
        }
        return null;
    }
}
