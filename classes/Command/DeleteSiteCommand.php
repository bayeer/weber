<?php
namespace Weber\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\Output;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Exception\LogicException;
use Weber\Weber;

class DeleteSiteCommand extends Command
{
    protected function configure()
    {
        $help = <<<EOF
Try like this:
> ./weber delete-site site1.test"

Weber v2.2 by Bayeer, 2016

EOF;
        $this
            ->setName('delete-site')
            ->setDescription('Deletes the site from virtual hosts and /var/www/')
            ->setHelp($help)
            ->setDefinition(
                new InputDefinition(array(
                    new InputArgument('sitename', InputArgument::REQUIRED, 'The directory name.')
                ))
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $sitename = $input->getArgument('sitename');

        $weberpath = realpath(__DIR__ . '/../../');

        $output->writeln([
            'Directory deleting',
            '==================',
            ''
        ]);

        $conf = include($weberpath . '/includes/conf.php');

        $weber = new Weber($this, $input, $output);

        // 1. getting sitename
        $siteName = $weber->getSitename($sitename);
        $dirName = $siteName . '.test';

        // 2. paths and dirs
        $siteDir = $conf['document_root'] . $dirName;
        $nginxSitesAvailableDir = $conf['nginx_dir'].'sites-available/';
        $nginxSitesEnabledDir = $conf['nginx_dir'].'sites-enabled/';

        // 3. creating directory if not exists
        $weber->deleteDirectory($conf['document_root'], $dirName);

        // 4. adding site to /etc/hosts
        $weber->deleteFromEtcHosts($dirName);

        // 5. creating nginx configs
        $weber->deleteNginxConfig($conf['nginx_dir'], $dirName);

        // 7. create MySQL user
        $weber->dropMySqlDatabase($conf['mysql']['host'], $siteName, $conf['mysql']['root_password']);

        // 8. restarting nginx
        $weber->restartNginx($conf['nginx_restart_cmd']);
    }
}