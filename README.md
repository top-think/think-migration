# thinkphp6 数据库迁移工具

## 安装

```
composer require lingyun/think-migration
```
## 数据库迁移

### 生成迁移文件

```
php think migrate:create AnyClassNameYouWant
```
### 编辑迁移文件

```
<?php

use think\migration\Migrator;
use think\migration\db\Column;
 
class  AnyClassNameYouWant extends  Migrator
{
   
    public function change()
    {
        $this->table('user')
        ->addColumn(Column::string('email')->setUnique())
        ->addColumn(Column::string('username')->setUnique())
        ->addColumn(Column::string('avatar')->setNullable())
        ->addColumn(Column::string('password'))
        ->addColumn(Column::timestamp('email_verified_at')->setNullable())
        ->addColumn(Column::string('remember_token', 100)->setNullable())
        ->addTimestamps()
        ->create();
    }

}
```
### 运行迁移文件

```
php think migrate:run
```
### 回滚迁移文件

```
php think migrate:rollback
```
### 设置迁移断点
`migrate:breakpoint` 命令用来设置断点，可以使你对回滚进行限制。你可以调用 `breakpoint` 命令不带任何参数，即将断点设在最新的迁移脚本上

```
php think migrate:breakpoint

```

可以使用 --target 或者 -t 来指定断点打到哪个迁移版本上

```
php think migrate:breakpoint -t 20120103083322

```
可以使用 --remove-all 或者-r 来移除所有断点


```
php think migrate:breakpoint -r

```
### 查看迁移状态

```
php think migrate:status  
```
输出格式：`text` 或者 `json`,默认输出`text`
```
php think migrate:status --format json
```
或者
```
php think migrate:status -f json
```
## 数据填充

### 创建Seeder类

```
php think seed:create UserSeeder
```

`seeder` 类只包含一个默认方法：run。这个方法会在执行 `php think seed:run` 命令时被调用。在 `run` 方法里，你可以根据需要在数据库中插入数据。你也可以用 构造查询器 或 [模型工厂](#模型工厂) 来手动插入数据。

```
<?php

use think\migration\Seeder;

class UserSeeder extends Seeder
{
    public function run()
    {
       //填充一条数据
        $this->table('user')->insert([
            'username'          => 'example1',
            'email'       => 'example1@qq.com',
            'password'          => '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', // password
        ])->save();

        //填充一条数据
        $this->insert('user', [
            'username'          => 'example2',
            'email'       => 'example2@qq.com',
            'password'          => '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', // password
        ]);     
    }
}
```
### 运行 Seeders

您可以使用  `php think seed:run` 来填充数据库。默认情况下将运行 `database/seeds` 目录下所有Seeder类,可以使用 `--seed` 选项来指定一个特定的 seeder 类：
```
php think seed:run

php think seed:run --seed=UserSeeder
```

### 使用模型工厂

使用 [模型工厂](#模型工厂) 轻松地生成大量数据库数据。

例如，创建 50 个用户并为每个用户创建关联：
```
<?php

use think\migration\Seeder;

class UserSeeder extends Seeder
{
    public function run()
    {
        $this->factory()->create(User::class); //使用工厂类填充1条数据
        $this->factory()->of(User::class)->times(2)->create(); //使用工厂类填充2条数据
        $this->factory()->of(User::class, 'example')->create();
    }
}
```
## 模型工厂
<a name='模型工厂'></a>


### 创建工厂

要创建工厂，请执行 `php think factory:create` 命令：

```
php think factory:create User
```

新创建的工厂类默认存放在 database/factories 目录下。

```
<?php

use Carbon\Carbon;
use Faker\Generator as Faker;
use think\helper\Str;
use think\migration\Factory;

/** @var Factory $factory */
$factory->define(\app\model\User::class, function (Faker $faker) {
    return [
        'username' => $faker->unique()->userName,
        'email' => $faker->unique()->safeEmail,
        'email_verified_at' => Carbon::now(),
        'password' => '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', // password
        'remember_token' => Str::random(10),
    ];
});
```

以上就是模型工厂的最基本形式，调用 `define` 闭包方法返回伪造的模型属性值集合，该集合会在使用模型工厂创建新的模型实例时应用到模型属性。

通过 `$faker` 参数,可以访问 `Faker PHP` 函数库， 它允许你便捷的生成各种随机数据来进行测试。具体属性和方法请参考`\Faker\Generator`类。

> 技巧：你也可以在 config/app.php 配置文件中添加 faker_locale 选项来设置 Faker 的语言环境。

可以使用 `define` 方法的第三个参数为模型工厂设置名称
```
<?php

use Carbon\Carbon;
use Faker\Generator as Faker;
use think\helper\Str;
use think\migration\Factory;

/** @var Factory $factory */
$factory->define(\app\model\User::class, function (Faker $faker) {
    return [
        'username' => $faker->unique()->name,
        'email' => $faker->unique()->freeEmail,
        'email_verified_at' => Carbon::now(),
        'password' => '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', // password
        'remember_token' => Str::random(10),
    ];
}, 'example');

```


### 工厂状态

状态操作方法允许你定义离散修改，可以以任意组合应用于模型工厂。通常调用工厂类提供的 state 方法：

```
<?php

use Faker\Generator as Faker;
use think\migration\Factory;

/** @var Factory $factory */
$factory->state(\app\model\User::class, 'unverified', ['email_verified_at' => null]);
$factory->state(\app\model\User::class, 'unremember', ['remember_token' => null]);

```
同时 也可以使用闭包：
```
$factory->state(\app\model\User::class, 'useFirstName', fn (Faker $faker) => ['username' =>  $faker->unique()->firstName]);
```

### 使用工厂创建模型

让我们来看看一些创建模型的例子。首先，我们将使用 make 方法来创建模型而不存储到数据库：

```
use app\model\User;

public function test()
{
    $factory = app(Factory::class);
    $user = $factory->of(User::class)->make(); //通过默认模型工厂创建模型
    dump($user);
    $user = $factory->of(User::class, 'example')->make();//通过example模型工厂创建模型
    dump($user);
    // 模型中的其他测试...
}
```
可以使用`times`方法创建一个包含多个模型的集合：

```
 app(Factory::class)->of(User::class)->times(3)->make();
```
还可以使用`state`方法应用任意定义的状态到模型类：

```
 app(Factory::class)->of(User::class)->state('unverified')->make();
```
如果你想要应用多个状态转化到模型类,可以使用`states`方法：
```
 app(Factory::class)->of(User::class)->states('unverified','unremember')->make();
 app(Factory::class)->of(User::class)->states(['unverified','unremember','useFirstName'])->make();
```

### 覆盖属性
如果你想覆盖模型的一些默认值，你可以将数组传递给 `make` 方法。只有指定值才会被替换，剩下值保持工厂指定的默认值不变：
```
$user = app(Factory::class)->of(User::class)->make(['username' => 'thinkphp']); 
```
### 模型存储

`create`  方法创建模型实例，并使用 `think\Model` 的 `save` 方法其存储到数据库中：
```
$user = app(Factory::class)->of(User::class)->create(); 
```
> 更多模型工厂用法自行研究