<?php
namespace suda\application\wrapper;

use suda\framework\Response;
use suda\framework\http\Stream;
use suda\framework\http\stream\StringStream;
use suda\application\template\ExceptionTemplate;
use suda\framework\response\AbstractContentWrapper;

/**
 * 异常类型响应包装器
 */
class ExceptionContentWrapper extends AbstractContentWrapper
{
    /**
     * 获取内容
     *
     * @param Response $response
     * @return Stream
     */
    public function getWrappedContent(Response $response):Stream
    {
        $content = $this->content;
        $template = new ExceptionTemplate($content);
        return new StringStream($template->getRenderedString());
    }
}
