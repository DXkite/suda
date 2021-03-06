<?php
namespace suda\framework;

use Closure;
use Exception;
use suda\framework\route\MatchResult;
use suda\framework\runnable\Runnable;
use suda\framework\route\RouteMatcher;
use suda\framework\route\uri\UriMatcher;
use suda\framework\route\RouteCollection;

class Route
{
    /**
     * 路由
     *
     * @var RouteCollection
     */
    protected $routes;

    /**
     * 可执行对象
     *
     * @var Runnable[]
     */
    protected $runnable;

    /**
     * 设置默认
     *
     * @var Runnable
     */
    protected $default;

    /**
     * 是否包含闭包
     *
     * @var boolean
     */
    protected $containClosure = false;
    
    public function __construct()
    {
        $this->routes = new RouteCollection;
        $this->runnable = [];
        $this->default = new Runnable([__CLASS__ , 'defaultResponse']);
    }

    /**
     * 创建 GET 路由
     *
     * @param string $name
     * @param string $url
     * @param Runnable|Closure|array|string $runnable
     * @param array $attributes
     * @return $this
     */
    public function get(string $name, string $url, $runnable, array $attributes = [])
    {
        return $this->request(['GET'], $name, $url, $runnable, $attributes);
    }

    /**
     * 创建 POST 路由
     *
     * @param string $name
     * @param string $url
     * @param Runnable|Closure|array|string $runnable
     * @param array $attributes
     * @return $this
     */
    public function post(string $name, string $url, $runnable, array $attributes = [])
    {
        return $this->request(['POST'], $name, $url, $runnable, $attributes);
    }

    /**
     * 创建 DELETE 路由
     *
     * @param string $name
     * @param string $url
     * @param Runnable|Closure|array|string $runnable
     * @param array $attributes
     * @return $this
     */
    public function delete(string $name, string $url, $runnable, array $attributes = [])
    {
        return $this->request(['DELETE'], $name, $url, $runnable, $attributes);
    }

    /**
     * 创建 HEAD 路由
     *
     * @param string $name
     * @param string $url
     * @param Runnable|Closure|array|string $runnable
     * @param array $attributes
     * @return $this
     */
    public function head(string $name, string $url, $runnable, array $attributes = [])
    {
        return $this->request(['HEAD'], $name, $url, $runnable, $attributes);
    }


    /**
     * 创建 OPTIONS 路由
     *
     * @param string $name
     * @param string $url
     * @param Runnable|Closure|array|string $runnable
     * @param array $attributes
     * @return $this
     */
    public function options(string $name, string $url, $runnable, array $attributes = [])
    {
        return $this->request(['OPTIONS'], $name, $url, $runnable, $attributes);
    }

    /**
     * 创建 PUT 路由
     *
     * @param string $name
     * @param string $url
     * @param Runnable|Closure|array|string $runnable
     * @param array $attributes
     * @return $this
     */
    public function put(string $name, string $url, $runnable, array $attributes = [])
    {
        return $this->request(['PUT'], $name, $url, $runnable, $attributes);
    }

    /**
     * 创建 TRACE 路由
     *
     * @param string $name
     * @param string $url
     * @param Runnable|Closure|array|string $runnable
     * @param array $attributes
     * @return $this
     */
    public function trace(string $name, string $url, $runnable, array $attributes = [])
    {
        return $this->request(['TRACE'], $name, $url, $runnable, $attributes);
    }

    /**
     * 创建路由
     *
     * @param string $name
     * @param string $url
     * @param Runnable|Closure|array|string $runnable
     * @param array $attributes
     * @return $this
     */
    public function any(string $name, string $url, $runnable, array $attributes = [])
    {
        return $this->request([], $name, $url, $runnable, $attributes);
    }

