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
 * @subpackage Phinx\Db\Adapter
 */
namespace Phinx\Db\Adapter;

use Phinx\Db\Adapter\AdapterInterface;
use Phinx\Db\Adapter\PdoAdapter;
use Phinx\Db\Table;
use Phinx\Db\Table\Column;
use Phinx\Db\Table\Index;
use Phinx\Db\Table\ForeignKey;
use think\db\exception\PDOException;

class DmAdapter extends PdoAdapter implements AdapterInterface
{
    const TEXT_TINY    = 255;
    const TEXT_SMALL   = 255; /* deprecated, alias of TEXT_TINY */
    const TEXT_REGULAR = 65535;
    const TEXT_MEDIUM  = 16777215;
    const TEXT_LONG    = 4294967295;

    // According to https://dev.mysql.com/doc/refman/5.0/en/blob.html BLOB sizes are the same as TEXT
    const BLOB_TINY    = 255;
    const BLOB_SMALL   = 255; /* deprecated, alias of BLOB_TINY */
    const BLOB_REGULAR = 65535;
    const BLOB_MEDIUM  = 16777215;
    const BLOB_LONG    = 4294967295;

    const INT_TINY    = 255;
    const INT_SMALL   = 65535;
    const INT_MEDIUM  = 16777215;
    const INT_REGULAR = 4294967295;
    const INT_BIG     = 18446744073709551615;

    const TYPE_YEAR   = 'year';

    /**
     * Columns with comments
     *
     * @var array
     */
    protected $columnsWithComments = array();

//    /**
//     * Gets the schema table name.
//     *
//     * @return string
//     */
    public function getSchemaTableName()
    {
        return $this->quoteSchemaName($this->schemaTableName);
    }

