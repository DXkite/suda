<?php

namespace suda\orm\struct;

use function array_key_exists;
use function array_search;
use ArrayIterator;
use IteratorAggregate;
use Traversable;

/**
 * Class TableStruct
 * @package suda\orm\struct
 */
class TableStruct implements IteratorAggregate
{
    /**
     * 数据表名
     *
     * @var string
     */
    protected $name;

    /**
     * 字段集合
     *
     * @var Field[]
     */
    protected $fields;

    /**
     * 键值对映射
     *
     * @var array
     */
    protected $alias;

    /**
     * 创建字段集合
     *
     * @param string $name
     */
    public function __construct(string $name)
    {
        $this->name = $name;
        $this->fields = [];
    }

    /**
     * 新建表列
     *
     * @param string $name
     * @param string $type
     * @param int|array $length
     * @return Field
     */
    public function field(string $name, string $type, $length = null)
    {
        if ($length === null) {
            $this->fields[$name] = new Field($this->name, $name, $type);
        } else {
            $this->fields[$name] = new Field($this->name, $name, $type, $length);
        }
        return $this->fields[$name];
    }

    /**
     * @param string $name
     * @param string $type
     * @param mixed $length
     * @return Field
     */
    public function newField(string $name, string $type, $length = null)
    {
        return $this->field($name, $type, $length);
    }

    /**
     * @param string $name
     * @return Field|null
     */
    public function getField(string $name)
    {
        return $this->fields[$name] ?? null;
    }

    /**
     * 添加表结构字段
     *
     * @param array|Field $fields
     * @return $this
     */
    public function fields($fields)
    {
        if (!is_array($fields) && $fields instanceof Field) {
            $fields = func_get_args();
        }
        foreach ($fields as $field) {
            $this->addField($field);
        }
        return $this;
    }

    /**
     * @param string $name
     * @return bool
     */
    public function hasField(string $name)
    {
        return array_key_exists($name, $this->fields);
    }

    /**
     * @param Field $field
     */
    public function addField(Field $field)
    {
        if ($field->getTableName() != $this->name) {
            return;
        }
        $name = $field->getName();
        $this->fields[$name] = $field;
        $this->alias[$name] = $field->getAlias();
    }

    /**
     * @param string $name
     * @return string
     */
    public function outputName(string $name): string
    {
        if (array_key_exists($name, $this->alias)) {
            return $this->alias[$name];
        }
        return $name;
    }

    /**
     * @param string $name
     * @return string
     */
    public function inputName(string $name): string
    {
        if ($key = array_search($name, $this->alias)) {
            return $key;
        }
        return $name;
    }

    /**
     * @return array
     */
    public function getFieldsName()
    {
        return array_keys($this->fields);
    }

    /**
     * Get the value of name
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @param string $name
     */
    public function setName(string $name): void
    {
        $this->name = $name;
    }

    /**
     * Get the value of fields
     */
    public function all()
    {
        return $this->fields;
    }

    /**
     * @return ArrayIterator|Traversable
     */
    public function getIterator()
    {
        return new ArrayIterator($this->fields);
    }
}
