<?php
namespace Weber;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

class Weber
{
    protected $command;
    protected $input;
    protected $output;

    public function __construct(Command $cmd, InputInterface $input, OutputInterface $output)
    {
        $this->command = $cmd;
        $this->input = $input;
        $this->output = $output;
    }

    public function isSitenameMatch($etcHostsLine, $sitename)
    {
        $parts = preg_split('/[\s]/', $etcHostsLine);
        $parts_cnt = count($parts);

        if ($parts_cnt > 0 && $parts[$parts_cnt-1] === $sitename) {
            return true;
        }

        return false;
    }

    public function getSiteCharset(&$args)
    {
        $charsets = array(
            0=>'utf8', 
            1=>'cp1251'
        );
        if (!array_key_exists('charset', $args)) {
            return 'utf8';
        }
        $charset = trim($args['charset']); //$this->output->writeln(PHP_EOL.PHP_EOL.print_r($args,true).PHP_EOL.PHP_EOL);
        if (!in_array($charset, $charsets)) {
            $this->output->writeln("Error. Value of 'charset' argument can be either 'utf8' or 'cp1251'". PHP_EOL);
            var_dump($args);
            exit;
        }
        return $charset;
    }

    public function getSitename(&$siteName)
    {
        // 2. getting sitename
        $extension = '.dev';

        if (false === strpos($siteName, '.')) {
            return $siteName;
        }

        $parts = explode('.', $siteName);
        $parts_cnt = count($parts);
        
        if ($parts[$parts_cnt-1]) {
            $extension = $parts[$parts_cnt-1];
        }

        $siteName = substr($siteName, 0, strrpos($siteName, '.'.$extension));

        return $siteName;
    }

    public function setFolderOwner($docRoot, &$dirName, $osUserName, $osUserGroup)
    {
        if (false === shell_exec('sudo chown -R '.$osUserName.':'.$osUserGroup.' '.$docRoot.$dirName)) {
            $helper = $this->command->getHelper('question');
            $question = new ConfirmationQuestion(PHP_EOL.'Couldn\'t chown directory. Continue? (y/N):'. PHP_EOL, false);

            if (!$helper->ask($this->input, $this->output, $question)) {
                exit;
            }

            $this->output->writeln('[Skipped]'. PHP_EOL);
        }
        $this->output->writeln('chowned site directory'. PHP_EOL);
    }

    public function createDirectory($docRoot, &$dirName)
    {
        // 1. check if directory exists
        if (file_exists($docRoot.$dirName)) {
            die('Directory already exists' . PHP_EOL);
        }

        // 2. creating directory in /var/www/
        if (false === mkdir($docRoot.$dirName)) {
            die('Could not create directory: ' . $docRoot . $dirName . '. Try to change chmod or run script under sudo.' . PHP_EOL);
        }
        $this->output->writeln(PHP_EOL. '* Created '.$docRoot.$dirName. PHP_EOL);
    }

    public function deleteDirectory($docRoot, &$dirname)
    {
        // 1. delete directory if directory exists
        $this->output->writeln('* Deleting site directory: '.$docRoot.$dirname.'...'. PHP_EOL);

        if (!file_exists($docRoot.$dirname)) {
            $helper = $this->command->getHelper('question');
            $question = new ConfirmationQuestion('Directory doesn\'t exist. Continue? (y/N):'. PHP_EOL, false);

            if (!$helper->ask($this->input, $this->output, $question)) {
                exit;
            }
            $this->output->writeln('[Skipped]'. PHP_EOL);
        }
        else {
            // deleting site directory in /var/www/
            if (false===shell_exec('rm -rf '.$docRoot.$dirname.'/')) {
                die('Could not clean up directory: ' . $docRoot . $dirname . '. Try to change chmod or run script under sudo.' . PHP_EOL);
            }
            $this->output->writeln('[OK]'. PHP_EOL);
        }
    }

