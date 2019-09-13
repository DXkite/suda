<?php

namespace suda\application\loader;

use suda\application\Module;
use suda\framework\loader\Loader;
use suda\application\database\Database;
use suda\database\exception\SQLException;
use suda\framework\filesystem\FileSystem;

/**
 * 应用程序
 */
class ApplicationLoader extends ApplicationModuleLoader
{

    const CACHE_ROUTE = 'application-route';
    const CACHE_ROUTE_RUNNABLE = 'application-route-runnable';

    /**
     * 加载额外vendor
     */
    public function loadVendorIfExist()
    {
        $vendorAutoload = $this->application->getPath() . '/vendor/autoload.php';
        if (FileSystem::exist($vendorAutoload)) {
            Loader::requireOnce($vendorAutoload);
        }
    }

    /**
     * 加载APP
     */
    public function load()
    {
        $this->loadVendorIfExist();
        $this->loadGlobalConfig();
        $this->loadModule();
        LanguageLoader::load($this->application);
    }

    /**
     * 加载全局配置
     */
    public function loadGlobalConfig()
    {
        $resource = $this->application->getResource();
        if ($configPath = $resource->getConfigResourcePath('config/config')) {
            $this->application->getConfig()->load($configPath);
        }
        if ($listenerPath = $resource->getConfigResourcePath('config/listener')) {
            $this->application->loadEvent($listenerPath);
        }
    }

    /**
     * 加载路由
     */
    public function loadRoute()
    {
        $name = 'application-route';
        $this->application->debug()->time($name);
        if (static::isDebug()) {
            $this->loadRouteFromModules();
            if ($this->application->getRoute()->isContainClosure()) {
                $this->application->debug()->warning('route contain closure, route prepare cannot be cacheables');
            } else {
                $this->application->cache()->set(ApplicationLoader::CACHE_ROUTE, $this->application->getRoute()->getRouteCollection());
                $this->application->cache()->set(ApplicationLoader::CACHE_ROUTE_RUNNABLE, $this->application->getRoute()->getRunnable());
            }
        } elseif ($this->routeCacheAvailable()) {
            $route = $this->application->cache()->get(ApplicationLoader::CACHE_ROUTE);
            $runnable = $this->application->cache()->get(ApplicationLoader::CACHE_ROUTE_RUNNABLE);
            $this->application->getRoute()->setRouteCollection($route);
            $this->application->getRoute()->setRunnable($runnable);
            $this->application->debug()->info('load route from cache');
        } else {
            $this->loadRouteFromModules();
        }
        $this->application->debug()->timeEnd($name);
    }

    /**
     * 从模块中加载路由
     */
    private function loadRouteFromModules()
    {
        foreach ($this->application->getModules() as $name => $module) {
            if ($module->getStatus() === Module::REACHABLE) {
                call_user_func([$this->moduleLoader[$name], 'toReachable']);
            }
        }
    }

    /**
     * @return bool
     */
    private function routeCacheAvailable()
    {
        return $this->application->cache()->has(ApplicationLoader::CACHE_ROUTE)
            && $this->application->cache()->has(ApplicationLoader::CACHE_ROUTE_RUNNABLE);
    }

    /**
     * 加载数据源
     *
     * @throws SQLException
     */
    public function loadDataSource()
    {
        Database::loadApplication($this->application);
        $dataSource = Database::getDefaultDataSource();
        $this->application->setDataSource($dataSource);
    }
}
