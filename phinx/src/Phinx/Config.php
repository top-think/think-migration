<?php
/**
 * Phinx
 *
 * (The MIT license)
 * Copyright (c) 2015 Rob Morgan
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated * documentation files (the "Software"), to
 * deal in the Software without restriction, including without limitation the
 * rights to use, copy, modify, merge, publish, distribute, sublicense, and/or
 * sell copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING
 * FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS
 * IN THE SOFTWARE.
 *
 * @package    Phinx
 * @subpackage Phinx\Config
 */
namespace Phinx;


/**
 * Phinx configuration class.
 *
 * @package Phinx
 * @author Rob Morgan
 */
class Config
{
    /**
     * @var array
     */
    private $values = [];


    private $dbConfig = [];

    /**
     * {@inheritdoc}
     */
    public function __construct($configFile)
    {
        // 加载配置
        $this->values = include APP_PATH . 'config' . EXT;
        //加载数据库配置
        $config = include $configFile;

        if ($config['deploy'] == 0) {
            $this->dbConfig = [
                'adapter'      => $config['type'],
                'host'         => $config['hostname'],
                'name'         => $config['database'],
                'user'         => $config['username'],
                'pass'         => $config['password'],
                'port'         => $config['hostport'],
                'charset'      => $config['charset'],
                'table_prefix' => $config['prefix']
            ];
        } else {
            $this->dbConfig = [
                'adapter'      => explode(',', $config['type'])[0],
                'host'         => explode(',', $config['hostname'])[0],
                'name'         => explode(',', $config['database'])[0],
                'user'         => explode(',', $config['username'])[0],
                'pass'         => explode(',', $config['password'])[0],
                'port'         => explode(',', $config['hostport'])[0],
                'charset'      => explode(',', $config['charset'])[0],
                'table_prefix' => explode(',', $config['prefix'])[0]
            ];
        }
        if (isset($this->values['migration']['table'])) {
            $this->dbConfig['default_migration_table'] = $this->values['migration']['table'];
        }
    }


    public function getDbConfig()
    {
        return $this->dbConfig;
    }


    /**
     * {@inheritdoc}
     */
    public function getMigrationPath()
    {
        if (!isset($this->values['migration']['path'])) {
            return ROOT_PATH . 'database' . DS . 'migrations' . DS;
        }

        return $this->values['migration']['path'];
    }

    /**
     * Gets the base class name for migrations.
     *
     * @param boolean $dropNamespace Return the base migration class name without the namespace.
     * @return string
     */
    public function getMigrationBaseClassName($dropNamespace = true)
    {
        $className = !isset($this->values['migration']['base_class']) ? 'Phinx\Migration\AbstractMigration' : $this->values['migration']['base_class'];

        return $dropNamespace ? substr(strrchr($className, '\\'), 1) : $className;
    }

    /**
     * Get the template file name.
     *
     * @return string|false
     */
    public function getMigrationTemplateFile()
    {
        if (!isset($this->values['migration']['template'])) {
            return false;
        }

        return $this->values['migration']['template'];
    }

    /**
     * Get the template class name.
     *
     * @return string|false
     */
    public function getMigrationTemplateClass()
    {
        if (!isset($this->values['migration']['class'])) {
            return false;
        }

        return $this->values['migration']['class'];
    }

    /**
     * {@inheritdoc}
     */
    public function getSeedPath()
    {
        if (!isset($this->values['seed']['path'])) {
            return ROOT_PATH . 'database' . DS . 'seeds' . DS;
        }

        return $this->values['seed']['path'];
    }


}
