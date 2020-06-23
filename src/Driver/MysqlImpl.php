<?php
namespace Be\Db4\Driver;

use Be\Db4\Driver;
use Be\Db4\DbException;

/**
 * 数据库类
 */
class MysqlImpl extends Driver
{

    /**
     * 连接数据库
     *
     * @return \PDO 连接
     * @throws DbException
     */
    public function connect()
    {
        if ($this->connection === null) {
            $config = $this->config;

            $options = array(
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION
            );

            if (isset($config['options'])) {
                $options = $config['options'] + $options;
            }

            $dsn = null;
            if (isset($config['dsn']) && $config['dsn']) {
                $dsn = $config['dsn'];
            } else {
                $dsn = 'mysql:dbname=' . $config['name'] . ';host=' . $config['host'] . ';port=' . $config['port'] . ';charset=utf8';
            }

            $connection = new \PDO($dsn, $config['user'], $config['pass'], $options);
            if (!$connection) throw new DbException('连接 数据库' . $config['name'] . '（' . $config['host'] . '） 失败！');

            // 设置默认编码为 UTF-8 ，UTF-8 为 PHPBE 默认字符集编码
            $connection->query('SET NAMES utf8');

            $this->connection = $connection;
        }

        return $this->connection;
    }

    /**
     * 预编译 sql 语句
     *
     * @param string $sql 查询语句
     * @param array $options 参数
     * @return \PDOStatement
     * @throws DbException | \PDOException | \Exception
     */
    public function prepare($sql, array $options = null)
    {
        if ($this->connection === null) $this->connect();

        try {
            $statement = null;
            if ($options === null) {
                $statement = $this->connection->prepare($sql);
            } else {
                $statement = $this->connection->prepare($sql, $options);
            }
            return $statement;
        } catch (\PDOException $e) {
            /*
             * 当错误码为2006/2013，且没有事务时，重连数据库，
             */
            if (($e->errorInfo[1] == 2006 || $e->errorInfo[1] == 2013) && $this->transactions == 0) {
                $this->close();
                return $this->prepare($sql, $options);
            }

            throw $e;
        } catch (\Exception $e) {

            if ($this->transactions == 0) {

                $errors = [
                    'server has gone away'
                ];

                $break = false;
                $message = $e->getMessage();
                foreach ($errors as $error) {
                    if (strpos($message, $error) !== false) {
                        $break = true;
                        break;
                    }
                }

                if ($break) {
                    $this->close();
                    return $this->prepare($sql, $options);
                }

            }

            throw $e;
        }
    }

    /**
     * 执行 sql 语句
     *
     * @param string $sql 查询语句
     * @param array $bind 占位参数
     * @param array $prepareOptions 参数
     * @return \PDOStatement
     * @throws DbException | \PDOException | \Exception
     */
    public function execute($sql, array $bind = null, array $prepareOptions = null)
    {

        try {

            if ($bind === null) {
                if ($this->connection === null) $this->connect();
                $statement = $this->connection->query($sql);
            } else {
                $statement = $this->prepare($sql, $prepareOptions);
                $statement->execute($bind);
            }
            return $statement;

        } catch (\PDOException $e) {
            /*
             * 当错误码为2006/2013，且没有事务时，重连数据库，
             */
            if (($e->errorInfo[1] == 2006 || $e->errorInfo[1] == 2013) && $this->transactions == 0) {
                $this->close();
                return $this->execute($sql, $bind, $prepareOptions);
            }

            throw $e;
        } catch (\Exception $e) {

            if ($this->transactions == 0) {

                $errors = [
                    'server has gone away'
                ];

                $break = false;
                $message = $e->getMessage();
                foreach ($errors as $error) {
                    if (strpos($message, $error) !== false) {
                        $break = true;
                        break;
                    }
                }

                if ($break) {
                    $this->close();
                    return $this->execute($sql, $bind, $prepareOptions);
                }

            }

            throw $e;
        }
    }

