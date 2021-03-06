<?php
namespace suda\database;

use ReflectionClass;
use ReflectionException;
use ReflectionProperty;
use suda\database\statement\Statement;
use suda\database\struct\ReadStatement;
use suda\database\middleware\Middleware;
use suda\database\struct\QueryStatement;
use suda\database\struct\TableStruct;
use suda\database\struct\WriteStatement;
use suda\database\middleware\NullMiddleware;
use suda\database\struct\TableStructBuilder;
use suda\database\struct\TableStructMiddleware;
use suda\database\struct\TableClassStructBuilder;
use suda\database\struct\TableStructAwareInterface;
use suda\database\middleware\MiddlewareAwareInterface;

/**
 * 数据访问
 */
class DataAccess
{

    /**
     * 数据源
     *
     * @var TableAccess
     */
    protected $access;

    /**
     * 数据类型
     *
     * @var string
     */
    protected $type;

    /**
     * 创建对数据的操作
     *
     * @param string $object
     * @param DataSource $source
     * @param Middleware|null $middleware
     * @throws ReflectionException
     */
    public function __construct(string $object, DataSource $source, ?Middleware $middleware = null)
    {
        $this->type = $object;
        $struct = $this->createStruct($object);
        $middleware = $middleware ?? $this->createMiddleware($object, $struct);
        $this->access = new TableAccess($struct, $source, $middleware);
    }

    /**
     * 读取数据
     *
     * @param array|string $fields
     * @return ReadStatement
     * @throws exception\SQLException
     */
    public function read(...$fields): ReadStatement
    {
        return $this->access->read(...$fields)->wantType($this->type);
    }

    /**
     * 写数据
     *
     * @param array|object|string $object
     * @return WriteStatement
     * @throws ReflectionException
     * @throws exception\SQLException
     */
    public function write($object): WriteStatement
    {
        if (is_object($object)) {
            $object = $this->createDataFromObject($object);
        }
        return $this->access->write($object);
    }

    /**
     * 删
     *
     * @param string|array $where
     * @param array $whereParameter
     * @return WriteStatement
     * @throws exception\SQLException
     */
    public function delete($where = null, ...$whereParameter):WriteStatement
    {
        if ($where !== null) {
            return $this->access->delete($where, ...$whereParameter);
        }
        return $this->access->delete();
    }

    /**
     * 统计计数
     *
     * @param string|array|object $where
     * @param array $whereBinder
     * @return integer
     * @throws ReflectionException
     * @throws exception\SQLException
     */
    public function count($where, array $whereBinder = []):int
    {
        if (is_object($where)) {
            $where = $this->createDataFromObject($where);
        }
        $fields = $this->access->getStruct()->all();
        $field = array_shift($fields);
        $total = $this->access->read([$field->getName()])->where($where, $whereBinder);
        $data = $this->access->query(
            sprintf("SELECT count(*) as `count` from (%s) as total", $total),
            $total->getBinder()
        )->one();
        return intval($data['count']);
    }

    /**
     * 查询语句
     *
     * @param string $query
     * @param mixed ...$parameter
     * @return QueryStatement
     * @throws exception\SQLException
     */
    public function query(string $query, ...$parameter):QueryStatement
    {
        return $this->access->query($query, ...$parameter);
    }


    /**
     * 运行SQL语句
     *
     * @param Statement $statement
     * @return mixed
     * @throws exception\SQLException
     */
    public function run(Statement $statement)
    {
        return $this->access->run($statement);
    }

    /**
     * 获取最后一次插入的主键ID（用于自增值
     *
     * @param string $name
     * @return string 则获取失败，整数则获取成功
     */
    public function lastInsertId(string $name = null):string
    {
        return $this->access->lastInsertId($name);
    }

    /**
     * 事务系列，开启事务
     *
     * @return void
     */
    public function beginTransaction()
    {
        $this->access->beginTransaction();
    }

    /**
     * 事务系列，提交事务
     *
     * @return void
     */
    public function commit()
    {
        $this->access->commit();
    }

