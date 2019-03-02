<?php
namespace suda\framework\server;

use suda\framework\Server;
use suda\framework\server\response\Header;
use suda\framework\server\response\MimeType;

class Response
{
    /**
     * 请求投
     *
     * @var Header
     */
    protected $header;

    /**
     * Cookie
     *
     * @var Cookie[]
     */
    protected $cookie;
    
    /**
     * 状态码
     *
     * @var int
     */
    protected $statusCode = 200;

    /**
     * 响应内容
     *
     * @var string
     */
    protected $content;


    /**
     * 创建响应
     *
     * @param integer $statusCode
     * @param mixed $content
     */
    public function __construct(int $statusCode = 200, $content = null)
    {
        $this->statusCode = $statusCode;
        $this->header = new Header;
        $this->setContent($content);
        $this->cookie = [];
    }
    
    /**
     * 设置类型
     *
     * @param string $extension
     * @return self
     */
    public function setType(string $extension)
    {
        $this->header->remove('Content-Type')->add('Content-Type', MimeType::getMimeType($extension));
        return $this;
    }

    /**
     * 设置头部
     *
     * @param string $name
     * @param string $content
     * @return self
     */
    public function setHeader(string $name, string $content)
    {
        $this->header->add($name, $content);
        return $this;
    }

    /**
     * 设置请求内容
     *
     * @param mixed $content
     * @return self
     */
    public function setContent($content)
    {
        $wrapper = ContentWrapper::getWrapper($content);
        $this->content = $wrapper->getContent(Server::request(), $this);
        $this->setHeader('Content-Length', strlen($this->content));
        return $this;
    }

    /**
     * 发送请求
     *
     * @return void
     */
    public function send()
    {
        $this->header->sendHeaders($this->statusCode);
        $this->sendCookies();
        $this->sendContent();
    }

    /**
     * 发送内容
     *
     * @return void
     */
    protected function sendContent()
    {
        echo $this->getContent();
    }

    /**
     * 设置Cookie
     *
     * @return void
     */
    protected function sendCookies() {
        foreach($this->cookie as $cookie ) {
            $cookie->send();
        }
    }

    /**
     * Get 响应内容
     *
     * @return  string
     */
    public function getContent()
    {
        return $this->content;
    }

    /**
     * Get 状态码
     *
     * @return  int
     */
    public function getStatusCode()
    {
        return $this->statusCode;
    }

    /**
     * 设置 Cookie
     *
     * @param  Cookie  $cookie  Cookie
     *
     * @return  self
     */
    public function setCookie(Cookie $cookie)
    {
        $this->cookie[$cookie->getName()] = $cookie;
        return $this;
    }

    /**
     * 获取Cookie
     *
     * @param string $name
     * @return Cookie|null
     */
    public function getCookie(string $name):?Cookie
    {
        return $this->cookie[$name] ?? null;
    }
}