    /**
     * {@inheritdoc}
     */
    public function getVersionLog()
    {
        $result = array();
        $rows = $this->fetchAll(sprintf('SELECT * FROM %s ORDER BY "version" ASC', $this->getSchemaTableName()));
        foreach ($rows as $version) {
            $result[$version['version']] = $version;
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function createSchemaTable()
    {
        try {
            $options = array(
                'id'          => false,
                'primary_key' => 'version'
            );

            $table = new Table($this->schemaTableName, $options, $this);

            if ($this->getConnection()->getAttribute(\PDO::ATTR_DRIVER_NAME) === 'mysql'
                && version_compare($this->getConnection()->getAttribute(\PDO::ATTR_SERVER_VERSION), '5.6.0', '>=')) {
                $table->addColumn('version', 'biginteger', array('limit' => 14))
                    ->addColumn('migration_name', 'string', array('limit' => 100, 'default' => null, 'null' => true))
                    ->addColumn('start_time', 'timestamp', array('default' => 'CURRENT_TIMESTAMP'))
                    ->addColumn('end_time', 'timestamp', array('default' => 'CURRENT_TIMESTAMP'))
                    ->addColumn('breakpoint', 'boolean', array('default' => false))
                    ->save();
            } else {
                $table->addColumn('version', 'biginteger')
                    ->addColumn('migration_name', 'string', array('limit' => 100, 'default' => null, 'null' => true))
                    ->addColumn('start_time', 'timestamp')
                    ->addColumn('end_time', 'timestamp')
                    ->addColumn('breakpoint', 'boolean', array('default' => false))
                    ->save();
            }
        } catch (\Exception $exception) {
            throw new \InvalidArgumentException('There was a problem creating the schema table: ' . $exception->getMessage());
        }
    }


    /**
     * {@inheritdoc}
     */
    public function connect()
    {
        if (null === $this->connection) {
            if (!class_exists('PDO') || !in_array('dm', \PDO::getAvailableDrivers(), true)) {
                // @codeCoverageIgnoreStart
                throw new \RuntimeException('You need to enable the PDO_DM extension for Phinx to run properly.');
                // @codeCoverageIgnoreEnd
            }

            $db = null;
            $options = $this->getOptions();

            if (isset($options['port'])) {
                $dsn = 'dm:host=' . $options['host'] . ';port=' . $options['port'] . ';dbname=' . $options['name'];
            } else {
                $dsn = 'dm:host=' . $options['host'] . ';dbname=' . $options['name'];
            }

            try {
                $db = new \PDO($dsn, $options['user'], $options['pass'], array(\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION));
                $this->connection=$db;

                if (!$this->hasDatabase($options['name'])) {
                    $this->createDatabase($options['name']);
                }
                $db->query("SET SCHEMA ".$options['name']);

                $this->setConnection($db);

            } catch (\PDOException $exception) {
                throw new \InvalidArgumentException(sprintf(
                    'There was a problem connecting to the database: %s',
                    $exception->getMessage()
                ));
            }



        }
    }

    /**
     * {@inheritdoc}
     */
    public function disconnect()
    {
        $this->connection = null;
    }

    /**
     * {@inheritdoc}
     */
    public function hasTransactions()
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function beginTransaction()
    {
        $this->execute('START TRANSACTION');
    }

    /**
     * {@inheritdoc}
     */
    public function commitTransaction()
    {
        $this->execute('COMMIT');
    }

    /**
     * {@inheritdoc}
     */
    public function rollbackTransaction()
    {
        $this->execute('ROLLBACK');
    }

    /**
     * Quotes a schema name for use in a query.
     *
     * @param string $schemaName Schema Name
     * @return string
     */
    public function quoteSchemaName($schemaName)
    {
        return $this->quoteColumnName($schemaName);
    }

    /**
     * {@inheritdoc}
     */
    public function quoteTableName($tableName)
    {
        return $this->quoteColumnName($tableName);
    }

    /**
     * {@inheritdoc}
     */
    public function quoteColumnName($columnName)
    {
        return '"'. $columnName . '"';
    }


    /**
     * {@inheritdoc}
     */
    public function hasTable($tableName)
    {
        $options = $this->getOptions();


        $sprintf = sprintf("SELECT COUNT(*)  FROM all_tables WHERE  OWNER='%s'  AND TABLE_NAME = '%s'",
            $options['name'],
            trim($tableName,'"'),
        );
        $result = $this->getConnection()->query($sprintf);

        $column = $result->fetchColumn();
        return $column >0;
    }

    /**
     * {@inheritdoc}
     */
    public function createTable(Table $table)
    {
        $this->startCommandTimer();
        try {
            $this->writeCommand('createTable', array($table->getName()));

            // This method is based on the MySQL docs here: http://dev.mysql.com/doc/refman/5.1/en/create-index.html
            $defaultOptions = array(
                'engine' => 'InnoDB',
                'collation' => 'utf8_general_ci'
            );
            $options = array_merge($defaultOptions, $table->getOptions());

            // Add the default primary key
            $columns = $table->getPendingColumns();

            if (!isset($options['id']) || (isset($options['id']) && $options['id'] === true)) {
                $options['id'] = 'id';
            }

            if (isset($options['id']) && is_string($options['id'])) {
                // Handle id => "field_name" to support AUTO_INCREMENT
                $column = new Column();
                $column->setName($options['id'])
                    ->setType('integer')
                    ->setSigned(isset($options['signed']) ? $options['signed'] : true)
                    ->setIdentity(true);

                array_unshift($columns, $column);
                $options['primary_key'] = $options['id'];
            }

            $optionsStr='';


            $sql = 'CREATE TABLE ';
            $sql .= $this->quoteTableName($table->getName()) . ' (';
            foreach ($columns as $column) {
                $sql.=sprintf("%s  %s  ,",$this->quoteColumnName($column->getName()),
                    $this->getColumnSqlDefinition($column)
                );
            }

            // set the primary key(s)
            if (isset($options['primary_key'])) {
                $sql = rtrim($sql);
                $sql .= ' PRIMARY KEY (';
                if (is_string($options['primary_key'])) {       // handle primary_key => 'id'
                    $sql .= $this->quoteColumnName($options['primary_key']);
                } elseif (is_array($options['primary_key'])) { // handle primary_key => array('tag_id', 'resource_id')
                    // PHP 5.4 will allow access of $this, so we can call quoteColumnName() directly in the
                    // anonymous function, but for now just hard-code the adapter quotes
                    $sql .= implode(
                        ',',
                        array_map(
                            function ($v) {
                                return '`' . $v . '`';
                            },
                            $options['primary_key']
                        )
                    );
                }
                $sql .= ')';
            } else {
                $sql = substr(rtrim($sql), 0, -1);              // no primary keys
            }
            $sql .= ') ' . $optionsStr;
            $this->execute($sql);

            // set the table comment
            if (isset($options['comment'])) {
                //todo
//                $this->getConnection()->exec($sql);
            }


            // set the indexes
            $indexes = $table->getIndexes();
            if (!empty($indexes)) {
                foreach ($indexes as $index) {
                    $sql= $this->getIndexSqlDefinition($index,$table->getName());
                    $this->execute($sql);
                }
            }

            // set the foreign keys
            $foreignKeys = $table->getForeignKeys();
            if (!empty($foreignKeys)) {
                foreach ($foreignKeys as $foreignKey) {
                    $sql = $this->getForeignKeySqlDefinition($foreignKey);
                    $this->execute($sql);

                }
            }
        } catch (\Exception $e) {
            $string = $e->getMessage();
            $iconv = iconv("GBK", 'UTF-8', $string);
            print_r($iconv);
            throw new \RuntimeException($string . " \n sql: \n" . $sql);
        }

        $this->endCommandTimer();
    }

    /**
     * {@inheritdoc}
     */
    public function renameTable($tableName, $newTableName)
    {
        $this->startCommandTimer();
        $this->writeCommand('renameTable', array($tableName, $newTableName));
        $sql = sprintf(
            'ALTER TABLE %s RENAME TO %s',
            $this->quoteTableName($tableName),
            $this->quoteTableName($newTableName),
        );
        $this->execute($sql);
        $this->endCommandTimer();
    }

    /**
     * {@inheritdoc}
     */
    public function dropTable($tableName)
    {
        $this->startCommandTimer();
        $this->writeCommand('dropTable', array($tableName));
        $this->execute(sprintf('DROP TABLE %s',$this->quoteTableName($tableName)));
        $this->endCommandTimer();
    }

    /**
     * {@inheritdoc}
     */
    public function getColumns($tableName)
    {
        $columns = array();
        $sql=sprintf("SELECT column_name,data_type,nullable,data_default,data_precision,data_scale FROM USER_TAB_COLUMNS WHERE TABLE_NAME='%s'",$tableName);
        $columnsInfo = $this->fetchAll($sql);

        foreach ($columnsInfo as $columnInfo) {
            $column = new Column();
            $column->setName($columnInfo['column_name'])
                ->setType($this->getPhinxType($columnInfo['data_type']))
                ->setNull($columnInfo['nullable'] === 'Y')
                ->setDefault($columnInfo['data_default'])
                //  达梦没有直接判断是否自增字段，通过 字段带有id，且不能为null 判断
                ->setIdentity(($columnInfo['nullable']!=='Y' && strpos(strtolower($columnInfo['column_name']),"id")!==false) === 'YES')
                ->setLimit($columnInfo['data_length'])
                ->setPrecision($columnInfo['data_precision'])
                ->setScale($columnInfo['data_scale']);

            if (preg_match('/\bwith time zone$/', $columnInfo['data_type'])) {
                $column->setTimezone(true);
            }

            $columns[] = $column;
        }
        return $columns;
    }

    /**
     * {@inheritdoc}
     */
    public function hasColumn($tableName, $columnName, $options = array())
    {
        $sql = sprintf("SELECT count(*) as count FROM USER_TAB_COLUMNS WHERE TABLE_NAME='%s' AND COLUMN_NAME='%s'",
            trim($tableName,'"'),
            $columnName
        );

        $result = $this->fetchRow($sql);
        return  $result['COUNT'] > 0;
    }

    /**
     * {@inheritdoc}
     */
    public function addColumn(Table $table, Column $column)
    {
        $this->startCommandTimer();
        $this->writeCommand('addColumn', array($table->getName(), $column->getName(), $column->getType()));
        $sql = sprintf(
            'ALTER TABLE %s ADD %s %s',
            $this->quoteTableName($table->getName()),
            $this->quoteColumnName($column->getName()),
            $this->getColumnSqlDefinition($column)
        );

        $this->execute($sql);
        $this->endCommandTimer();
    }

    /**
     * {@inheritdoc}
     */
    public function renameColumn($tableName, $columnName, $newColumnName)
    {
        $this->startCommandTimer();
        $result = $this->fetchRow($sql);
        if (!$this->hasColumn($tableName,$columnName)) {
            throw new \InvalidArgumentException("The specified column does not exist: $columnName");
        }
        $this->writeCommand('renameColumn', array($tableName, $columnName, $newColumnName));
        $this->execute(
            sprintf(
                'ALTER TABLE %s RENAME COLUMN %s TO %s',
                $this->quoteTableName($tableName),
                $this->quoteColumnName($columnName),
                $this->quoteColumnName($newColumnName),
            )
        );
        $this->endCommandTimer();
    }

    /**
     * {@inheritdoc}
     */
    public function changeColumn($tableName, $columnName, Column $newColumn)
    {
        $this->startCommandTimer();
        $this->writeCommand('changeColumn', array($tableName, $columnName, $newColumn->getType()));
        // todo after 没生效
//        $after = $newColumn->getAfter() ? ' AFTER ' . $this->quoteColumnName($newColumn->getAfter()) : '';

        // sql
        // ALTER TABLE student MODIFY  age varchar(10) ;
        $sql = sprintf(
            'ALTER TABLE %s MODIFY  %s %s;',
            $this->quoteTableName($tableName),
            $this->quoteColumnName($columnName),
            $this->getColumnSqlDefinition($newColumn),
        );
        $this->execute($sql);
        $this->endCommandTimer();
    }

    /**
     * {@inheritdoc}
     */
    public function dropColumn($tableName, $columnName)
    {
        $this->startCommandTimer();
        $this->writeCommand('dropColumn', array($tableName, $columnName));
        $this->execute(
            sprintf(
                'ALTER TABLE %s DROP COLUMN %s',
                $this->quoteTableName($tableName),
                $this->quoteColumnName($columnName),
            )
        );
        $this->endCommandTimer();
    }

    /**
     * Get an array of indexes from a particular table.
     *
     * @param string $tableName Table Name
     * @return array
     */
    protected function getIndexes($tableName)
    {
        $indexes = array();
        $sql = sprintf("SELECT COLUMN_NAME ,INDEX_NAME   from DBA_IND_COLUMNS WHERE TABLE_NAME='%s'", trim($tableName,'"'));
        $rows = $this->fetchAll($sql);
        $rows=array_map(function ($item){
            return array_change_key_case($item);
        },$rows);

        foreach ($rows as $row) {
            if (!isset($indexes[$row['index_name']])) {
                $indexes[$row['index_name']] = array('columns' => array());
            }
            $indexes[$row['index_name']]['columns'][] = strtolower($row['column_name']);
        }
        return $indexes;
    }

    /**
     * {@inheritdoc}
     */
    public function hasIndex($tableName, $columns)
    {
        if (is_string($columns)) {
            $columns = array($columns);
        }
        $columns = array_map('strtolower', $columns);
        $indexes = $this->getIndexes($tableName);
        foreach ($indexes as $index) {
            if (array_diff($index['columns'], $columns) === array_diff($columns, $index['columns'])) {
                return true;
            }
        }
        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function hasIndexByName($tableName, $indexName)
    {
        $indexes = $this->getIndexes($tableName);
        foreach ($indexes as $name => $index) {
            if ($name === $indexName) {
                return true;
            }
        }
        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function addIndex(Table $table, Index $index)
    {
        $this->startCommandTimer();
        $this->writeCommand('addIndex', array($table->getName(), $index->getColumns()));
        $sql = $this->getIndexSqlDefinition($index, $table->getName());
        $this->execute($sql);
        $this->endCommandTimer();
    }

    /**
     * {@inheritdoc}
     */
    public function dropIndex($tableName, $columns)
    {
        $this->startCommandTimer();
        if (is_string($columns)) {
            $columns = array($columns); // str to array
        }

        $this->writeCommand('dropIndex', array($tableName, $columns));
        $indexes = $this->getIndexes($tableName);
        $columns = array_map('strtolower', $columns);

        foreach ($indexes as $indexName => $index) {
            $a = array_diff($columns, $index['columns']);
            if (empty($a)) {
                $sql = sprintf(
                    'DROP INDEX IF EXISTS %s',
                    $indexName
                );
                $this->execute(
                    $sql
                );
                $this->endCommandTimer();
                return;
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function dropIndexByName($tableName, $indexName)
    {
        $this->startCommandTimer();
        $this->writeCommand('dropIndexByName', array($tableName, $indexName));
        $sql = sprintf(
            'DROP INDEX IF EXISTS %s',
            $indexName
        );
        $this->execute($sql);
        $this->endCommandTimer();
    }

    /**
     * {@inheritdoc}
     */
    public function hasForeignKey($tableName, $columns, $constraint = null)
    {
        if (is_string($columns)) {
            $columns = array($columns); // str to array
        }
        $foreignKeys = $this->getForeignKeys($tableName);
        if ($constraint) {
            if (isset($foreignKeys[$constraint])) {
                return !empty($foreignKeys[$constraint]);
            }
            return false;
        } else {
            foreach ($foreignKeys as $key) {
                $a = array_diff($columns, $key['columns']);
                if (empty($a)) {
                    return true;
                }
            }
            return false;
        }
    }

    /**
     * Get an array of foreign keys from a particular table.
     *
     * @param string $tableName Table Name
     * @return array
     */
    protected function getForeignKeys($tableName)
    {
        $foreignKeys = array();
        $rows = $this->fetchAll(sprintf(
            "SELECT
  a.constraint_name,
  a.table_name as table_name,
  b.table_name as referenced_table_name,
  a.column_name as column_name,
  b.column_name as referenced_column_name
FROM
  user_cons_columns a
JOIN
  user_constraints c ON a.owner = c.owner AND a.constraint_name = c.constraint_name
JOIN
  user_cons_columns b ON c.r_owner = b.owner AND c.r_constraint_name = b.constraint_name
WHERE
  c.constraint_type = 'R' AND a.table_name = '%s';
",
            $tableName
        ));
        foreach ($rows as $row) {
            $foreignKeys[$row['constraint_name']]['table'] = $row['table_name'];
            $foreignKeys[$row['constraint_name']]['columns'][] = $row['column_name'];
            $foreignKeys[$row['constraint_name']]['referenced_table'] = $row['referenced_table_name'];
            $foreignKeys[$row['constraint_name']]['referenced_columns'][] = $row['referenced_column_name'];
        }
        return $foreignKeys;
    }

    /**
     * {@inheritdoc}
     */
    public function addForeignKey(Table $table, ForeignKey $foreignKey)
    {
        $this->startCommandTimer();
        $this->writeCommand('addForeignKey', array($table->getName(), $foreignKey->getColumns()));
        $sql = sprintf(
            'ALTER TABLE %s ADD %s',
            $table->getName(),
            $this->getForeignKeySqlDefinition($foreignKey, $table->getName())
        );
        $this->execute($sql);
        $this->endCommandTimer();
    }

    /**
     * {@inheritdoc}
     */
    public function dropForeignKey($tableName, $columns, $constraint = null)
    {
        $this->startCommandTimer();
        if (is_string($columns)) {
            $columns = array($columns); // str to array
        }
        $this->writeCommand('dropForeignKey', array($tableName, $columns));

        if ($constraint) {
            $this->execute(
                sprintf(
                    'ALTER TABLE %s DROP CONSTRAINT %s',
                    $this->quoteTableName($tableName),
                    $constraint
                )
            );
        } else {
            foreach ($columns as $column) {
                $rows = $this->fetchAll(sprintf(
                    "SELECT
  a.constraint_name,
FROM
  user_cons_columns a
JOIN
  user_constraints c ON a.owner = c.owner AND a.constraint_name = c.constraint_name
JOIN
  user_cons_columns b ON c.r_owner = b.owner AND c.r_constraint_name = b.constraint_name
WHERE
  c.constraint_type = 'R' AND a.table_name = '%s' AND column_name='%s';
",
                    $tableName,
                    $column,
                ));

                foreach ($rows as $row) {
                    $this->dropForeignKey($tableName, $columns, $row['constraint_name']);
                }
            }
        }
        $this->endCommandTimer();
    }

    /**
     * {@inheritdoc}
     */
    public function getSqlType($type, $limit = null)
    {
        switch ($type) {
            case static::PHINX_TYPE_STRING:
                return array('name' => 'varchar', 'limit' => $limit ? $limit : 255);
                break;
            case static::PHINX_TYPE_CHAR:
                return array('name' => 'char', 'limit' => $limit ? $limit : 255);
                break;
            case static::PHINX_TYPE_TEXT:
                if ($limit) {
                    $sizes = array(
                        // Order matters! Size must always be tested from longest to shortest!
                        'longtext'   => static::TEXT_LONG,
                        'mediumtext' => static::TEXT_MEDIUM,
                        'text'       => static::TEXT_REGULAR,
                        'tinytext'   => static::TEXT_SMALL,
                    );
                    foreach ($sizes as $name => $length) {
                        if ($limit >= $length) {
                            return array('name' => $name);
                        }
                    }
                }
                return array('name' => 'text');
                break;
            case static::PHINX_TYPE_BINARY:
                return array('name' => 'binary', 'limit' => $limit ? $limit : 255);
                break;
            case static::PHINX_TYPE_VARBINARY:
                return array('name' => 'varbinary', 'limit' => $limit ? $limit : 255);
                break;
            case static::PHINX_TYPE_BLOB:
                if ($limit) {
                    $sizes = array(
                        // Order matters! Size must always be tested from longest to shortest!
                        'longblob'   => static::BLOB_LONG,
                        'mediumblob' => static::BLOB_MEDIUM,
                        'blob'       => static::BLOB_REGULAR,
                        'tinyblob'   => static::BLOB_SMALL,
                    );
                    foreach ($sizes as $name => $length) {
                        if ($limit >= $length) {
                            return array('name' => $name);
                        }
                    }
                }
                return array('name' => 'blob');
                break;
            case static::PHINX_TYPE_INTEGER:
                if ($limit && $limit >= static::INT_TINY) {
                    $sizes = array(
                        // Order matters! Size must always be tested from longest to shortest!
                        'bigint'    => static::INT_BIG,
                        'int'       => static::INT_REGULAR,
                    );
                    $limits = array(
                        'int'    => 11,
                        'bigint' => 20,
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
            case static::PHINX_TYPE_BIG_INTEGER:
                return array('name' => 'bigint', 'limit' => 20);
                break;
            case static::PHINX_TYPE_FLOAT:
                return array('name' => 'float');
                break;
            case static::PHINX_TYPE_DECIMAL:
                return array('name' => 'decimal');
                break;
            case static::PHINX_TYPE_DATETIME:
                return array('name' => 'datetime');
                break;
            case static::PHINX_TYPE_TIMESTAMP:
                return array('name' => 'timestamp');
                break;
            case static::PHINX_TYPE_TIME:
                return array('name' => 'time');
                break;
            case static::PHINX_TYPE_DATE:
                return array('name' => 'date');
                break;
            case static::PHINX_TYPE_BOOLEAN:
                return array('name' => 'int', 'limit' => 1);
                break;
            case static::PHINX_TYPE_UUID:
                return array('name' => 'char', 'limit' => 36);
            // Geospatial database types
            case static::PHINX_TYPE_GEOMETRY:
            case static::PHINX_TYPE_POINT:
            case static::PHINX_TYPE_LINESTRING:
            case static::PHINX_TYPE_POLYGON:
                return array('name' => $type);
            case static::PHINX_TYPE_ENUM:
                return array('name' => 'enum');
                break;
            case static::PHINX_TYPE_SET:
                return array('name' => 'set');
                break;
            case static::TYPE_YEAR:
                if (!$limit || in_array($limit, array(2, 4)))
                    $limit = 4;
                return array('name' => 'year', 'limit' => $limit);
                break;
            case static::PHINX_TYPE_JSON:
                return array('name' => 'json');
                break;
            default:
                throw new \RuntimeException('The type: "' . $type . '" is not supported.');
        }
    }

    /**
     * Returns Phinx type by SQL type
     *
     * @param string $sqlType SQL type
     * @returns string Phinx type
     */
    public function getPhinxType($sqlType)
    {
        switch ($sqlType) {
            case 'character varying':
            case 'varchar':
                return static::PHINX_TYPE_STRING;
            case 'character':
            case 'char':
                return static::PHINX_TYPE_CHAR;
            case 'text':
                return static::PHINX_TYPE_TEXT;
            case 'json':
                return static::PHINX_TYPE_JSON;
            case 'jsonb':
                return static::PHINX_TYPE_JSONB;
            case 'smallint':
                return array(
                    'name' => 'smallint',
                    'limit' => static::INT_SMALL
                );
            case 'int':
            case 'int4':
            case 'integer':
                return static::PHINX_TYPE_INTEGER;
            case 'decimal':
            case 'numeric':
                return static::PHINX_TYPE_DECIMAL;
            case 'bigint':
            case 'int8':
                return static::PHINX_TYPE_BIG_INTEGER;
            case 'real':
            case 'float4':
                return static::PHINX_TYPE_FLOAT;
            case 'bytea':
                return static::PHINX_TYPE_BINARY;
                break;
            case 'time':
            case 'timetz':
            case 'time with time zone':
            case 'time without time zone':
                return static::PHINX_TYPE_TIME;
            case 'date':
                return static::PHINX_TYPE_DATE;
            case 'timestamp':
            case 'timestamptz':
            case 'timestamp with time zone':
            case 'timestamp without time zone':
                return static::PHINX_TYPE_DATETIME;
            case 'bool':
            case 'boolean':
                return static::PHINX_TYPE_BOOLEAN;
            case 'uuid':
                return static::PHINX_TYPE_UUID;
            default:
                throw new \RuntimeException('The  type: "' . $sqlType . '" is not supported');
        }
    }

    /**
     * {@inheritdoc}
     */
    public function createDatabase($name, $options = array())
    {
        $this->startCommandTimer();
        $this->writeCommand('createDatabase', array($name));
        $this->execute(sprintf("CREATE SCHEMA  %s", $name));

        $this->endCommandTimer();
    }

    /**
     * {@inheritdoc}
     */
    public function hasDatabase($databaseName)
    {
        $sql = sprintf("select COUNT(1) from sysobjects where NAME='%s' and SUBTYPE$ is null", $databaseName);
        $result = $this->getConnection()->query($sql);
        return $result->fetchColumn(0)>0;
    }

    /**
     * {@inheritdoc}
     */
    public function dropDatabase($name)
    {
        $this->startCommandTimer();
        $this->writeCommand('dropDatabase', array($name));
        $this->disconnect();
        $this->execute(sprintf('DROP DATABASE IF EXISTS %s', $name));
        $this->connect();
        $this->endCommandTimer();
    }

    /**
     * Get the defintion for a `DEFAULT` statement.
     *
     * @param  mixed $default
     * @return string
     */
    protected function getDefaultValueDefinition($default)
    {
        if (is_string($default) && 'CURRENT_TIMESTAMP' !== $default) {
            $default = $this->getConnection()->quote($default);
        } elseif (is_bool($default)) {
            $default = $this->castToBool($default);
        }
        return isset($default) ? 'DEFAULT ' . $default : '';
    }

    /**
     * Gets the  Column Definition for a Column object.
     *
     * @param Column $column Column
     * @return string
     */
    protected function getColumnSqlDefinition(Column $column)
    {
        $buffer = array();
        if ($column->isIdentity()) {
            $buffer[] = $column->getType() == 'biginteger' ? 'bigint IDENTITY' : 'int IDENTITY';
        } else {
            $sqlType = $this->getSqlType($column->getType(), $column->getLimit());
            $buffer[] = strtoupper($sqlType['name']);
            // integers cant have limits in postgres
            if (static::PHINX_TYPE_DECIMAL === $sqlType['name'] && ($column->getPrecision() || $column->getScale())) {
                $buffer[] = sprintf(
                    '(%s, %s)',
                    $column->getPrecision() ? $column->getPrecision() : $sqlType['precision'],
                    $column->getScale() ? $column->getScale() : $sqlType['scale']
                );
            }else if($column->getType()==static::PHINX_TYPE_STRING){
                $buffer[]=sprintf("(%s)",$sqlType['limit']);
            }

        }

        $buffer[] = $column->isNull() ? 'NULL' : 'NOT NULL';

        if (!is_null($column->getDefault())) {
            $buffer[] = $this->getDefaultValueDefinition($column->getDefault());
        }

        return implode(' ', $buffer);
    }


    /**
     * Gets the  Index Definition for an Index object.
     *
     * @param Index  $index Index
     * @param string $tableName Table name
     * @return string
     */
    protected function getIndexSqlDefinition(Index $index, $tableName)
    {
        if (is_string($index->getName())) {
            $indexName = $index->getName();
        } else {
            $columnNames = $index->getColumns();
            if (is_string($columnNames)) {
                $columnNames = array($columnNames);
            }
            $indexName = sprintf('%s_%s', $tableName, implode('_', $columnNames));
        }

        $fieldName=function ($cols){
            $tmp=[];
            foreach ($cols as $col){
                $tmp[]=$this->quoteColumnName($col);
            }
            return implode(",",$tmp);
        };
        $def = sprintf(
            "CREATE %s INDEX %s ON %s (%s);",
            ($index->getType() === Index::UNIQUE ? 'UNIQUE' : ''),
            $indexName,
            $this->quoteTableName($tableName),
            $fieldName($index->getColumns())
        );
        return $def;
    }

    /**
     * Gets the MySQL Foreign Key Definition for an ForeignKey object.
     *
     * @param ForeignKey $foreignKey
     * @param string     $tableName  Table name
     * @return string
     */
    protected function getForeignKeySqlDefinition(ForeignKey $foreignKey, $tableName)
    {
        $constraintName = $foreignKey->getConstraint() ?: $this->quoteTableName($tableName) . '_' . implode('_', $foreignKey->getColumns());
        $def = ' CONSTRAINT "' . $constraintName . '" FOREIGN KEY ("' . implode('", "', $foreignKey->getColumns()) . '")';
        $def .= " REFERENCES {$this->quoteTableName($foreignKey->getReferencedTable()->getName())} (\"" . implode('", "', $foreignKey->getReferencedColumns()) . '")';
        if ($foreignKey->getOnDelete()) {
            $def .= " ON DELETE {$foreignKey->getOnDelete()}";
        }
        if ($foreignKey->getOnUpdate()) {
            $def .= " ON UPDATE {$foreignKey->getOnUpdate()}";
        }
        return $def;
    }

    /**
     * {@inheritdoc}
     */
    public function getColumnTypes()
    {
        return array_merge(parent::getColumnTypes(), array('json', 'jsonb'));
    }

    /**
     * {@inheritdoc}
     */
    public function isValidColumnType(Column $column)
    {
        // If not a standard column type, maybe it is array type?
        return (parent::isValidColumnType($column) || $this->isArrayType($column->getType()));
    }

    /**
     * Check if the given column is an array of a valid type.
     *
     * @param  string $columnType
     * @return bool
     */
    protected function isArrayType($columnType)
    {
        if (!preg_match('/^([a-z]+)(?:\[\]){1,}$/', $columnType, $matches)) {
            return false;
        }

        $baseType = $matches[1];
        return in_array($baseType, $this->getColumnTypes());
    }

    /**
     * Gets the schema name.
     *
     * @return string
     */
    private function getSchemaName()
    {
//        return '';
//        $options = $this->getOptions();
//        return empty($options['schema']) ? 'public' : $options['schema'];
    }

    /**
     * Cast a value to a boolean appropriate for the adapter.
     *
     * @param mixed $value The value to be cast
     *
     * @return mixed
     */
    public function castToBool($value)
    {
        return (bool) $value ? 'TRUE' : 'FALSE';
    }
}
