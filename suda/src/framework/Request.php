<?php

namespace suda\framework;

use suda\framework\http\UploadedFile;
use suda\framework\request\RequestWrapper;
use suda\framework\http\Request as RequestInterface;

class Request extends RequestWrapper
{
    /**
     * 参数
     *
     * @var array
     */
    protected $parameter = [];

    /**
     * 附加属性
     *
     * @var array
     */
    protected $attribute = [];

    /**
     * 是否为JSON提交
     *
     * @var boolean
     */
    protected $isJson = false;

    /**
     * 创建请求
     *
     * @param RequestInterface $request
     */
    public function __construct(RequestInterface $request)
    {
        parent::__construct($request);
        $this->setIsJson($this->contentIsJson());
    }

    /**
     * 获取URL
     *
     * @return string
     */
    public function getUrl(): string
    {
        return $this->getServer('request-uri', '/');
    }

    /**
     * 获取请求属性
     *
     * @param string $name
     * @param mixed $default
     * @return  mixed
     */
    public function getAttribute(string $name, $default = null)
    {
        return $this->attribute[$name] ?? $default;
    }

    /**
     * 设置请求属性
     *
     * @param string $name
     * @param mixed $attribute
     * @return $this
     */
    public function setAttribute(string $name, $attribute)
    {
        $this->attribute[$name] = $attribute;

        return $this;
    }

    /**
     * Set 属性
     *
     * @param array $attribute 属性
     *
     * @return  self
     */
    public function setAttributes(array $attribute)
    {
        $this->attribute = $attribute;

        return $this;
    }

    /**
     * 获取Scheme
     *
     * @return string
     */
    public function getScheme(): string
    {
        return $this->isSecure() ? 'https' : 'http';
    }

    /**
     * 判断是否是POST请求
     *
     * @return boolean
     */
    public function isPost(): bool
    {
        return $this->getMethod() === 'POST';
    }

    /**
     * 判断是否为GET请求
     *
     * @return boolean
     */
    public function isGet(): bool
    {
        return $this->getMethod() === 'GET';
    }

    /**
     * 判断内容是否为JSON
     *
     * @return boolean
     */
    public function isJson(): bool
    {
        return $this->isJson;
    }

    /**
     * 获取HTTP输入
     *
     * @return string
     */
    public function input(): string
    {
        return $this->request->input();
    }

    /**
     * 获取JSON数据
     *
     * @return array|null
     */
    public function json(): ?array
    {
        return $this->isJson ? $this->parameter : null;
    }

    /**
     * 获取提交的文件
     *
     * @param string $name
     * @return UploadedFile|null
     */
    public function file(string $name): ?UploadedFile
    {
        $uploaded = $this->getFile($name);
        if ($uploaded instanceof UploadedFile) {
            return $uploaded;
        }
        return null;
    }

    /**
     * 判断是否有GET请求
     *
     * @param string|null $name
     * @return boolean
     */
    public function hasGet(?string $name = null)
    {
        $get = $this->getQuery();
        if ($name !== null) {
            return array_key_exists($name, $get);
        }
        return count($get) > 0;
    }

    /**
     * 判断是否有JSON数据请求
     *
     * @return boolean
     */
    public function hasJson()
    {
        if ($this->isJson() && $this->json()) {
            return true;
        }
        return false;
    }

    /**
     * 判断是否有POST数据请求
     *
     * @param string|null $name
     * @return boolean
     */
    public function hasPost(?string $name = null)
    {
        $post = $this->post();
        if ($name !== null && is_array($post)) {
            return array_key_exists($name, $post);
        }
        return count($post) > 0;
    }

    /**
     * 获取POST请求的值
     *
     * @param string $name
     * @param mixed $default
     * @return mixed 获取的值
     */
    public function post(?string $name = null, $default = null)
    {
        if ($name === null) {
            return $this->request->post();
        }
        return $this->request->post()[$name] ?? $default;
    }

    /**
     * 获取GET参数
     *
     * @param string|null $name
     * @param mixed $default
     * @return mixed
     */
    public function get(?string $name = null, $default = null)
    {
        return $this->getQuery($name, $default);
    }

    /**
     * 获取Cookie
     *
     * @param string $name
     * @param mixed $default
     * @return mixed
     */
    public function cookie(string $name, $default = null)
    {
        return $_COOKIE[$name] ?? $default;
    }

    /**
     * 获取URI中的参数
     * @param string|null $name
     * @param mixed $default
     * @return mixed
     */
    public function getParameter(?string $name = null, $default = null)
    {
        return $name === null ? $this->parameter : $this->parameter[$name] ?? $default;
    }

    /**
     * 设置URI中的参数
     * @param array $parameter
     * @return $this
     */
    public function setParameter(array $parameter)
    {
        $this->parameter = $parameter;
        return $this;
    }

    /**
     * 设置是否为JSON提交
     * @param bool $isJson
     * @return $this
     */
    public function setIsJson(bool $isJson)
    {
        $this->isJson = $isJson;
        return $this;
    }

    /**
     * 判断是否为JSON
     *
     * @return boolean
     */
    private function contentIsJson()
    {
        $header = strtolower($this->getServer('content-type', ''));
        return null !== $header && strpos($header, 'json') !== false;
    }
}
