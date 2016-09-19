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

class SetupSiteCommand extends Command
{
    protected function configure()
    {
        $help = <<<EOF
Try like this:
> ./weber setup-site test.dev --charset=cp1251"

Weber v2.1 by Bayeer, 2016

EOF;
        $this
            ->setName('setup-site')
            ->setDescription('Sets up new site')
            ->setHelp($help)
            ->setDefinition(
                new InputDefinition(array(
                    new InputArgument('sitename', InputArgument::REQUIRED, 'The directory name.'),
                    new InputArgument('type', InputArgument::OPTIONAL, 'Site type', 'simple'),
                    new InputOption('charset', 'charset', InputArgument::OPTIONAL, 'The site character set', 'utf8')
                ))
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $sitename = $input->getArgument('sitename');
        $type = $input->getArgument('type');
        $charset = $input->getOption('charset');

        $weberpath = realpath(__DIR__ . '/../../');

        $output->writeln([
            'Directory creating',
            '==================',
            ''
        ]);

        $conf = include($weberpath . '/includes/conf.php');

        $weber = new Weber($this, $input, $output);

        // 2. getting sitename
        $siteName = $weber->getSitename($sitename);
        $dirName = $siteName . '.dev';

        // 3. paths and dirs
        $siteDir = $conf['document_root'] . $dirName;
        $nginxSitesAvailableDir = $conf['nginx_dir'].'sites-available/';
        $nginxSitesEnabledDir = $conf['nginx_dir'].'sites-enabled/';

        // 2. creating directory if not exists
        $output->writeln('Trying to create directory '. $siteDir. '...'. PHP_EOL);
        $weber->createDirectory($conf['document_root'], $dirName);

        // 3. adding site to /etc/hosts
        $weber->addToEtcHosts($dirName);

        // 4. writing nginx config
        $weber->createNginxConfig($type, $conf['document_root'], $dirName, $conf['nginx_dir'], $conf['nginx_log_dir'], $charset, $conf['phpfpm_socket_path']);

        if ($type == 'bitrix') {
            // 5. downloading bitrixsetup.php script and creating MySQL user $dirName
            $weber->processBitrix($conf['document_root'], $dirName, $charset);
        }

        if ($type !== 'simple') {
            // 6. create MySQL user
            $weber->createMySqlUser($conf['mysql']['host'], $siteName, $conf['mysql']['default_password'], $conf['mysql']['root_password'], $charset);
        }

        if ($type == 'laravel') {
            $weber->executeLaraComposer($conf['document_root'], $dirName);
        }

        // 7. set site directory owner including inner files
        $weber->setFolderOwner($conf['document_root'], $dirName, $conf['os_username'], $conf['os_usergroup']);

        // 7. restarting nginx
        $weber->restartNginx($conf['nginx_restart_cmd']);

    }
}