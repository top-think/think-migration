<?php
// +----------------------------------------------------------------------
// | TopThink [ WE CAN DO IT JUST THINK IT ]
// +----------------------------------------------------------------------
// | Copyright (c) 2015 http://www.topthink.com All rights reserved.
// +----------------------------------------------------------------------
// | Author: zhangyajun <448901948@qq.com>
// +----------------------------------------------------------------------

namespace think\migration\command\seed;

use Phinx\Util;

use think\console\Input;
use think\console\Output;
use think\migration\command\AbstractCommand;
use think\console\input\Argument as InputArgument;

class Create extends AbstractCommand
{
    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        parent::configure();

        $this->setName('seed:create')
            ->setDescription('Create a new database seeder')
            ->addArgument('name', InputArgument::REQUIRED, 'What is the name of the seeder?')
            ->setHelp(sprintf(
                '%sCreates a new database seeder%s',
                PHP_EOL,
                PHP_EOL
            ));
    }

    /**
     * Create the new seeder.
     *
     * @param Input  $input
     * @param Output $output
     * @throws \RuntimeException
     * @throws \InvalidArgumentException
     * @return void
     */
    protected function execute(Input $input, Output $output)
    {
        $this->bootstrap($input, $output);

        // get the seed path from the config
        $path = $this->getConfig()->getSeedPath();

        if (!file_exists($path)) {
            if ($output->confirm($input, 'Create seeds directory?')) {
                mkdir($path, 0755, true);
            }
        }

        $this->verifySeedDirectory($path);

        $path      = realpath($path);
        $className = $input->getArgument('name');

        if (!Util::isValidPhinxClassName($className)) {
            throw new \InvalidArgumentException(sprintf(
                'The seed class name "%s" is invalid. Please use CamelCase format',
                $className
            ));
        }

        // Compute the file path
        $filePath = $path . DIRECTORY_SEPARATOR . $className . '.php';

        if (is_file($filePath)) {
            throw new \InvalidArgumentException(sprintf(
                'The file "%s" already exists',
                basename($filePath)
            ));
        }

        // inject the class names appropriate to this seeder
        $contents = file_get_contents($this->getSeedTemplateFilename());
        $classes  = [
            '$useClassName'  => 'Phinx\Seed\AbstractSeed',
            '$className'     => $className,
            '$baseClassName' => 'AbstractSeed',
        ];
        $contents = strtr($contents, $classes);

        if (false === file_put_contents($filePath, $contents)) {
            throw new \RuntimeException(sprintf(
                'The file "%s" could not be written to',
                $path
            ));
        }

        $output->writeln('<info>using seed base class</info> ' . $classes['$useClassName']);
        $output->writeln('<info>created</info> .' . str_replace(getcwd(), '', $filePath));
    }
}