    /**
     * 执行 sql 语句
     *
     * @param string $sql 查询语句
     * @return int 影响的行数
     * @throws DbException | \PDOException | \Exception
     */
    public function query($sql, array $bind = null, array $prepareOptions = null)
    {
        try {
            $statement = $this->execute($sql, $bind, $prepareOptions);
            $effectLines = $statement->rowCount();
            $statement->closeCursor();
            return $effectLines;
        } catch (\PDOException $e) {

            /*
             * 当错误码为2006/2013，且没有事务时，重连数据库，
             */
            if (($e->errorInfo[1] == 2006 || $e->errorInfo[1] == 2013) && $this->transactions == 0) {
                $this->close();
                return $this->query($sql, $bind, $prepareOptions);
            }

            throw $e;

        } catch (\Exception $e) {

            if ($this->transactions == 0) {
                $errors = [
                    'server has gone away'
                ];

                $break = false;
                $message = $e->getMessage();
                foreach ($errors as $error) {
                    if (strpos($message, $error) !== false) {
                        $break = true;
                        break;
                    }
                }

                if ($break) {
                    $this->close();
                    return $this->query($sql, $bind, $prepareOptions);
                }
            }

            throw $e;
        }
    }

    /**
     * 插入一个对象到数据库
     *
     * @param string $table 表名
     * @param array | object $object 要插入数据库的对象或对象数组，对象属性需要和该表字段一致
     * @return int 插入的主键ID
     * @throws DbException
     */
    public function insert($table, $object)
    {
        $vars = null;
        if (is_array($object)) {
            $vars = $object;
        } elseif (is_object($object)) {
            $vars = get_object_vars($object);
        } else {
            throw new DbException('插入的数据格式须为对象或数组');
        }

        $fields = [];
        foreach (array_keys($vars) as $field) {
            $fields[] = $this->quoteKey($field);
        }

        $sql = 'INSERT INTO ' . $this->quoteKey($table) . '(' . implode(',', $fields) . ') VALUES(' . implode(',', array_fill(0, count($vars), '?')) . ')';
        $statement = $this->execute($sql, array_values($vars));
        $statement->closeCursor();
        return $this->getLastInsertId();
    }

    /**
     * 批量插入多个对象到数据库
     *
     * @param string $table 表名
     * @param array $objects 要插入数据库的对象数组，对象属性需要和该表字段一致
     * @return array 批量插入的ID列表
     * @throws DbException
     */
    public function insertMany($table, $objects)
    {
        if (!is_array($objects) || count($objects) == 0) return [];

        $ids = [];
        reset($objects);
        $object = current($objects);
        $vars = null;
        if (is_array($object)) {
            $vars = $object;
        } elseif (is_object($object)) {
            $vars = get_object_vars($object);
        } else {
            throw new DbException('批量插入的数据格式须为对象或数组');
        }
        ksort($vars);

        $fields = [];
        foreach (array_keys($vars) as $field) {
            $fields[] = $this->quoteKey($field);
        }

        $sql = 'INSERT INTO ' . $this->quoteKey($table) . '(' . implode(',', $fields) . ') VALUES(' . implode(',', array_fill(0, count($vars), '?')) . ')';
        $statement = $this->prepare($sql);
        foreach ($objects as $o) {
            $vars = null;
            if (is_array($o)) {
                $vars = $o;
            } elseif (is_object($o)) {
                $vars = get_object_vars($o);
            } else {
                throw new DbException('批量插入的数据格式须为对象或数组');
            }
            ksort($vars);
            $statement->execute(array_values($vars));
            $ids[] = $this->getLastInsertId();
        }
        $statement->closeCursor();

        return $ids;
    }

