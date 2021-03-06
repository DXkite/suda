<?php
namespace suda\framework\route;

use function file_get_contents;
use Iterator;
use ArrayIterator;
use IteratorAggregate;
use function unserialize;

/**
 * 路由集合
 *
 */
class RouteCollection implements IteratorAggregate
{
    /**
     * 属性
     *
     * @var RouteMatcher[]
     */
    protected $collection = [];

    /**
     * 创建集合
     *
     * @param RouteMatcher[] $collection
     */
    public function __construct(array $collection = [])
    {
        $this->mergeArray($collection);
    }

    /**
     * 合并集合
     *
     * @param array $collection
     * @return void
     */
    public function mergeArray(array $collection = [])
    {
        $this->collection = array_merge($this->collection, $collection);
    }

    /**
     * 合并
     *
     * @param RouteCollection $route
     * @return void
     */
    public function merge(RouteCollection $route)
    {
        $this->collection = array_merge($this->collection, $route->collection);
    }

    /**
     * 添加集合
     *
     * @param string $name
     * @param RouteMatcher $routeMatcher
     * @return void
     */
    public function add(string $name, RouteMatcher $routeMatcher)
    {
        $this->collection[$name] = $routeMatcher;
    }

    /**
     * 获取集合
     *
     * @param string $name
     * @return RouteMatcher|null
     */
    public function get(string $name):?RouteMatcher
    {
        return $this->collection[$name] ?? null;
    }

    /**
     * 获取迭代器
     *
     * @return RouteMatcher[]
     */
    public function getCollection():array
    {
        return $this->collection;
    }

    /**
     * 从文件创建
     *
     * @param string $path
     * @return $this
     */
    public static function fromFile(string $path)
    {
        $collection = unserialize(file_get_contents($path));
        return new static($collection);
    }

    public function getIterator():Iterator
    {
        return new ArrayIterator($this->collection);
    }
}
