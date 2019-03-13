<?php
/**
 * Created by PhpStorm.
 * User: yunwuxin
 * Date: 2019/3/12
 * Time: 18:24
 */

namespace think\migration;

class Service extends \think\Service
{
    public function boot()
    {
        $this->commands([
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