    public function addToEtcHosts(&$dirName)
    {
        // 1. getting contents of /etc/hosts
        $hosts = file_get_contents('/etc/hosts');

        // 2. appending $dirName to /etc/hosts
        if (false === file_put_contents('/etc/hosts', PHP_EOL . "127.0.0.1\t$dirName", FILE_APPEND)) {
            die('Could not write to /etc/hosts. Try to run script under sudo.'.PHP_EOL);
        }
        $this->output->writeln('* Writed to /etc/hosts'.PHP_EOL);
    }

    public function deleteFromEtcHosts(&$dirname)
    {
        // 7. deleting site from /etc/hosts
        $this->output->writeln('* Deleting site line from /etc/hosts'. PHP_EOL);
        $hosts = file_get_contents('/etc/hosts');
        //$lines = explode(PHP_EOL, $hosts);
        $lines = preg_split("/(\n|\r\n|\r)/", $hosts);

        $zlines = array();

        foreach ($lines as $line) {
            if ($this->isSitenameMatch($line, $dirname)) {
                continue;
            }
            $zlines[] = $line;
        }
        file_put_contents('/etc/hosts', join(PHP_EOL, $zlines));

        $this->output->writeln('[OK]'. PHP_EOL);

    }

    public function createNginxConfig($type='simple', $docRoot, $dirName, $nginxConfDir, $nginxLogDir, $charset, $phpFpmSockPath='/var/run/php5-fpm.sock')
    {
        // * setting php flags in config

        $php_flag = "\tfastcgi_param PHP_ADMIN_VALUE \"mbstring.func_overload=2\";\n";

        if ($charset == 'cp1251') {
            $php_flag = "\tfastcgi_param PHP_ADMIN_VALUE \"mbstring.func_overload=0\";\n";
        }



        // * creating /etc/nginx/sites-available/$dirName

        $config_filepath = realpath(__DIR__.'/../templates/').'/nginx_'.$type.'_site.conf.tpl.php';

        if (!file_exists($config_filepath)) {
            //$this->output->writeln(getcwd() . PHP_EOL);
            die('Couldn\'t find config file: '.$config_filepath . PHP_EOL);
        }

        include $config_filepath;



        // * writing virtual host

        $nginxSitesAvailableDir = $nginxConfDir.'sites-available/';
        $nginxSitesEnabledDir = $nginxConfDir.'sites-enabled/';

        $this->output->writeln('Trying to create nginx config '.$nginxSitesAvailableDir. $dirName. '...'. PHP_EOL);


        if (false === file_put_contents($nginxSitesAvailableDir.$dirName, $conf)) {
            die('Couldn\'t create config file: '.$nginxSitesAvailableDir.$dirName.PHP_EOL);
        }
        $this->output->writeln('* Successfully created '.$nginxSitesAvailableDir.$dirName.PHP_EOL);

        
        
        // * creating symbolic link to /etc/nginx/sites-enabled/@$dirName
        
        if (false===symlink($nginxSitesAvailableDir.$dirName, $nginxSitesEnabledDir.$dirName)) {
            die('Couldn\'t create symbolic link to '.$nginxSitesEnabledDir.'@'.$dirName.PHP_EOL);
        }
        $this->output->writeln('* Symlink created '.$nginxSitesEnabledDir.'@'.$dirName.PHP_EOL);
    }

    public function deleteNginxConfig($nginxConfDir, &$dirName)
    {
        $nginxSitesAvailableDir = $nginxConfDir.'sites-available/';
        $nginxSitesEnabledDir = $nginxConfDir.'sites-enabled/';

        $this->output->writeln('Deleting virtual hosts from'.$nginxSitesAvailableDir. $dirName. '...'. PHP_EOL);



        // * deleting site symlink from from /etc/nginx/sites-enabled/

        $this->output->writeln('* Deleting site symlink '.$nginxSitesEnabledDir.$dirName.'...');
        if (false === unlink($nginxSitesEnabledDir.$dirName)) {
            $helper = $this->command->getHelper('question');
            $question = new ConfirmationQuestion('Could not delete symlink: '.$nginxSitesEnabledDir.$dirName. '. Continue? (y/N):', false);

            if (!$helper->ask($this->input, $this->output, $question)) {
                exit;
            }
            $this->output->writeln('[Skipped]');
        }
        else {
            $this->output->writeln('[OK]');
        }



        // * deleting site conf from /etc/nginx/sites-available/

        $this->output->writeln('* Deleting site config file '.$nginxSitesAvailableDir.$dirName.'...');
        if (false === unlink($nginxSitesAvailableDir.$dirName)) {
            $helper = $this->command->getHelper('question');
            $question = new ConfirmationQuestion('Could not delete config file: '.$nginxSitesAvailableDir.$dirName.'. Continue? (y/N):', false);

            if (!$helper->ask($this->input, $this->output, $question)) {
                exit;
            }

            $this->output->writeln('[Skipped]');
        }
        else {
            $this->output->writeln('[OK]');
        }

    }

