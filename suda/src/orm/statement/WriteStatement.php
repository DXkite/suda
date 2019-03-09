<?php
namespace suda\orm\statement;

use suda\orm\Binder;
use suda\orm\TableStruct;
use suda\orm\statement\Statement;
use suda\orm\exception\SQLException;
use suda\orm\statement\PrepareTrait;

class WriteStatement extends Statement
{
    use PrepareTrait;

    /**
     * 数据
     *
     * @var array
     */
    protected $data;

    /**
     * 数据表结构
     *
     * @var TableStruct
     */
    protected $struct;

    /**
     * 条件更新
     *
     * @var array|null
     */
    protected $where = null;

    /**
     * 数据原始表名
     *
     * @var string
     */
    protected $table;

    /**
     * 条件
     *
     * @var string|null
     */
    protected $whereCondition = null;

    /**
     * 创建写
     *
     * @param string $rawTableName
     * @param TableStruct $struct
     */
    public function __construct(string $rawTableName, TableStruct $struct)
    {
        $this->type = self::WRITE;
        $this->struct = $struct;
        $this->table = $rawTableName;
    }

    /**
     * 写数据
     *
     * @param string|array $name
     * @param array $value
     * @return self
     */
    public function write($name, $value = null)
    {
        if (is_array($name)) {
            foreach ($name as $key => $value) {
                $this->write($key, $value);
            }
        } else {
            if ($this->struct->getFields()->hasField($name)) {
                $this->data[$name] = $value;
            } else {
                throw new SQLException(\sprintf('table has no fields %s', $this->struct->getName()));
            }
        }
        return $this;
    }

    /**
     * 条件查询
     *
     * @param string|array $where
     * @param array $whereParameter
     * @return self
     */
    public function where($where, array $whereParameter = [])
    {
        if (\is_string($where)) {
            $this->whereCondition($where, $whereParameter);
        } else {
            $this->where = $where;
        }
        return $this;
    }

    /**
     * 获取字符串
     *
     * @return void
     */
    public function prepare()
    {
        if ($this->where !== null) {
            list($updateSet, $upbinder) = $this->prepareUpdateSet($this->data);
            list($where, $wherebinder) = $this->parepareWhere($this->where);
            $this->string = "UPDATE {$this->table} SET {$updateSet} WHERE {$where}";
            $this->binder = array_merge($upbinder, $wherebinder);
        } elseif ($this->whereCondition !== null) {
            list($updateSet, $upbinder) = $this->prepareUpdateSet($this->data);
            $this->binder = array_merge($this->binder, $upbinder);
            $this->string = "UPDATE {$this->table} SET {$updateSet} WHERE {$this->whereCondition}";
        } else {
            $this->parepareInsert($this->data);
        }
    }

    protected function whereCondition(string $where, array $whereParameter)
    {
        $this->whereCondition = $where;
        if (\is_array($whereParameter)) {
            foreach ($whereParameter as $key => $value) {
                $this->binder[] = new Binder($key, $value);
            }
        }
    }

    protected function prepareUpdateSet(array $data)
    {
        $binders = [];
        $sets = [];
        foreach ($data as $name => $value) {
            $_name = Binder::index($name);
            $binders[] = new Binder($_name, $value, $name);
            $sets[] = "`{$name}`=:{$_name}";
        }
        return [\implode(',', $sets), $binders];
    }


    public function parepareInsert(array $data)
    {
        $names = [];
        $binds = [];
        foreach ($data as $name => $value) {
            $_name = Binder::index($name);
            $this->binder[] = new Binder($_name, $value, $name);
            $names[] = "`{$name}`";
            $binds[] = ":{$_name}";
        }
        $i_name = \implode(',', $names);
        $i_bind = \implode(',', $binds);
        $this->string = "INSERT INTO {$this->table} ({$i_name}) VALUES ({$i_bind})";
    }
}