    /**
     * 快速插入一个对象到数据库
     *
     * @param string $table 表名
     * @param array | object $object 要插入数据库的对象，对象属性需要和该表字段一致
     * @return int 插入的主键ID
     * @throws DbException
     */
    public function quickInsert($table, $object)
    {
        $effectLines = null;

        $vars = null;
        if (is_array($object)) {
            $vars = $object;
        } elseif (is_object($object)) {
            $vars = get_object_vars($object);
        } else {
            throw new DbException('快速插入的数据格式须为对象或数组');
        }

        $fields = [];
        foreach (array_keys($vars) as $field) {
            $fields[] = $this->quoteKey($field);
        }

        $sql = 'INSERT INTO ' . $this->quoteKey($table) . '(' . implode(',', $fields) . ') VALUES';
        $values = array_values($vars);
        foreach ($values as &$value) {
            if ($value !== null) {
                $value = $this->quoteValue($value);
            } else {
                $value = 'null';
            }
        }
        $sql .= '(' . implode(',', $values) . ')';
        $statement = $this->execute($sql);
        $statement->closeCursor();

        return $this->getLastInsertId();
    }

    /**
     * 快速批量插入多个对象到数据库
     *
     * @param string $table 表名
     * @param array $objects 要插入数据库的对象数组，对象属性需要和该表字段一致
     * @return int 影响的行数
     * @throws DbException
     */
    public function quickInsertMany($table, $objects)
    {
        if (!is_array($objects) || count($objects) == 0) return 0;

        reset($objects);
        $object = current($objects);
        $vars = null;
        if (is_array($object)) {
            $vars = $object;
        } elseif (is_object($object)) {
            $vars = get_object_vars($object);
        } else {
            throw new DbException('快速批量插入的数据格式须为对象或数组');
        }
        ksort($vars);


        $fields = [];
        foreach (array_keys($vars) as $field) {
            $fields[] = $this->quoteKey($field);
        }

        $sql = 'INSERT INTO ' . $this->quoteKey($table) . '(' . implode(',', $fields) . ') VALUES';
        foreach ($objects as $o) {
            $vars = null;
            if (is_array($o)) {
                $vars = $o;
            } elseif (is_object($o)) {
                $vars = get_object_vars($o);
            } else {
                throw new DbException('快速批量插入的数据格式须为对象或数组');
            }
            ksort($vars);
            $values = array_values($vars);
            foreach ($values as &$value) {
                if ($value !== null) {
                    $value = $this->quoteValue($value);
                } else {
                    $value = 'null';
                }
            }
            $sql .= '(' . implode(',', $values) . '),';
        }
        $sql = substr($sql, 0, -1);
        $statement = $this->execute($sql);
        $effectLines = $statement->rowCount();
        $statement->closeCursor();

        return $effectLines;
    }

    /**
     * 更新一个对象到数据库
     *
     * @param string $table 表名
     * @param array | object $object 要插入数据库的对象，对象属性需要和该表字段一致
     * @param null | string | array $primaryKey 主键或指定键名更新，未指定时自动取表的主键
     * @return int 影响的行数
     * @throws DbException
     */
    public function update($table, $object, $primaryKey = null)
    {
        $fields = [];
        $fieldValues = [];

        $where = [];
        $whereValue = [];

        if ($primaryKey === null) {
            $primaryKey = $this->getTablePrimaryKey($table);
            if ($primaryKey === null) {
                throw new DbException('新数据表' . $table . '无主键，不支持按主键更新！');
            }
        }

        $vars = null;
        if (is_array($object)) {
            $vars = $object;
        } elseif (is_object($object)) {
            $vars = get_object_vars($object);
        } else {
            throw new DbException('更新的数据格式须为对象或数组');
        }

        foreach ($vars as $key => $value) {
            if (is_array($value) || is_object($value)) {
                continue;
            }

            if (is_array($primaryKey)) {

                if (in_array($key, $primaryKey)) {
                    $where[] = $this->quoteKey($key) . '=?';
                    $whereValue[] = $value;
                    continue;
                }

            } else {

                // 主键不更新
                if ($key == $primaryKey) {
                    $where[] = $this->quoteKey($key) . '=?';
                    $whereValue[] = $value;
                    continue;
                }
            }

            $fields[] = $this->quoteKey($key) . '=?';
            $fieldValues[] = $value;
        }

        if (!$where) {
            throw new DbException('更新数据时未指定条件！');
        }

        $sql = 'UPDATE ' . $this->quoteKey($table) . ' SET ' . implode(',', $fields) . ' WHERE ' . implode(' AND ', $where);
        $fieldValues = array_merge($fieldValues, $whereValue);

        $statement = $this->execute($sql, $fieldValues);
        $effectLines = $statement->rowCount();
        $statement->closeCursor();

        return $effectLines;
    }

