<?php
namespace suda\framework\debug\attach;

use ReflectionClass;
use ReflectionException;
use Throwable;

/**
 * 打印
 */
trait DumpTrait
{
    protected static $dumpStringLength = 80;

    /**
     * @param object $object
     * @param int $deep
     * @return string
     */
    protected static function objectToString(object $object, int $deep):string
    {
        $objectName = get_class($object);
        $parameterString = '';
        if ($deep > 0) {
            $vars = get_class_vars($objectName);
            foreach ($vars as $key => $value) {
                $parameterString .= static::valueToString($key, $value, $deep);
            }
            $parameterString .= static::objectGetter($objectName, $object, $deep);
        } else {
            $parameterString = '...';
        }
        return $objectName.' {'.trim($parameterString, ',').'}';
    }

    /**
     * @param string $objectName
     * @param object $object
     * @param int $deep
     * @return string
     */
    protected static function objectGetter(string $objectName, object $object, int $deep)
    {
        try {
            $class = new ReflectionClass($objectName);
        } catch (ReflectionException $e) {
            return $objectName.'{error:'.$e->getMessage().'}';
        }
        $properties = $class->getProperties();
        $parameterString = '';
        foreach ($properties as $property) {
            if ($property->getDeclaringClass() === $class->getName()) {
                $property->setAccessible(true);
                $name = $property->getName();
                $value = $property->getValue($object);
                $parameterString .= static::valueToString($name, $value, $deep);
            }
        }
        return $parameterString;
    }
    
    protected static function arrayToString(array $array, int $deep)
    {
        $parameterString = '';
        if ($deep > 0) {
            foreach ($array as $key => $value) {
                $parameterString .= static::valueToString($key, $value, $deep);
            }
        } else {
            $parameterString = '...';
        }
        return '['.trim($parameterString, ',').']';
    }

    /**
     * 属性转换成字符串
     *
     * @param string $key
     * @param mixed $value
     * @param integer $deep
     * @return string
     */
    protected static function valueToString(string $key, $value, int $deep):string
    {
        if (is_string($value) && strlen($value) > static::$dumpStringLength) {
            return  $key.'='.json_encode(substr($value, 0, static::$dumpStringLength), JSON_UNESCAPED_UNICODE) .'... ,';
        } elseif (is_bool($value)) {
            return $key.'='.($value ? 'true' : 'false').' ,';
        } else {
            return $key.'='.self::parameterToString($value, $deep - 1) .' ,';
        }
    }


    public static function parameterToString($object, int $deep = 2)
    {
        if (null === $object) {
            return 'NULL';
        } elseif ($object instanceof Throwable) {
            return static::dumpThrowable($object);
        } elseif (is_object($object)) {
            return static::objectToString($object, $deep);
        } elseif (is_array($object)) {
            return static::arrayToString($object, $deep);
        }
        return $object;
    }

    public static function dumpTrace(array $backtrace, bool $str = true, string $perfix = '')
    {
        $tracesConsole = [];
        foreach ($backtrace as $trace) {
            $tracesConsole[] = static::buildTraceLine($trace);
        }
        if ($str) {
            $str = '';
            foreach ($tracesConsole as $trace_info) {
                $str .= $perfix.preg_replace('/\n/', "\n".$perfix."\t", $trace_info).PHP_EOL;
            }
            return $str;
        }
        return  $tracesConsole;
    }

    protected static function buildTraceLine(array $trace):string
    {
        $line = '';
        if (isset($trace['file'])) {
            $line = $trace['file'].':'.$trace['line'];
        }
        if (isset($trace['class'])) {
            $function = $trace['class'].$trace['type'].$trace['function'];
        } else {
            $function = $trace['function'];
        }
        $argsDump = '';
        if (!empty($trace['args'])) {
            foreach ($trace['args'] as $arg) {
                $argsDump .= self::parameterToString($arg) .',';
            }
            $argsDump = rtrim($argsDump, ',');
        }
        $line .= ' '.$function.'('.$argsDump.')';
        return $line;
    }

    public static function dumpThrowable(Throwable $e)
    {
        $dump = get_class($e).':'. $e->getMessage() .PHP_EOL;
        $dump .= 'At: ' . $e->getFile().':'.$e->getLine().PHP_EOL;
        $dump .= static::dumpTrace($e->getTrace());
        return $dump;
    }
}
