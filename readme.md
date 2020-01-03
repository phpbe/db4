
#### 简介
本数据库类经过实际使用中大量改进优化，在保证安全的前提下提供了极高的执行效率。实际项目中，
我们大量用作亿级Mysql数据的快速迁移，转换处理。实路证明PHP的效率完全可以媲美ETL工具。

PHP处理大量数据时，你是否经常遇到内存溢出？使用本类库的迭代器方法不仅消耗极少的内存，页且迁移数据效率极高，在我们公司（有棵树）的服务器上的数据迁移操作：
- 每秒处理2万条数据（批量5000的插入(insert into)或更新(replace Into)操作, 1秒能执行4条）
- 每分种处理100万条以上，
- 1千万的数据10分钟内处理完成。



#### 创建实例

---

```php
<?php
require_once './vendor/autoload.php';

// 在 ThinkPHP 中使用
// 单例，多次调用返回同一个实例（ThinkPHP 5.0）
$db = \Be\Db4\Tp50::singleton();
// ThinkPHP 5.1
$db = \Be\Db4\Tp51::singleton();

// 创建数据库实便，每次调用返回一个新实例
$db = \Be\Db4\Tp51::instance();



```
#### 事务处理示例
```php

// 开启事务
$db->startTransaction();
try {
    // ...
    // 提交事务
    $db->commit();
} catch (\Exception $e) {
    // 回滚事务
    $db->rollback();
    // ...
}

```

#### 读相关操作

```php
// 返回用户信息对象
$user = $db->getObject('SELECT * FROM user WHERE id=1');

// 返回用户信息数组
$user = $db->getArray('SELECT * FROM user WHERE id=1');

// 返回符合条件的所有用户信息, 对象数组
$users = $db->getObjects('SELECT * FROM user WHERE age=18');

// 返回符合条件的所有用户信息, 二维数组
$users = $db->getArrays('SELECT * FROM user WHERE age=18');

// 返回单个值
$count = $db->getValue('SELECT COUNT(*) FROM user WHERE age=18');

// 返回多个值，数组
$names = $db->getValues('SELECT name FROM user WHERE age=18');

// 返回键值对, 带键名的数组（id 作为键名，name 作为值）
$idNameKeyValues = $db->getKeyValues('SELECT id, name FROM user WHERE age=18');

// 返回键值对, 带键名的数组（id 作为键名，用户信息数组 作为值）
$idUserKeyArrays = $db->getKeyArrays('SELECT id, name, age FROM user WHERE age=18');

// 返回键值对, 带键名的数组（id 作为键名，用户信息对像 作为值）
$idUserKeyObjects = $db->getKeyValues('SELECT id, name, age FROM user WHERE age=18');

```
##### 迭代器
```php

// 以迭代器形式返回符合条件的所有用户信息，对象数组，用作处理大量数据，消耗较少内存
$users = $db->getYieldObjects('SELECT * FROM user WHERE age=18');

// 以迭代器形式返回符合条件的用户信息，二维数组，用作处理大量数据，消耗较少内存
$users = $db->getYieldArrays('SELECT * FROM user WHERE age=18');
```
##### 占位符
```php
$users = $db->getObjects('SELECT * FROM user WHERE age=?', [18]);
$users = $db->getObjects('SELECT * FROM user WHERE age>=? AND age<=', [18, 25]);
$users = $db->getObjects('SELECT * FROM user WHERE name=?', ['abc']);
$users = $db->getObjects('SELECT * FROM user WHERE name=? AND age=?', ['abc', 18]);

```


#### 写相关操作

---

##### 对象插入

```php
$user = new stdClass();
$user->name = 'abc';
$user->age = 18;
$db->insert('user', $user);
$userId = $db->getLastInsertId();
```
##### 数组插入
```php
$user = [];
$user['name'] = 'abc';
$user['age'] = 18;
$db->insert('user', $user);
$userId = $db->getLastInsertId();
```
##### 批量插入
```php

$users = [];
$users[] = $user1;
$users[] = $user2;
// ...
$db->insertMany('user', $users);
```
##### 快速插入，未预编译SQL
```php
$db->quickInsert('user', $user);
$db->quickInsertMany('user', $users);

```
##### 更新用户资料
```php
$user->id = 1;
$db->update('user', $user);
$db->update('user', $user, 'id');
```
##### 新增或更新（主键冲突时更新）
```php
$db->replace('user', $user);
$db->replaceMany('user', $users);

$db->quickReplace('user', $user);
$db->quickReplaceMany('user', $users);

```
*  quick 开头的函数均为快速处理方法，未预编译SQL


#### 其它

---

##### 拼接SQL, 防注入
```php
$search = "abc'abc";
$sql = 'SELECT * FROM ' . $db->quoteKey('user') . ' WHERE ' . $db->quoteKey('name') . '=' . $db->quoteValue($search)
$user = $db->getObject($sql);
```
执行的SQL: SELECT * FROM `user` WHERE `name` = 'abc\'abc'


##### 拼接SQL, 防注入, escape 方法
```php
$search = "abc'abc";
$sql = 'SELECT * FROM ' . $db->quoteKey('user') . ' WHERE ' . $db->quoteKey('name') . '=\'' . $db->escape($search) . '\''
$user = $db->getObject($sql);
```
执行的SQL: SELECT * FROM `user` WHERE `name` = 'abc\'abc'



##### 执行 SQL
```php
$sql = 'UPDATE user SET age=20 WHERE name=\'abc\''
$db->query($sql);


$sql = 'UPDATE user SET age=20 WHERE name=?'
$db->execute($sql, ['abc']);
```
