<?php
namespace suda\framework\context;

use suda\framework\Config;
use suda\framework\Debugger;
use suda\framework\loader\Loader;

/**
 * PHP环境
 */
class PHPContext
{
    /**
     * PHP自动加载
     *
     * @var Loader
     */
    protected $loader;

    /**
     * 全局配置
     *
     * @var Config
     */
    protected $config;


    /**
     * 创建PHP环境
     *
     * @param Config $config
     * @param Loader $loader
     */
    public function __construct(Config $config, Loader $loader)
    {
        $this->loader = $loader;
        $this->config = $config;
    }

    /**
     * 获取加载器
     *
     * @return Loader
     */
    public function loader():Loader
    {
        return $this->loader;
    }


    /**
     * 获取配置
     *
     * @return Config
     */
    public function config():Config
    {
        return $this->config;
    }


    /**
     * 获取配置信息
     *
     * @param string $name
     * @param mixed $default
     * @return mixed
     */
    public function conf(string $name, $default = null)
    {
        return $this->config->get($name, $default);
    }

    /**
     * Get PHP自动加载
     *
     * @return  Loader
     */
    public function getLoader()
    {
        return $this->loader;
    }

    /**
     * Set PHP自动加载
     *
     * @param Loader $loader  PHP自动加载
     *
     * @return  self
     */
    public function setLoader(Loader $loader)
    {
        $this->loader = $loader;

        return $this;
    }

    /**
     * Get 全局配置
     *
     * @return  Config
     */
    public function getConfig()
    {
        return $this->config;
    }

    /**
     * Set 全局配置
     *
     * @param Config $config  全局配置
     *
     * @return  self
     */
    public function setConfig(Config $config)
    {
        $this->config = $config;

        return $this;
    }
}
