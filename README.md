# thinkphp5.1 数据库迁移工具

## 安装
~~~
composer require topthink/think-migration
~~~

## 修改（v2.0.3）
* Column的类型报错

* PdoAdapter.php中的getColumnTypes方法中添加了smallinteger的数组内容，
这里修改后可以过滤掉Column的类型报错
* mysql的类型不支持报错
* AdapterInterface.php中添加常量 const PHINX_TYPE_SMALL_INTEGER  = 'smallinteger';
* MysqlAdapter.php中修改和添加getSqlType方法中的内容
```
   case static::PHINX_TYPE_INTEGER:
       if ($limit && $limit >= static::INT_TINY) {
           $sizes = array(
               // Order matters! Size must always be tested from longest to shortest!
               'bigint'    => static::INT_BIG,
               'int'       => static::INT_REGULAR,
               'mediumint' => static::INT_MEDIUM,
               'smallint'  => static::INT_SMALL,
               'tinyint'   => static::INT_TINY,
           );
           $limits = array(
                // 修改部分开始########
               'smallint'  => 5,
                // 修改部分结束########
               'int'       => 11,
               'bigint'    => 20,
           );
           foreach ($sizes as $name => $length) {
               if ($limit >= $length) {
                   $def = array('name' => $name);
                   if (isset($limits[$name])) {
                       $def['limit'] = $limits[$name];
                   }
                   return $def;
               }
           }
       } elseif (!$limit) {
           $limit = 11;
       }
       return array('name' => 'int', 'limit' => $limit);
       break;
    // 后面是添加部分
   case static::PHINX_TYPE_SMALL_INTEGER:
       return array('name' => 'smallint', 'limit' => 5);
       break;

```
