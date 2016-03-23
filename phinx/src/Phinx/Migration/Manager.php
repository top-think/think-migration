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
 * @subpackage Phinx\Migration
 */
namespace Phinx\Migration;

use Phinx\Db\Adapter\AdapterFactory;
use Phinx\Db\Adapter\AdapterInterface;
use Phinx\Db\Adapter\ProxyAdapter;
use think\console\Output;
use Phinx\Config;
use Phinx\Migration\Manager\Environment;
use Phinx\Seed\AbstractSeed;
use Phinx\Seed\SeedInterface;
use Phinx\Util;

class Manager
{
    /**
     * @var Config
     */
    protected $config;

    /**
     * @var Output
     */
    protected $output;


    /**
     * @var array
     */
    protected $migrations;

    /**
     * @var array
     */
    protected $seeds;

    /**
     * @var integer
     */
    const EXIT_STATUS_DOWN = 1;

    /**
     * @var integer
     */
    const EXIT_STATUS_MISSING = 2;

    /**
     * @var int
     */
    protected $currentVersion;

    /**
     * @var AdapterInterface
     */
    protected $adapter;

    /**
     * Class Constructor.
     *
     * @param Config $config Configuration Object
     * @param Output $output Console Output
     */
    public function __construct(Config $config, Output $output)
    {
        $this->setConfig($config);
        $this->setOutput($output);
    }

    /**
     * Prints the specified environment's migration status.
     *
     * @param null $format
     * @return integer 0 if all migrations are up, or an error code
     */
    public function printStatus($format = null)
    {
        $output = $this->getOutput();
        $migrations = array();
        $hasDownMigration = false;
        $hasMissingMigration = false;
        if (count($this->getMigrations())) {
            // TODO - rewrite using Symfony Table Helper as we already have this library
            // included and it will fix formatting issues (e.g drawing the lines)
            $output->writeln('');
            $output->writeln(' Status  Migration ID    Started              Finished             Migration Name ');
            $output->writeln('----------------------------------------------------------------------------------');

            $versions = $this->getVersionLog();
            $maxNameLength = $versions ? max(array_map(function ($version) {
                return strlen($version['migration_name']);
            }, $versions)) : 0;

            foreach ($this->getMigrations() as $migration) {
                $version = array_key_exists($migration->getVersion(), $versions) ? $versions[$migration->getVersion()] : false;
                if ($version) {
                    $status = '     <info>up</info> ';
                } else {
                    $hasDownMigration = true;
                    $status = '   <error>down</error> ';
                }
                $maxNameLength = max($maxNameLength, strlen($migration->getName()));

                $output->writeln(sprintf(
                    '%s %14.0f  %19s  %19s  <comment>%s</comment>',
                    $status, $migration->getVersion(), $version['start_time'], $version['end_time'], $migration->getName()
                ));
                $migrations[] = array('migration_status' => trim(strip_tags($status)), 'migration_id' => sprintf('%14.0f', $migration->getVersion()), 'migration_name' => $migration->getName());
                unset($versions[$migration->getVersion()]);
            }

            if (count($versions)) {
                $hasMissingMigration = true;
                foreach ($versions as $missing => $version) {
                    $output->writeln(sprintf(
                        '     <error>up</error>  %14.0f  %19s  %19s  <comment>%s</comment>  <error>** MISSING **</error>',
                        $missing, $version['start_time'], $version['end_time'], str_pad($version['migration_name'], $maxNameLength, ' ')
                    ));
                }
            }
        } else {
            // there are no migrations
            $output->writeln('');
            $output->writeln('There are no available migrations. Try creating one using the <info>create</info> command.');
        }

        // write an empty line
        $output->writeln('');
        if ($format !== null) {
            switch ($format) {
                case 'json':
                    $output->writeln(json_encode(
                        array(
                            'pending_count' => count($this->getMigrations()),
                            'migrations'    => $migrations
                        )
                    ));
                    break;
                default:
                    $output->writeln('<info>Unsupported format: ' . $format . '</info>');
            }
        }

        if ($hasMissingMigration) {
            return self::EXIT_STATUS_MISSING;
        } else if ($hasDownMigration) {
            return self::EXIT_STATUS_DOWN;
        } else {
            return 0;
        }
    }