    /**
     * 添加请求
     *
     * @param array $method
     * @param string $name
     * @param string $url
     * @param Runnable|Closure|array|string $runnable
     * @param array $attributes
     * @return $this
     */
    public function request(array $method, string $name, string $url, $runnable, array $attributes = [])
    {
        $matcher = new RouteMatcher($method, $url, $attributes);
        $target = new Runnable($runnable);
        $this->routes->add($name, $matcher);
        $this->runnable[$name] = $target;
        if ($target->isClosure()) {
            $this->containClosure = true;
        }
        return $this;
    }

    /**
     * 设置默认运行器
     *
     * @param Runnable|Closure|array|string $runnable
     * @return $this
     */
    public function default($runnable)
    {
        $this->default = new Runnable($runnable);
        return $this;
    }

    /**
     * 匹配路由
     *
     * @param string $method
     * @param string $uri
     * @return MatchResult|null
     */
    public function match(string $method, string $uri): ?MatchResult
    {
        /** @var RouteMatcher $matcher */
        /** @var string $name */
        foreach ($this->routes as $name => $matcher) {
            if (($parameter = $matcher->match($method, $uri)) !== null) {
                return new MatchResult($matcher, $name, $this->runnable[$name], $parameter);
            }
        }
        return null;
    }

    /**
     * 运行结果
     *
     * @param MatchResult|null $result
     * @param Request $request
     * @param Response $response
     * @return Response
     * @throws Exception
     */
    public function run(?MatchResult $result, Request $request, Response $response):Response
    {
        if ($result !== null) {
            return $this->buildResponse(
                $result,
                $request
                    ->setParameter($request->getParameter())
                    ->mergeQueries($result->getParameter())
                    ->setAttributes($result->getMatcher()->getAttribute()),
                $response
            );
        }
        return $this->buildDefaultResponse($request, $response);
    }

    /**
     * 构建响应
     *
     * @param MatchResult $result
     * @param Request $request
     * @param Response $response
     * @return Response
     * @throws Exception
     */
    protected function buildResponse(MatchResult $result, Request $request, Response $response):Response
    {
        $content = $result->getRunnable()->run($request, $response);
        if ($content !== null && !$response->isSend()) {
            $response->setContent($content);
        }
        return $response;
    }

    /**
     * 构建默认响应
     *
     * @param Request $request
     * @param Response $response
     * @return Response
     * @throws Exception
     */
    protected function buildDefaultResponse(Request $request, Response $response):Response
    {
        $content = $this->default->run($request, $response);
        if ($content !== null && !$response->isSend()) {
            $response->setContent($content);
        }
        return $response;
    }

    /**
     * 创建默认运行器
     *
     * @return Runnable
     */
    public function getDefaultRunnable():Runnable
    {
        return $this->default;
    }

    /**
     * 默认响应
     *
     * @param Request $request
     * @param Response $response
     * @return mixed
     */
    protected static function defaultResponse(Request $request, Response $response)
    {
        $response->status(404);
        $response->setType('html');
        return 'Page Not Found: '.$request->getUrl();
    }

    /**
     * 创建URL
     *
     * @param string $name
     * @param array $parameter
     * @param bool $allowQuery
     * @return string|null
     */
    public function create(string $name, array $parameter, bool $allowQuery = true):?string
    {
        if ($matcher = $this->routes->get($name)) {
            return UriMatcher::buildUri($matcher->getMatcher(), $parameter, $allowQuery);
        }
        return null;
    }

    /**
     * 判断是否包含闭包
     *
     * @return  boolean
     */
    public function isContainClosure():bool
    {
        return $this->containClosure;
    }

    /**
     * Get 路由
     *
     * @return  RouteCollection
     */
    public function getRouteCollection():RouteCollection
    {
        return $this->routes;
    }

    /**
     * @param RouteCollection $routes
     */
    public function setRouteCollection(RouteCollection $routes)
    {
        $this->routes = $routes;
    }

    /**
     * @return Runnable[]
     */
    public function getRunnable(): array
    {
        return $this->runnable;
    }

    /**
     * @param Runnable[] $runnable
     */
    public function setRunnable(array $runnable): void
    {
        $this->runnable = $runnable;
    }
}
