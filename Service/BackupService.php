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
     * @param string $root
     * @param string $env
     * @param array  $dbConfig
     */
    public function __construct($root, $env, $dbConfig = array())
    {
        $this->root      = $root;
        $this->backupDir = $root . '/backup';
        $this->dbConfig  = $dbConfig;

        // latest backup log
        $this->logger = new Logger('ns_backup');
        $latestFileName = $root . '/logs/' . $env . '.ns.backup_latest.log';
        $this->logger->pushHandler(new StreamHandler(fopen($latestFileName, 'w')));
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
        try {
            $this->logger->info("Starting backup", func_get_args());

            // removing temp dir
            $this->logger->info("Trying to remove temp dir if exists", array($this->getTempDir()));
            $this->removeDir($this->getTempDir());

            // initialization
            $backup = new Backup();
            $backup->setName($this->generateBackupName());
            $backup->setDir($this->backupDir);
            $this->logger->debug("Backup name", array($backup->getName()));
            $this->logger->debug("Backup dir", array($this->backupDir));

            // creating temp dir
            $this->logger->info("Creating temp dir", array($this->getTempDir()));
            $this->createDir($this->getTempDir());

            // creating dump
            if ($dump) {
                $this->createDump();
            }

            // app
            if ($app) {
                $this->addApp($parameters);
            }

            // src
            if ($src) {
                $this->addSrc();
            }

            // vendor
            if ($vendor) {
                $this->addVendor();
            }

            // web
            if ($web) {
                $this->addWeb($upload);
            }

            // archiving
            $this->logger->info("Creating archive file");
            $cmd = "tar -czhf ../{$backup->getName()}.tar.gz *";
            $this->exec($cmd, false, $this->getTempDir());

            // removing temp dir
            $this->logger->info("Removing temp dir", array($this->getTempDir()));
            $this->removeDir($this->getTempDir());

            $this->logger->info("Done");

        } catch(\Exception $e) {
            // removing temp dir
            $this->logger->info("Removing temp dir", array($this->getTempDir()));
            $this->removeDir($this->getTempDir());

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
        $tarFileName  = $this->root . '/../' . basename($fileName);
        $dumpFileName = $this->root . '/../dump.sql';

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
            if (file_exists($dumpFileName)) {
                $this->restoreDump($dumpFileName);
            }

            // clearing
            $this->exec("rm -f {$tarFileName}");
            $this->exec("rm -f {$dumpFileName}");
            $this->exec("rm -rf {$this->root}/cache/*");

        } catch(\Exception $e) {
            $this->exec("rm -f {$tarFileName}");
            $this->exec("rm -f {$dumpFileName}");
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
    private function getTempDir()
    {
        return $this->backupDir . '/.tmp';
    }

    /**
     * @return string
     */
    private function generateBackupName()
    {
        return date('Y-m-d_H-i-s_') . str_pad('0', 4, rand(0, 9999));
    }

    /**
     * @param string $dir
     * @throws \Exception
     */
    private function createDir($dir)
    {
        $this->logger->debug("Creating dir", array($dir));
        if (!is_dir($dir) && !@mkdir($dir, 0777, true)) {
            throw new \Exception("Unable to create dir '{$dir}'");
        }
    }

    /**
     * Removes $dir subdir
     *
     * @param string $dir
     */
    private function removeDir($dir)
    {
        $this->logger->debug("Removing dir", array($dir));
        $this->exec("rm -rf {$this->escapePath($dir)}");
    }

    /**
     * @param $source
     * @param $dest
     */
    private function symlink($source, $dest)
    {
        $this->exec("ln -s {$this->escapePath($source)} {$this->escapePath($dest)}");
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
    private function createDump()
    {
        $dumpFileName = $this->getTempDir() . '/dump.sql';
        $this->logger->info("Creating SQL dump", array($dumpFileName));
        $this->logger->debug("Database credentials", $this->dbConfig);

        // checking MySQL
        if ($this->dbConfig['database_driver'] !== 'pdo_mysql') {
            throw new \Exception("This backup implementation supports only pdo_mysql database driver");
        }

        $cmd = sprintf("mysqldump -u%s -p%s -h%s %s > %s",
            $this->dbConfig['database_user'],
            $this->dbConfig['database_password'],
            $this->dbConfig['database_host'],
            $this->dbConfig['database_name'],
            $this->escapePath($dumpFileName));
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
     * @param bool $parameters
     */
    private function addApp($parameters = true)
    {
        $source = $this->root;
        $dest   = $this->getTempDir() . '/app';

        $this->logger->info("Adding app dir", array('source' => $source, 'dest' => $dest));

        // excluding parameters
        $exclude = array('backup', 'cache', 'deploy', 'logs', 'spool');
        if (!$parameters) {
            $this->logger->info('Excluding parameters.yml');
            $exclude[] = 'config';
        }

        // creating temp structure
        $create  = array('cache', 'logs');
        $this->symlinkDir($source, $dest, $exclude, $create);

        // parameters
        if (!$parameters) {
            $this->logger->debug('Adding config files skipping parameters.yml');
            $this->symlinkDir(
                $source . '/config', $dest . '/config',
                array(), array(),
                array('parameters.yml')
            );
        }
    }

    private function addSrc()
    {
        $source = $this->root . '/../src';
        $dest   = $this->getTempDir() . '/src';

        $this->logger->info("Adding src dir", array('source' => $source, 'dest' => $dest));
        $this->symlinkDir($source, $dest);
    }

    private function addVendor()
    {
        $source = $this->root . '/../vendor';
        $dest   = $this->getTempDir() . '/vendor';

        $this->logger->info("Adding vendor dir", array('source' => $source, 'dest' => $dest));
        $this->symlinkDir($source, $dest);
    }

    private function addWeb($upload)
    {
        $source = $this->root . '/../web';
        $dest   = $this->getTempDir() . '/web';

        $this->logger->info("Adding web dir", array('source' => $source, 'dest' => $dest));

        $exclude = array();
        $create  = array();
        if (!$upload) {
            $this->logger->info("Skipping upload dir");
            $exclude[] = 'upload';
            $create[] = 'upload';
            $create[] = 'upload/documents';
            $create[] = 'upload/images';
            $create[] = 'upload/j';
            $create[] = 'upload/cache';
        }

        $this->symlinkDir($source, $dest, $exclude, $create);
    }

    /**
     * @param       $from
     * @param       $to
     * @param array $exclude
     * @param array $create
     * @param array $notName
     * @param bool  $precise
     */
    private function symlinkDir($from, $to, array $exclude = array(), array $create = array(), array $notName = array(), $precise = false)
    {
        $this->logger->debug('Symlinking structure', array('from' => $from, 'to' => $to));
        $this->createDir($to);

        // creating subdirs
        $finder = Finder::create()
            ->in($from)
            ->ignoreVCS(true)
            ->ignoreDotFiles(false)
            ->notName('.DS_Store')
            ->notName('._*')
        ;

        // skipping files
        foreach ($notName as $name) {
            $finder->notName($name);
        }

        // excluding
        $this->logger->debug('Excluding dirs', $exclude);
        $finder->exclude($exclude);

        // iterator
        if ($precise) {
            /** @var SplFileInfo $file */
            foreach ($finder->files() as $file) {
                $this->createDir(dirname($to . '/' . $file->getRelativePathname()));
                $this->symlink(
                    $from . '/' . $file->getRelativePathname(),
                    $to . '/' . $file->getRelativePathname()
                );
            }
        }
        else {
            /** @var SplFileInfo $file */
            foreach ($finder->depth(0) as $file) {
                $this->symlink(
                    $from . '/' . $file->getRelativePathname(),
                    $to . '/' . $file->getRelativePathname()
                );
            }
        }

        // creating empty dirs
        $this->logger->debug('Creating empty dirs', $create);
        foreach ($create as $dir) {
            $this->createDir($to . '/' . $dir);
        }
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
        $process = new Process($cmd, $cwd);
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