    /**
     * Migrate to the version of the database on a given date.
     *
     * @param \DateTime $dateTime Date to migrate to
     *
     * @return void
     */
    public function migrateToDateTime(\DateTime $dateTime)
    {
        $versions = array_keys($this->getMigrations());
        $dateString = $dateTime->format('YmdHis');

        $outstandingMigrations = array_filter($versions, function ($version) use ($dateString) {
            return $version <= $dateString;
        });

        if (count($outstandingMigrations) > 0) {
            $migration = max($outstandingMigrations);
            $this->getOutput()->writeln('Migrating to version ' . $migration);
            $this->migrate($migration);
        }
    }

    /**
     * Roll back to the version of the database on a given date.
     *
     * @param \DateTime $dateTime Date to roll back to
     *
     * @return void
     */
    public function rollbackToDateTime(\DateTime $dateTime)
    {
        $versions = $this->getVersions();
        $dateString = $dateTime->format('YmdHis');
        sort($versions);

        $earlierVersion = null;
        $availableMigrations = array_filter($versions, function ($version) use ($dateString, &$earlierVersion) {
            if ($version <= $dateString) {
                $earlierVersion = $version;
            }
            return $version >= $dateString;
        });

        if (count($availableMigrations) > 0) {
            if (is_null($earlierVersion)) {
                $this->getOutput()->writeln('Rolling back all migrations');
                $migration = 0;
            } else {
                $this->getOutput()->writeln('Rolling back to version ' . $earlierVersion);
                $migration = $earlierVersion;
            }
            $this->rollback($migration);
        }
    }

    /**
     * Migrate an environment to the specified version.
     *
     * @param int $version
     * @return void
     */
    public function migrate($version = null)
    {
        $migrations = $this->getMigrations();

        $versions = $this->getVersions();
        $current = $this->getCurrentVersion();

        if (empty($versions) && empty($migrations)) {
            return;
        }

        if (null === $version) {
            $version = max(array_merge($versions, array_keys($migrations)));
        } else {
            if (0 != $version && !isset($migrations[$version])) {
                $this->output->writeln(sprintf(
                    '<comment>warning</comment> %s is not a valid version',
                    $version
                ));
                return;
            }
        }

        // are we migrating up or down?
        $direction = $version > $current ? MigrationInterface::UP : MigrationInterface::DOWN;

        if ($direction === MigrationInterface::DOWN) {
            // run downs first
            krsort($migrations);
            foreach ($migrations as $migration) {
                if ($migration->getVersion() <= $version) {
                    break;
                }

                if (in_array($migration->getVersion(), $versions)) {
                    $this->executeMigration($migration, MigrationInterface::DOWN);
                }
            }
        }

        ksort($migrations);
        foreach ($migrations as $migration) {
            if ($migration->getVersion() > $version) {
                break;
            }

            if (!in_array($migration->getVersion(), $versions)) {
                $this->executeMigration($migration, MigrationInterface::UP);
            }
        }
    }

