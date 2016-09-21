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

class RenameSiteCommand extends Command
{
    protected function configure()
    {
        $help = <<<EOF
Try like this:
> ./weber rename-site oldsitename.dev newsitename.dev"

Weber v2.1 by Bayeer, 2016

EOF;
        $this
            ->setName('rename-site')
            ->setDescription('Rename the site: forder name, virtual hosts, mysql database name and database user')
            ->setHelp($help)
            ->setDefinition(
                new InputDefinition(array(
                    new InputArgument('sitename1', InputArgument::REQUIRED, 'The old directory name.'),
                    new InputArgument('sitename2', InputArgument::REQUIRED, 'The new directory name.'),
                    new InputArgument('type', InputArgument::OPTIONAL, 'Site type', 'simple'),
                    new InputOption('charset', 'charset', InputArgument::OPTIONAL, 'The site character set', 'utf8')
                ))
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $sitename1 = $input->getArgument('sitename1');
        $sitename2 = $input->getArgument('sitename2');
        $type      = $input->getArgument('type');
        $charset   = $input->getOption('charset');

        $weberpath = realpath(__DIR__ . '/../../');

        $output->writeln([
            'Site renaming',
            '==================',
            ''
        ]);

        $conf = include($weberpath . '/includes/conf.php');

        $weber = new Weber($this, $input, $output);

        // 1. paths and dirs

        $siteName1 = $weber->getSitename($sitename1);
        $dirName1 = $siteName1 . '.dev';
        $siteDir1 = $conf['document_root'] . $dirName1;

        $siteName2 = $weber->getSitename($sitename2);
        $dirName2 = $siteName2 . '.dev';
        $siteDir2 = $conf['document_root'] . $dirName2;

        // 2. nginx directories

        $nginxSitesAvailableDir = $conf['nginx_dir'].'sites-available/';
        $nginxSitesEnabledDir = $conf['nginx_dir'].'sites-enabled/';

        // 3. creating directory if not exists
        $weber->renameDirectory($conf['document_root'], $dirName1, $dirName2);

        // 4. adding site to /etc/hosts
        $weber->deleteFromEtcHosts($dirName1);
        $weber->addToEtcHosts($dirName2);


        // 5. creating nginx configs
        $weber->deleteNginxConfig($conf['nginx_dir'], $dirName1);
        $weber->createNginxConfig($type, $conf['document_root'], $dirName2, $conf['nginx_dir'], $conf['nginx_log_dir'], $charset, $conf['phpfpm_socket_path']);

        if ($type !== 'simple') {
            // 6. create MySQL user
           $weber->renameMySqlDatabase($conf['mysql']['host'], $conf['mysql']['root_password'], $siteName1, $siteName2, $charset);
        }

        // 7. restarting nginx
        $weber->restartNginx($conf['nginx_restart_cmd']);
    }
}