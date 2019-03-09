<?php
namespace suda\orm;

use PDO;
use PDOStatement;
use suda\orm\Binder;
use suda\orm\DataSource;
use suda\orm\TableStruct;
use suda\orm\statement\Statement;
use suda\orm\connection\Connection;
use suda\orm\middleware\Middleware;
use suda\orm\observer\NullObserver;
use suda\orm\exception\SQLException;
use suda\orm\statement\ReadStatement;
use suda\orm\statement\QueryAccess;
use suda\orm\statement\WriteStatement;
use suda\orm\middleware\NullMiddleware;


class TableAccess extends QueryAccess
{

    /**
     * 数据源
     *
     * @var DataSource
     */
    protected $source;
    
    /**
     * 表结构
     *
     * @var TableStruct
     */
    protected $struct;
    
    /**
     * 创建数据表
     *
     * @param DataSource $source
     * @param TableStruct $struct
     * @param Middleware $middleware
     */
    public function __construct(TableStruct $struct, DataSource $source, Middleware $middleware = null)
    {
        parent::__construct($source->write(), $middleware);
        $this->source = $source;
        $this->struct = $struct;
    }

    /**
     * 设置中间件
     *
     * @param Middleware $middleware
     * @return self
     */
    public function middleware(Middleware $middleware)
    {
        return $this->setMiddleware($middleware);
    }

    /**
     * 获取表结构
     *
     * @return \suda\orm\TableStruct
     */
    public function getStruct():TableStruct
    {
        return $this->struct;
    }

    /**
     * 获取最后一次插入的主键ID（用于自增值
     *
     * @param string $name
     * @return null|int 则获取失败，整数则获取成功
     */
    public function lastInsertId(string $name = null):?int
    {
        return $this->source->write()->lastInsertId();
    }

    /**
     * 事务系列，开启事务
     *
     * @return void
     */
    public function beginTransaction()
    {
        return $this->source->write()->beginTransaction();
    }

    /**
     * 事务系列，提交事务
     *
     * @return void
     */
    public function commit()
    {
        return $this->source->write()->commit();
    }

    /**
     * 事务系列，撤销事务
     *
     * @return void
     */
    public function rollBack()
    {
        return $this->source->write()->rollBack();
    }
    /**
     * 写
     *
     * @param mixed ...$args
     * @return WriteStatement
     */
    public function write(...$args):WriteStatement
    {
        return (new WriteStatement($this->source->write()->rawTableName($this->struct->getName()), $this->struct))->write(...$args);
    }

    /**
     * 读
     *
     * @param mixed ...$args
     * @return ReadStatement
     */
    public function read(...$args):ReadStatement
    {
        return (new ReadStatement($this->source->write()->rawTableName($this->struct->getName()), $this->struct))->want(...$args);
    }

    /**
     * 运行SQL语句
     *
     * @param Statement $statement
     * @return mixed
     */
    public function run(Statement $statement)
    {
        $connection = $statement->isRead() ? $this->source->read() : $this->source->write();
        $this->runStatement($this->connection, $statement);
        return $this->resultFrom($statement);
    }

    /**
     * 处理一行数据
     *
     * @param array $data
     * @return TableStruct
     */
    protected function fetchOneProccess(array $data):TableStruct
    {
        if ($this->middleware !== null) {
            foreach ($data as $name => $value) {
                $data[$name] = $this->middleware->output($name, $value);
            }
        }
        return $this->struct->createOne($data);
    }

    /**
     * 处理多行数据
     *
     * @param ReadStatement $statement
     * @param array $data
     * @return array
     */
    protected function fetchAllProccess(ReadStatement $statement, array $data): array
    {
        foreach ($data as $index => $row) {
            $row = $this->fetchOneProccess($row);
            $row = $this->middleware->outputRow($row);
            $key = $statement->getWithKey();
            if ($key === null) {
                $data[$index] = $row;
            } else {
                unset($data[$index]);
                $data[$row[$key]] = $row;
            }
        }
        return $data;
    }

    /**
     * 取结果
     *
     * @param Statement $statement
     * @return mixed
     */
    protected function fetchResult(Statement $statement)
    {
        if ($statement->isFetchOne()) {
            return $this->fetchOneProccess($statement->getStatement()->fetch(PDO::FETCH_ASSOC));
        } elseif ($statement->isFetchAll()) {
            return $this->fetchAllProccess($statement, $statement->getStatement()->fetchAll(PDO::FETCH_ASSOC));
        }
    }


    /**
     * Get 中间件
     *
     * @return  DataSource
     */
    public function getSource():DataSource
    {
        return $this->source;
    }
}