    /**
     * Execute a migration against the specified environment.
     *
     * @param MigrationInterface $migration Migration
     * @param string $direction Direction
     * @return void
     */
    public function executeMigration(MigrationInterface $migration, $direction = MigrationInterface::UP)
    {
        $this->getOutput()->writeln('');
        $this->getOutput()->writeln(
            ' =='
            . ' <info>' . $migration->getVersion() . ' ' . $migration->getName() . ':</info>'
            . ' <comment>' . ($direction === MigrationInterface::UP ? 'migrating' : 'reverting') . '</comment>'
        );

        // Execute the migration and log the time elapsed.
        $start = microtime(true);

        $startTime = time();
        $direction = ($direction === MigrationInterface::UP) ? MigrationInterface::UP : MigrationInterface::DOWN;
        $migration->setAdapter($this->getAdapter());

        // begin the transaction if the adapter supports it
        if ($this->getAdapter()->hasTransactions()) {
            $this->getAdapter()->beginTransaction();
        }

        // Run the migration
        if (method_exists($migration, MigrationInterface::CHANGE)) {
            if ($direction === MigrationInterface::DOWN) {
                // Create an instance of the ProxyAdapter so we can record all
                // of the migration commands for reverse playback
                /** @var ProxyAdapter $proxyAdapter */
                $proxyAdapter = AdapterFactory::instance()
                    ->getWrapper('proxy', $this->getAdapter());
                $migration->setAdapter($proxyAdapter);
                /** @noinspection PhpUndefinedMethodInspection */
                $migration->change();
                $proxyAdapter->executeInvertedCommands();
                $migration->setAdapter($this->getAdapter());
            } else {
                /** @noinspection PhpUndefinedMethodInspection */
                $migration->change();
            }
        } else {
            $migration->{$direction}();
        }

        // commit the transaction if the adapter supports it
        if ($this->getAdapter()->hasTransactions()) {
            $this->getAdapter()->commitTransaction();
        }

        // Record it in the database
        $this->getAdapter()->migrated($migration, $direction, date('Y-m-d H:i:s', $startTime), date('Y-m-d H:i:s', time()));


        $end = microtime(true);

        $this->getOutput()->writeln(
            ' =='
            . ' <info>' . $migration->getVersion() . ' ' . $migration->getName() . ':</info>'
            . ' <comment>' . ($direction === MigrationInterface::UP ? 'migrated' : 'reverted')
            . ' ' . sprintf('%.4fs', $end - $start) . '</comment>'
        );
    }

    /**
     * Execute a seeder against the specified environment.
     *
     * @param SeedInterface $seed Seed
     * @return void
     */
    public function executeSeed(SeedInterface $seed)
    {
        $this->getOutput()->writeln('');
        $this->getOutput()->writeln(
            ' =='
            . ' <info>' . $seed->getName() . ':</info>'
            . ' <comment>seeding</comment>'
        );

        // Execute the seeder and log the time elapsed.
        $start = microtime(true);

        $seed->setAdapter($this->getAdapter());

        // begin the transaction if the adapter supports it
        if ($this->getAdapter()->hasTransactions()) {
            $this->getAdapter()->beginTransaction();
        }

        // Run the seeder
        if (method_exists($seed, SeedInterface::RUN)) {
            $seed->run();
        }

        // commit the transaction if the adapter supports it
        if ($this->getAdapter()->hasTransactions()) {
            $this->getAdapter()->commitTransaction();
        }

        $end = microtime(true);

        $this->getOutput()->writeln(
            ' =='
            . ' <info>' . $seed->getName() . ':</info>'
            . ' <comment>seeded'
            . ' ' . sprintf('%.4fs', $end - $start) . '</comment>'
        );
    }

    /**
     * Rollback an environment to the specified version.
     *
     * @param int $version
     * @return void
     */
    public function rollback($version = null)
    {
        $migrations = $this->getMigrations();
        $versions = $this->getVersions();

        ksort($migrations);
        sort($versions);

        // Check we have at least 1 migration to revert
        if (empty($versions) || $version == end($versions)) {
            $this->getOutput()->writeln('<error>No migrations to rollback</error>');
            return;
        }

        // If no target version was supplied, revert the last migration
        if (null === $version) {
            // Get the migration before the last run migration
            $prev = count($versions) - 2;
            $version = $prev >= 0 ? $versions[$prev] : 0;
        } else {
            // Get the first migration number
            $first = reset($versions);

            // If the target version is before the first migration, revert all migrations
            if ($version < $first) {
                $version = 0;
            }
        }

        // Check the target version exists
        if (0 !== $version && !isset($migrations[$version])) {
            $this->getOutput()->writeln("<error>Target version ($version) not found</error>");
            return;
        }

        // Revert the migration(s)
        krsort($migrations);
        foreach ($migrations as $migration) {
            if ($migration->getVersion() <= $version) {
                break;
            }

            if (in_array($migration->getVersion(), $versions)) {
                $this->executeMigration($migration, MigrationInterface::DOWN);
            }
        }
    }

