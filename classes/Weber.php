<?php
namespace Weber;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

/**
 * Class Weber
 * @package Weber
 */

class Weber
{
    protected $command;
    protected $input;
    protected $output;

    /**
     * Weber constructor.
     * @param Command $cmd
     * @param InputInterface $input
     * @param OutputInterface $output
     */
    public function __construct(Command $cmd, InputInterface $input, OutputInterface $output)
    {
        $this->command = $cmd;
        $this->input = $input;
        $this->output = $output;
    }

    /**
     * @param $etcHostsLine
     * @param $sitename
     * @return bool
     */
    public function isSitenameMatch($etcHostsLine, $sitename)
    {
        $parts = preg_split('/[\s]/', $etcHostsLine);
        $parts_cnt = count($parts);

        if ($parts_cnt > 0 && $parts[$parts_cnt-1] === $sitename) {
            return true;
        }

        return false;
    }

    /**
     * @param $siteName
     * @return false|string
     */
    public function getSitename($siteName)
    {
        // 2. getting sitename
        $extension = '.loc';

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

    /**
     * @param $siteName
     * @return string
     */
    public function getSiteDomain($siteName)
    {
        // 2. getting sitename
        $domain = '.loc';

        if (false === strpos($siteName, '.')) {
            return $domain;
        }

        $parts = explode('.', $siteName);
        $parts_cnt = count($parts);
        
        if ($parts[$parts_cnt-1]) {
            $domain = '.' . $parts[$parts_cnt-1];
        }

        return $domain;
    }

    /**
     * @param $docRoot
     * @param $dirName
     * @param $osUserName
     * @param $osUserGroup
     */
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

    /**
     * @param $docRoot
     * @param $dirName
     */
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

    /**
     * @param $docRoot
     * @param $dirName1
     * @param $dirName2
     */
    public function renameDirectory($docRoot, &$dirName1, &$dirName2)
    {
        // 1. check if directory exists
        if (!file_exists($docRoot.$dirName1)) {
            die('Directory \'' . $docRoot.$dirName1 . '\' does not exist' . PHP_EOL);
        }

        // 2. creating directory in /var/www/
        if (false === rename($docRoot.$dirName1, $docRoot.$dirName2)) {
            die('Could not rename directory ' . PHP_EOL .
                    $docRoot . $dirName1 . PHP_EOL . 
                    ' to ' . PHP_EOL . 
                    $docRoot . $dirName2 . PHP_EOL . 
                    'Try to change chmod or run script under sudo.' . PHP_EOL);
        }
        $this->output->writeln(PHP_EOL. '* Renamed '. $docRoot.$dirName1 . ' to '.$docRoot.$dirName2. PHP_EOL);
    }

    /**
     * @param $docRoot
     * @param $dirname
     */
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

    /**
     * @param $dirName
     */
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

    /**
     * @param $dirname
     */
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

    /**
     * @param string $type
     * @param $docRoot
     * @param $dirName
     * @param $nginxConfDir
     * @param $nginxLogDir
     * @param $charset
     * @param string $phpFpmSockPath
     */
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

    /**
     * @param $nginxConfDir
     * @param $dirName
     */
    public function deleteNginxConfig($nginxConfDir, &$dirName)
    {
        $nginxSitesAvailableDir = $nginxConfDir.'sites-available/';
        $nginxSitesEnabledDir = $nginxConfDir.'sites-enabled/';

        $this->output->writeln('Deleting virtual hosts from'.$nginxSitesAvailableDir. $dirName. '...'. PHP_EOL);



        // * deleting site symlink from from /etc/nginx/sites-enabled/

        $this->output->writeln('* Deleting site symlink '.$nginxSitesEnabledDir.$dirName.'...');
        try {
            unlink($nginxSitesEnabledDir.$dirName);
            $this->output->writeln('[OK]');
        }
        catch (\Exception $ex) {
            $helper = $this->command->getHelper('question');
            $question = new ConfirmationQuestion('Could not delete symlink: '.$nginxSitesEnabledDir.$dirName. '. Continue? (y/N):', false);

            if (!$helper->ask($this->input, $this->output, $question)) {
                exit;
            }
            $this->output->writeln('[Skipped]');
        }



        // * deleting site conf from /etc/nginx/sites-available/

        $this->output->writeln('* Deleting site config file '.$nginxSitesAvailableDir.$dirName.'...');
        try {
            unlink($nginxSitesAvailableDir.$dirName);
            $this->output->writeln('[OK]');
        }
        catch (\Exception $ex) {
            $helper = $this->command->getHelper('question');
            $question = new ConfirmationQuestion('Could not delete config file: '.$nginxSitesAvailableDir.$dirName.'. Continue? (y/N):', false);

            if (!$helper->ask($this->input, $this->output, $question)) {
                exit;
            }
            $this->output->writeln('[Skipped]');
        }
    }

    /**
     * @param $docRoot
     * @param $dirName
     */
    public function executeLaraComposer($docRoot, $dirName)
    {
        // creating laravel project via composer
        if (false===shell_exec('composer create-project --no-progress --profile --prefer-dist laravel/laravel '.$docRoot.$dirName)) {
            die('Creating laravel project in \''.$docRoot.$dirName.'\' failed.' . PHP_EOL);
        }
        $this->output->writeln('* laravel project created via composer in '.$docRoot.$dirName. PHP_EOL);
    }

    /**
     * @param $docRoot
     * @param $dirName
     */
    public function executeYii1Composer($docRoot, $dirName)
    {
        // creating laravel project via composer
        if (false===shell_exec('composer create-project --no-progress --profile --prefer-dist yiisoft/yii '.$docRoot.$dirName)) {
            die('Creating yii1 project in \''.$docRoot.$dirName.'\' failed.' . PHP_EOL);
        }

        if (false===shell_exec($docRoot.$dirName.'/framework/yiic webapp '.$docRoot.$dirName.'/public')) {
            die('Creating webapp under '.$docRoot.$dirName.'/public failed'. PHP_EOL);
        }
        $this->output->writeln('* Yii1 webapp created under '.$docRoot.$dirName.'/public'. PHP_EOL);
    }

    /**
     * @param $docRoot
     * @param $dirName
     */
    public function executeYii2Composer($docRoot, $dirName)
    {
        // creating laravel project via composer
        if (false===shell_exec('composer create-project --no-progress --profile --prefer-dist yiisoft/yii2-app-basic '.$docRoot.$dirName)) {
            die('Creating yii2 project in \''.$docRoot.$dirName.'\' failed.' . PHP_EOL);
        }
        $this->output->writeln('* laravel project created via composer in '.$docRoot.$dirName. PHP_EOL);
    }

    /**
     * @param $docRoot
     * @param $dirName
     * @param $charset
     */
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

    /**
     * @param $dbhost
     * @param $dbname
     * @param $dbpass
     * @param $dbrootpass
     * @param $charset
     */
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

    /**
     * @param $dbhost
     * @param $dbrootpass
     * @param $dbname1
     * @param $dbname2
     * @param string $charset
     */
    public function renameMysqlDatabase($dbhost, $dbrootpass, $dbname1, $dbname2, $charset='utf8')
    {
        try {
            // 1. connecting to MySQL
            $dbh1 = new \PDO("mysql:host={$dbhost};charset={$charset}","root",$dbrootpass);

            // 2. setting exceptions mode ON
            $dbh1->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

            // 3. creating new database
            $this->output->writeln('* Creating new database `'.$dbname2.'`...'. PHP_EOL);
            $dbh1->exec("CREATE DATABASE `{$dbname2}` CHARSET {$charset} COLLATE {$charset}_general_ci");
            $this->output->writeln('[OK]'. PHP_EOL);

            $dbh1 = null; // closing first connection

            // 3. moving tables by renaming
            $dbh2 = new \PDO("mysql:host={$dbhost};dbname={$dbname1};charset={$charset}","root",$dbrootpass);

            $this->output->writeln('* Renaming tables in '.$dbname1.'...'. PHP_EOL);

            $query = $dbh2->query('SHOW TABLES');
            $tables = $query->fetchAll(\PDO::FETCH_COLUMN);
            foreach ($tables as $tablename) {
                $dbh2->exec("RENAME TABLE `{$dbname1}`.`{$tablename}` TO `{$dbname2}`.`{$tablename}`");
            }

            $this->output->writeln('[OK]'. PHP_EOL);

            // 4. revoking MySQL user privileges
            $this->output->writeln('* Revoking all privileges from user '.$dbname1.'...'. PHP_EOL);
            $dbh2->exec("REVOKE ALL PRIVILEGES ON {$dbname1}.* FROM '{$dbname1}'@'localhost'");
            $this->output->writeln('[OK]'. PHP_EOL);

            // 5. renaming mysql user
            $this->output->writeln("* Renaming user `{$dbname1}` to `{$dbname2}`...". PHP_EOL);
            $dbh2->exec("RENAME USER '{$dbname1}'@'localhost' TO '{$dbname2}'@'localhost'");
            $this->output->writeln('[OK]'. PHP_EOL);

            // 6. grant MySQL user privileges
            $this->output->writeln('* Granting all privileges to user '.$dbname2.'...'. PHP_EOL);
            $dbh2->exec("GRANT ALL ON `{$dbname2}`.* TO `{$dbname2}`@`localhost`;
                        FLUSH PRIVILEGES;");
            $this->output->writeln('[OK]'. PHP_EOL);


            // 7. dropping MySQL database
            $this->output->writeln('* Dropping database '.$dbname1.'...'. PHP_EOL);
            $count = $dbh2->exec("DROP DATABASE {$dbname1}");
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

    /**
     * @param $dbhost
     * @param $dbname
     * @param $dbrootpass
     */
    public function dropMysqlDatabase($dbhost, $dbname, $dbrootpass)
    {
        try {
            // 1. connecting to MySQL
            $dbh = new \PDO("mysql:host={$dbhost};dbname={$dbname};charset=utf8","root",$dbrootpass);

            // 2. setting exceptions mode ON
            $dbh->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

            // 3. revoking MySQL user privileges
            $this->output->writeln('* Revoking all privileges from user '.$dbname.'...'. PHP_EOL);
            $dbh->exec("REVOKE ALL PRIVILEGES ON `{$dbname}`.* FROM '{$dbname}'@'localhost'");
            $this->output->writeln('[OK]'. PHP_EOL);

            // 2. dropping MySQL user
            $this->output->writeln('* Dropping user '.$dbname.'...'. PHP_EOL);
            $dbh->exec("DROP USER '{$dbname}'@'localhost'");
            $this->output->writeln('[OK]'. PHP_EOL);

            // 3. dropping MySQL database
        
            $this->output->writeln('* Dropping database '.$dbname.'...'. PHP_EOL);
            $count = $dbh->exec("DROP DATABASE `{$dbname}`");
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

    /**
     * @param $dbhost
     * @param $siteA
     * @param $siteB
     * @param $dbrootpass
     */
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

    /**
     * @param $nginxRestartCommand
     */
    public function restartNginx($nginxRestartCommand)
    {
        // restarting nginx via shell command
        if (false===shell_exec($nginxRestartCommand)) {
            die('Could not restart nginx' . PHP_EOL);
        }
        $this->output->writeln('* nginx restarted'. PHP_EOL);
    }
}
