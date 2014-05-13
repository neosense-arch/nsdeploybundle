<?php

namespace NS\DeployBundle\Command;

use NS\CoreBundle\Service\VersionService;
use NS\DeployBundle\Service\BackupService;
use Symfony\Bridge\Monolog\Handler\ConsoleHandler;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class BackupCreateCommand
 *
 * @package NS\CoreBundle\Command
 */
class BackupCreateCommand extends ContainerAwareCommand
{
	protected function configure()
	{
		$this
			->setName('ns:backup:create')
			->setDescription('Creates backup archive file')
            ->addOption(
                'dir', null, InputOption::VALUE_OPTIONAL,
                'Backup directory relatively to kernel.root_dir',
                'backup')
            ->addOption(
                'dump', null, InputOption::VALUE_NONE,
                'Adds <info>SQL dump</info> file to backup')
            ->addOption(
                'dump-filename', null, InputOption::VALUE_OPTIONAL,
                'SQL dump file name relatively to kernel.root_dir',
                '../dump.sql')
            ->addOption(
                'app', null, InputOption::VALUE_NONE,
                'Adds application files (<info>app</info>) to backup')
            ->addOption(
                'parameters', null, InputOption::VALUE_NONE,
                'Adds application parameters file (<info>parameters.yml</info>) to backup')
            ->addOption(
                'src', null, InputOption::VALUE_NONE,
                'Adds project bundles (<info>src</info> dir) to backup')
            ->addOption(
                'vendor', null, InputOption::VALUE_NONE,
                'Adds vendor bundles (<info>vendor</info> dir) to backup')
            ->addOption(
                'web', null, InputOption::VALUE_NONE,
                'Adds public <info>web</info> files to backup')
            ->addOption(
                'upload', null, InputOption::VALUE_NONE,
                'Adds <info>upload</info> web files to backup')
            ->addOption(
                'full', 'f', InputOption::VALUE_NONE,
                'Creates <info>full</info> possible backup')
		;
	}

	/**
	 * @param InputInterface  $input
	 * @param OutputInterface $output
	 * @return int|null|void
	 */
	protected function execute(InputInterface $input, OutputInterface $output)
	{
        $flags = $this->getFlags($input);

        /** @var BackupService $backupService */
        $backupService = $this->getContainer()->get('ns_deploy.service.backup');

        // logger
        if ($output->getVerbosity() == OutputInterface::VERBOSITY_DEBUG) {
            $backupService->getLogger()
                ->pushHandler(new ConsoleHandler($output));
        }

        $backupService->create($flags['dump'], $flags['app'], $flags['parameters'], $flags['src'],
            $flags['vendor'], $flags['web'], $flags['upload']);
	}

    /**
     * @param InputInterface $input
     * @return bool[]
     */
    private function getFlags(InputInterface $input)
    {
        $flags = array(
            'dump'       => $input->getOption('dump'),
            'app'        => $input->getOption('app'),
            'parameters' => $input->getOption('parameters'),
            'src'        => $input->getOption('src'),
            'vendor'     => $input->getOption('vendor'),
            'web'        => $input->getOption('web'),
            'upload'     => $input->getOption('upload'),
        );

        // full backup
        if ($input->getOption('full')) {
            return array_combine(
                array_keys($flags),
                array_fill(0, count($flags), true)
            );
        }

        return $flags;
    }
}