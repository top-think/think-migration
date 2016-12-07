<?php
// +----------------------------------------------------------------------
// | TopThink [ WE CAN DO IT JUST THINK IT ]
// +----------------------------------------------------------------------
// | Copyright (c) 2015 http://www.topthink.com All rights reserved.
// +----------------------------------------------------------------------
// | Author: zhangyajun <448901948@qq.com>
// +----------------------------------------------------------------------

namespace think\migration\command;

use InvalidArgumentException;
use think\console\Command;
use think\console\Input;
use think\console\input\Option as InputOption;
use think\console\Output;
use Phinx\Config;
use Phinx\Migration\Manager;
use Phinx\Db\Adapter\AdapterInterface;

abstract class AbstractCommand extends Command
{
    /**
     * The location of the default migration template.
     */
    const DEFAULT_MIGRATION_TEMPLATE = '/../../phinx/src/Phinx/Migration/Migration.template.php.dist';

    /**
     * The location of the default seed template.
     */
    const DEFAULT_SEED_TEMPLATE = '/../../phinx/src/Phinx/Seed/Seed.template.php.dist';

    /**
     * @var Config
     */
    protected $config;

    /**
     * @var AdapterInterface
     */
    protected $adapter;

    /**
     * @var Manager
     */
    protected $manager;

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this->addOption('--configuration', '-c', InputOption::VALUE_REQUIRED, 'The configuration file to load');
    }

    /**
     * Bootstrap Phinx.
     *
     * @param Input $input
     * @param Output $output
     * @return void
     */
    public function bootstrap(Input $input, Output $output)
    {
        if (!$this->getConfig()) {
            $this->loadConfig($input, $output);
        }

        $this->loadManager($output);
        // report the paths
        $output->writeln('<info>using migration path</info> ' . $this->getConfig()->getMigrationPath());
        try {
            $output->writeln('<info>using seed path</info> ' . $this->getConfig()->getSeedPath());
        } catch (\UnexpectedValueException $e) {
            // do nothing as seeds are optional
        }
    }

    /**
     * Sets the config.
     *
     * @param  Config $config
     * @return AbstractCommand
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
     * Sets the database adapter.
     *
     * @param AdapterInterface $adapter
     * @return AbstractCommand
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
        return $this->adapter;
    }

    /**
     * Sets the migration manager.
     *
     * @param Manager $manager
     * @return AbstractCommand
     */
    public function setManager(Manager $manager)
    {
        $this->manager = $manager;
        return $this;
    }

    /**
     * Gets the migration manager.
     *
     * @return Manager
     */
    public function getManager()
    {
        return $this->manager;
    }

    /**
     * Parse the config file and load it into the config object
     *
     * @param Input $input
     * @param Output $output
     * @throws \InvalidArgumentException
     * @return void
     */
    protected function loadConfig(Input $input, Output $output)
    {
        $configFile = $input->getOption('configuration');

        if (null === $configFile || false === $configFile) {
            $configFile = CONF_PATH . 'database' . EXT;
        }

        if (!is_file($configFile)) {
            throw new InvalidArgumentException();
        }

        $output->writeln('<info>using config file</info> .' . str_replace(getcwd(), '', realpath($configFile)));

        $config = new Config($configFile);
        $this->setConfig($config);
    }

    /**
     * Load the migrations manager and inject the config
     *
     * @param Output $output
     * @return void
     */
    protected function loadManager(Output $output)
    {
        if (null === $this->getManager()) {
            $manager = new Manager($this->getConfig(), $output);
            $this->setManager($manager);
        }
    }

    /**
     * Verify that the migration directory exists and is writable.
     *
     * @param $path
     */
    protected function verifyMigrationDirectory($path)
    {
        if (!is_dir($path)) {
            throw new InvalidArgumentException(sprintf(
                'Migration directory "%s" does not exist',
                $path
            ));
        }

        if (!is_writable($path)) {
            throw new InvalidArgumentException(sprintf(
                'Migration directory "%s" is not writable',
                $path
            ));
        }
    }

    /**
     * Verify that the seed directory exists and is writable.
     *
     * @param $path
     */
    protected function verifySeedDirectory($path)
    {
        if (!is_dir($path)) {
            throw new InvalidArgumentException(sprintf(
                'Seed directory "%s" does not exist',
                $path
            ));
        }

        if (!is_writable($path)) {
            throw new InvalidArgumentException(sprintf(
                'Seed directory "%s" is not writable',
                $path
            ));
        }
    }

    /**
     * Returns the migration template filename.
     *
     * @return string
     */
    protected function getMigrationTemplateFilename()
    {
        return __DIR__ . self::DEFAULT_MIGRATION_TEMPLATE;
    }

    /**
     * Returns the seed template filename.
     *
     * @return string
     */
    protected function getSeedTemplateFilename()
    {
        return __DIR__ . self::DEFAULT_SEED_TEMPLATE;
    }
}