    /**
     * 快速更新一个对象到数据库
     *
     * @param string $table 表名
     * @param array | object $object 要插入数据库的对象，对象属性需要和该表字段一致
     * @param null | string | array $primaryKey 主键或指定键名更新，未指定时自动取表的主键
     * @return int 影响的行数
     * @throws DbException
     */
    public function quickUpdate($table, $object, $primaryKey = null)
    {
        $where = [];
        $fields = [];

        if ($primaryKey === null) {
            $primaryKey = $this->getTablePrimaryKey($table);
            if ($primaryKey === null) {
                throw new DbException('新数据表' . $table . '无主键，不支持按主键更新！');
            }
        }

        $vars = null;
        if (is_array($object)) {
            $vars = $object;
        } elseif (is_object($object)) {
            $vars = get_object_vars($object);
        } else {
            throw new DbException('更新的数据格式须为对象或数组');
        }

        foreach ($vars as $key => $value) {
            if (is_array($value) || is_object($value)) {
                continue;
            }

            if (is_array($primaryKey)) {

                if (in_array($key, $primaryKey)) {
                    $where[] = $this->quoteKey($key) . '=' . $this->quoteValue($value);
                    continue;
                }

            } else {

                // 主键不更新
                if ($key == $primaryKey) {
                    $where[] = $this->quoteKey($key) . '=' . $this->quoteValue($value);
                    continue;
                }
            }

            $fields[] = $this->quoteKey($key) . '=' . $this->quoteValue($value);
        }

        if (!$where) {
            throw new DbException('更新数据时未指定条件！');
        }

        $sql = 'UPDATE ' . $this->quoteKey($table) . ' SET ' . implode(',', $fields) . ' WHERE ' . implode(' AND ', $where);
        $statement = $this->execute($sql);
        $effectLines = $statement->rowCount();
        $statement->closeCursor();

        return $effectLines;
    }

