<?php
namespace Weber\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\Output;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Exception\LogicException;

class CreateDirectoryCommand extends Command
{
    protected function configure()
    {
        $this
            ->setName('create-directory')
            ->setDescription('Creates directory')
            ->setHelp('This command allows you to create directory...')
            ->addArgument('dirname', InputArgument::REQUIRED, 'The directory name.')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $dirname = $input->getArgument('dirname');

        $output->writeln([
            'Directory creating',
            '==================',
            ''
        ]);

        if (file_exists($dirname) && is_dir($dirname)) {
            throw new LogicException('Folder already exists');
        }

        if (false === mkdir($dirname)) {
            throw new \RuntimeException('Error creating directory');
        }

        $output->write('Directory \'');
        $output->write($dirname);
        $output->writeln('\' successfully created.');
    }
}