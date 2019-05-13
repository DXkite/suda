<?php
namespace suda\application\database;

use ReflectionException;
use suda\application\Application;
use suda\orm\middleware\Middleware;

/**
 * 数据表抽象对象
 *
 * 用于提供对数据表的操作
 *
 */
class DataAccess extends \suda\orm\DataAccess
{

    /**
     * 应用引用
     *
     * @var Application
     */
    protected static $application;

    /**
     * @var StatementSet
     */
    protected $statement;

    /**
     * 创建对数据的操作
     *
     * @param string $object
     * @param Middleware|null $middleware
     * @throws ReflectionException
     */
    public function __construct(string $object, ?Middleware $middleware = null)
    {
        parent::__construct($object, static::$application->getDataSource(), $middleware);
    }

    /**
     * 加载语句集合
     * @param string $statement
     * @return $this
     */
    public function load(string $statement)
    {
        $this->statement = new StatementSet($this->access, $statement);
        $this->statement->load(static::$application);
        return $this;
    }

    /**
     * 从变量创建中间件
     *
     * @param object $object
     * @return DataAccess
     * @throws ReflectionException
     */
    public static function create($object):DataAccess
    {
        $middleware = null;
        if ($object instanceof Middleware) {
            $middleware = $object;
        }
        return new self(get_class($object), $middleware);
    }

    /**
     * 创建访问工具
     *
     * @param string $object
     * @param Middleware|null $middleware
     * @return DataAccess
     * @throws ReflectionException
     */
    public static function new(string $object, ?Middleware $middleware = null):DataAccess
    {
        return new self($object, $middleware);
    }

    /**
     * @return StatementSet
     */
    public function getStatement(): StatementSet
    {
        return $this->statement;
    }

    /**
     * 从应用创建表
     *
     * @param Application $application
     * @return void
     */
    public static function loadApplication(Application $application)
    {
        static::$application = $application;
    }

    /**
     * Get 应用引用
     *
     * @return  Application
     */
    public static function application()
    {
        return static::$application;
    }
}
