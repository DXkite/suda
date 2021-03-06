<?php

namespace suda\framework\filesystem;

use function dirname;
use function restore_error_handler;
use function set_error_handler;
use suda\framework\loader\Path;
use suda\framework\loader\PathTrait;

/**
 * 文件辅助函数
 */
class FileSystem implements FileSystemInterface
{
    use DirectoryHelper {
        rm as protected;
        rmdirs as protected;
        move as moveDir;
        copy as copyDir;
    }

    use FileHelper {
        delete as protected deleteFile;
        FileHelper::copy insteadof DirectoryHelper;
        FileHelper::move insteadof DirectoryHelper;
    }

    /**
     * 判断是否溢出路径
     *
     * @param string $root 根目录
     * @param string $target 溢出目录
     * @return boolean
     */
    public static function isOverflowPath(string $root, string $target)
    {
        $absolute = PathTrait::toAbsolutePath($target);
        $root = PathTrait::toAbsolutePath($root);
        return strpos($absolute, $root . DIRECTORY_SEPARATOR) !== 0;
    }

    /**
     * 删除文件或者目录
     *
     * @param string $path
     * @return boolean
     */
    public static function delete(string $path): bool
    {
        if (($path = Path::format($path)) !== null) {
            if (is_file($path)) {
                return static::deleteFile($path);
            }

            if (is_dir($path)) {
                return static::rmDirs($path);
            }
        }
        return false;
    }

    /**
     * 是否可写
     *
     * @param string $path
     * @return boolean
     */
    public static function isWritable(string $path): bool
    {
        if (static::exist($path) === false) {
            return static::isWritable(dirname($path));
        }
        return static::tryCheckWritable($path);
    }

    protected static function tryCheckWritable(string $path): bool
    {
        $writable = false;
        set_error_handler(null);
        if (DIRECTORY_SEPARATOR === '/' && ini_get('safe_mode') === 'On') {
            $writable = is_writable($path);
        } elseif (is_dir($path)) {
            $writable = static::tryWriteDirectory($path);
        } elseif (is_file($path)) {
            $writable = static::tryWriteFile($path);
        }
        restore_error_handler();
        return $writable;
    }

    protected static function tryWriteFile(string $path): bool
    {
        if (($fp = fopen($path, 'ab'))) {
            fclose($fp);
            return true;
        }
        return false;
    }

    protected static function tryWriteDirectory(string $path): bool
    {
        $path = rtrim($path, '/') . '/' . md5(mt_rand(1, 100) . mt_rand(1, 100));
        if (($fp = fopen($path, 'ab')) === false) {
            return false;
        }
        fclose($fp);
        chmod($path, 0777);
        unlink($path);
        return true;
    }
}
