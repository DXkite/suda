<?php

use suda\framework\Event;
use suda\framework\Route;
use suda\framework\Config;
use suda\framework\Server;
use suda\framework\Context;
use suda\framework\Request;
use suda\framework\Debugger;
use suda\framework\Response;
use suda\framework\Container;
use suda\framework\Application;
use suda\framework\runnable\Runnable;
use suda\framework\filesystem\FileSystem;
use suda\framework\debug\log\LoggerInterface;
use suda\framework\debug\log\logger\FileLogger;
use suda\framework\debug\log\logger\NullLogger;
use suda\framework\http\Request as HTTPRequest;

require_once __DIR__ .'/loader.php';

$context = new Context;

$context->setSingle('loader', $loader);
$context->setSingle('config', Config::class);
$context->setSingle('event', Event::class);
$context->setSingle('route', Route::class);

$context->setSingle('request', function () {
    return new Request(HTTPRequest::create());
});

$context->setSingle('response', function () {
    return new Response;
});

$context->setSingle('debug', function () use ($context) {
    return Debugger::create($context);
});

$context->get('debug')->notice('system booting');


$app = new Application($context);

$route = $context->get('route');

$route->get('index', '/', function ($request, $response) use ($route) {
    return 'hello, index';
});

$route->get('hello', '/helloworld', function ($request, $response) use ($route) {
    return 'hello world <strong>' . $route->create('hello', ['name' => 'dxkite']).'</strong>';
});

$match = $route->match($context->get('request'));

if ($match) {
    $match->run($context->get('request'), $context->get('response'));
} else {
    echo '404';
}