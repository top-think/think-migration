# ThinkPHP6 数据库迁移工具

ThinkPHP6 数据库迁移工具集成了 Phinx，提供了简单易用的数据库迁移和数据填充功能，帮助开发者更高效地管理数据库版本。

## 安装

使用 Composer 安装此工具：
```bash
composer require topthink/think-migration
```

## 功能特性
- **数据库迁移**：使用数据库无关的 PHP 代码编写迁移脚本。
- **数据填充**：在数据库创建后填充初始数据。
- **快速上手**：不到 5 分钟即可开始使用。

## 使用方法

### 1. 配置 Phinx
在开发模式下，工具会自动复制 Phinx 相关文件到项目中。确保项目配置文件中包含正确的数据库连接信息。

### 2. 创建迁移文件
使用以下命令创建一个新的迁移文件：
```bash
php think migrate:create YourMigrationName
```
这将在 `phinx/Migration` 目录下生成一个新的迁移文件，你可以在其中编写数据库迁移逻辑。

### 3. 编写迁移逻辑
打开生成的迁移文件，继承 `Phinx\Migration\AbstractMigration` 类，并实现 `up` 和 `down` 方法：
```php
<?php

use Phinx\Migration\AbstractMigration;

class YourMigrationName extends AbstractMigration
{
    public function change()
    {
        $table = $this->table('followers', ['id' => false, 'primary_key' => ['user_id', 'follower_id']]);
        $table->addColumn('user_id', 'integer')
            ->addColumn('follower_id', 'integer')
            ->addColumn('created', 'datetime')
            ->addIndex(['email','username'], ['limit' => ['email' => 5, 'username' => 2]])
            ->addIndex('user_guid', ['limit' => 6])
           ->create();
    }
 
}
```

### 4. 执行迁移
执行向上迁移：
```bash
php think migrate:run
```
执行向下迁移：
```bash
php think migrate:rollback
```

## 多数据库支持

执行命令
```
php think  migrate:rollback   --connection=db_conn_name -vvv
```

回滚命令
```
php think  migrate:rollback   --connection=db_conn_name -vvv
```

其他命令类似

