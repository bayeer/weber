<?php
namespace Weber\Command;

use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Weber\Command\SetupSiteCommand;
use Weber\Command\DeleteSiteCommand;
use PHPUnit\Framework\TestCase;

class RenameSiteCommandTest extends TestCase
{
    public function testSetupSite()
    {
        $sitename1 = uniqid('site1_');
        $sitename2 = uniqid('site2_');
        $dirname1 = $sitename1 . '.loc';
        $dirname2 = $sitename2 . '.loc';
        $weberpath = realpath(__DIR__ . '/..');

        $conf = include($weberpath . '/includes/conf.php');

        // check if mysql user is root
        $this->assertEquals('root', $conf['mysql']['username'], 'Database user is not \'root\'');


        $app = new Application('weber', $conf['version']);
        $app->add(new SetupSiteCommand());
        $app->add(new RenameSiteCommand());
        $app->add(new DeleteSiteCommand());


        // create site

        $command = $app->find('setup-site');
        $commandTester = new CommandTester($command);
        $commandTester->execute(array(
                                    'command' => $command->getName(),
                                    'sitename' => $sitename1,
                                    'type' => 'bitrix'
                                ));

        $sitepath1 = $conf['document_root'] . '/' . $dirname1;

        $this->assertTrue(file_exists($sitepath1) && is_dir($sitepath1), 'Could not create folder');


        // rename site

        $command = $app->find('rename-site');
        $commandTester = new CommandTester($command);
        $commandTester->execute(array(
                                    'command' => $command->getName(),
                                    'sitename1' => $sitename1,
                                    'sitename2' => $sitename2,
                                    'type' => 'bitrix'
                                ));

        $sitepath2 = $conf['document_root'] . '/' . $dirname2;
        $this->assertTrue(file_exists($sitepath2) && is_dir($sitepath2), 'Could not rename folder');


        // delete site

        $command = $app->find('delete-site');
        $commandTester = new CommandTester($command);
        $commandTester->execute(array(
                                    'command' => $command->getName(),
                                    'sitename' => $sitename2
                                ));
        $this->assertFalse(file_exists($conf['document_root'] . '/' . $sitename2), 'Could not delete folder');
    }
}