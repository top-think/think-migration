<?php
// +----------------------------------------------------------------------
// | TopThink [ WE CAN DO IT JUST THINK IT ]
// +----------------------------------------------------------------------
// | Copyright (c) 2015 http://www.topthink.com All rights reserved.
// +----------------------------------------------------------------------
// | Author: zhangyajun <448901948@qq.com>
// +----------------------------------------------------------------------

namespace think\migration\command\migrate;


use think\migration\command\AbstractCommand;
use think\console\input\Option as InputOption;
use think\console\Input;
use think\console\Output;

class Status extends AbstractCommand
{
    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        parent::configure();
        
        $this->setName('migrate:status')
             ->setDescription('Show migration status')
             ->addOption('--format', '-f', InputOption::VALUE_REQUIRED, 'The output format: text or json. Defaults to text.')
             ->setHelp(
<<<EOT
The <info>migrate:status</info> command prints a list of all migrations, along with their current status

<info>php console migrate:status</info>
<info>php console migrate:status -f json</info>
EOT
             );
    }

    /**
     * Show the migration status.
     *
     * @param Input $input
     * @param Output $output
     * @return integer 0 if all migrations are up, or an error code
     */
    protected function execute(Input $input, Output $output)
    {
        $this->bootstrap($input, $output);

        $format = $input->getOption('format');


        if (null !== $format) {
            $output->writeln('<info>using format</info> ' . $format);
        }

        // print the status
        return $this->getManager()->printStatus($format);
    }
}
