<?php
namespace suda\orm;

/**
 * 中间件
 * 处理数据输出输出
 */
interface Middleware 
{
    /**
     * 处理输入数据
     *
     * @param string $name
     * @param mixed $data
     * @return mixed
     */
    public function input(string $name, $data);

    /**
     * 处理输出数据
     *
     * @param string $name
     * @param mixed $data
     * @return mixed
     */
    public function output(string $name, $data);

    /**
     * 对输出列进行处理
     *
     * @param TableStruct $row
     * @return array
     */
    public function outputRow(TableStruct $row);
}
