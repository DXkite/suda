<?php
/**
 * Suda FrameWork
 *
 * An open source application development framework for PHP 7.0.0 or newer
 *
 * Copyright (c)  2017 DXkite
 *
 * @category   PHP FrameWork
 * @package    Suda
 * @copyright  Copyright (c) DXkite
 * @license    MIT
 * @link       https://github.com/DXkite/suda
 * @version    since 1.2.4
 */
namespace suda\tool;

use suda\exception\JSONException;

class Json
{
    public static $error=[
       JSON_ERROR_NONE=>'No errors',
       JSON_ERROR_DEPTH=>'Maximum stack depth exceeded',
       JSON_ERROR_STATE_MISMATCH=>'Underflow or the modes mismatch',
       JSON_ERROR_CTRL_CHAR=>'Unexpected control character found',
       JSON_ERROR_SYNTAX=>'Syntax error, malformed JSON',
       JSON_ERROR_UTF8=>'Malformed UTF-8 characters, possibly incorrectly encoded',
    ];
    

    
    public static function decode(string $json, bool $assoc = false, int $depth = 512, int $options = 0)
    {
        $value=json_decode($json, $assoc, $depth, $options);
        if (json_last_error()!==JSON_ERROR_NONE) {
            throw new JSONException(json_last_error());
        }
        return $value;
    }

    public static function encode()
    {
        return call_user_func_array('json_encode', func_get_args());
    }

    public static function parseFile(string $path)
    {
        if (!file_exists($path)) {
            throw new JSONException("File {$path} No Found");
        }
        $content=file_get_contents($path);
        return self::decode($content, true);
    }
    
    public static function saveFile(string $path, $jsonable)
    {
        $json=json_encode($jsonable);
        return file_put_contents($path, $json);
    }
    
    public static function loadFile(string $path)
    {
        ob_start();
        require $path;
        $content=ob_get_clean();
        return self::decode($content, true);
    }
}
