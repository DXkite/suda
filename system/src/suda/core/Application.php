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

use suda\template\Manager;
use suda\tool\Json;
use suda\tool\ArrayHelper;
use suda\exception\ApplicationException;
use suda\exception\JSONException;

class Application
{
    // app 目录
    private $path;
    // 当前模块名
    private $active_module;
    // 激活的模块
    private $module_live=null;
    // 模块配置
    private $module_configs=null;
    // 模块名缓存
    private $module_name_cache=[];
    // 模块目录装换成模块名
    private $module_dir_name=[];
    private $modules_path=[];


    private static $instance;

    private function __construct()
    {
        debug()->trace(__('application load %s', APP_DIR));
        $this->path=APP_DIR;
        // 获取基本配置信息
        if (Storage::exist($path=CONFIG_DIR.'/config.json')) {
            try {
                Config::load($path);
            } catch (JSONException $e) {
                debug()->die(__('parse application config.json error'));
            }
            // 开发状态覆盖
            if (defined('DEBUG')) {
                Config::set('debug', DEBUG);
                Config::set('app.debug', DEBUG);
            }
        }
        // 加载外部数据库配置
        $this->configDBify();
        // 监听器
        if (Storage::exist($path=CONFIG_DIR.'/listener.json')) {
            Hook::loadJson($path);
        }
        
        // 设置PHP属性
        set_time_limit(Config::get('timelimit', 0));
        // 设置时区
        date_default_timezone_set(Config::get('timezone', 'PRC'));
        // 设置默认命名空间
        Autoloader::setNamespace(Config::get('app.namespace'));
        // 系统共享库
        Autoloader::addIncludePath(SHRAE_DIR);
        // 注册模块目录
        $this->addModulesPath(SYSTEM_RESOURCE.'/modules');
        $this->addModulesPath(MODULES_DIR);
        // 读取目录，注册所有模块
        $this->registerModules();
        // 加载模块
        $this->loadModules();
    }

    public static function getInstance()
    {
        if (is_null(static::$instance)) {
            static::$instance=new self();
        }
        return static::$instance;
    }

    public function init()
    {
        // 调整模板
        Manager::theme(conf('app.template', 'default'));
        Hook::exec('Application:init');
        // 初次运行初始化资源
        if (conf('app.init')) {
            init_resource();
        }
        Locale::path($this->path.'/resource/locales/');
        hook()->listen('Router:dispatch::before', [$this, 'onRequest']);
        hook()->listen('system:shutdown', [$this, 'onShutdown']);
        hook()->listen('system:uncaughtException', [$this,'uncaughtException']);
        hook()->listen('system:uncaughtError', [$this, 'uncaughtError']);
    }

    /**
     * 添加模块扫描目录
     *
     * @param string $path
     * @return void
     */
    public function addModulesPath(string $path)
    {
        $path=Storage::abspath($path);
        if ($path && !in_array($path, $this->modules_path)) {
            $this->modules_path[]=$path;
        }
    }

    /**
     * 载入模块
     *
     * @return void
     */
    protected function loadModules()
    {
        // 模块共享库
        $module_all=self::getModules();
        // 激活模块
        $module_use=self::getLiveModules();
        // 安装 启用 活动
        foreach ($module_all as $module_temp) {
            $root=self::getModulePath($module_temp);
            $config=self::getModuleConfig($module_temp);

            // 注册模块共享目录自动加载
            foreach ($config['autoload']['share'] as $namespace=>$path) {
                if (Storage::isDir($share_path=$root.DIRECTORY_SEPARATOR.$path)) {
                    Autoloader::addIncludePath($share_path, $namespace);
                }
            }
            // 注册Import函数
            foreach ($config['import'] as $path) {
                if (Storage::isFile($importPath=$root.DIRECTORY_SEPARATOR.$path)) {
                    Autoloader::import($importPath);
                }
            }
            // 自动安装
            if (conf('auto-install', true)) {
                Hook::listen('Application:init', function () use ($module_temp) {
                    self::installModule($module_temp);
                });
            }

            // 是否激活
            $is_live_module=in_array($module_temp, $module_use);
            if ($is_live_module) {
                // 加载监听器
                if (Storage::exist($listener_path=$root.'/resource/config/listener.json')) {
                    Hook::loadJson($listener_path);
                }
                // 设置语言包库
                Locale::path($root.'/resource/locales/');
            }
        }
    }

