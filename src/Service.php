<?php
/**
 * Created by PhpStorm.
 * User: yunwuxin
 * Date: 2019/3/12
 * Time: 18:24
 */

namespace think\migration;


use think\App;
use think\Console;

class Service
{
    public function register(App $app)
    {
        /** @var Console $console */
        $console = $app->make(Console::class);

        $console->addCommands([
            "think\\migration\\command\\migrate\\Create",
            "think\\migration\\command\\migrate\\Run",
            "think\\migration\\command\\migrate\\Rollback",
            "think\\migration\\command\\migrate\\Breakpoint",
            "think\\migration\\command\\migrate\\Status",
            "think\\migration\\command\\seed\\Create",
            "think\\migration\\command\\seed\\Run",
        ]);
    }
}