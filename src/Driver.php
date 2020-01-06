<?php
namespace Be\Db4;

/**
 * 数据库类
 */
abstract class Driver
{
    /**
     * @var \PDO
     */
    protected $connection = null; // 数据库连接

    /**
     * @var \PDOStatement
     */
    protected $statement = null; // 预编译 sql

    protected $config = [];

    protected $transactions = 0; // 开启的事务数，防止嵌套

    protected $cache = [];

    public function __construct($config)
    {
        $this->config = $config;
    }

    /**
     * 连接数据库
     *
     * @return \PDO 连接
     * @throws DbException
     */
    public function connect()
    {
        return $this->connection;
    }

    /**
     * 关闭数据库连接
     *
     * @return bool 是否关闭成功
     */
    public function close()
    {
        if ($this->connection) $this->connection = null;
        return true;
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

        $statement = null;
        if ($options === null) {
            $statement = $this->connection->prepare($sql);
        } else {
            $statement = $this->connection->prepare($sql, $options);
        }
        return $statement;
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
        $statement = $this->prepare($sql, $prepareOptions);
        if ($bind === null) {
            $statement->execute();
        } else {
            $statement->execute($bind);
        }
        return $statement;
    }

    /**
     * 执行 sql 语句
     *
     * @param string $sql 查询语句
     * @return \PDOStatement
     * @throws DbException | \PDOException | \Exception
     */
    public function query($sql)
    {
        if ($this->connection === null) $this->connect();
        $statement = $this->connection->query($sql);
        return $statement;
    }

    /**
     * 返回单一查询结果, 多行多列记录时, 只返回第一行第一列
     *
     * @param string $sql 查询语句
     * @param array $bind 参数
     * @return string
     */
    public function getValue($sql, array $bind = null)
    {
        $statement = $bind === null ? $this->query($sql) : $this->execute($sql, $bind);
        $tuple = $statement->fetch(\PDO::FETCH_NUM);
        $statement->closeCursor();
        if ($tuple === false) return false;
        return $tuple[0];
    }

    /**
     * 返回查询单列结果的数组
     *
     * @param string $sql 查询语句
     * @param array $bind 参数
     * @return array
     */
    public function getValues($sql, array $bind = null)
    {
        $statement = $bind === null ? $this->query($sql) : $this->execute($sql, $bind);
        $values = $statement->fetchAll(\PDO::FETCH_COLUMN);
        $statement->closeCursor();
        return $values;
    }

    /**
     * 返回一个跌代器数组
     *
     * @param string $sql 查询语句
     * @param array $bind 参数
     * @return \Generator
     */
    public function getYieldValues($sql, array $bind = null)
    {
        $connection = $this->connection;
        $this->connection = null;
        $this->connect();
        $this->connection->setAttribute(\PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, false);
        $statement = $this->execute($sql, $bind);
        $this->connection = $connection;
        while ($tuple = $statement->fetch(\PDO::FETCH_NUM)) {
            yield $tuple[0];
        }
        $statement->closeCursor();
    }

    /**
     * 返回键值对数组
     * 查询两个或两个以上字段，第一列字段作为 key, 乘二列字段作为 value，多于两个字段时忽略
     *
     * @param string $sql 查询语句
     * @param array $bind 参数
     * @return array
     */
    public function getKeyValues($sql, array $bind = null)
    {
        $statement = $bind === null ? $this->query($sql) : $this->execute($sql, $bind);
        $keyValues = $statement->fetchAll(\PDO::FETCH_UNIQUE | \PDO::FETCH_COLUMN);
        $statement->closeCursor();
        return $keyValues;
    }

    /**
     * 返回一个数组
     *
     * @param string $sql 查询语句
     * @param array $bind 参数
     * @return array
     */
    public function getArray($sql, array $bind = null)
    {
        $statement = $bind === null ? $this->query($sql) : $this->execute($sql, $bind);
        $array = $statement->fetch(\PDO::FETCH_ASSOC);
        $statement->closeCursor();
        return $array;
    }

    /**
     * 返回一个二维数组
     *
     * @param string $sql 查询语句
     * @param array $bind 参数
     * @return array
     */
    public function getArrays($sql, array $bind = null)
    {
        $statement = $bind === null ? $this->query($sql) : $this->execute($sql, $bind);
        $arrays = $statement->fetchAll(\PDO::FETCH_ASSOC);
        $statement->closeCursor();
        return $arrays;
    }