    public function executeLaraComposer($docRoot, $dirName)
    {
        // creating laravel project via composer
        if (false===shell_exec('composer create-project --prefer-dist laravel/laravel '.$docRoot.$dirName)) {
            die('Creating laravel project in \''.$docRoot.$dirName.'\' failed.' . PHP_EOL);
        }
        $this->output->writeln('* laravel project created via composer in '.$docRoot.$dirName. PHP_EOL);
    }

    public function processBitrix($docRoot, $dirName, &$charset)
    {
        // 1 downloading bitrixsetup.php script
        if (false===shell_exec('wget -P '.$docRoot.$dirName.'/ http://1c-bitrix.ru/download/scripts/bitrixsetup.php')) {
            die('Downloading http://1c-bitrix.ru/download/scripts/bitrixsetup.php failed.' . PHP_EOL);
        }
        $this->output->writeln('* bitrixsetup.php script downloaded to '.$docRoot.$dirName. PHP_EOL);

        // 2 create /local/ directories
        mkdir($docRoot.$dirName . '/local');
        mkdir($docRoot.$dirName . '/local/components/');
        mkdir($docRoot.$dirName . '/local/php_interface/');
        mkdir($docRoot.$dirName . '/local/templates/');
        mkdir($docRoot.$dirName . '/local/modules/');

        // 3 create /local/php_interface/init.php
        $init_file = <<<'EOF'
<?
// include iblock module
CModule::IncludeModule("iblock");

// include gpfunctions
require_once($_SERVER["DOCUMENT_ROOT"]."/local/php_interface/gpfunctions.php");

EOF;
        // 4 converting charset of text if charset is not utf8
        if ($charset == 'cp1251') {
            $init_file = mb_convert_encoding($init_file, 'cp1251', 'utf8');
        }
        file_put_contents($docRoot.$dirName . '/local/php_interface/init.php', $init_file);

        // 5 create /local/php_interface/gpfunctions.php
        $gpfunctions = <<<'EOF'
<?php
// IBLOCKS
define('PHOTOGALLERY_IBLOCK_ID', 1);

// custom functions

function getIblockProperty($iblock_id, $elem_id, $prop_name) {
    $props = CIBlockElement::GetProperty($iblock_id, $elem_id, array("sort" => "asc"), Array("CODE"=>$prop_name));
    if ($ar_props = $props->Fetch()) {
        return $ar_props["VALUE"];
    }
    return NULL;
}

function getSections($iblock_id) {
    // собираем все разделы из информационного блока $ID
    $items = GetIBlockSectionList($iblock_id, null, Array("sort"=>"asc"), null);
    return $items;
}
EOF;
        file_put_contents($docRoot.$dirName.'/local/php_interface/gpfunctions.php', $gpfunctions);

        // 6. setting up chmod
        //chmod($docRoot.$dirName.'/', 0777);
        shell_exec('chmod -R 777 ' . $docRoot.$dirName.'/');
    }

