<?php
// +----------------------------------------------------------------------
// | TopThink [ WE CAN DO IT JUST THINK IT ]
// +----------------------------------------------------------------------
// | Copyright (c) 2015 http://www.topthink.com All rights reserved.
// +----------------------------------------------------------------------
// | Author: zhangyajun <448901948@qq.com>
// +----------------------------------------------------------------------

namespace think\migration\command\migrate;

use think\console\input\Option as InputOption;
use think\console\Input;
use think\console\Output;
use think\migration\command\AbstractCommand;

class Rollback extends AbstractCommand
{
    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        parent::configure();

        $this->setName('migrate:rollback')
             ->setDescription('Rollback the last or to a specific migration')
             ->addOption('--target', '-t', InputOption::VALUE_REQUIRED, 'The version number to rollback to')
             ->addOption('--date', '-d', InputOption::VALUE_REQUIRED, 'The date to rollback to')
             ->setHelp(
<<<EOT
The <info>migrate:rollback</info> command reverts the last migration, or optionally up to a specific version

<info>php console migrate:rollback</info>
<info>php console migrate:rollback -t 20111018185412</info>
<info>php console migrate:rollback -d 20111018</info>
<info>php console migrate:rollback -v</info>

EOT
             );
    }

    /**
     * Rollback the migration.
     *
     * @param Input $input
     * @param Output $output
     * @return void
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
        } 
        
        
        // rollback the specified environment
        $start = microtime(true);
        if (null !== $date) {
            $this->getManager()->rollbackToDateTime(new \DateTime($date));
        } else {
            $this->getManager()->rollback($version);
        }
        $end = microtime(true);

        $output->writeln('');
        $output->writeln('<comment>All Done. Took ' . sprintf('%.4fs', $end - $start) . '</comment>');
    }
}
