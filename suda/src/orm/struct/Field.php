<?php
namespace suda\orm\struct;

/**
 * 数据表字段创建工具
 * 用于创建数据表字段
 */
class Field
{
    // AUTO_INCREMENT
    protected $auto;
    // COMMENT
    protected $comment;
    protected $key; //primary unique index
    // foreign key
    protected $foreign;
 
    protected $name;
    protected $type;
    protected $length;
    protected $default;
    protected $isDefault;
    
    protected $null; // nullable
    protected $attribute; // binary unsigned
    protected $collation;
    protected $tableName;
    protected $charset;
    
    const BINARY = 'BINARY';
    const UNSIGNED = 'UNSIGNED';
    
    const UNIQUE = 'UNIQUE';
    const PRIMARY = 'PRIMARY';
    const INDEX = 'INDEX';
    const KEY = 'KEY';

    public function __construct(string $tableName, string $name, string $type, int $length = null)
    {
        $this->tableName = $tableName;
        $this->name = $name;
        $this->type = strtoupper($type);
        $this->length = $length;
        $this->isDefault = false;
    }

    public function charset(string $charset)
    {
        $this->charset = 'CHARACTER SET ' . $charset;
        return $this;
    }

    public function comment(string $comment)
    {
        $this->comment = $comment;
        return $this;
    }

    public function length(int $length)
    {
        $this->length = $length;
        return $this;
    }
    
    public function key()
    {
        $this->key = self::KEY;
        return $this;
    }

    public function primary()
    {
        $this->key = self::PRIMARY;
        return $this;
    }
    public function index()
    {
        $this->key = self::INDEX;
        return $this;
    }
    
    public function unique()
    {
        $this->key = self::UNIQUE;
        return $this;
    }

    public function collate(string $collate)
    {
        $this->collation = $collate;
        return $this;
    }
    
    public function auto()
    {
        $this->auto = true;
        return $this;
    }

    public function foreign(Field $field)
    {
        $this->foreign = $field;
        $this->type = $field->type;
        $this->length = $field->length;
        $this->default = null;
        if ($field->attribute) {
            $this->attribute = $field->attribute;
        }
        return $this;
    }

    public function null(bool $set = true)
    {
        $this->null = $set;
        return $this;
    }

    public function default($value)
    {
        $this->isDefault = true;
        $this->default = $value;
        if (null === $value) {
            $this->null = true;
        }
        return $this;
    }

    public function binary()
    {
        $this->attribute = self::BINARY;
        return $this;
    }

    public function unsigned()
    {
        $this->attribute = self::UNSIGNED;
        return $this;
    }


    /**
     * Get the value of auto
     */
    public function getAuto()
    {
        return $this->auto;
    }

    /**
     * Get the value of comment
     */
    public function getComment()
    {
        return $this->comment;
    }

    /**
     * Get the value of key
     */
    public function getType()
    {
        return $this->key;
    }

    /**
     * Get the value of foreign
     */
    public function getForeignKey()
    {
        return $this->foreign;
    }

    /**
     * Get the value of name
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Get the value of type
     */
    public function getValueType()
    {
        return $this->type;
    }

    /**
     * Get the value of length
     */
    public function getLength()
    {
        return $this->length;
    }

    /**
     * Get the value of default
     */
    public function getDefault()
    {
        return $this->default;
    }

    /**
     * Get the value of isDefault
     */
    public function isDefault()
    {
        return $this->isDefault;
    }

    /**
     * Get the value of null
     */
    public function getNull()
    {
        return $this->null;
    }

    /**
     * Get the value of attribute
     */
    public function getAttribute()
    {
        return $this->attribute;
    }

    /**
     * Get the value of collation
     */
    public function getCollation()
    {
        return $this->collation;
    }

    /**
     * Get the value of tableName
     */
    public function getTableName()
    {
        return $this->tableName;
    }

    /**
     * Get the value of charset
     */
    public function getCharset()
    {
        return $this->charset;
    }
}
