# suda: nebula

[![Latest Stable Version](https://poser.pugx.org/dxkite/suda/v/stable)](https://packagist.org/packages/dxkite/suda)
[![PHP >= 7.2](https://img.shields.io/badge/php-%3E%3D7.2-8892BF.svg)](https://php.net/)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/dxkite/suda/badges/quality-score.png)](https://scrutinizer-ci.com/g/dxkite/suda)
[![Total Downloads](https://poser.pugx.org/dxkite/suda/downloads)](https://packagist.org/packages/dxkite/suda)
[![License](https://poser.pugx.org/dxkite/suda/license)](https://packagist.org/packages/dxkite/suda)

高性能、轻量化Web框架，文档 [dxkite.github.io/suda](https://dxkite.github.io/suda/)

## 特性

- 标准化请求以及响应处理
- 响应包装器
- 事件监控器
- 读写分离与ORM
- 多缓存支持
- 模块化支持
- 标准化日志接口

## CS Fixer Rules

```
@PSR2,dir_constant,final_internal_class,is_null,line_ending,lowercase_static_reference,no_empty_statement,no_multiline_whitespace_around_double_arrow,no_unset_cast,single_quote,binary_operator_spaces
```

# PHP 特性使用情况


| 特性 |  版本 | 项目使用情况 | 备注 |
|-----|------|----|---|
| 允许重写抽象方法 | 7.2 | × | 可能会使用 |
| PDOStatement::debugDumpParams() | 7.2 | × | 可能会使用 |
| object 类型 | 7.2 | × | 可能会使用 |
| 可为空（Nullable）类型 | 7.1  | √ | |
| Symmetric array destructuring | 7.1 | √ | |
| list() 支持键名 | 7.1 | √ |  |
| 短数组声明 | 7.0 | √ |  |
| 返回值类型声明 |7.0 | √ |  |
| null合并运算符 |7.0 | √ |  |
| 匿名类 | 7.0 | √ |  |