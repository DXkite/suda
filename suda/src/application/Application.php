<?php

namespace suda\application;

use Exception;
use Throwable;
use suda\framework\Request;
use suda\framework\Response;
use suda\framework\http\Status;
use suda\framework\route\MatchResult;
use suda\application\template\Template;
use suda\application\debug\RequestDumpCatcher;
use suda\application\loader\ModuleLoader;
use suda\database\exception\SQLException;
use suda\application\template\RawTemplate;
use suda\application\debug\ExceptionCatcher;
use suda\application\wrapper\TemplateWrapper;
use suda\application\loader\ApplicationLoader;
use suda\application\processor\FileRequestProcessor;
use suda\framework\http\Request as RequestInterface;
use suda\application\wrapper\ExceptionContentWrapper;
use suda\application\exception\ConfigurationException;
use suda\framework\http\Response as ResponseInterface;
use suda\application\processor\TemplateAssetProcessor;
use suda\application\processor\TemplateRequestProcessor;
use suda\application\processor\RunnableRequestProcessor;

/**
 * 应用程序
 */
class Application extends ApplicationRoute
{
    /**
     * @var ExceptionCatcher
     */
    protected $catcher;

    /**
     * 准备运行环境
     *
     * @return void
     * @throws SQLException
     */
    public function load()
    {
        $this->debug->info('===============================');
        parent::load();
        $appLoader = new ApplicationLoader($this);
        $this->debug->time('loading route');
        $appLoader->loadRoute();
        $this->event->exec('application:load-route', [$this->route, $this]);
        $route = $this->debug->timeEnd('loading route');
        $this->debug->recordTiming('route', $route);
        $this->debug->info('-------------------------------');
    }

    /**
     * 准备环境
     *
     * @param Request $request
     * @param Response $response
     * @throws SQLException
     */
    protected function prepare(Request $request, Response $response)
    {
        $response->setHeader('x-powered-by', 'suda/' . SUDA_VERSION, true);
        $response->getWrapper()->register(ExceptionContentWrapper::class, [Throwable::class]);
        $response->getWrapper()->register(TemplateWrapper::class, [RawTemplate::class]);
        $this->setCatcher(new RequestDumpCatcher($this, $request, $response));

        $this->debug->info('{request-time} {remote-ip} {request-method} {request-uri} debug={debug}', [
            'remote-ip' => $request->getRemoteAddr(),
            'debug' => SUDA_DEBUG,
            'request-uri' => $request->getUrl(),
            'request-method' => $request->getMethod(),
            'request-time' => date('Y-m-d H:i:s', constant('SUDA_START_TIME')),
        ]);

        $this->load();
    }

    /**
     * @param ExceptionCatcher $catcher
     */
    public function setCatcher(ExceptionCatcher $catcher) {
        if ($this->catcher !== null) {
            $this->catcher->restore();
        }
        $this->catcher = $catcher;
        $this->catcher->register();
    }

    /**
     * @param Throwable $throwable
     */
    public function dumpException($throwable)
    {
        $this->catcher->dumpThrowable($throwable);
    }

    /**
     * 运行程序
     *
     * @param RequestInterface $request
     * @param ResponseInterface $response
     */
    public function run(RequestInterface $request, ResponseInterface $response)
    {
        $appRequest = new Request($request);
        $appResponse = new Response($response, $this);

        try {
            $this->debug->time('init');
            $this->prepare($appRequest, $appResponse);
            $init = $this->debug->timeEnd('init');
            $this->debug->recordTiming('init', $init, 'init total');
            $this->debug->time('match route');
            $result = $this->route->match($appRequest->getMethod(), $appRequest->getUri());
            $match = $this->debug->timeEnd('match route');
            $this->debug->recordTiming('dispatch', $match);
            if ($result !== null) {
                $this->event->exec('application:route:match::after', [$result, $appRequest]);
            }
            $this->debug->time('sending response');
            $appResponse = $this->createResponse($result, $appRequest, $appResponse);
            if (!$appResponse->isSend()) {
                $appResponse->end();
            }
            $this->debug->info('responded with code ' . $appResponse->getStatus());
            $this->debug->timeEnd('sending response');
        } catch (Throwable $e) {
            $this->debug->uncaughtException($e);
            $this->catcher->dumpThrowable($e);
            $appResponse->sendContent($e);
            $this->debug->timeEnd('sending response');
        }
        $this->debug->info('system shutdown');
    }

