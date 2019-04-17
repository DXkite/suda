<?php
/**
 * Suda FrameWork
 *
 * An open source application development framework for PHP 7.2.0 or newer
 *
 * Copyright (c)  2017-2018 DXkite
 *
 * @category   PHP FrameWork
 * @package    Suda
 * @copyright  Copyright (c) DXkite
 * @license    MIT
 * @link       https://github.com/DXkite/suda
 * @version    since 1.2.4
 */
namespace suda\core;

use suda\tool\Security;
use suda\template\Manager;
use suda\core\route\Mapping;
use suda\exception\JSONException;
use suda\exception\ApplicationException;

// TODO: If-Modified-Since
// TODO: Access-Control

/**
 * 网页响应类，用于处理来自服务器的请求
 *
 */
abstract class Response
{
    // 状态输出
    private static $status = null;
    private static $mime;
    public static $name;
    protected $type;
    const EnableOutputBuffer = true;

    public function __construct()
    {
        // Mark Version
        if (conf('mark-version', true)) {
            self::setHeader('X-Application:'.conf('app.name', 'suda-app').'/'.conf('app.version').' '.self::$name .' request/' .conf('request'));
        }
        
        if (conf('debug')) {
            $this->noCache();
            // for windows debug touch file to avoid 304 by server
            if (!IS_LINUX && !IN_PHAR) {
                $content = file_get_contents(SUDA_ENTRANCE);
                if (preg_match('/\<\?php\s+#\d+\r\n/i', $content)) {
                    $content = preg_replace('/\<\?php\s+#\d+\r\n/i', '<?php #'.time().PHP_EOL, $content);
                } else {
                    $content = preg_replace('/\<\?php/i', '<?php #'.time().PHP_EOL, $content);
                }
                file_put_contents(SUDA_ENTRANCE, $content);
            }
        }
    }
    
    abstract public function onRequest(Request $request);
    
    public static function state(int $state)
    {
        self::setHeader('HTTP/1.1 '.$state.' '.self::statusMessage($state));
        self::setHeader('Status:'.$state.' '.self::statusMessage($state));
    }

    public static function setName(string $name)
    {
        self::$name = $name;
    }

    public static function getName()
    {
        return self::$name;
    }
 
    public function type(string $type)
    {
        $this->type = $type;
        self::setHeader('Content-Type:'.self::mime($type));
    }

    public function noCache()
    {
        self::setHeader('Cache-Control: no-store');
    }

    /**
     * 渲染输出JSON
     *
     * @param mixed $values
     * @return void
     */
    public function json($values)
    {
        $jsonstr = json_encode($values, JSON_UNESCAPED_UNICODE);
        if ($jsonstr === false && json_last_error() !== JSON_ERROR_NONE) {
            throw new JSONException(json_last_error());
        }
        Hook::exec('suda:system:display:json', [&$jsonstr]);
        $this->type('json');
        self::ifMatchETag(md5($jsonstr));
        self::send($jsonstr);
    }