    /**
     * 返回一个跌代器二维数组
     *
     * @param string $sql 查询语句
     * @param array $bind 参数
     * @return \Generator
     */
    public function getYieldArrays($sql, array $bind = null)
    {
        $connection = $this->connection;
        $this->connection = null;
        $this->connect();
        $this->connection->setAttribute(\PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, false);
        $statement = $this->execute($sql, $bind);
        $this->connection = $connection;
        while ($result = $statement->fetch(\PDO::FETCH_ASSOC)) {
            yield $result;
        }
        $statement->closeCursor();
    }

    /**
     * 返回一个带下标索引的二维数组
     *
     * @param string $sql 查询语句
     * @param array $bind 参数
     * @param string $key 作为下标索引的字段名
     * @return array
     */
    public function getKeyArrays($sql, array $bind = null, $key)
    {
        $statement = $bind === null ? $this->query($sql) : $this->execute($sql, $bind);
        $arrays = $statement->fetchAll(\PDO::FETCH_ASSOC);
        $statement->closeCursor();

        $result = [];
        foreach ($arrays as $array) {
            $result[$array[$key]] = $array;
        }

        return $result;
    }

    /**
     * 返回一个数据库记录对象
     *
     * @param string $sql 查询语句
     * @param array $bind 参数
     * @return object
     */
    public function getObject($sql, array $bind = null)
    {
        $statement = $bind === null ? $this->query($sql) : $this->execute($sql, $bind);
        $object = $statement->fetchObject();
        $statement->closeCursor();
        return $object;
    }

    /**
     * 返回一个对象数组
     *
     * @param string $sql 查询语句
     * @param array $bind 参数
     * @return array(object)
     */
    public function getObjects($sql, array $bind = null)
    {
        $statement = $bind === null ? $this->query($sql) : $this->execute($sql, $bind);
        $objects = $statement->fetchAll(\PDO::FETCH_OBJ);
        $statement->closeCursor();
        return $objects;
    }

    /**
     * 返回一个跌代器对象数组
     *
     * @param string $sql 查询语句
     * @param array $bind 参数
     * @return \Generator
     */
    public function getYieldObjects($sql, array $bind = null)
    {
        $connection = $this->connection;
        $this->connection = null;
        $this->connect();
        $this->connection->setAttribute(\PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, false);
        $statement = $this->execute($sql, $bind);
        $this->connection = $connection;
        while ($result = $statement->fetchObject()) {
            yield $result;
        }
        $statement->closeCursor();
    }

    /**
     * 返回一个带下标索引的对象数组
     *
     * @param string $sql 查询语句
     * @param array $bind 参数
     * @param string $key 作为下标索引的字段名
     * @return array(object)
     */
    public function getKeyObjects($sql, array $bind = null, $key)
    {
        $statement = $this->execute($sql, $bind);
        $objects = $statement->fetchAll(\PDO::FETCH_OBJ);
        $statement->closeCursor();

        $result = [];
        foreach ($objects as $object) {
            $result[$object->$key] = $object;
        }
        return $result;
    }

    /**
     * 插入一个对象到数据库
     *
     * @param string $table 表名
     * @param array | object $object 要插入数据库的对象或对象数组，对象属性需要和该表字段一致
     * @return int 插入的主键ID
     * @throws DbException
     */
    abstract public function insert($table, $object);

    /**
     * 批量插入多个对象到数据库
     *
     * @param string $table 表名
     * @param array $objects 要插入数据库的对象数组，对象属性需要和该表字段一致
     * @return int 影响的行数
     * @throws DbException
     */
    abstract public function insertMany($table, $objects);

    /**
     * 快速插入一个对象到数据库
     *
     * @param string $table 表名
     * @param array | object $object 要插入数据库的对象，对象属性需要和该表字段一致
     * @return int 插入的主键ID
     * @throws DbException
     */
    public abstract function quickInsert($table, $object);

    /**
     * 快速批量插入多个对象到数据库
     *
     * @param string $table 表名
     * @param array $objects 要插入数据库的对象数组，对象属性需要和该表字段一致
     * @return int 影响的行数
     * @throws DbException
     */
    public abstract function quickInsertMany($table, $objects);

    /**
     * 更新一个对象到数据库
     *
     * @param string $table 表名
     * @param array | object $object 要插入数据库的对象，对象属性需要和该表字段一致
     * @param null | string | array $primaryKey 主键
     * @return int 影响的行数
     * @throws DbException
     */
    public abstract function update($table, $object, $primaryKey = null);

