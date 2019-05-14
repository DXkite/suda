<?php
namespace suda\application\database\creator;

use ReflectionException;
use suda\orm\exception\SQLException;
use suda\orm\struct\Field;
use suda\orm\struct\TableStruct;
use suda\orm\connection\Connection;
use suda\orm\statement\QueryStatement;

/**
 * 数据表链接对象
 *
 */
class SQLiteTableCreator
{
    /**
     * 连接
     *
     * @var Connection
     */
    protected $connection;

    /**
     * 字段
     *
     * @var TableStruct
     */
    protected $fields;

    const ENGINE_MyISAM = 'MyISAM';
    const ENGINE_InnoDB = 'InnoDB';

    protected $name;
    protected $engine = self::ENGINE_InnoDB;
    protected $comment;

    protected $collate;
    protected $charset = 'utf8mb4';
    
    protected $auto;
    protected $foreignKeys;

    public function __construct(Connection $connection, TableStruct $fields)
    {
        $this->name = $fields->getName();
        $this->fields = $fields;
        $this->connection = $connection;
    }

    /**
     * @return bool
     * @throws SQLException
     * @throws ReflectionException
     */
    public function create()
    {
        $statement = new QueryStatement($this->toSQL());
        $statement->isWrite(true);
        return $this->connection->query($statement) > 0;
    }

    protected function seekField(Field $field)
    {
        $name = $field->getName();
        $this->fields[$name] = $field;
        if ($foreign = $field->getForeignKey()) {
            $this->foreignKeys[$name] = $foreign;
        }
        return $this;
    }


    protected function toSQL()
    {
        // TODO
        return '';
    }
}