    /**
     * 渲染输出文件
     *
     * @param string $path
     * @param string $filename
     * @param string $type
     * @return void
     */
    public function file(string $path, string $filename = null, string $type = null)
    {
        $content = file_get_contents($path);
        $hash = md5($content);
        if (! self::ifMatchETag($hash)) {
            $type = $type ?? pathinfo($path, PATHINFO_EXTENSION);
            $name = $filename ?? pathinfo($path, PATHINFO_FILENAME);
            $this->type($type);
            self::setHeader('Content-Disposition: attachment;filename="'.$name.'.'.$type.'"');
            self::setHeader('Cache-Control: max-age=0');
            self::setHeader('Expires: ' . gmdate('D, d M Y H:i:s') . ' GMT');
            self::setHeader('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
            self::setHeader('Cache-Control: cache, must-revalidate');
            self::setHeader('Pragma: public');
            self::send($content);
        }
    }

    /**
     * 渲染HTML
     *
     * @param string $html
     * @return void
     */
    public function html(string $html)
    {
        $csp = null;
        if (conf('module.enable.Content-Security-Policy', conf('enable.Content-Security-Policy', true))) {
            if (\property_exists($this, 'contentSecurityPolicy')) {
                $csp = Security::cspGeneretor($this->contentSecurityPolicy);
            } elseif (\method_exists($this, 'getContentSecurityPolicy')) {
                $csp = Security::cspGeneretor($this->getContentSecurityPolicy());
            } elseif (null !== Mapping::$current && is_array(Mapping::$current->getParam()) && array_key_exists('Content-Security-Policy', Mapping::$current->getParam())) {
                $csp = Security::cspGeneretor(Mapping::$current->getParam()['Content-Security-Policy']);
            } else {
                $csp = Security::cspGeneretor(conf('module.header.Content-Security-Policy', conf('header.Content-Security-Policy')));
            }
            if (\strlen($csp) > 0) {
                $this->addHeader('Content-Security-Policy', $csp);
            }
            $xfo = conf('module.header.Content-Security-Policy', conf('header.X-Frame-Options', 'sameorigin'));
            if (is_string($xfo) && \strlen($xfo) > 0) {
                $this->addHeader('X-Frame-Options', $xfo);
            }
        }
        $this->type('html');
        // if (conf('app.etag', !conf('debug')) && $this->etag(md5($html))) {
        // } else {
            self::send($html);
        // }
    }

    /**
     * 发送内容
     *
     * @param string $content
     * @return void
     */
    public static function send(string $content)
    {
        hook()->exec('suda:response:send::before', [&$content]);
        if (conf('app.calc-content-length', !conf('debug'))) {
            $length = strlen($content);
            self::setHeader('Content-Length:'.$length);
        }
        echo $content;
    }

    /**
     * 输出HTML页面
     *
     * @param string $template HTML页面模板
     * @param array $values 页面模板的值
     * @return mixed
     */
    public function page(string $template, array $values = [])
    {
        $view = $this->view($template, $values);
        if ($view) {
            return $view;
        }
        throw new ApplicationException(__('template[$0] file not exist: $1', $template, $template));
    }

    /**
     * 输出HTML页面
     *
     * @param string $template HTML页面模板
     * @param array $values 页面模板的值
     * @return mixed
     */
    public function view(string $template, array $values = [])
    {
        $tpl = Manager::displaySource($template, 'html');
        if (null !== $tpl) {
            return $tpl->response($this)->assign($values);
        }
        return null;
    }

    /**
     * 输出模板文件
     *
     * @param string $template 模板路径
     * @param array $values 页面值
     * @param string $name 模板名
     * @return mixed
     */
    public function template(string $filepath, array $values = [], ?string  $name = null)
    {
        return Manager::displayFile($filepath, $name)->response($this)->assign($values);
    }

    public function refresh()
    {
        $this->go(Router::getInstance()->buildUrl(self::$name, $_GET, false));
    }

    public function forward():bool
    {
        if ($forward = self::getForward()) {
            $this->go($forward);
            return true;
        }
        return false;
    }

    public static function getForward():?string
    {
        $referer = $_GET['redirect_uri'] ?? Request::referer();
        if (Cookie::has('redirect_uri')) {
            $referer = Cookie::get('redirect_uri', $referer);
            Cookie::delete('redirect_uri');
        }
        return $referer?:null;
    }

    public function setForward(string $url)
    {
        Cookie::set('redirect_uri', $url);
    }

    public function go(string $url)
    {
        $this->setHeader('Location:'.$url);
    }


    public function redirect(string $url, int $time = 1, string $message = null)
    {
        $this->noCache();
        $page = $this->page('suda:redirect');
        if ($message) {
            $page->set('message', $message);
        }
        $page->set('url', $url);
        $page->set('time', $time);
        $page->render();
    }

    /**
     * 使用Etag
     * 注意：请不要再输出内容
     *
     * @param string $etag
     * @return boolean
     */
    public static function etag(string $etag)
    {
        self::setHeader('Etag: "'.$etag.'"');
        $request = Request::getInstance();
        if ($str = $request->getHeader('If-None-Match')) {
            if (strcasecmp($etag, $str) === 0) {
                self::state(304);
                self::close();
                return true;
            }
        }
        return false;
    }


    public static function close()
    {
        self::setHeader('Connection: close');
    }

    /**
    *  页面MIME类型
    */
    public static function mime(string $name = '')
    {
        if (!self::$mime) {
            self::$mime = parse_ini_file(SYSTEM_RESOURCE.'/mime.ini');
        }
        if ($name) {
            return self::$mime[$name] ?? conf('mime.'.$name, $name);
        } else {
            return self::$mime;
        }
    }

    /**
    * 安全设置Header值
    */
    public static function setHeader(string $header, bool $replace = true)
    {
        if (!headers_sent()) {
            header($header, $replace);
        }
    }

    public static function addHeader(string $name, string $value)
    {
        self::setHeader(trim($name).':'.$value);
    }

    
    protected static function ifMatchETag(string $etag)
    {
        if (conf('app.etag', !conf('debug'))) {
            return self::etag($etag);
        }
        return false;
    }
   
    public static function statusMessage(int $state)
    {
        if (null === self::$status) {
            self::$status = parse_ini_file(SYSTEM_RESOURCE.'/status.ini');
        }
        return self::$status[$state] ?? 'OK';
    }
}
