<?php

namespace NS\DeployBundle\Service;

use Monolog\Handler\RotatingFileHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use NS\DeployBundle\Model\Backup;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;
use Symfony\Component\Process\Process;

class BackupService
{
    /**
     * @var string
     */
    private $root;

    /**
     * @var string
     */
    private $web;

    /**
     * @var string
     */
    private $backupDir;

    /**
     * @var Logger
     */
    private $logger;

    /**
     * @var array
     */
    private $dbConfig;

    /**
     * @var string
     */
    private $dumpFileName;

    /**
     * @param string $root
     * @param string $env
     * @param array  $dbConfig
     */
    public function __construct($root, $env, $dbConfig = array())
    {
        $this->root         = $root;
        $this->web          = $root . '/../..';
        $this->backupDir    = $root . '/backup';
        $this->dbConfig     = $dbConfig;
        $this->dumpFileName = $root . '/../restore.sql';
    }

    /**
     * @param bool $dump
     * @param bool $app
     * @param bool $parameters
     * @param bool $src
     * @param bool $vendor
     * @param bool $web
     * @param bool $upload
     * @throws \Exception
     */
    public function create($dump = false, $app = false, $parameters = false, $src = false,
       $vendor = false, $web = false, $upload = false)
    {
        // latest backup log
        $this->logger = new Logger('ns_deploy_backup_last');
        $this->logger->pushHandler(new StreamHandler(fopen($this->root . '/logs/ns_deploy_backup_last.log', 'w')));

        try {
            // logger initialization
            $this->logger->info("Starting backup", func_get_args());

            // initialization
            $name = $this->generateBackupName();
            $this->logger->debug("Backup name", array($name));
            $this->logger->debug("Backup dir", array($this->backupDir));

            // creating backup dir
            if (!is_dir($this->backupDir) && !@mkdir($this->backupDir, 0777, true)) {
                throw new \Exception("Unable to create dir '{$this->backupDir}'");
            }

            // include/exclude instructions
            $include = array('.');
            $exclude = array(
                '.git',
                '.DS_Store',
                './composer.json',
                './composer.lock',
            );

            // creating dump
            @unlink($this->dumpFileName);
            if ($dump) {
                $this->createDump($this->dumpFileName);
            }

            // app
            if ($app) {
                $exclude[] = 'ns/app/backup';
                $exclude[] = 'ns/app/cache';
                $exclude[] = 'ns/app/deploy';
                $exclude[] = 'ns/app/logs';
                $exclude[] = 'ns/app/spool';
                $exclude[] = 'ns/app/phpunit.xml.dist';
                if (!$parameters) {
                    $exclude[] = 'ns/app/config/parameters.yml';
                }
            }
            else {
                $exclude[] = 'ns/app';
            }

            // src
            if (!$src) {
                $exclude[] = 'ns/src';
            }

            // vendor
            if (!$vendor) {
                $exclude[] = 'ns/vendor';
            }

            // web
            if (!$web) {
                $exclude[] = 'bundles';
                $exclude[] = 'upload';
            }

            // upload
            if (!$upload) {
                $exclude[] = 'upload';
            }

            $strInclude = join(' ', $include);
            $strExclude = $exclude ? ('--exclude="' . join('" --exclude="', $exclude) . '"') : '';

            // archiving
            $this->logger->info("Creating archive file");

            // executing
            $cmd = "tar {$strExclude} -czhvf {$this->backupDir}/{$name}.tar.gz {$strInclude}";
            $this->exec($cmd, false, $this->web);

            // removing sql dump file
            @unlink($this->dumpFileName);

            $this->logger->info("Done");

        } catch(\Exception $e) {
            // removing sql dump file
            @unlink($this->dumpFileName);

            $this->logger->critical("Exception occurred", array('message' => $e->getMessage()));
            throw $e;
        }
    }

