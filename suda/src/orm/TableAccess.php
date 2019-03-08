<?php
namespace suda\orm;

use PDO;
use PDOStatement;

use suda\orm\Binder;
use suda\orm\DataSource;



use suda\orm\TableStruct;

use suda\orm\observer\Observer;
use suda\orm\statement\Statement;
use suda\orm\connection\Connection;
use suda\orm\middleware\Middleware;
use suda\orm\observer\NullObserver;
use suda\orm\exception\SQLException;
use suda\orm\statement\ReadStatement;
use suda\orm\statement\WriteStatement;
use suda\orm\middleware\NullMiddleware;

class TableAccess
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
     * 中间件
     *
     * @var Middleware
     */
    protected $middleware;

    /**
     * 性能观测
     *
     * @var Observer
     */
    protected $observer;

    /**
     * 创建数据表
     *
     * @param DataSource $source
     * @param TableStruct $struct
     * @param Middleware $middleware
     */
    public function __construct(TableStruct $struct, DataSource $source, Middleware $middleware = null)
    {
        $this->source = $source;
        $this->struct = $struct;
        $this->middleware = $middleware ?: new NullMiddleware;
        $this->observer = new NullObserver;
    }

    /**
     * 运行SQL语句
     *
     * @param Statement $statement
     * @return mixed
     */
    public function run(Statement $statement)
    {
        $source = $statement->isRead() ? $this->source->read() : $this->source->write();
        $this->runStatement($source, $statement);
        if ($statement->isWrite()) {
            return $statement->getStatement()->rowCount() > 0;
        } elseif ($statement->isFetch()) {
            return $this->fetchResult($statement);
        }
        return null;
    }

    /**
     * 设置中间件
     *
     * @param Middleware $middleware
     * @return self
     */
    public function middleware(Middleware $middleware)
    {
        $this->middleware = $middleware;
        return $this;
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
    public function begin()
    {
        $this->beginTransaction();
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
     * 创建SQL语句
     *
     * @param Connection $source
     * @param Statement $statement
     * @return PDOStatement
     */
    protected function createStmt(Connection $source, Statement $statement): PDOStatement
    {
        if ($statement->scroll() === true) {
            return $source->getPdo()->prepare($statement->getString(), [PDO::ATTR_CURSOR => PDO::CURSOR_SCROLL]);
        } else {
            return $source->getPdo()->prepare($statement->getString());
        }
    }

    /**
     * 绑定值
     *
     * @param PDOStatement $stmt
     * @param Statement $statement
     * @return void
     */
    protected function bindStmt(PDOStatement $stmt, Statement $statement)
    {
        foreach ($statement->getBinder() as $binder) {
            if ($binder->getKey() !== null) {
                $value = $this->middleware->input($binder->getKey(), $binder->getValue());
                $stmt->bindValue($binder->getName(), $value, Binder::build($value));
            } else {
                $stmt->bindValue($binder->getName(), $binder->getValue(), Binder::build($binder->getValue()));
            }
        }
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
     * 运行语句
     *
     * @param Statement $statement
     * @return void
     */
    protected function runStatement(Connection $source, Statement $statement)
    {
        if ($statement->scroll() && $this->getStatement() !== null) {
            $stmt = $this->getStatement();
        } else {
            $stmt = $this->createStmt($source, $statement);
            $this->bindStmt($stmt, $statement);
            $statement->setStatement($stmt);
            $start = \microtime(true);
            $status = $stmt->execute();
            $this->observer->observe($statement, \microtime(true) - $start, $status);
            if ($status === false) {
                throw new SQLException($stmt->errorInfo()[2], intval($stmt->errorCode()));
            }
        }
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
     * Get 数据源
     *
     * @return  DataSource
     */
    public function getSource():DataSource
    {
        return $this->source;
    }

    /**
     * Get 表结构
     *
     * @return  TableStruct
     */
    public function getStruct():TableStruct
    {
        return $this->struct;
    }

    /**
     * Get 中间件
     *
     * @return  Middleware
     */
    public function getMiddleware():Middleware
    {
        return $this->middleware;
    }

    /**
     * Get 性能观测
     *
     * @return  Observer
     */
    public function getObserver():Observer
    {
        return $this->observer;
    }

    /**
     * Set 性能观测
     *
     * @param  Observer  $observer  性能观测
     *
     * @return  self
     */
    public function setObserver(Observer $observer)
    {
        $this->observer = $observer;

        return $this;
    }
}
