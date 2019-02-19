<?php
namespace suda\framework\server;

use suda\framework\server\config\PathResolver;
use suda\framework\server\config\ContentLoader;
use suda\component\arrayobject\ArrayDotAccess;

/**
 * 服务器参数处理
 */
class Config
{

    /**
     * 配置数组
     *
     * @var array
     */
    public $config;

    /**
     * 静态实例
     *
     * @var self
     */
    protected static $instance;


    protected function __construct()
    {
        $this->config = [];
    }

    /**
     * 返回实例
     *
     * @return self
     */
    public static function instance()
    {
        if (isset(static::$instance)) {
            return static::$instance;
        }
        return static::$instance = new static;
    }

    /**
     * 加载配置
     *
     * @param string $path
     * @param array $extra
     * @return self
     */
    public function load(string $path, array $extra = null)
    {
        $data = $this->loadConfig($path, $extra ?? $this->config);
        if ($data) {
            $this->assign($data);
        }
        return $this;
    }

    public function exist(string $path):bool
    {
        return PathResolver::resolve($path) !== null;
    }

    public function assign(array $config)
    {
        return $this->config = array_merge($this->config, $config);
    }

    public function get(string $name = null, $default = null)
    {
        if (null === $name) {
            return $this->config;
        }
        return ArrayDotAccess::get($this->config, $name, $default);
    }

    public function set(string $name, $value, $combine = null)
    {
        return ArrayDotAccess::set($this->config, $name, $value, $combine);
    }

    public function has(string $name)
    {
        return ArrayDotAccess::exist($this->config, $name);
    }

    public function loadConfig(string $path, array $extra = []):?array
    {
        if (!file_exists($path)) {
            $path = PathResolver::resolve($path);
        }
        if ($path) {
            $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            if (in_array($ext, ['yaml','yml','json','php','ini'])) {
                return ContentLoader::{'load'.ucfirst($ext)}($path, $extra);
            }
            return ContentLoader::loadJson($path, $extra);
        }
        return null;
    }
}