    /**
     * Run database seeders against an environment.
     *
     * @param string $seed Seeder
     * @return void
     */
    public function seed($seed = null)
    {
        $seeds = $this->getSeeds();

        if (null === $seed) {
            // run all seeders
            foreach ($seeds as $seeder) {
                if (array_key_exists($seeder->getName(), $seeds)) {
                    $this->executeSeed($seeder);
                }
            }
        } else {
            // run only one seeder
            if (array_key_exists($seed, $seeds)) {
                $this->executeSeed($seeds[$seed]);
            } else {
                throw new \InvalidArgumentException(sprintf('The seed class "%s" does not exist', $seed));
            }
        }
    }


    /**
     * Sets the console output.
     *
     * @param Output $output Output
     * @return Manager
     */
    public function setOutput(Output $output)
    {
        $this->output = $output;
        return $this;
    }

    /**
     * Gets the console output.
     *
     * @return Output
     */
    public function getOutput()
    {
        return $this->output;
    }

    /**
     * Sets the database migrations.
     *
     * @param array $migrations Migrations
     * @return Manager
     */
    public function setMigrations(array $migrations)
    {
        $this->migrations = $migrations;
        return $this;
    }

    /**
     * Gets an array of the database migrations.
     *
     * @throws \InvalidArgumentException
     * @return AbstractMigration[]
     */
    public function getMigrations()
    {
        if (null === $this->migrations) {
            $config = $this->getConfig();
            $phpFiles = glob($config->getMigrationPath() . DIRECTORY_SEPARATOR . '*.php', GLOB_BRACE);

            // filter the files to only get the ones that match our naming scheme
            $fileNames = array();
            /** @var AbstractMigration[] $versions */
            $versions = array();

            foreach ($phpFiles as $filePath) {
                if (Util::isValidMigrationFileName(basename($filePath))) {
                    $version = Util::getVersionFromFileName(basename($filePath));

                    if (isset($versions[$version])) {
                        throw new \InvalidArgumentException(sprintf('Duplicate migration - "%s" has the same version as "%s"', $filePath, $versions[$version]->getVersion()));
                    }

                    // convert the filename to a class name
                    $class = Util::mapFileNameToClassName(basename($filePath));

                    if (isset($fileNames[$class])) {
                        throw new \InvalidArgumentException(sprintf(
                            'Migration "%s" has the same name as "%s"',
                            basename($filePath),
                            $fileNames[$class]
                        ));
                    }

                    $fileNames[$class] = basename($filePath);

                    // load the migration file
                    /** @noinspection PhpIncludeInspection */
                    require_once $filePath;
                    if (!class_exists($class)) {
                        throw new \InvalidArgumentException(sprintf(
                            'Could not find class "%s" in file "%s"',
                            $class,
                            $filePath
                        ));
                    }

                    // instantiate it
                    $migration = new $class($version);

                    if (!($migration instanceof AbstractMigration)) {
                        throw new \InvalidArgumentException(sprintf(
                            'The class "%s" in file "%s" must extend \Phinx\Migration\AbstractMigration',
                            $class,
                            $filePath
                        ));
                    }

                    $migration->setOutput($this->getOutput());
                    $versions[$version] = $migration;
                }
            }

            ksort($versions);
            $this->setMigrations($versions);
        }

        return $this->migrations;
    }

    /**
     * Sets the database seeders.
     *
     * @param array $seeds Seeders
     * @return Manager
     */
    public function setSeeds(array $seeds)
    {
        $this->seeds = $seeds;
        return $this;
    }