    /**
     * 添加请求
     *
     * @param array $method
     * @param string $name
     * @param string $url
     * @param array $attributes
     * @return void
     */
    public function request(array $method, string $name, string $url, array $attributes = [])
    {
        $route = $attributes['config'] ?? [];
        $runnable = RunnableRequestProcessor::class . '->onRequest';
        if (array_key_exists('class', $route)) {
            $attributes['class'] = $route['class'];
        } elseif (array_key_exists('source', $route)) {
            $attributes['class'] = FileRequestProcessor::class;
            $attributes['source'] = $route['source'];
        } elseif (array_key_exists('template', $route)) {
            $attributes['class'] = TemplateRequestProcessor::class;
            $attributes['template'] = $route['template'];
        } elseif (array_key_exists('runnable', $route)) {
            $attributes['runnable'] = $route['runnable'];
        } else {
            throw new ConfigurationException('request config error', ConfigurationException::ERR_CONFIG_SET);
        }
        $this->route->request($method, $name, $url, $runnable, $attributes);
    }

    /**
     * 运行默认请求
     * @param Application $application
     * @param Request $request
     * @param Response $response
     * @return mixed
     * @throws Exception
     */
    protected function defaultResponse(Application $application, Request $request, Response $response)
    {
        (new TemplateAssetProcessor)->onRequest($application, $request, $response);
        if ($response->getStatus() === Status::HTTP_NOT_FOUND) {
            return $this->route->getDefaultRunnable()->run($request, $response);
        }
        return null;
    }

    /**
     * 运行请求
     * @param MatchResult|null $result
     * @param Request $request
     * @param Response $response
     * @return Response
     * @throws Exception
     */
    protected function createResponse(?MatchResult $result, Request $request, Response $response)
    {
        if (SUDA_DEBUG) {
            $response->setHeader('x-route', $result === null ? 'default' : $result->getName());
        }
        if ($result === null) {
            $response->status(Status::HTTP_NOT_FOUND);
            $content = $this->defaultResponse($this, $request, $response);
        } else {
            $response->status(Status::HTTP_OK);
            $content = $this->runResult($result, $request, $response);
        }
        if ($content instanceof  Response) {
            return $response;
        }
        if ($content !== null && !$response->isSend()) {
            $response->setContent($content);
        }
        return $response;
    }

    /**
     * 运行结果
     *
     * @param MatchResult $result
     * @param Request $request
     * @param Response $response
     * @return mixed
     */
    protected function runResult(MatchResult $result, Request $request, Response $response)
    {
        $request
            ->setParameter($request->getParameter())
            ->mergeQueries($result->getParameter())
            ->setAttributes($result->getMatcher()->getAttribute());
        $request->setAttribute('result', $result);
        $module = $request->getAttribute('module');
        if ($module && ($running = $this->find($module))) {
            $moduleLoader = new ModuleLoader($this, $running);
            $moduleLoader->toRunning();
        }
        return ($result->getRunnable())($this, $request, $response);
    }

    /**
     * 获取模板页面
     *
     * @param string $name
     * @param Request $request
     * @param string|null $default
     * @return Template
     */
    public function getTemplate(string $name, Request $request, ?string $default = null): Template
    {
        if ($default === null && $this->running !== null) {
            $default = $this->running->getFullName();
        } else {
            $default = $default ?? $request->getAttribute('module');
        }
        return new Template($this->getModuleSourceName($name, $default), $this, $request, $default);
    }

    /**
     * @inheritDoc
     */
    public function __clone()
    {
        $this->config = clone $this->config;
        $this->event = clone $this->event;
        $this->loader = clone $this->loader;
    }
}