    /**
     * 批量更新多个对象到数据库
     *
     * @param string $table 表名
     * @param array $objects $object 要更新的对象数组，对象属性需要和该表字段一致
     * @param null | string | array $primaryKey 主键或指定键名更新，未指定时自动取表的主键
     * @return int 影响的行数
     * @throws DbException
     */
    public function updateMany($table, $objects, $primaryKey = null)
    {
        if (!is_array($objects) || count($objects) == 0) return 0;

        if ($primaryKey === null) {
            $primaryKey = $this->getTablePrimaryKey($table);
            if ($primaryKey === null) {
                throw new DbException('新数据表' . $table . '无主键，不支持按主键更新！');
            }
        }

        reset($objects);
        $object = current($objects);
        $vars = null;
        if (is_array($object)) {
            $vars = $object;
        } elseif (is_object($object)) {
            $vars = get_object_vars($object);
        } else {
            throw new DbException('批量更新的数据格式须为对象或数组');
        }
        ksort($vars);

        $fields = [];
        $where = [];
        foreach ($vars as $key => $value) {
            if (is_array($value) || is_object($value)) {
                continue;
            }

            if (is_array($primaryKey)) {
                if (in_array($key, $primaryKey)) {
                    $where[] = $this->quoteKey($key) . '=?';
                    continue;
                }
            } else {
                // 主键不更新
                if ($key == $primaryKey) {
                    $where[] = $this->quoteKey($key) . '=?';
                    continue;
                }
            }

            $fields[] = $this->quoteKey($key) . '=?';
        }

        if (!$where) {
            throw new DbException('更新数据时未指定条件！');
        }

        $sql = 'UPDATE ' . $this->quoteKey($table) . ' SET ' . implode(',', $fields) . ' WHERE ' . implode(' AND ', $where);
        $statement = $this->prepare($sql);

        $effectLines = 0;
        foreach ($objects as $o) {
            $vars = null;
            if (is_array($o)) {
                $vars = $o;
            } elseif (is_object($o)) {
                $vars = get_object_vars($o);
            } else {
                throw new DbException('批量更新的数据格式须为对象或数组');
            }
            ksort($vars);

            $fieldValues = [];
            $whereValue = [];
            foreach ($vars as $key => $value) {
                if (is_array($value) || is_object($value)) {
                    continue;
                }

                if (is_array($primaryKey)) {

                    if (in_array($key, $primaryKey)) {
                        $whereValue[] = $value;
                        continue;
                    }

                } else {

                    // 主键不更新
                    if ($key == $primaryKey) {
                        $whereValue[] = $value;
                        continue;
                    }
                }

                $fieldValues[] = $value;
            }

            if (count($whereValue) != count($where)) {
                throw new DbException('批量更新的数组未包含必须的主键名！');
            }

            if (count($fieldValues) != count($fields)) {
                throw new DbException('批量更新的数组内部结构不一致！');
            }

            $fieldValues = array_merge($fieldValues, $whereValue);
            $statement->execute(array_values($fieldValues));
            $effectLines += $statement->rowCount();
        }
        $statement->closeCursor();

        return $effectLines;
    }

    /**
     * 快速批量更新多个对象到数据库
     *
     * @param string $table 表名
     * @param array $objects 要快速批量更新的对象数组，对象属性需要和该表字段一致
     * @param null | string | array $primaryKey 主键或指定键名更新，未指定时自动取表的主键
     * @return int 影响的行数
     * @throws DbException
     */
    public function quickUpdateMany($table, $objects, $primaryKey = null)
    {
        if (!is_array($objects) || count($objects) == 0) return 0;

        if ($primaryKey === null) {
            $primaryKey = $this->getTablePrimaryKey($table);
            if ($primaryKey === null) {
                throw new DbException('新数据表' . $table . '无主键，不支持按主键快速批量更新！');
            }
        }

        // 获取第一条记灵的结构作为更新的数据结构
        reset($objects);
        $object = current($objects);
        $vars = null;
        if (is_array($object)) {
            $vars = $object;
        } elseif (is_object($object)) {
            $vars = get_object_vars($object);
        } else {
            throw new DbException('快速批量更新的数据结构须为对象或数组');
        }
        ksort($vars);
        $fields = array_keys($vars);

        // 检查主键值是否存在
        if (is_array($primaryKey)) {
            foreach ($primaryKey as $pKey) {
                if (!in_array($pKey, $fields)) {
                    throw new DbException('快速批量更新的数据结构中未包念主键' . $pKey . '！');
                }
            }
        } else {
            if (!in_array($primaryKey, $fields)) {
                throw new DbException('快速批量更新的数据结构中未包念主键' . $primaryKey . '！');
            }
        }

        $sql = 'UPDATE ' . $this->quoteKey($table) . ' SET ';

        $primaryKeyIn = [];
        $caseMapping = [];
        foreach ($fields as $field) {
            if (is_array($primaryKey)) {
                if (in_array($field, $primaryKey)) {
                    continue;
                }
            } else {
                if ($field == $primaryKey) {
                    continue;
                }
            }

            $caseMapping[$field] = [];
        }

        foreach ($objects as $o) {
            $vars = null;
            if (is_array($o)) {
                $vars = $o;
            } elseif (is_object($o)) {
                $vars = get_object_vars($o);
            } else {
                throw new DbException('批量更新的数据结构须为对象或数组');
            }
            ksort($vars);

            foreach ($fields as $field) {
                if (!isset($vars[$field])) {
                    throw new DbException('批量更新的数据结构不一致');
                }

                if (is_array($primaryKey)) {
                    if (in_array($field, $primaryKey)) {
                        continue;
                    }

                    $when = [];
                    foreach ($primaryKey as $pKey) {
                        $when[] = $this->quoteKey($pKey) . '=' . $this->quoteValue($vars[$pKey]);
                    }
                    $caseMapping[$field][] = 'WHEN ' . implode(' AND ', $when) . ' THEN ' . $this->quoteValue($vars[$field]);

                } else {
                    // 主键不更新
                    if ($field == $primaryKey) {
                        continue;
                    }
                    $caseMapping[$field][] = 'WHEN ' . $this->quoteValue($vars[$primaryKey]) . ' THEN ' . $this->quoteValue($vars[$field]);
                }
            }

            if (is_array($primaryKey)) {
                $in = [];
                foreach ($primaryKey as $pKey) {
                    $in[] = $this->quoteValue($vars[$pKey]);
                }
                $primaryKeyIn[] = '('.implode(',', $in).')';
            } else {
                $primaryKeyIn[] = $this->quoteValue($vars[$primaryKey]);
            }
        }

        foreach ($caseMapping as $field => $cases) {
            $sql .= $this->quoteKey($field) . ' = CASE ';

            if (!is_array($primaryKey)) {
                $sql .= $this->quoteKey($primaryKey) . ' ';
            }

            $sql .= implode(' ', $cases);
            $sql .= 'END,';
        }

        $sql = substr($sql, 0, -1);
        $sql .= ' WHERE ';
        if (is_array($primaryKey)) {
            $sql .= '(' . implode(',', $this->quoteKeys($primaryKey)) . ') IN ';
        } else {
            $sql .= $this->quoteKey($primaryKey) . ' IN ';
        }

        $sql .= '('.implode(',', $primaryKeyIn).')';

        $statement = $this->execute($sql);
        $effectLines = $statement->rowCount();
        $statement->closeCursor();

        return $effectLines;
    }

