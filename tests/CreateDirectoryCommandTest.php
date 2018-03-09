<?php
namespace Tests\Weber\Command;

use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Weber\Command\CreateDirectoryCommand;
use PHPUnit\Framework\TestCase;

class CreateDirectoryCommandTest extends TestCase
{
    public function testCreateDir()
    {
        $dirname = 'test';
        $path = realpath(__DIR__ . '/..') . '/' . $dirname;

        $_SERVER['argv']['create-directory'] = 'create-directory';
        $_SERVER['argv'][$dirname] = $dirname;

        $app = new Application('weber', 'v2.3');
        $app->add(new CreateDirectoryCommand());

        $command = $app->find('create-directory');
        $commandTester = new CommandTester($command);
        $commandTester->execute(array(
                                    'command' => $command->getName(),
                                    'dirname' => $dirname
                                ));

        $this->assertTrue(file_exists($path) && is_dir($path), 'Could not create folder');

        $this->assertFalse(false === rmdir($path), 'Could not delete folder');
    }
}