    public function createMySqlUser($dbhost, $dbname, $dbpass, $dbrootpass, $charset)
    {
        try {
            // 1. trying to connect to database
            $dbh = new \PDO("mysql:host=$dbhost", 'root', $dbrootpass);

            // 2. setting exceptions mode ON
            $dbh->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

            // creating InnoDb utf8mb4 database
            // creating user with password
            // granting all privileges to created user on created database
            $dbh->exec("CREATE DATABASE `{$dbname}` CHARSET {$charset} COLLATE {$charset}_general_ci;
                    CREATE USER '{$dbname}'@'localhost' IDENTIFIED BY '{$dbpass}';
                    GRANT ALL ON `{$dbname}`.* TO '{$dbname}'@'localhost';
                    FLUSH PRIVILEGES;");
        } catch (\PDOException $e) {
            die("DB ERROR: ". $e->getMessage() . PHP_EOL);
        }
        $this->output->writeln('* MySQL user `' . $dbname . '`@`localhost` successfully added'. PHP_EOL);
    }

    public function dropMysqlDatabase($dbhost, $dbname, $dbrootpass)
    {
        try {
            // 1. connecting to MySQL
            $dbh = new \PDO("mysql:host={$dbhost};dbname={$dbname};charset=utf8","root",$dbrootpass);

            // 2. setting exceptions mode ON
            $dbh->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

            // 3. revoking MySQL user privileges
            $this->output->writeln('* Revoking all privileges from user '.$dbname.'...'. PHP_EOL);
            $dbh->exec("REVOKE ALL PRIVILEGES ON {$dbname}.* FROM '{$dbname}'@'localhost'");
            $this->output->writeln('[OK]'. PHP_EOL);

            // 2. dropping MySQL user
            $this->output->writeln('* Dropping user '.$dbname.'...'. PHP_EOL);
            $dbh->exec("DROP USER '{$dbname}'@'localhost'");
            $this->output->writeln('[OK]'. PHP_EOL);

            // 3. dropping MySQL database
        
            $this->output->writeln('* Dropping database '.$dbname.'...'. PHP_EOL);
            $count = $dbh->exec("DROP DATABASE {$dbname}");
            $this->output->writeln('[OK]'. PHP_EOL);
        }
        catch (\Exception $ex) {
            $this->output->writeln('* Could not drop database. Exception:'. PHP_EOL);
            $this->output->writeln($ex->getMessage(). PHP_EOL);
            
            $helper = $this->command->getHelper('question');
            $question = new ConfirmationQuestion(PHP_EOL. 'Continue? (y/N):'.PHP_EOL, false);

            if (!$helper->ask($this->input, $this->output, $question)) {
                exit;
            }
            $this->output->writeln('[Skipped]'. PHP_EOL);
        }
    }

    public function updateBoptionTable($dbhost, $siteA, $siteB, $dbrootpass)
    {
        try {
            // 1. connecting to MySQL
            $db = new \PDO("mysql:host={$dbhost};dbname={$siteA};charset=utf8","root",$dbrootpass);
            $count = $db->exec("SET NAMES utf8");
            $q= $db->query("SELECT `value` FROM b_option WHERE `name`='admin_passwordh'");
            $hash = (string)$q->fetchColumn();

            $this->output->writeln('new admin_passwordh: '. $hash. PHP_EOL);

            $db = new \PDO("mysql:host={$dbhost};dbname={$siteB};charset=utf8","root",$dbrootpass);
            $db->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

            $count = $db->exec("UPDATE b_option SET `value`='{$hash}' WHERE `name`='admin_passwordh'");
            $this->output->writeln("* Updated 'admin_passwordh' in 'b_option' table". PHP_EOL);

            $this->output->writeln('Everything is OK. Bye!'.PHP_EOL);
        }
        catch (Exception $ex) {
            $this->output->writeln('* Could not update admin_passwordh in \'b_option\' table. Exception:'. PHP_EOL);
            $this->output->writeln($ex->getMessage(). PHP_EOL);

            $helper = $this->command->getHelper('question');
            $question = new ConfirmationQuestion(PHP_EOL. 'Continue? (y/N):'.PHP_EOL, false);

            if (!$helper->ask($this->input, $this->output, $question)) {
                exit;
            }
            $this->output->writeln('[Skipped]'. PHP_EOL);
        }
    }


    public function restartNginx($nginxRestartCommand)
    {
        // restarting nginx via shell command
        if (false===shell_exec($nginxRestartCommand)) {
            die('Could not restart nginx' . PHP_EOL);
        }
        $this->output->writeln('* nginx restarted'. PHP_EOL);
    }
}