    /**
     * @param string $fileName
     * @throws \Exception
     */
    public function restore($fileName)
    {
        // latest backup log
        $this->logger = new Logger('ns_deploy_restore_last');
        $this->logger->pushHandler(new StreamHandler(fopen($this->root . '/logs/ns_deploy_restore_last.log', 'w')));

        $tarFileName  = $this->web . '/' . basename($fileName);

        try {
            if (!file_exists($fileName)) {
                throw new \Exception("File '{$fileName}' wasn't found");
            }

            copy($fileName, $tarFileName);

            if (!file_exists($tarFileName)) {
                throw new \Exception("Unable to copy backup file from {$fileName} to {$tarFileName}");
            }

            // untar
            $this->exec("tar -xzvf {$this->escapePath($tarFileName)}");

            // dump
            if (file_exists($this->dumpFileName)) {
                $this->restoreDump($this->dumpFileName);
            }

            // clearing
            @unlink($this->dumpFileName);
            @unlink($tarFileName);
            $this->exec("rm -rf {$this->root}/cache/*");

        } catch(\Exception $e) {
            @unlink($this->dumpFileName);
            @unlink($tarFileName);
            throw $e;
        }
    }

    /**
     * @param string $backupDir
     */
    public function setBackupDir($backupDir)
    {
        $this->backupDir = $backupDir;
    }

    /**
     * @return SplFileInfo[]
     */
    public function getBackups()
    {
        if (!file_exists($this->backupDir)) {
            return array();
        }

        $backups = array();
        $files = Finder::create()
            ->in($this->backupDir)
            ->depth(0)
            ->files()
            ->sortByName();

        foreach ($files as $file) {
            $backups[] = $file;
        }

        return array_reverse($backups);
    }

    /**
     * @return string
     */
    private function generateBackupName()
    {
        return date('Y-m-d_H-i-s_') . str_pad('0', 4, rand(0, 9999));
    }

    /**
     * Escapes spaces in path to use as a shell command argument
     *
     * @param string $path
     * @return string
     */
    private function escapePath($path)
    {
        return str_replace(" ", "\\ ", $path);
    }

    /**
     * @throws \Exception
     */
    private function createDump($fileName)
    {
        $this->logger->info("Creating SQL dump", array($fileName));
        $this->logger->debug("Database credentials", $this->dbConfig);

        // checking MySQL
        if ($this->dbConfig['database_driver'] !== 'pdo_mysql') {
            throw new \Exception("This backup implementation supports only pdo_mysql database driver");
        }

        $cmd = sprintf("mysqldump --opt -u%s -p%s -h%s %s > %s",
            $this->dbConfig['database_user'],
            $this->dbConfig['database_password'],
            $this->dbConfig['database_host'],
            $this->dbConfig['database_name'],
            $this->escapePath($fileName));
        $this->exec($cmd);
    }

    private function restoreDump($fileName)
    {
        if (!file_exists($fileName)) {
            throw new \Exception("SQL dump file name {$fileName} wasn't found");
        }

        // checking MySQL
        if ($this->dbConfig['database_driver'] !== 'pdo_mysql') {
            throw new \Exception("This backup implementation supports only pdo_mysql database driver");
        }

        $cmd = sprintf("mysql -u%s -p%s -h%s %s < %s",
            $this->dbConfig['database_user'],
            $this->dbConfig['database_password'],
            $this->dbConfig['database_host'],
            $this->dbConfig['database_name'],
            $this->escapePath($fileName));
        $this->exec($cmd);
    }

    /**
     * @param      $cmd
     * @param bool $quiet
     * @param null $cwd
     * @throws \Exception
     */
    private function exec($cmd, $quiet = false, $cwd = null)
    {
        if (!$quiet) {
            $this->logger->debug("Executing process", array($cmd));
        }

        // mac os x mysql path
        $path = 'PATH=$PATH:/usr/local/mysql/bin';

        $process = new Process("{$path} && {$cmd}", $cwd, null, null, 300);
        $process->run();
        if (!$process->isSuccessful()) {
            $this->logger->critical("Process failed", array('cmd' => $cmd, 'message' => $process->getErrorOutput()));
            throw new \Exception("Process failed");
        }
    }

    /**
     * @return Logger
     */
    public function getLogger()
    {
        return $this->logger;
    }
}