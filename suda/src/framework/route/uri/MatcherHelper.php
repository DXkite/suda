<?php
namespace suda\framework\route\uri;

use InvalidArgumentException;
use suda\framework\route\uri\UriMatcher;
use suda\framework\route\uri\parameter\IntParameter;
use suda\framework\route\uri\parameter\UrlParameter;
use suda\framework\route\uri\parameter\FloatParameter;
use suda\framework\route\uri\parameter\StringParameter;

/**
 * 可执行命令表达式
 *
 */
class MatcherHelper
{
    protected static $parameters = [
        'float' => FloatParameter::class,
        'int' => IntParameter::class,
        'string' => StringParameter::class,
        'url' => UrlParameter::class,
    ];

    public static function build(string $uri):UriMatcher
    {
        // 参数
        $parameters = [];
        // 转义正则
        $url = preg_replace('/([\/\.\\\\\+\(\^\)\$\!\<\>\-\?\*])/', '\\\\$1', $uri);
        // 添加忽略
        $url = preg_replace('/(\[)([^\[\]]+)(?(1)\])/', '(?:$2)?', $url);
        // 添加 * ? 匹配
        $url = str_replace(['\*','\?'], ['[^/]*?','[^/]'], $url);
        // 编译页面参数
        $url = preg_replace_callback('/\{(\w+)(?:\:([^}]+?))?\}/', function ($match) use (&$parameters) {
            $name = $match[1];
            $type = 'string';
            $extra = '';
            if (isset($match[2])) {
                if (strpos($match[2], '=') !== false) {
                    list($type, $extra) = \explode('=', $match[2]);
                } else {
                    $type = $match[2];
                }
            }
            if (!\in_array($type, array_keys(static::$parameters))) {
                throw new InvalidArgumentException(sprintf('unknown parameter type %s', $type), 1);
            }
            $parameter = static::$parameters[$type]::build($name, $extra);
            $parameters[] = $parameter;
            return $parameter->getMatch();
        }, $url);

        return new UriMatcher($uri, $url, $parameters);
    }

    public static function buildUri(UriMatcher $matcher, array $parameter, bool $allowQuery = true):string
    {
        $uri = $matcher->getUri();
        // 拆分参数
        list($mapper, $query) = static::analyseParameter($matcher, $parameter);
        // for * ?
        $url = \str_replace(['*','?'], ['','-'], $uri);
        // for ignore value
        $url = preg_replace_callback('/\[(.+?)\]/', function ($match) use ($matcher, $parameter, $mapper) {
            if (preg_match('/\{(\w+).+?\}/', $match[1])) {
                $count = 0;
                $subUrl = static::replaceParameter($match[1], $matcher, $parameter, $mapper, $count);
                if ($count > 0) {
                    return $subUrl;
                }
            }
            return '';
        }, $url);
 
        $url = static::replaceParameter($url, $matcher, $parameter, $mapper);

        if (count($query) && $allowQuery) {
            return $url.'?'.http_build_query($query, 'v', '&', PHP_QUERY_RFC3986);
        }
        return $url;
    }

    protected static function analyseParameter(UriMatcher $matcher, array $parameter):array
    {
        $query = [];
        $mapper = [];

        foreach ($parameter as $key => $value) {
            $mp = $matcher->getParameter($key);
            // 多余参数
            if (null === $mp) {
                $query[$key] = $value;
            }
            $mapper[$key] = $mp;
        }
        return [$mapper, $query];
    }

    protected static function replaceParameter(string $input, UriMatcher $matcher, array $parameter, array $mapper, ?int &$count = null)
    {
        return preg_replace_callback('/\{(\w+).+?\}/', function ($match) use ($matcher, $parameter, $mapper) {
            if (\array_key_exists($match[1], $mapper)) {
                return $mapper[$match[1]]->packValue($parameter[$match[1]]);
            }
            if ($default = $matcher->getParameter($match[1])) {
                return $default->getDefaultValue();
            }
            throw new InvalidArgumentException(sprintf('unknown parameter %s in %s', $match[1], $matcher->getUri()), 1);
        }, $input, -1, $count);
    }
}