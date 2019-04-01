<?php
namespace suda\application\loader;

use suda\framework\Config;
use suda\framework\Request;
use suda\application\Module;
use suda\framework\Response;
use suda\application\Resource;
use suda\application\Application;
use suda\framework\filesystem\FileSystem;
use suda\application\builder\ApplicationBuilder;
use suda\application\processor\RequestProcessor;
use suda\application\exception\ApplicationException;

/**
 * 应用程序
 */
class ModuleLoader
{
    /**
     * 应用程序
     *
     * @var Application
     */
    protected $application;

    /**
     * 运行环境
     *
     * @var Module
     */
    protected $module;

    /**
     * 模块加载器
     *
     * @param \suda\application\Application $application
     * @param \suda\application\Module $module
     */
    public function __construct(Application $application, Module $module)
    {
        $this->module = $module;
        $this->application = $application;
    }

    public function toLoaded()
    {
        $this->loadVendorIfExist();
        $this->loadEventListener();
        $this->loadShareLibrary();
        $this->loadExtraModuleResourceLibrary();
        $this->application->debug()->info('loaded - '.$this->module->getFullName());
    }

    public function toReacheable()
    {
        $this->loadRoute();
        $this->application->debug()->info('reacheable = '.$this->module->getFullName());
    }

    public function toRunning()
    {
        $this->checkFrameworkVersion();
        $this->checkRequirements();
        $this->loadPrivateLibrary();
        $this->application->setRunning($this->module);
        $this->application->debug()->info('run + '.$this->module->getFullName());
    }

    /**
     * 检查框架依赖
     *
     * @return void
     */
    protected function checkRequirements()
    {
        if ($require = $this->module->getConfig('require')) {
            foreach ($require as $module => $version) {
                $this->assertModuleVersion($module, $version);
            }
        }
    }

    protected function assertModuleVersion(string $module, string $version)
    {
        $target = $this->application->find($module);
        if ($target === null) {
            throw new ApplicationException(sprintf('%s module need %s %s but not exist', $this->module->getFullName(), $module, $version), ApplicationException::ERR_MODULE_REQUIREMENTS);
        }
        if (static::versionCompire($version, $target->getVersion()) !== true) {
            throw new ApplicationException(sprintf('%s module need %s version %s', $this->module->getFullName(), $target->getName(), $target->getVersion()), ApplicationException::ERR_MODULE_REQUIREMENTS);
        }
    }

    /**
     * 检查模块需求
     *
     * @return void
     */
    protected function checkFrameworkVersion()
    {
        if ($sudaVersion = $this->module->getConfig('suda')) {
            if (static::versionCompire($sudaVersion, SUDA_VERSION) !== true) {
                throw new ApplicationException(sprintf('%s module need suda version %s', $this->module->getFullName(), $sudaVersion), ApplicationException::ERR_FRAMEWORK_VERSION);
            }
        }
    }
    protected function loadShareLibrary()
    {
        $import = $this->module->getConfig('import.share', []);
        if (count($import)) {
            $this->importClassLoader($import, $this->module->getPath());
        }
    }

    protected function loadPrivateLibrary()
    {
        $import = $this->module->getConfig('import.src', []);
        if (count($import)) {
            $this->importClassLoader($import, $this->module->getPath());
        }
    }

    public function loadVendorIfExist()
    {
        $vendorAutoload = $this->module->getPath().'/vendor/autoload.php';
        if (FileSystem::exist($vendorAutoload)) {
            require_once $vendorAutoload;
        }
    }

    protected function loadExtraModuleResourceLibrary()
    {
        $import = $this->module->getConfig('module-resource', []);
        if (count($import)) {
            foreach ($import as $name => $path) {
                if ($module = $this->application->find($name)) {
                    $module->getResource()->addResourcePath(Resource::getPathByRelativedPath($path, $this->module->getPath()));
                }
            }
        }
    }

    protected function loadEventListener()
    {
        if ($path = $this->module->getResource()->getConfigResourcePath('config/listener')) {
            $listener = Config::loadConfig($path, [
                'module' => $this->module->getName(),
                'config' => $this->module->getConfig(),
            ]);
            if (\is_array($listener)) {
                $this->application->event()->load($listener);
            }
        }
    }

    protected function loadRoute()
    {
        foreach ($this->application->getRouteGroup() as  $group) {
            $this->loadRouteGroup($group);
        }
    }

    /**
     * 导入 ClassLoader 配置
     *
     * @param array $import
     * @return void
     */
    protected function importClassLoader(array $import, string $relativePath)
    {
        ApplicationBuilder::importClassLoader($this->application->loader(), $import, $relativePath);
    }

    /**
     * 加载路由组
     *
     * @param string $groupName
     * @return void
     */
    protected function loadRouteGroup(string $groupName)
    {
        $group = $groupName === 'default' ? '': '-'. $groupName;
        if ($path = $this->module->getResource()->getConfigResourcePath('config/router'.$group)) {
            $routeConfig = Config::loadConfig($path, [
                'module' => $this->module->getName(),
                'group' => $groupName,
                'config' => $this->module->getConfig(),
            ]);
            if ($routeConfig !== null) {
                $prefix = $this->module->getConfig('route-prefix.'.$groupName, '');
                $this->loadRouteConfig($prefix, $groupName, $routeConfig);
            }
        }
    }

    protected function loadRouteConfig(string $prefix, string $groupName, array $routeConfig)
    {
        $module =  $this->module->getFullName();
        foreach ($routeConfig as $name => $config) {
            $exname = $this->application->getRouteName($name, $module, $groupName);
            $method = $config['method'] ?? [];
            $attriute = [];
            $attriute['module'] = $module;
            $attriute['config'] = $config;
            $attriute['route'] = $exname;
            $uri = $config['url'] ?? '/';
            $anti = array_key_exists('anti-prefix', $config) && $config['anti-prefix'];
            if ($anti) {
                $uri = '/'.trim($uri, '/');
            } else {
                $uri = '/'.trim($prefix . $uri, '/');
            }
            $this->application->request($method, $exname, $uri, $attriute);
        }
    }

    /**
     * 比较版本
     *
     * @param string $version 比较用的版本，包含比较符号
     * @param string $compire 对比的版本
     * @return bool
     */
    protected static function versionCompire(string $version, string $compire)
    {
        if (preg_match('/^(<=?|>=?|<>|!=)(.+)$/i', $version, $match)) {
            list($s, $op, $ver) = $match;
            return  version_compare($compire, $ver, $op);
        }
        return version_compare($compire, $version, '>=');
    }
}