    /**
     * 替换一个对象到数据库
     *
     * @param string $table 表名
     * @param array | object $object 要替换的对象，对象属性需要和该表字段一致
     * @return int 影响的行数
     * @throws DbException
     */
    public function replace($table, $object)
    {
        $vars = null;
        if (is_array($object)) {
            $vars = $object;
        } elseif (is_object($object)) {
            $vars = get_object_vars($object);
        } else {
            throw new DbException('替换的数据格式须为对象或数组');
        }

        $fields = [];
        foreach (array_keys($vars) as $field) {
            $fields[] = $this->quoteKey($field);
        }

        $sql = 'REPLACE INTO ' . $this->quoteKey($table) . '(' . implode(',', $fields) . ') VALUES(' . implode(',', array_fill(0, count($vars), '?')) . ')';
        $statement = $this->execute($sql, array_values($vars));
        $effectLines = $statement->rowCount();
        $statement->closeCursor();

        return $effectLines;
    }

    /**
     * 批量替换多个对象到数据库
     *
     * @param string $table 表名
     * @param array $objects 要替换的对象数组，对象属性需要和该表字段一致
     * @return int 影响的行数
     * @throws DbException
     */
    public function replaceMany($table, $objects)
    {
        if (!is_array($objects) || count($objects) == 0) return 0;

        reset($objects);
        $object = current($objects);
        $vars = null;
        if (is_array($object)) {
            $vars = $object;
        } elseif (is_object($object)) {
            $vars = get_object_vars($object);
        } else {
            throw new DbException('批量替换的数据格式须为对象或数组');
        }
        ksort($vars);

        $effectLines = 0;

        $fields = [];
        foreach (array_keys($vars) as $field) {
            $fields[] = $this->quoteKey($field);
        }

        $sql = 'REPLACE INTO ' . $this->quoteKey($table) . '(' . implode(',', $fields) . ') VALUES(' . implode(',', array_fill(0, count($vars), '?')) . ')';
        $statement = $this->prepare($sql);
        foreach ($objects as $o) {
            $vars = null;
            if (is_array($o)) {
                $vars = $o;
            } elseif (is_object($o)) {
                $vars = get_object_vars($o);
            } else {
                throw new DbException('批量替换的数据格式须为对象或数组');
            }
            ksort($vars);
            $statement->execute(array_values($vars));
            $effectLines += $statement->rowCount();
        }
        $statement->closeCursor();

        return $effectLines;
    }

