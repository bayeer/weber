<?php
namespace Weber\Command;

use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Weber\Command\SetupSiteCommand;
use Weber\Command\DeleteSiteCommand;
use PHPUnit\Framework\TestCase;

class SetupSiteCommandTest extends TestCase
{
    public function testSetupSite()
    {
        $sitename = 'site1';
        $dirname = 'site1.test';
        $weberpath = realpath(__DIR__ . '/..');

        $conf = include($weberpath . '/includes/conf.php');


        $app = new Application('weber', 'v2.2');
        $app->add(new SetupSiteCommand());
        $app->add(new DeleteSiteCommand());

        $command = $app->find('setup-site');
        $commandTester = new CommandTester($command);
        $commandTester->execute(array(
                                    'command' => $command->getName(),
                                    'sitename' => $sitename,
                                    'type' => 'bitrix'
                                ));

        $sitepath = $conf['document_root'] . '/' . $dirname;

        $this->assertTrue(file_exists($sitepath) && is_dir($sitepath), 'Could not create folder');



        $conf = include($weberpath . '/includes/conf.php');

        $this->assertEquals('root', $conf['mysql']['username'], 'Database user is not \'root\'');

        $command = $app->find('delete-site');
        $commandTester = new CommandTester($command);
        $commandTester->execute(array(
                                    'command' => $command->getName(),
                                    'sitename' => $sitename
                                ));
        $this->assertFalse(file_exists($conf['document_root'] . '/' . $sitename), 'Could not delete folder');
    }
}