    /**
     * 快速更新一个对象到数据库
     *
     * @param string $table 表名
     * @param array | object $object 要插入数据库的对象，对象属性需要和该表字段一致
     * @param null | string | array $primaryKey 主键
     * @return int 影响的行数
     * @throws DbException
     */
    public abstract function quickUpdate($table, $object, $primaryKey = null);

    /**
     * 更新一个对象到数据库
     *
     * @param string $table 表名
     * @param array | object $object 要更新的对象，对象属性需要和该表字段一致
     * @return int 影响的行数
     * @throws DbException
     */
    public abstract function replace($table, $object);

    /**
     * 批量更新多个对象到数据库
     *
     * @param string $table 表名
     * @param array $objects 要更新的对象数组，对象属性需要和该表字段一致
     * @return int 影响的行数
     * @throws DbException
     */
    public abstract function replaceMany($table, $objects);

    /**
     * 快速更新一个对象到数据库
     *
     * @param string $table 表名
     * @param array | object $object 要更新的对象，对象属性需要和该表字段一致
     * @return int 影响的行数
     * @throws DbException
     */
    public abstract function quickReplace($table, $object);

    /**
     * 快速批量更新多个对象到数据库
     *
     * @param string $table 表名
     * @param array $objects 要更新的对象数组，对象属性需要和该表字段一致
     * @return int 影响的行数
     * @throws DbException
     */
    public abstract function quickReplaceMany($table, $objects);

    /**
     * 获取 insert 插入后产生的 id
     *
     * @return int
     */
    public function getLastInsertId()
    {
        if ($this->connection === null) $this->connect();
        return $this->connection->lastInsertId();
    }

    /**
     * 获取当前数据库所有表信息
     *
     * @return array
     */
    public abstract function getTables();

    /**
     * 获取当前数据库所有表名
     *
     * @return array
     */
    public abstract function getTableNames();

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
    public abstract function getTableFields($table);

    /**
     * 获取指定表的主银
     *
     * @param string $table 表名
     * @return string | array | false
     */
    public abstract function getTablePrimaryKey($table);

    /**
     * 删除表
     *
     * @param string $table 表名
     */
    public abstract function dropTable($table);

    /**
     * 开启事务处理
     *
     */
    public function startTransaction()
    {
        $this->beginTransaction();
    }

    public function beginTransaction()
    {
        if ($this->connection === null) $this->connect();

        $this->transactions++;
        if ($this->transactions == 1) {
            $this->connection->beginTransaction();
        }
    }

    /**
     * 事务回滚
     */
    public function rollback()
    {
        if ($this->connection === null) $this->connect();
        $this->transactions--;
        if ($this->transactions == 0) {
            $this->connection->rollBack();
        }
    }

    /**
     * 事务提交
     */
    public function commit()
    {
        if ($this->connection === null) $this->connect();

        $this->transactions--;
        if ($this->transactions == 0) {
            $this->connection->commit();
        }
    }

    /**
     * 是否在事务中
     *
     * @return bool
     */
    public function inTransaction()
    {
        if ($this->connection === null) $this->connect();
        return $this->connection->inTransaction();
    }

    /**
     * 获取数据库连接对象
     *
     * @return \PDO
     */
    public function getConnection()
    {
        if ($this->connection === null) $this->connect();
        return $this->connection;
    }

    /**
     * 获取 版本号
     *
     * @return string
     */
    public function getVersion()
    {
        if ($this->connection === null) $this->connect();
        return $this->connection->getAttribute(\PDO::ATTR_SERVER_VERSION);
    }


    /**
     * 处理插入数据库的字段名或表名
     *
     * @param string $field
     * @return string
     */
    public abstract function quoteKey($field);


    /**
     * 处理插入数据库的字符串值，防注入, 使用了PDO提供的quote方法
     *
     * @param string $value
     * @return string
     */
    public function quoteValue($value) {
        if ($this->connection === null) $this->connect();
        return $this->connection->quote($value);
    }

    /**
     * 处理插入数据库的字符串值，防注入, 仅处理敏感字符，不加外层引号，
     * 与 quoteValue 方法的区别可以理解为 quoteValue 比 escape 多了最外层的引号
     *
     * @param string $value
     * @return string
     */
    public abstract function escape($value);

}
