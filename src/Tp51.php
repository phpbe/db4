<?php
namespace Be\Db4;

use think\facade\Config;

/**
 *  ThinkPHP 5.1 数据库工厂
 * @package System
 *
 */
abstract class Tp51
{

    private static $cache = []; // 缓存资源实例

    /**
     * 获取数据库对象
     *
     * @param string $db 数据库名
     * @return \Be\Db4\Driver
     * @throws \Exception
     */
    public static function getDb($db = 'default')
    {
        $key = 'Db:' . $db;
        if (isset(self::$cache[$key])) return self::$cache[$key];
        self::$cache[$key] = self::newDb($db);
        return self::$cache[$key];
    }

    /**
     * 获取带超时时间的数据库对象
     *
     * @param string $db 数据库名
     * @param int $expire 超时时间(单位：秒)
     * @return \Be\Db4\Driver
     * @throws \Exception
     */
    public static function getExpireDb($db = 'default', $expire = 600)
    {
        $key = 'ExpireDb:' . $db;
        if (isset(self::$cache[$key]['expire']) && self::$cache[$key]['expire'] > time()) {
            return self::$cache[$key]['instance'];
        }

        self::$cache[$key] = [
            'expire' => time() + $expire,
            'instance' => self::newDb($db)
        ];
        return self::$cache[$key]['instance'];
    }

    /**
     * @param string $db
     * @return \Be\Db4\Driver
     * @throws \Exception
     */
    public static function newDb($db = 'default')
    {
        $config = null;
        if ($db == 'default') {
            $config = array(
                'type' => Config::get('database.type'),
                'host' => Config::get('database.hostname'),
                'port' => Config::get('database.hostport'),
                'name' => Config::get('database.database'),
                'user' => Config::get('database.username'),
                'pass' => Config::get('database.password'),
            );
        } else {

            $dsn = Config::get($db);
            if (!$dsn) {
                throw new DbException('数据库配置项（'.$db.'）不存在！');
            }

            if (is_string($dsn)) {

                $dsn = parse_url($dsn);
                if (!is_array($dsn)) {
                    throw new DbException('数据库配置项（'.$db.'）不是有效的DSN！');
                }

                $config = array(
                    'type' => $dsn['scheme'],
                    'host' => $dsn['host'],
                    'port' => isset($dsn['port']) ? $dsn['port'] : 3306,
                    'name' => isset($dsn['path']) ? trim($dsn['path'], '/') : '',
                    'user' => isset($dsn['user']) ? $dsn['user'] : '',
                    'pass' => isset($dsn['pass']) ? $dsn['pass'] : '',
                );

            } else {

                if (!is_array($dsn)) {
                    throw new DbException('数据库配置项（'.$db.'）不是有效的DSN！');
                }

                $config = array(
                    'type' => $dsn['type'],
                    'host' => $dsn['hostname'],
                    'port' => isset($dsn['hostport']) ? $dsn['hostport'] : 3306,
                    'name' => isset($dsn['database']) ? trim($dsn['database'], '/') : '',
                    'user' => isset($dsn['username']) ? $dsn['username'] : '',
                    'pass' => isset($dsn['password']) ? $dsn['password'] : '',
                    'dsn' => isset($dsn['dsn']) ? $dsn['dsn'] : '',
                );
            }

        }

        $class = null;
        switch (strtolower($config['type'])) {
            case 'mysql':
                $class = '\\Be\\Db4\\Driver\\MysqlImpl';
                break;
            case 'oracle':
                $class = '\\Be\\Db4\\Driver\\OracleImpl';
                break;
            default:
                throw new DbException('不支持的数据库类型（'.$config['type'].'）！');
                break;
        }

        return new $class($config);
    }

}
