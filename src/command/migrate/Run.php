<?php
// +----------------------------------------------------------------------
// | TopThink [ WE CAN DO IT JUST THINK IT ]
// +----------------------------------------------------------------------
// | Copyright (c) 2015 http://www.topthink.com All rights reserved.
// +----------------------------------------------------------------------
// | Author: zhangyajun <448901948@qq.com>
// +----------------------------------------------------------------------

namespace think\migration\command\migrate;

use think\console\Input;
use think\console\input\Option as InputOption;
use think\console\Output;
use think\migration\command\AbstractCommand;

class Run extends AbstractCommand
{
    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        parent::configure();

        $this->setName('migrate:run')
             ->setDescription('Migrate the database')
             ->addOption('--target', '-t', InputOption::VALUE_REQUIRED, 'The version number to migrate to')
             ->addOption('--date', '-d', InputOption::VALUE_REQUIRED, 'The date to migrate to')
             ->setHelp(
<<<EOT
The <info>migrate:run</info> command runs all available migrations, optionally up to a specific version

<info>php console migrate:run</info>
<info>php console migrate:run -t 20110103081132</info>
<info>php console migrate:run -d 20110103</info>
<info>php console migrate:run -v</info>

EOT
             );
    }

    /**
     * Migrate the database.
     *
     * @param Input $input
     * @param Output $output
     * @return integer integer 0 on success, or an error code.
     */
    protected function execute(Input $input, Output $output)
    {
        $this->bootstrap($input, $output);

        $version     = $input->getOption('target');
        $date        = $input->getOption('date');

        $dbConfig = $this->config->getDbConfig();

        if (isset($dbConfig['adapter'])) {
            $output->writeln('<info>using adapter</info> ' . $dbConfig['adapter']);
        }
        
        if (isset($dbConfig['name'])) {
            $output->writeln('<info>using database</info> ' . $dbConfig['name']);
        } else {
            $output->writeln('<error>Could not determine database name! Please specify a database name in your config file.</error>');
            return 1;
        }

        if (isset($dbConfig['table_prefix'])) {
            $output->writeln('<info>using table prefix</info> ' . $dbConfig['table_prefix']);
        }


        // run the migrations
        $start = microtime(true);
        if (null !== $date) {
            $this->getManager()->migrateToDateTime(new \DateTime($date));
        } else {
            $this->getManager()->migrate($version);
        }
        $end = microtime(true);

        $output->writeln('');
        $output->writeln('<comment>All Done. Took ' . sprintf('%.4fs', $end - $start) . '</comment>');

        return 0;
    }
}
