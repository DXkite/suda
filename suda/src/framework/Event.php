<?php

namespace suda\framework;

use ArrayIterator;
use Iterator;
use suda\framework\runnable\Runnable;

class Event
{
    protected $queue = [];

    /**
     * 加载事件处理
     *
     * @param array $arrays
     * @return void
     */
    public function load(array $arrays)
    {
        $this->queue = array_merge_recursive($this->queue, $arrays);
    }

    /**
     * 注册一条命令
     *
     * @param string $name
     * @param mixed $command
     * @return void
     */
    public function listen(string $name, $command)
    {
        $this->add($name, $command);
    }

    /**
     * 注册一条命令
     *
     * @param string $name
     * @param mixed $command
     * @return void
     */
    public function register(string $name, $command)
    {
        $this->add($name, $command);
    }

    /**
     * 添加命令到底部
     *
     * @param string $name
     * @param mixed $command
     * @return void
     */
    public function add(string $name, $command)
    {
        if (!in_array($command, $this->queue[$name] ?? [])) {
            $this->queue[$name][] = $command;
        }
    }

    /**
     * 添加命令到顶部
     *
     * @param string $name
     * @param mixed $command
     * @return void
     */
    public function addTop(string $name, $command)
    {
        if (array_key_exists($name, $this->queue) && is_array($this->queue[$name])) {
            if (!in_array($command, $this->queue[$name])) {
                array_unshift($this->queue[$name], $command);
            }
        } else {
            $this->add($name, $command);
        }
    }

    /**
     * 移除一条命令
     *
     * @param string $name
     * @param mixed $remove
     * @return void
     */
    public function remove(string $name, $remove)
    {
        if (array_key_exists($name, $this->queue) && is_array($this->queue[$name])) {
            foreach ($this->queue[$name] as $key => $command) {
                if ($command === $remove) {
                    unset($this->queue[$name][$key]);
                }
            }
        }
    }

    /**
     * 运行所有命令
     *
     * @param string $name
     * @param array $args
     * @param bool $reverse
     * @return void
     */
    public function exec(string $name, array $args = [], bool $reverse = false)
    {
        if ($this->hasListenEvent($name)) {
            $iterator = $this->getCallbackIterator($name, $reverse);
            foreach ($iterator as $command) {
                $this->call($name, $command, $args);
            }
        }
    }

    /**
     * 获取迭代器
     * @param string $name
     * @param bool $reverse
     * @return Iterator
     */
    protected function getCallbackIterator(string $name, bool $reverse = false): Iterator {
        return $reverse?new ArrayIterator(array_reverse($this->queue[$name])):new ArrayIterator($this->queue[$name]);
    }

    /**
     * 继续执行
     *
     * @param string $name
     * @param mixed $value
     * @param array $args
     * @param bool $reverse
     * @return mixed
     */
    public function process(string $name, $value, array $args = [], bool $reverse = false)
    {
        if ($this->hasListenEvent($name)) {
            array_unshift($args, $value);
            $iterator = $this->getCallbackIterator($name, $reverse);
            foreach ($iterator as $command) {
                $args[0] = $value;
                $value = $this->call($name, $command, $args);
            }
        }
        return $value;
    }

    /**
     * 继续执行
     *
     * @param string $name
     * @param array $args
     * @param mixed $condition
     * @param bool $reverse
     * @return boolean
     */
    public function next(string $name, array $args = [], $condition = true, bool $reverse = false): bool
    {
        if ($this->hasListenEvent($name)) {
            $iterator = $this->getCallbackIterator($name, $reverse);
            foreach ($iterator as $command) {
                if ($this->call($name, $command, $args) === $condition) {
                    continue;
                } else {
                    return false;
                }
            }
        }
        return true;
    }

    /**
     * 运行最先注入的命令
     *
     * @param string $name
     * @param array $args
     * @return mixed
     */
    public function execFirst(string $name, array $args = [])
    {
        if ($this->hasListenEvent($name)) {
            return $this->call($name, $this->queue[$name][0], $args);
        }
        return null;
    }

    /**
     * 运行最后一个注入的命令
     *
     * @param string $name
     * @param array $args
     * @return mixed
     */
    public function execLast(string $name, array $args = [])
    {
        if ($this->hasListenEvent($name)) {
            $last = count($this->queue[$name]) - 1;
            return $this->call($name, $this->queue[$name][$last], $args);
        }
        return null;
    }

    /**
     * 调用对象
     *
     * @param string $event
     * @param mixed $command
     * @param array $args
     * @return mixed
     */
    protected function call(string $event, $command, array &$args)
    {
        return (new Runnable($command))->apply($args);
    }

    /**
     * 判断是监控事件
     *
     * @param string $name
     * @return boolean
     */
    public function hasListenEvent(string $name): bool
    {
        if (array_key_exists($name, $this->queue) && is_array($this->queue[$name])) {
            return count($this->queue[$name]) > 0;
        }
        return false;
    }
}