    public function installModule(string $module)
    {
        $install_lock = DATA_DIR.'/install/install_'.substr(md5($module), 0, 6).'.lock';
        storage()->path(dirname($install_lock));
        $config=self::getModuleConfig($module);
        if (isset($config['install']) && !file_exists($install_lock)) {
            $installs=$config['install'];
            if (is_string($installs)) {
                $installs=[$installs];
            }
            foreach ($installs as $cmd) {
                cmd($cmd)->args($config);
            }
            file_put_contents($install_lock, 'name='.$module."\r\n".'time='.microtime(true));
        }
    }


    /**
     * 获取所有模块
     *
     * @return void
     */
    public function getModules()
    {
        return array_values($this->module_dir_name);
    }

    public function getModuleDirs()
    {
        return array_keys($this->module_dir_name);
    }

    
    public function getActiveModule()
    {
        return $this->active_module;
    }

    public function getModuleConfig(string $module)
    {
        return $this->module_configs[self::getModuleFullName($module)]??[];
    }

    public function getModulePrefix(string $module)
    {
        $prefix=conf('module-prefix.'.$module, null);
        if (is_null($prefix)) {
            $prefix=$this->module_configs[self::getModuleFullName($module)]['prefix']??null;
        }
        return $prefix;
    }

    public function checkModuleExist(string $name)
    {
        return $this->getModuleDir($name)!=false;
    }

    public function getLiveModules()
    {
        if ($this->module_live) {
            return $this->module_live;
        }
        $modules=conf('app.modules', self::getModules());
        $exclude=defined('DISABLE_MODULES')?explode(',', trim(DISABLE_MODULES, ',')):[];
        foreach ($exclude as $index=>$name) {
            $exclude[$index]=$this->getModuleFullName($name);
        }
        // debug()->trace('modules', json_encode($modules));
        // debug()->trace('exclude', json_encode($exclude));
        foreach ($modules as $index => $name) {
            $fullname=$this->getModuleFullName($name);
            // 剔除模块名
            if (!self::checkModuleExist($name) || in_array($fullname, $exclude)) {
                unset($modules[$index]);
            } else {
                $modules[$index]=$fullname;
            }
        }
        // 排序，保证为数组
        sort($modules);
        debug()->trace('live modules', json_encode($modules));
        return $this->module_live=$modules;
    }

    /**
     * 激活运行的模块
     *
     * @param string $module
     * @return void
     */
    public function activeModule(string $module)
    {
        Hook::exec('Application:active', [$module]);
        debug()->trace(__('active module %s', $module));
        $this->active_module=$module;
        $root=self::getModulePath($module);
        $module_config=self::getModuleConfig($module);
        define('MODULE_RESOURCE', Storage::path($root.'/resource'));
        define('MODULE_CONFIG', Storage::path(MODULE_RESOURCE.'/config'));
        debug()->trace(__('set locale %s', Config::get('app.locale', 'zh-CN')));
        Locale::path(MODULE_RESOURCE.'/locales');
        Locale::set(Config::get('app.locale', 'zh-CN'));
        if (isset($module_config['namespace'])) {
            // 缩减命名空间
            Autoloader::setNamespace($module_config['namespace']);
        }
        // 自动加载私有库
        foreach ($module_config['autoload']['src'] as $namespace=>$path) {
            if (Storage::isDir($srcPath=$root.DIRECTORY_SEPARATOR.$path)) {
                Autoloader::addIncludePath($srcPath, $namespace);
            }
        }
        // 加载模块配置到 module命名空间
        if (Storage::exist($path=MODULE_CONFIG.'/config.json')) {
            Config::set('module', Json::loadFile($path));
        }
    }


    public function onRequest(Request $request)
    {
        return true;
    }
    
