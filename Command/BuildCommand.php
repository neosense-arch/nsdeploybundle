<?php

namespace NS\DeployBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;

use Symfony\Component\Console\Helper\ProgressHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;

use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Process\Process;

/**
 * Class BuildCommand
 *
 * @package NS\DeployBundle\Command
 */
class BuildCommand extends ContainerAwareCommand
{
	/**
	 * Configuration
	 *
	 */
	protected function configure()
	{
		$this
			->setName('deploy:build')
			->setDescription('Builds zip archive to upload')
			->addOption(
				'skip-vendors',
				null,
				InputOption::VALUE_NONE,
				'Skips vendors'
			)
			->addOption(
				'skip-web',
				null,
				InputOption::VALUE_NONE,
				'Skips web path'
			)
			->addOption(
				'skip-upload',
				null,
				InputOption::VALUE_NONE,
				'Skips uploaded user files'
			)
			->addOption(
				'skip-dump',
				null,
				InputOption::VALUE_NONE,
				'Skips SQL dumping feature'
			)
			->addOption(
				'web-path',
				'w',
				InputOption::VALUE_OPTIONAL,
				'Web path (relatively to site root)',
				'web'
			)
		;
	}

	/**
	 * Execution
	 *
	 * @param InputInterface $input
	 * @param OutputInterface $output
	 * @throws \Exception
	 * @return int|null|void
	 */
	protected function execute(InputInterface $input, OutputInterface $output)
	{
		$output->writeln("<info>Site deploy started</info>");

		/**
		 * @var Kernel $kernel
		 */
		$kernel = $this
			->getContainer()
			->get('kernel');

		// deploy name
		$name = time() . str_pad(rand(0, 9999), 4, '0', \STR_PAD_LEFT);

		// finder
		$root = realpath($kernel->getRootDir() . '/..');
		$finder = Finder::create()
			->in($root)
			->ignoreDotFiles(true)
			->ignoreVCS(true)
		;

		// deploy path
		$deploy = $kernel->getRootDir() . '/deploy';
		$finder->exclude($deploy);
		$this->createDir($input, $output, $deploy);
		$output->writeln("Deploy folder\n  <comment>{$deploy}</comment>\n");

		// clearing deploy dir
		exec("rm -rf {$this->escapePath($deploy)}/*");

		// deploy temp path
		$tempPath = "{$deploy}/tmp/{$name}";
		$this->createDir($input, $output, $tempPath);
		$output->writeln("Temp folder\n  <comment>{$tempPath}</comment>\n");

		// testing web path
		$web = $input->getOption('web-path');
		if (!file_exists($root . '/' . $web)) {
			throw new \Exception("Web path {$web} wasn't found. Try to use --web to set relative web path");
		}
		$output->writeln("Web path\n  <comment>{$root}/{$web}</comment>\n");

		// dumping database
		if (!$input->getOption('skip-dump')) {
			$output->write("Dumping database...");

			$dbUser     = $this->getContainer()->getParameter('database_user');
			$dbPassword = $this->getContainer()->getParameter('database_password');
			$dbHost     = $this->getContainer()->getParameter('database_host');
			$dbName     = $this->getContainer()->getParameter('database_name');

			$cmd = array(
				'mysqldump',
				"-u{$dbUser}",
			);

			if ($dbPassword) {
				$cmd[] = "-p{$dbPassword}";
			}

			if ($dbHost) {
				$cmd[] = "-h{$dbHost}";
			}

			$cmd[] = $dbName;
			$cmd[] = " > {$root}/dump.sql";

			$process = new Process(implode(' ', $cmd));
			$process->run();

			if (!$process->isSuccessful()) {
				throw new \Exception($process->getErrorOutput());
			}

			$output->writeln(" <info>ok</info>\n");
		}

		// skipping common dirs
		$finder
			->notPath('app/cache')
			->notPath('app/import')
			->notPath('app/deploy')
			->notPath("{$web}/media/cache")
			->notName('parameters.yml')
		;

		// skipping vendors
		if ($input->getOption('skip-vendors')) {
			$finder->notPath('vendor');
		}

		// skipping web
		if ($input->getOption('skip-web')) {
			$finder->notPath($input->getOption('web-path'));
		}

		// skipping uploaded files
		if ($input->getOption('skip-upload')) {
			$finder->notPath("{$web}/upload");
		}

		// copying files
		$output->writeln("Copying files\n  from <comment>{$root}</comment>\n  to   <comment>{$tempPath}</comment>...\n");

		/** @var ProgressHelper $progress */
		$progress = $this->getHelper('progress');
		$progress->start($output, count($finder->files()));

		/** @var SplFileInfo $file */
		foreach ($finder->files() as $file) {
			$source      = $file->getPathname();
			$destination = $tempPath . '/' . $file->getRelativePathname();

			$destinationPath = dirname($destination);
			if (!file_exists($destinationPath)) {
				mkdir(dirname($destination), 0777, true);
			}

			copy($source, $destination);
			$progress->advance();
		}
		$output->writeln("\n");

		// zipping
		$output->write("Zipping to <comment>{$deploy}/{$name}.zip</comment>...");
		$cwd = getcwd();
		exec("cd {$this->escapePath($tempPath)}; zip --symlinks -r {$name}.zip ./*; cd {$this->escapePath($cwd)};");

		rename("{$tempPath}/{$name}.zip", "{$deploy}/{$name}.zip");
		$output->writeln(" <info>ok</info>");

		// removing temp files
		$output->write("Removing temp dir <comment>{$tempPath}</comment>...");
		exec("rm -rf {$this->escapePath($tempPath)}");
		$output->writeln(" <info>ok</info>");

		// done
		$output->writeln("");
		$output->writeln("<info>Done</info>");
	}

	/**
	 * Creates dir
	 *
	 * @param InputInterface $input
	 * @param OutputInterface $output
	 * @param string $dir
	 * @throws \Exception
	 */
	private function createDir(InputInterface $input, OutputInterface $output, $dir)
	{
		if (!is_dir($dir)) {
			if ($input->getOption('verbose')) {
				$output->write("Creating folder <comment>{$dir}</comment>... ");
			}

			if (!@mkdir($dir, 0777, true)) {
				throw new \Exception("Unable to create dir '{$dir}'");
			}

			if ($input->getOption('verbose')) {
				$output->writeln("<info>ok</info>\n");
			}
		}
	}

	/**
	 * Removes $dir subdir
	 *
	 * @param InputInterface $input
	 * @param OutputInterface $output
	 * @param string $dir
	 * @return BuildCommand
	 */
	private function removeDir(InputInterface $input, OutputInterface $output, $dir)
	{
		$output->write("Removing <comment>{$dir}</comment> subfolder...");
		exec("rm -rf {$this->escapePath($dir)}");
		$output->writeln(" <info>ok</info>");

		return $this;
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
}
