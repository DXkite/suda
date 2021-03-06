<?php
namespace suda\database\middleware;

use suda\database\struct\TableStruct;

/**
 * 感知表结构
 */
interface MiddlewareAwareInterface
{
    /**
     * @param TableStruct $struct
     * @return Middleware
     */
    public static function getMiddleware(TableStruct $struct):Middleware;
}