    public function onShutdown()
    {
        // TODO: CACHE Appication Info
        // ArrayHelper::export(CACHE_DIR.'/module_configs.cache.php','module_configs',$this->module_configs);
        // ArrayHelper::export(CACHE_DIR.'/module_dir_name.cache.php','module_dir_name',$this->module_dir_name);
    }

    public function uncaughtException($e)
    {
        return false;
    }

    /**
     * 获取模块名，不包含版本号
     *
     * @param string $name 不完整模块名
     * @return void
     */
    public function getModuleName(string $name)
    {
        $name=self::getModuleFullName($name);
        return preg_replace('/:.+$/', '', $name);
    }
    
    /**
     * 获取模块全名（包括版本）
     * name:version,name,namespace/name => namespace/name:version
     * 未指定版本则调整到最优先版本
     *
     * @param string $name 不完整模块名
     * @return void
     */
    public function getModuleFullName(string $name)
    {
        // 存在缓存则返回缓存
        if (isset($this->module_name_cache[$name])) {
            return $this->module_name_cache[$name];
        }
        preg_match('/^(?:([a-zA-Z0-9_-]+)\/)?([a-zA-Z0-9_-]+)(?::(.+))?$/', $name, $matchname);
        $preg='/^'.(isset($matchname[1])&&$matchname[1]? preg_quote($matchname[1]).'\/':'(\w+\/)?') // 限制域
            .preg_quote($matchname[2]) // 名称
            .(isset($matchname[3])&&$matchname[3]?':'.preg_quote($matchname[3]):'(:.+)?').'$/'; // 版本号
        $targets=[];
        // debug()->debug($matchname, $preg);
        // 匹配模块名，查找符合格式的模块
        foreach ($this->module_configs as $module_name=>$module_config) {
            // 匹配到模块名
            if (preg_match($preg, $module_name)) {
                preg_match('/^(?:(\w+)\/)?(\w+)(?::(.+))?$/', $module_name, $matchname);
                // 获取版本号
                if (isset($matchname[3])&&$matchname[3]) {
                    $targets[$matchname[3]]=$module_name;
                } else {
                    $targets[]=$module_name;
                }
            }
        }
        // 排序版本
        uksort($targets, 'version_compare');
        return count($targets)>0?array_pop($targets):$name;
    }

    /**
     * 获取模块所在的文件夹名
     *
     * @param string $name
     * @return void
     */
    public function getModuleDir(string $name)
    {
        $name=self::getModuleFullName($name);
        if (isset($this->module_configs[$name])) {
            return $this->module_configs[$name]['directory'];
        }
        return false;
    }

    /**
     * 根据模块目录名转换成模块名
     *
     * @param string $dirname
     * @return void
     */
    public function moduleName(string $dirname)
    {
        return $this->module_dir_name[$dirname]?:$name;
    }

    /**
     * 注册所有模块信息
     *
     * @return void
     */
    private function registerModules()
    {
        foreach ($this->modules_path as $path) {
            $dirs=Storage::readDirs($path);
            foreach ($dirs as $dir) {
                self::registerModule($path.'/'.$dir);
            }
        }
    }

    public function registerModule(string $path)
    {
        if (Storage::exist($file=$path.'/module.json')) {
            $dir=basename($path);
            debug()->trace(__('load module config %s', $file));
            $json=Json::parseFile($file);
            $name=$json['name'] ?? $dir;
            $json['directory']=$dir;
            $json['path']=$path;
            // 注册默认自动加载
            $json['autoload']=array_merge([
                'share'=>[''=>'share/'],
                'src'=>[''=>'src/']
            ], $json['autoload']??[]);
            $json['import']= $json['import']??[];
            $name.=isset($json['version'])?':'.$json['version']:'';
            $this->module_configs[$name]=$json;
            $this->module_dir_name[$dir]=$name;
        }
    }

    public function getModulesInfo()
    {
        return $this->module_configs;
    }

    public function getModulePath(string $module)
    {
        $name=self::getModuleFullName($module);
        if (isset($this->module_configs[$name])) {
            return $this->module_configs[$name]['path'];
        }
        return false;
    }

    private function configDBify()
    {
        if (file_exists($path=RUNTIME_DIR.'/database.config.php')) {
            $config=include $path;
            Config::set('database', $config);
        }
    }
}
