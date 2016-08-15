<?php
// +----------------------------------------------------------------------
// | TopThink [ WE CAN DO IT JUST THINK IT ]
// +----------------------------------------------------------------------
// | Copyright (c) 2015 http://www.topthink.com All rights reserved.
// +----------------------------------------------------------------------
// | Author: zhangyajun <448901948@qq.com>
// +----------------------------------------------------------------------

namespace think\migration\command\migrate;

use Phinx\Migration\CreationInterface;
use Phinx\Util;
use think\console\input\Argument as InputArgument;
use think\console\Input;
use think\console\input\Option as InputOption;
use think\console\Output;
use think\migration\command\AbstractCommand;

class Create extends AbstractCommand
{
    /**
     * The name of the interface that any external template creation class is required to implement.
     */
    const CREATION_INTERFACE = 'Phinx\Migration\CreationInterface';

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        parent::configure();

        $this->setName('migrate:create')
            ->setDescription('Create a new migration')
            ->addArgument('name', InputArgument::REQUIRED, 'What is the name of the migration?')
            ->setHelp(sprintf(
                '%sCreates a new database migration%s',
                PHP_EOL,
                PHP_EOL
            ));

        // An alternative template.
        $this->addOption('template', 't', InputOption::VALUE_REQUIRED, 'Use an alternative template');

        // A classname to be used to gain access to the template content as well as the ability to
        // have a callback once the migration file has been created.
        $this->addOption('class', 'l', InputOption::VALUE_REQUIRED, 'Use a class implementing "' . self::CREATION_INTERFACE . '" to generate the template');
    }

    /**
     * Create the new migration.
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

        // get the migration path from the config
        $path = $this->getConfig()->getMigrationPath();

        if (!file_exists($path)) {
            if ($output->confirm($input, 'Create migrations directory?')) {
                mkdir($path, 0755, true);
            }
        }

        $this->verifyMigrationDirectory($path);

        $path      = realpath($path);
        $className = $input->getArgument('name');

        if (!Util::isValidPhinxClassName($className)) {
            throw new \InvalidArgumentException(sprintf(
                'The migration class name "%s" is invalid. Please use CamelCase format.',
                $className
            ));
        }

        if (!Util::isUniqueMigrationClassName($className, $path)) {
            throw new \InvalidArgumentException(sprintf(
                'The migration class name "%s" already exists',
                $className
            ));
        }

        // Compute the file path
        $fileName = Util::mapClassNameToFileName($className);
        $filePath = $path . DIRECTORY_SEPARATOR . $fileName;

        if (is_file($filePath)) {
            throw new \InvalidArgumentException(sprintf(
                'The file "%s" already exists',
                $filePath
            ));
        }

        // Get the alternative template and static class options, but only allow one of them.
        $altTemplate = $input->getOption('template');
        if (!$altTemplate) {
            $altTemplate = $this->getConfig()->getMigrationTemplateFile();
        }

        $creationClassName = $input->getOption('class');
        if (!$creationClassName) {
            $creationClassName = $this->getConfig()->getMigrationTemplateClass();
        }

        if ($altTemplate && $creationClassName) {
            throw new \InvalidArgumentException('Cannot use --template and --class at the same time');
        }

        // Verify the alternative template file's existence.
        if ($altTemplate && !is_file($altTemplate)) {
            throw new \InvalidArgumentException(sprintf(
                'The alternative template file "%s" does not exist',
                $altTemplate
            ));
        }

        // Verify that the template creation class (or the aliased class) exists and that it implements the required interface.
        $aliasedClassName = null;
        if ($creationClassName) {
            // Supplied class does not exist, is it aliased?
            if (!class_exists($creationClassName)) {
                throw new \InvalidArgumentException(sprintf(
                    'The class "%s" does not exist',
                    $creationClassName
                ));
            }

            // Does the class implement the required interface?
            if (!is_subclass_of($creationClassName, self::CREATION_INTERFACE)) {
                throw new \InvalidArgumentException(sprintf(
                    'The class "%s" does not implement the required interface "%s"',
                    $creationClassName,
                    self::CREATION_INTERFACE
                ));
            }
        }

        // Determine the appropriate mechanism to get the template
        if ($creationClassName) {
            // Get the template from the creation class
            /** @var CreationInterface $creationClass */
            $creationClass = new $creationClassName();
            $contents      = $creationClass->getMigrationTemplate();
        } else {
            // Load the alternative template if it is defined.
            $contents = file_get_contents($altTemplate ?: $this->getMigrationTemplateFilename());
        }

        // inject the class names appropriate to this migration
        $classes  = [
            '$useClassName'  => $this->getConfig()->getMigrationBaseClassName(false),
            '$className'     => $className,
            '$version'       => Util::getVersionFromFileName($fileName),
            '$baseClassName' => $this->getConfig()->getMigrationBaseClassName(true),
        ];
        $contents = strtr($contents, $classes);

        if (false === file_put_contents($filePath, $contents)) {
            throw new \RuntimeException(sprintf(
                'The file "%s" could not be written to',
                $path
            ));
        }

        // Do we need to do the post creation call to the creation class?
        if ($creationClassName) {
            /** @var CreationInterface $creationClass */
            $creationClass->postMigrationCreation($filePath, $className, $this->getConfig()->getMigrationBaseClassName());
        }

        $output->writeln('<info>using migration base class</info> ' . $classes['$useClassName']);

        if (!empty($altTemplate)) {
            $output->writeln('<info>using alternative template</info> ' . $altTemplate);
        } elseif (!empty($creationClassName)) {
            $output->writeln('<info>using template creation class</info> ' . $creationClassName);
        } else {
            $output->writeln('<info>using default template</info>');
        }

        $output->writeln('<info>created</info> .' . str_replace(getcwd(), '', $filePath));
    }
}
