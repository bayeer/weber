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
        $sitename = uniqid('site_');
        $dirname = $sitename . '.loc';

        $weberpath = realpath(__DIR__ . '/..');
        $conf = include($weberpath . '/includes/conf.php');


        $app = new Application('weber', $conf['version']);
        $app->add(new SetupSiteCommand());
        $app->add(new DeleteSiteCommand());

        $command = $app->find('setup-site');
        $commandTester = new CommandTester($command);
        $commandTester->execute($args = array(
                                    'command' => $command->getName(),
                                    'sitename' => $sitename,
                                    'type' => 'bitrix'
                                ),
                                $options = array(
                                    'distro' => 'no'
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