    /**
     * 快速替换一个对象到数据库
     *
     * @param string $table 表名
     * @param array | object $object 要替换的对象，对象属性需要和该表字段一致
     * @return int 影响的行数
     * @throws DbException
     */
    public function quickReplace($table, $object)
    {
        $vars = null;
        if (is_array($object)) {
            $vars = $object;
        } elseif (is_object($object)) {
            $vars = get_object_vars($object);
        } else {
            throw new DbException('快速替换的数据格式须为对象或数组');
        }

        $fields = [];
        foreach (array_keys($vars) as $field) {
            $fields[] = $this->quoteKey($field);
        }

        $sql = 'REPLACE INTO ' . $this->quoteKey($table) . '(' . implode(',', $fields) . ') VALUES';
        $values = array_values($vars);
        foreach ($values as &$value) {
            if ($value !== null) {
                $value = $this->quoteValue($value);
            } else {
                $value = 'null';
            }
        }
        $sql .= '(' . implode(',', $values) . ')';
        $statement = $this->execute($sql);
        $effectLines = $statement->rowCount();
        $statement->closeCursor();

        return $effectLines;
    }

    /**
     * 快速批量替换多个对象到数据库
     *
     * @param string $table 表名
     * @param array $objects 要替换的对象数组，对象属性需要和该表字段一致
     * @return int 影响的行数
     * @throws DbException
     */
    public function quickReplaceMany($table, $objects)
    {
        if (!is_array($objects) || count($objects) == 0) return 0;

        reset($objects);
        $object = current($objects);
        $vars = null;
        if (is_array($object)) {
            $vars = $object;
        } elseif (is_object($object)) {
            $vars = get_object_vars($object);
        } else {
            throw new DbException('快速批量替换的数据格式须为对象或数组');
        }
        ksort($vars);

        $fields = [];
        foreach (array_keys($vars) as $field) {
            $fields[] = $this->quoteKey($field);
        }

        $sql = 'REPLACE INTO ' . $this->quoteKey($table) . '(' . implode(',', $fields) . ') VALUES';
        foreach ($objects as $o) {
            $vars = null;
            if (is_array($o)) {
                $vars = $o;
            } elseif (is_object($o)) {
                $vars = get_object_vars($o);
            } else {
                throw new DbException('快速批量替换的数据格式须为对象或数组');
            }
            ksort($vars);
            $values = array_values($vars);
            foreach ($values as &$value) {
                if ($value !== null) {
                    $value = $this->quoteValue($value);
                } else {
                    $value = 'null';
                }
            }
            $sql .= '(' . implode(',', $values) . '),';
        }
        $sql = substr($sql, 0, -1);
        $statement = $this->execute($sql);
        $effectLines = $statement->rowCount();
        $statement->closeCursor();

        return $effectLines;
    }

    /**
     * 获取当前数据库所有表信息
     *
     * @return array
     */
    public function getTables()
    {
        return $this->getObjects('SHOW TABLE STATUS');
    }

    /**
     * 获取当前数据库所有表名
     *
     * @return array
     */
    public function getTableNames()
    {
        return $this->getValues('SHOW TABLES');
    }

