<?php

namespace suda\application;

use function array_key_exists;
use ArrayIterator;
use function explode;
use function implode;
use function in_array;
use IteratorAggregate;
use function sprintf;
use function strpos;
use function strrpos;
use function strtolower;
use function substr;
use suda\framework\filesystem\FileSystem;
use suda\application\exception\ApplicationException;
use function version_compare;

/**
 * 模块名
 */
class ModuleBag implements IteratorAggregate
{
    /**
     * 模块
     *
     * @var Module[]
     */
    protected $module = [];

    /**
     * 已知全部全名
     *
     * @var array
     */
    protected $version = [];

    /**
     * 查找缓存
     *
     * @var array
     */
    protected $cache = [];

    /**
     * 添加模块
     *
     * @param Module $module
     * @return void
     */
    public function add(Module $module)
    {
        $name    = $module->getName();
        $version = $module->getVersion();
        $unique  = $module->getUnique();
        $version = $this->formatVersion($version);
        if (!in_array($name, $this->version)) {
            $this->version[$name][$version] = $unique;
            uksort($this->version[$name], [$this, 'sort']);
        }
        if (in_array($unique, $this->module)) {
            throw new ApplicationException(sprintf('conflict module unique name %s', $unique), ApplicationException::ERR_CONFLICT_MODULE_UNIQUE);
        }
        $this->module[$unique] = $module;
    }

    /**
     * 合并模块包
     *
     * @param ModuleBag $module
     * @return void
     */
    public function merge(ModuleBag $module)
    {
        $this->module  = array_merge($this->module, $module->module);
        $this->version = array_merge($this->version, $module->version);
        $this->cache   = array_merge($this->cache, $module->cache);
    }

    /**
     * 推测文件所在模块
     *
     * @param string $path
     * @return Module|null
     */
    public function guess(string $path): ?Module
    {
        foreach ($this->module as $module) {
            if (FileSystem::isOverflowPath($module->getPath(), $path) === false) {
                return $module;
            }
        }
        return null;
    }

    /**
     * 根据路径获取所在模块
     *
     * @param string $path
     * @return Module
     */
    public function getModuleFromPath(string $path): Module
    {
        if (($module = $this->guess($path)) !== null) {
            return $module;
        }
        throw new ApplicationException(
            sprintf('path %s not exist in any module', $path),
            ApplicationException::ERR_PATH_NOT_EXISTS_IN_MODULE
        );
    }

    /**
     * 获取模块
     *
     * @param string $name
     * @return Module|null
     */
    public function get(string $name): ?Module
    {
        $uniqueName = $this->getUniqueName($name);
        return $this->module[$uniqueName] ?? null;
    }

    /**
     * 获取所有模块
     *
     * @return Module[]
     */
    public function all(): array
    {
        return $this->module;
    }

    public function getIterator()
    {
        return new ArrayIterator($this->module);
    }

    /**
     * 检测模块是否存在
     *
     * @param string $name
     * @return boolean
     */
    public function exist(string $name): bool
    {
        return $this->get($name) !== null;
    }

    /**
     * 拆分名称
     * [namespace/]name[:version]:data -> [namespace/name:version,data]
     *
     * @param string $name
     * @param string|null $default
     * @return array
     */
    public function info(string $name, ?string $default = null): array
    {
        $rp = strrpos($name, ':');
        if ($rp > 0) {
            $module     = substr($name, 0, $rp);
            $name       = substr($name, $rp + 1);
            $uniqueName = $this->getUniqueName($module);
            return [$uniqueName, $name];
        }
        if ($rp === 0) {
            return [$default, substr($name, 1)];
        }
        return [$default, $name];
    }

    /**
     * 获取模块全名
     *
     * @param string $name
     * @return string
     */
    public function getUniqueName(string $name): string
    {
        if (array_key_exists($name, $this->module)) {
            return $name;
        }
        if (array_key_exists($name, $this->cache)) {
            return $this->cache[$name];
        }
        $uniqueName         = $this->createUniqueName($name);
        $this->cache[$name] = $uniqueName;
        return $uniqueName;
    }

    /**
     * 创建模块全名
     *
     * @param string $name
     * @return string
     */
    protected function createUniqueName(string $name)
    {
        $version    = null;
        $hasVersion = false;
        if (strpos($name, ':')) {
            $hasVersion = true;
            list($shortName, $version) = explode(':', $name, 2);
        } else {
            $shortName = $name;
        }
        $shortName = $this->getLikeName($shortName);
        if (array_key_exists($shortName, $this->version) === false) {
            return $name;
        }
        if (array_key_exists($version, $this->version[$shortName])) {
            return $this->version[$shortName][$version];
        }
        return $hasVersion ? $shortName . ':' . $version : end($this->version[$shortName]);
    }

    protected function getLikeName(string $name): string
    {
        $names = [];
        foreach (array_keys($this->version) as $keyName) {
            if (strpos($keyName, $name) !== false) {
                $names[] = $keyName;
            }
        }
        if (count($names) === 0) {
            return $name;
        }
        if (count($names) > 1 && in_array($name, $names) === false) {
            throw new ApplicationException(sprintf('conflict module name %s in %s', $name, implode(',', $names)), ApplicationException::ERR_CONFLICT_MODULE_NAME);
        }
        return $names[array_search($name, $names)];
    }

    protected function sort(string $a, string $b)
    {
        return version_compare($a, $b);
    }

    protected function formatVersion(string $version)
    {
        $version = strtolower($version);
        if (strpos($version, 'v') === 0) {
            return substr($version, 1);
        }
        return $version;
    }
}
