<?php
namespace suda\core;
use suda\tool\Command;

class Hook
{
    private static $hooks=[];
    public static function load(array $arrays)
    {
        self::$hooks=array_merge(self::$hooks, $arrays);
    }
    public static function listen(string $name, $command)
    {
        self::add($name, $command);
    }

    public static function register(string $name, $command)
    {
        self::add($name, $command);
    }

    public static function add(string $name, $command)
    {
        self::$hooks[$name][]=$command;
    }
    public static function addTop(string $name, $command)
    {
        if (isset(self::$hooks[$name]) && is_array(self::$hooks[$name])) {
            array_unshift(self::$hooks[$name], $command);
        } else {
            self::add($name, $command);
        }
    }
    public static function remove(string $name, $remove)
    {
        if (isset(self::$hooks[$name]) && is_array(self::$hooks[$name])) {
            foreach (self::$hooks[$name] as $key=>$command) {
                if ($command === $remove) {
                    unset(self::$hooks[$name][$key]);
                }
            }
        }
    }
    /* --- 运行区 ---*/
    public static function exec(string $name, array $args=[])
    {
        if (isset(self::$hooks[$name]) && is_array(self::$hooks[$name])) {
            foreach (self::$hooks[$name] as $command) {
                self::call($command, $args);
            }
        }
    }

    public static function execIf(string $name, array $args=[], $condition = true)
    {
        if (isset(self::$hooks[$name]) && is_array(self::$hooks[$name])) {
            foreach (self::$hooks[$name] as $command) {
                if (self::call($command, $args)!==$condition) {
                    return false;
                }
            }
        }
        return true;
    }

    public static function execNotNull(string $name, array $args=[])
    {
        if (isset(self::$hooks[$name]) && is_array(self::$hooks[$name])) {
            foreach (self::$hooks[$name] as $command) {
                if (!is_null($value=self::call($command, $args))) {
                    return $value;
                }
            }
        }
        return null;
    }

    public static function execTop(string $name, array $args=[])
    {
        if (isset(self::$hooks[$name]) && is_array(self::$hooks[$name])) {
            return  self::call(array_shift(self::$hooks[$name]),$args);
        }
    }

    public static function execTail(string $name, array $args=[])
    {
        if (isset(self::$hooks[$name]) && is_array(self::$hooks[$name])) {
            return  self::call(array_pop(self::$hooks[$name]),$args);
        }
    }

    protected static function call($command, array &$args)
    {
        return (new Command($command))->exec($args);
    }
}