    /**
     * Gets an array of database seeders.
     *
     * @throws \InvalidArgumentException
     * @return AbstractSeed[]
     */
    public function getSeeds()
    {
        if (null === $this->seeds) {
            $config = $this->getConfig();
            $phpFiles = glob($config->getSeedPath() . DIRECTORY_SEPARATOR . '*.php');

            // filter the files to only get the ones that match our naming scheme
            $fileNames = array();
            /** @var AbstractSeed[] $seeds */
            $seeds = array();

            foreach ($phpFiles as $filePath) {
                if (Util::isValidSeedFileName(basename($filePath))) {
                    // convert the filename to a class name
                    $class = pathinfo($filePath, PATHINFO_FILENAME);
                    $fileNames[$class] = basename($filePath);

                    // load the seed file
                    /** @noinspection PhpIncludeInspection */
                    require_once $filePath;
                    if (!class_exists($class)) {
                        throw new \InvalidArgumentException(sprintf(
                            'Could not find class "%s" in file "%s"',
                            $class,
                            $filePath
                        ));
                    }

                    // instantiate it
                    $seed = new $class();

                    if (!($seed instanceof AbstractSeed)) {
                        throw new \InvalidArgumentException(sprintf(
                            'The class "%s" in file "%s" must extend \Phinx\Seed\AbstractSeed',
                            $class,
                            $filePath
                        ));
                    }

                    $seed->setOutput($this->getOutput());
                    $seeds[$class] = $seed;
                }
            }

            ksort($seeds);
            $this->setSeeds($seeds);
        }

        return $this->seeds;
    }

    /**
     * Sets the config.
     *
     * @param  Config $config Configuration Object
     * @return Manager
     */
    public function setConfig(Config $config)
    {
        $this->config = $config;
        return $this;
    }

    /**
     * Gets the config.
     *
     * @return Config
     */
    public function getConfig()
    {
        return $this->config;
    }

    /**
     * Gets all migrated version numbers.
     *
     * @return array
     */
    public function getVersions()
    {
        return $this->getAdapter()->getVersions();
    }

    /**
     * Get all migration log entries, indexed by version number.
     *
     * @return array
     */
    public function getVersionLog()
    {
        return $this->getAdapter()->getVersionLog();
    }

    /**
     * Sets the current version of the environment.
     *
     * @param int $version Environment Version
     * @return Environment
     */
    public function setCurrentVersion($version)
    {
        $this->currentVersion = $version;
        return $this;
    }

    /**
     * Gets the current version of the environment.
     *
     * @return int
     */
    public function getCurrentVersion()
    {
        // We don't cache this code as the current version is pretty volatile.
        // TODO - that means they're no point in a setter then?
        // maybe we should cache and call a reset() method everytime a migration is run
        $versions = $this->getVersions();
        $version = 0;

        if (!empty($versions)) {
            $version = end($versions);
        }

        $this->setCurrentVersion($version);
        return $this->currentVersion;
    }

    /**
     * Sets the database adapter.
     *
     * @param AdapterInterface $adapter Database Adapter
     * @return Environment
     */
    public function setAdapter(AdapterInterface $adapter)
    {
        $this->adapter = $adapter;
        return $this;
    }

    /**
     * Gets the database adapter.
     *
     * @return AdapterInterface
     */
    public function getAdapter()
    {
        if (isset($this->adapter)) {
            return $this->adapter;
        }

        $options = $this->config->getDbConfig();

        if (!isset($options['adapter'])) {
            throw new \RuntimeException('No adapter was specified');
        }

        $adapter = AdapterFactory::instance()
            ->getAdapter($options['adapter'], $options);


        if ($this->getOutput()) {
            $adapter->setOutput($this->getOutput());
        }

        // Use the TablePrefixAdapter if table prefix/suffixes are in use
        if ($adapter->hasOption('table_prefix') || $adapter->hasOption('table_suffix')) {
            $adapter = AdapterFactory::instance()
                ->getWrapper('prefix', $adapter);
        }

        $this->setAdapter($adapter);

        return $adapter;
    }
}