    /**
     * 事务系列，撤销事务
     *
     * @return void
     */
    public function rollBack()
    {
        $this->access->rollBack();
    }

    /**
     * 创建数据
     *
     * @param object $object
     * @return array
     * @throws ReflectionException
     */
    protected function createDataFromObject($object)
    {
        if (method_exists($object, '__get')) {
            return $this->createDataViaMagicGet($object);
        }
        return $this->createDataViaReflection($object);
    }

    /**
     * 使用魔术方法获取值
     *
     * @param object $object
     * @return array
     */
    protected function createDataViaMagicGet($object)
    {
        $fields = $this->access->getStruct();
        $data = [];
        $isset = method_exists($object, '__isset');
        if ($isset) {
            foreach ($fields as $name => $value) {
                if ($object->__isset($name)) {
                    $dataField = $this->access->getMiddleware()->inputName($name);
                    $data[$dataField] = $object->__get($name);
                }
            }
        } else {
            foreach ($fields as $name => $value) {
                $dataField = $this->access->getMiddleware()->inputName($name);
                $data[$dataField] = $object->__get($name);
            }
        }
        return $data;
    }

    /**
     * 使用反射方法获取值
     *
     * @param object $object
     * @return array
     * @throws ReflectionException
     */
    protected function createDataViaReflection($object)
    {
        $reflection = new ReflectionClass($object);
        $data = [];
        foreach ($reflection->getProperties(
            ReflectionProperty::IS_PUBLIC
            | ReflectionProperty::IS_PROTECTED
            | ReflectionProperty::IS_PRIVATE
        ) as $property) {
            if (TableStructBuilder::isTableField($property)) {
                $property->setAccessible(true);
                $value = $property->getValue($object);
                if ($value !== null) {
                    $dataField = $this->access->getMiddleware()->inputName($property->getName());
                    $data[$dataField] = $value;
                }
            }
        }
        return $data;
    }

    /**
     * 创建表结构
     *
     * @param string $object
     * @return TableStruct
     * @throws ReflectionException
     */
    public static function createStruct(string $object)
    {
        if (is_subclass_of($object, TableStructAwareInterface::class)) {
            return forward_static_call([$object, 'getTableStruct']);
        }
        return (new TableClassStructBuilder($object))->createStruct();
    }


    /**
     * @param string $object
     * @return string
     */
    public static function createTableName(string $object)
    {
        $pos = strrpos($object, '\\');
        if ($pos !== false && $pos >= 0) {
            $object = substr($object, $pos + 1);
        }
        return TableStructBuilder::createName($object);
    }

    /**
     * 创建中间件
     *
     * @param string $object
     * @param TableStruct $struct
     * @return Middleware
     * @throws ReflectionException
     */
    public static function createMiddleware(string $object, TableStruct $struct)
    {
        if (is_subclass_of($object, MiddlewareAwareInterface::class)) {
            return forward_static_call([$object, 'getMiddleware']);
        }
        return static::createDefaultMiddleware($object, $struct);
    }

    /**
     * 创建默认中间件
     *
     * @param string $object
     * @param TableStruct $struct
     * @return Middleware
     * @throws ReflectionException
     */
    protected static function createDefaultMiddleware(string $object, TableStruct $struct)
    {
        $reflectObject = new ReflectionClass($object);
        $classDoc = is_string($reflectObject->getDocComment())?$reflectObject->getDocComment():'';
        if (preg_match('/@field-(serialize|json)\s+(\w+)/i', $classDoc, $matchs)) {
            return new TableStructMiddleware($object, $struct);
        }
        return new NullMiddleware;
    }

    /**
     * @return TableAccess
     */
    public function getAccess(): TableAccess
    {
        return $this->access;
    }

    /**
     * 获取表结构
     *
     * @return TableStruct
     */
    public function getStruct():TableStruct
    {
        return $this->access->getStruct();
    }

    /**
     * 获取表名
     *
     * @return string
     */
    public function getName():string
    {
        return $this->access->getName();
    }
}
