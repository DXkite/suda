<?php
namespace suda\application;

use function array_key_exists;
use function constant;
use Exception;
use suda\orm\exception\SQLException;
use Throwable;
use suda\framework\Request;
use suda\framework\Response;
use suda\application\database\Table;
use suda\framework\route\MatchResult;
use suda\application\database\DataAccess;
use suda\application\loader\ModuleLoader;
use suda\application\template\RawTemplate;
use suda\application\loader\LanguageLoader;
use suda\application\wrapper\TemplateWrapper;
use suda\application\loader\ApplicationLoader;
use suda\application\processor\FileRequestProcessor;
use suda\application\wrapper\ExceptionContentWrapper;
use suda\application\processor\TemplateAssetProccesser;
use suda\application\processor\TemplateRequestProcessor;

/**
 * 应用程序
 */
class Application extends ApplicationSource
{

    /**
     * 准备运行环境
     *
     * @return void
     * @throws SQLException
     */
    public function load()
    {
        $appLoader = new ApplicationLoader($this);
        $this->debug->info('===============================');
        $this->debug->time('loading application');
        $appLoader->load();
        $this->event->exec('application:load-config', [ $this->config ,$this]);
        $this->debug->timeEnd('loading application');
        $this->debug->time('loading datasource');
        $appLoader->loadDataSource();
        Table::load($this);
        DataAccess::load($this);
        $this->event->exec('application:load-environment', [ $this->config ,$this]);
        $this->debug->timeEnd('loading datasource');
        $this->debug->time('loading route');
        $appLoader->loadRoute();
        $this->event->exec('application:load-route', [$this->route , $this]);
        $this->debug->timeEnd('loading route');
        $this->debug->info('-------------------------------');
    }

    /**
     * 准备环境
     *
     * @param Request $request
     * @param Response $response
     * @return void
     * @throws SQLException
     */
    protected function prepare(Request $request, Response $response)
    {
        $response->setHeader('x-powered-by', 'nebula/'.SUDA_VERSION, true);
        $response->getWrapper()->register(ExceptionContentWrapper::class, [Throwable::class]);
        $response->getWrapper()->register(TemplateWrapper::class, [RawTemplate::class]);
        $dumpper = new DebugDumpper($this, $response);
        $dumpper->register();
        $this->debug->info('{request-time} {remote-ip} {request-method} {request-uri} debug={debug}', [
            'remote-ip' => $request->getRemoteAddr(),
            'debug' => SUDA_DEBUG,
            'request-uri' => $request->getUrl(),
            'request-method' => $request->getMethod(),
            'request-time' => date('Y-m-d H:i:s', constant('SUDA_START_TIME')),
        ]);
        if ($this->isPrepared === false) {
            $this->load();
            $this->isPrepared = true;
        }
    }

    /**
     * 运行程序
     *
     * @param Request $request
     * @param Response $response
     * @return void
     * @throws Exception
     */
    public function run(Request $request, Response $response)
    {
        try {
            $this->prepare($request, $response);
            $this->debug->time('match route');
            $result = $this->route->match($request);
            $this->debug->timeEnd('match route');
            if ($result !== null) {
                $this->event->exec('application:route:match::after', [$result, $request]);
            }
            $this->debug->time('sending response');
            $response = $this->createResponse($result, $request, $response);
            if (!$response->isSended()) {
                $response->end();
            }
            $this->debug->info('resposned with code '. $response->getStatus());
            $this->debug->timeEnd('sending response');
        } catch (Throwable $e) {
            $this->debug->uncaughtException($e);
            $response->sendContent($e);
            $response->end();
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
     * @throws Exception
     */
    public function request(array $method, string $name, string $url, array $attributes = [])
    {
        $route = $attributes['config'] ?? [];
        $runnable = null;
        if (array_key_exists('class', $route)) {
            $runnable = $this->className($route['class']).'->onRequest';
        } elseif (array_key_exists('source', $route)) {
            $attributes['source'] = $route['source'];
            $runnable = FileRequestProcessor::class.'->onRequest';
        } elseif (array_key_exists('template', $route)) {
            $attributes['template'] = $route['template'];
            $runnable = TemplateRequestProcessor::class.'->onRequest';
        } elseif (array_key_exists('runnable', $route)) {
            $runnable = $route['runnable'];
        } else {
            throw new Exception('request failed');
        }
        $this->route->request($method, $name, $url, $runnable, $attributes);
    }

    /**
     * 运行默认请求
     * @param Application $application
     * @param Request $request
     * @param Response $response
     * @return mixed|void
     * @throws Exception
     */
    protected function defaultResponse(Application $application, Request $request, Response $response)
    {
        if ((new TemplateAssetProccesser)->onRequest($application, $request, $response)) {
            return;
        }
        return $this->route->getDefaultRunnable()->run($request, $response);
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
            $response->setHeader('x-route', $result === null?'default':$result->getName());
        }
        if ($result === null) {
            $content = $this->defaultResponse($this, $request, $response);
        } else {
            $content = $this->runResult($result, $request, $response);
        }
        if ($content !== null && !$response->isSended()) {
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
        $request->mergeQueries($result->getParameter())->setAttributes($result->getMatcher()->getAttribute());
        $request->setAttribute('result', $result);
        $module = $request->getAttribute('module');
        if ($module && ($running = $this->find($module))) {
            $moduleLoader = new ModuleLoader($this, $running);
            $moduleLoader->toRunning();
        }
        LanguageLoader::load($this);
        return ($result->getRunnable())($this, $request, $response);
    }
}
