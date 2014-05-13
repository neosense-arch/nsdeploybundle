<?php

namespace NS\DeployBundle\Command;

use NS\CoreBundle\Service\VersionService;
use NS\DeployBundle\Service\BackupService;
use Symfony\Bridge\Monolog\Handler\ConsoleHandler;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class BackupRestoreCommand
 *
 * @package NS\DeployBundle\Command
 */
class BackupRestoreCommand extends ContainerAwareCommand
{
	protected function configure()
	{
		$this
			->setName('ns:backup:restore')
			->setDescription('Restores selected backup')
            ->addArgument('file', InputArgument::REQUIRED, 'Backup file name to restore')
		;
	}

    /**
     * @param InputInterface  $input
     * @param OutputInterface $output
     * @throws \Exception
     * @return int|null|void
     */
	protected function execute(InputInterface $input, OutputInterface $output)
	{
        /** @var BackupService $backupService */
        $backupService = $this->getContainer()->get('ns_deploy.service.backup');
        $backupService->restore($input->getArgument('file'));
	}
}