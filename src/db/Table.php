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
    /**
     * 设置id
     * @param $id
     * @return $this
     */
    public function setId($id)
    {
        $this->options['id'] = $id;
        return $this;
    }

    /**
     * 设置主键
     * @param $key
     * @return $this
     */
    public function setPrimaryKey($key)
    {
        $this->options['primary_key'] = $key;
        return $this;
    }

    /**
     * 设置引擎
     * @param $engine
     * @return $this
     */
    public function setEngine($engine)
    {
        $this->options['engine'] = $engine;
        return $this;
    }

    /**
     * 设置表注释
     * @param $comment
     * @return $this
     */
    public function setComment($comment)
    {
        $this->options['comment'] = $comment;
        return $this;
    }

    /**
     * 设置排序比对方法
     * @param $collation
     * @return $this
     */
    public function setCollation($collation)
    {
        $this->options['collation'] = $collation;
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