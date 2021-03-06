<?php
namespace suda\database\statement;

use PDO;
use PDOStatement;
use suda\database\Binder;
use suda\database\DataSource;
use suda\database\connection\Connection;
use suda\database\middleware\Middleware;
use suda\database\exception\SQLException;
use suda\database\middleware\NullMiddleware;

class QueryAccess
{
    /**
     * 数据源
     *
     * @var DataSource
     */
    protected $source;

    /**
     * 中间件
     *
     * @var Middleware
     */
    protected $middleware;

    /**
     * 创建运行器
     *
     * @param DataSource $source
     * @param Middleware $middleware
     */
    public function __construct(DataSource $source, Middleware $middleware = null)
    {
        $this->source = $source;
        $this->middleware = $middleware ?: new NullMiddleware;
    }


    /**
     * 获取最后一次插入的主键ID（用于自增值
     *
     * @param string $name
     * @return string 则获取失败，整数则获取成功
     */
    public function lastInsertId(string $name = null):string
    {
        return $this->source->write()->lastInsertId($name);
    }

    /**
     * 事务系列，开启事务
     *
     * @return void
     */
    public function beginTransaction()
    {
        $this->source->write()->beginTransaction();
    }

    /**
     * 事务系列，提交事务
     *
     * @return void
     */
    public function commit()
    {
        $this->source->write()->commit();
    }

    /**
     * 事务系列，撤销事务
     *
     * @return void
     */
    public function rollBack()
    {
        $this->source->write()->rollBack();
    }

    /**
     * 运行SQL语句
     *
     * @param Statement $statement
     * @return mixed
     * @throws SQLException
     */
    public function run(Statement $statement)
    {
        $connection = $statement->isRead() ? $this->source->read() : $this->source->write();
        $this->runStatement($connection, $statement);
        return $this->createResult($connection, $statement);
    }


    /**
     * 获取运行结果
     *
     * @param $connection
     * @param Statement $statement
     * @return mixed
     * @throws SQLException
     */
    protected function createResult(Connection $connection, Statement $statement)
    {
        return (new QueryResult($connection, $this->middleware))->createResult($statement);
    }

    /**
     * 设置中间件
     *
     * @param Middleware $middleware
     * @return $this
     */
    public function setMiddleware(Middleware $middleware)
    {
        $this->middleware = $middleware;
        return $this;
    }

    /**
     * 创建SQL语句
     *
     * @param Connection $connection
     * @param Statement $statement
     * @return PDOStatement
     * @throws SQLException
     */
    protected function createPDOStatement(Connection $connection, Statement $statement): PDOStatement
    {
        $statement->prepare();
        $queryObj = $statement->getQuery();
        $query = $connection->prefix($queryObj->getQuery());
        if ($statement->isScroll()) {
            $stmt = $connection->getPdo()->prepare($query, [
                PDO::ATTR_CURSOR => PDO::CURSOR_SCROLL
            ]);
        } else {
            $stmt = $connection->getPdo()->prepare($query);
        }
        if ($stmt instanceof PDOStatement) {
            return $stmt;
        }
        throw new SQLException(sprintf('prepare error: %s', $statement->getString()), SQLException::ERR_PREPARE);
    }

    /**
     * 绑定值
     *
     * @param PDOStatement $stmt
     * @param Statement $statement
     * @return void
     */
    protected function bindPDOStatementValues(PDOStatement $stmt, Statement $statement)
    {
        foreach ($statement->getQuery()->getBinder() as $binder) {
            if ($binder->getKey() !== null) {
                $value = $this->middleware->input($binder->getKey(), $binder->getValue());
                $stmt->bindValue($binder->getName(), $value, Binder::typeOf($value));
            } else {
                $stmt->bindValue($binder->getName(), $binder->getValue(), Binder::typeOf($binder->getValue()));
            }
        }
    }

    /**
     * 运行语句
     *
     * @param Connection $connection
     * @param Statement $statement
     * @return void
     * @throws SQLException
     */
    protected function runStatement(Connection $connection, Statement $statement)
    {
        if ($statement->isScroll() && $statement->getStatement() !== null) {
            // noop
        } else {
            $stmt = $this->createPDOStatement($connection, $statement);
            $this->bindPDOStatementValues($stmt, $statement);
            $statement->setStatement($stmt);
            $start = microtime(true);
            $status = $stmt->execute();
            $statement->setSuccess($status);
            $connection->getObserver()->observe($this, $connection, $statement, microtime(true) - $start, $status);
            if ($status === false) {
                throw new SQLException(implode(':', $stmt->errorInfo()), intval($stmt->errorCode()));
            }
        }
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
     * 原始SQL
     * @param string $query
     * @param array $parameter
     * @return Query
     */
    public function raw(string $query, array $parameter = []) {
        return new Query($query, $parameter);
    }
}
