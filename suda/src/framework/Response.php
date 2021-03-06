<?php
namespace suda\framework;

use suda\framework\http\Cookie;
use suda\framework\http\Stream;
use suda\framework\http\stream\DataStream;
use suda\framework\response\MimeType;
use suda\framework\response\ContentWrapper;
use suda\framework\response\ResponseWrapper;
use suda\framework\http\Response as ResponseInterface;

class Response extends ResponseWrapper
{
    /**
     * 包装器
     *
     * @var ContentWrapper
     */
    protected $wrapper;

    /**
     * @var Context
     */
    private $context;
    
    /**
     * 响应数据
     *
     * @var Stream|string
     */
    protected $data;


    public function __construct(ResponseInterface $response, Context $context)
    {
        parent::__construct($response);
        $this->wrapper = new ContentWrapper;
        $this->context = $context;
    }

    /**
     * 设置类型
     *
     * @param string $extension
     * @return $this
     */
    public function setType(string $extension)
    {
        $this->header('content-type', MimeType::getMimeType($extension), true);
        return $this;
    }

    /**
     * 设置头部
     *
     * @param string $name
     * @param string $content
     * @param bool $replace
     * @return $this
     */
    public function setHeader(string $name, string $content, bool $replace = false)
    {
        $this->header($name, $content, $replace);
        return $this;
    }

    /**
     * 设置请求内容
     *
     * @param mixed $content
     * @return $this
     */
    public function setContent($content)
    {
        if (is_string($content) || $content instanceof Stream) {
            $this->data = $content;
        } else {
            $wrapper = $this->wrapper->getWrapper($content);
            $this->data = $wrapper->getWrappedContent($this);
        }
        return $this;
    }


    /**
     * 发送内容数据
     *
     * @param array|string|Stream|null $data
     * @return $this
     */
    protected function sendContentLength($data)
    {
        if ($data === null) {
            $this->setHeader('content-length', 0);
        } elseif (is_array($data)) {
            $this->setHeader('content-length', $this->getDataLengthArray($data), true);
        } else {
            $this->setHeader('content-length', $this->getDataLengthItem($data), true);
        }
        return $this;
    }

    /**
     * 获取数据长度
     *
     * @param Stream[] $data
     * @return int
     */
    protected function getDataLengthArray(array $data):int
    {
        $length = 0;
        foreach ($data as $item) {
            $length += $this->getDataLengthItem($item);
        }
        return $length;
    }

    /**
     * 获取数据长度
     *
     * @param Stream|string $data
     * @return integer
     */
    protected function getDataLengthItem($data):int
    {
        if (is_string($data)) {
            return strlen($data);
        } else {
            return $data->length();
        }
    }

    /**
     * 设置 Cookie
     *
     * @param string $name
     * @param string $value
     * @param integer $expire
     * @return Cookie
     */
    public function setCookie(string $name, string $value, int $expire = 0):Cookie
    {
        $cookie = new Cookie($name, $value, $expire);
        $this->cookie($cookie);
        return $cookie;
    }

    /**
     * 直接发送数据
     *
     * @param mixed $content
     * @return void
     */
    public function sendContent($content = null)
    {
        if ($content !== null) {
            $this->setContent($content);
        }
        $this->sendContentLength($this->data);
        $this->send($this->data);
    }


    /**
     * 发送文件内容
     *
     * @param string $filename
     * @param integer $offset
     * @param integer $length
     * @return void
     */
    public function sendFile(string $filename, int $offset = 0, int $length = null)
    {
        $this->triggerSendEvent();
        $data = new DataStream($filename, $offset, $length);
        $this->setHeader('content-length', $data->length());
        $this->response->sendFile($filename, $offset, $length);
    }

    /**
     * 重定向页面
     * 不直接结束请求
     * @param string $url
     * @param int $httpCode
     */
    public function redirect(string $url, int $httpCode = 302)
    {
        $this->status($httpCode);
        $this->header('location', $url);
    }

    /**
     * 发送缓存的内容
     */
    public function end()
    {
        $this->sendContent($this->data);
    }

    /**
     * @param string|Stream $data
     */
    public function send($data)
    {
        $this->triggerSendEvent();
        parent::send($data);
    }

    /**
     * 出发发送内容事件
     */
    private function triggerSendEvent()
    {
        if ($this->isSend() === false) {
            $this->context->event()->exec('response::before-send', [$this]);
        }
    }

    /**
     * @return Context
     */
    public function getContext(): Context
    {
        return $this->context;
    }

    /**
     * @param Context $context
     */
    public function setContext(Context $context): void
    {
        $this->context = $context;
    }

    /**
     * @return ContentWrapper
     */
    public function getWrapper(): ContentWrapper
    {
        return $this->wrapper;
    }

    /**
     * @param ContentWrapper $wrapper
     */
    public function setWrapper(ContentWrapper $wrapper)
    {
        $this->wrapper = $wrapper;
    }
}
