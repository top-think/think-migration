<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK IT ]
// +----------------------------------------------------------------------
// | Copyright (c) 2016 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: yunwuxin <448901948@qq.com>　
// +----------------------------------------------------------------------

namespace think\migration\db;

use Phinx\Db\Table\Column;

class Table extends \Phinx\Db\Table
{
    public function setId($id)
    {
        $this->options['id'] = $id;
        return $this;
    }

    public function setPrimaryKey($key)
    {
        $this->options['primary_key'] = $key;
        return $this;
    }

    public function addTimestamps($createdAtColumnName = 'create_time', $updatedAtColumnName = 'update_time')
    {
        return parent::addTimestamps($createdAtColumnName, $updatedAtColumnName);
    }

    public function changeColumn($columnName, $newColumnType = null, $options = [])
    {
        if ($columnName instanceof Column) {
            return parent::changeColumn($columnName->getName(), $columnName, $options);
        }
        return parent::changeColumn($columnName, $newColumnType, $options);
    }
}