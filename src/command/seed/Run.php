<?php
// +----------------------------------------------------------------------
// | TopThink [ WE CAN DO IT JUST THINK IT ]
// +----------------------------------------------------------------------
// | Copyright (c) 2015 http://www.topthink.com All rights reserved.
// +----------------------------------------------------------------------
// | Author: zhangyajun <448901948@qq.com>
// +----------------------------------------------------------------------

namespace think\migration\command\seed;


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

        $this->setName('seed:run')
            ->setDescription('Run database seeders')
            ->addOption('--seed', '-s', InputOption::VALUE_REQUIRED, 'What is the name of the seeder?')
            ->setHelp(
                <<<EOT
                The <info>seed:run</info> command runs all available or individual seeders

<info>php console seed:run</info>
<info>php console seed:run -s UserSeeder</info>
<info>php console seed:run -v</info>

EOT
            );
    }

    /**
     * Run database seeders.
     *
     * @param Input $input
     * @param Output $output
     * @return void
     */
    protected function execute(Input $input, Output $output)
    {
        $this->bootstrap($input, $output);

        $seed = $input->getOption('seed');

        $dbConfig = $this->config->getDbConfig();

        if (isset($dbConfig['adapter'])) {
            $output->writeln('<info>using adapter</info> ' . $dbConfig['adapter']);
        }

        if (isset($dbConfig['name'])) {
            $output->writeln('<info>using database</info> ' . $dbConfig['name']);
        } else {
            $output->writeln('<error>Could not determine database name! Please specify a database name in your config file.</error>');
            return;
        }

        if (isset($dbConfig['table_prefix'])) {
            $output->writeln('<info>using table prefix</info> ' . $dbConfig['table_prefix']);
        }

        // run the seed(ers)
        $start = microtime(true);
        $this->getManager()->seed($seed);
        $end = microtime(true);

        $output->writeln('');
        $output->writeln('<comment>All Done. Took ' . sprintf('%.4fs', $end - $start) . '</comment>');
    }
}