    /**
     * 获取一个表的字段列表
     *
     * @param string $table 表名
     * @return array 对象数组
     * 字段对象典型结构
     * {
     *      'name' => '字段名',
     *      'type' => '类型',
     *      'length' => '长度',
     *      'precision' => '精度',
     *      'scale' => '长度',
     *      'comment' => '备注',
     *      'default' => '默认值',
     *      'nullAble' => '是否允许为空',
     * }
     */
    public function getTableFields($table)
    {
        $cacheKey = 'TableFields:' . $table;
        if (isset($this->cache[$cacheKey])) {
            return $this->cache[$cacheKey];
        }

        $fields = $this->getObjects('SHOW FULL FIELDS FROM ' . $this->quoteKey($table));

        $data = [];
        foreach ($fields as $field) {
            $type = '';
            $length = '';
            $precision = '';
            $scale = '';

            $pos = strpos($field->Type, '(');
            if ($pos !== false) {
                $type = strtolower(substr($field->Type, 0, $pos));
                switch ($type) {
                    case 'int':
                    case 'tinyint':
                    case 'smallint':
                    case 'mediumint':
                    case 'bigint':
                    case 'bit':

                    case 'char':
                    case 'varchar':

                    case 'year':
                        $length = substr($field->Type, $pos + 1, strpos($field->Type, ')') - $pos - 1);
                        break;
                    case 'decimal':

                        $str = substr($field->Type, $pos + 1, strpos($field->Type, ')') - $pos - 1);
                        $tmpPos = strpos($str, ',');
                        if ($tmpPos === false) {
                            $precision = $str;
                            $scale = 0;
                        } else {
                            $precision = substr($str, 0, $tmpPos);
                            $scale = substr($str, $tmpPos + 1);
                        }

                        break;
                }
            } else {
                $pos = strpos($field->Type, ' ');
                if ($pos !== false) {
                    $type = strtolower(substr($field->Type, 0, $pos));
                } else {
                    $type = strtolower($field->Type);
                }
            }

            $unsigned = false;
            if (in_array($type, [
                'int', 'tinyint', 'smallint', 'mediumint', 'bigint', 'float', 'double', 'decimal'
            ])) {
                if (strpos($field->Type, 'unsigned') !== false) {
                    $unsigned = true;
                }
            }

            $data[$field->Field] = [
                'name' => $field->Field,
                'type' => $type,
                'length' => $length,
                'precision' => $precision,
                'scale' => $scale,
                'comment' => $field->Comment,
                'default' => $field->Default,
                'nullAble' => $field->Null == 'YES' ? true : false,

                'unsigned' => $unsigned,
                'collation' => $field->Collation,
                'key' => $field->Key,
                'extra' => $field->Extra,
                'privileges' => $field->Privileges,
            ];
        }

        $this->cache[$cacheKey] = $data;
        return $data;
    }

    /**
     * 获取指定表的主银
     *
     * @param string $table 表名
     * @return string | array | null
     */
    public function getTablePrimaryKey($table)
    {
        $tableFields = $this->getTableFields($table);

        $primaryKeys = [];
        foreach ($tableFields as $tableField) {
            if ($tableField['key'] == 'PRI') {
                $primaryKeys[] = $tableField['name'];
            }
        }

        $count = count($primaryKeys);
        if ($count > 1) {
            return $primaryKeys;
        } elseif ($count == 1) {
            return $primaryKeys[0];
        }

        return null;
    }

    /**
     * 删除表
     *
     * @param string $table 表名
     */
    public function dropTable($table)
    {
        $this->query('DROP TABLE IF EXISTS ' . $this->quoteKey($table));
    }

    /**
     * 处理插入数据库的字段名或表名
     *
     * @param string $field
     * @return string
     */
    public function quoteKey($field)
    {
        if (strpos($field, '.')) {
            $field = str_replace('.', '`.`', $field);
        }

        return '`' . $field . '`';
    }

    /**
     * 处理插入数据库的字符串值，防注入, 仅处理敏感字符，不加外层引号，
     * 与 quote 方法的区别可以理解为 quote 比 escape 多了最外层的引号
     *
     * @param string $value
     * @return string
     */
    public function escape($value)
    {
        return addslashes($value);
    }


}
