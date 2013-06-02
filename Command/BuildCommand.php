<?php

namespace NS\DeployBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;

use Symfony\Component\HttpKernel\Kernel;

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
			->addOption('no-vendors', null, InputOption::VALUE_NONE, 'Skip vendors')
		;
	}

	/**
	 * Execution
	 *
	 * @param InputInterface $input
	 * @param OutputInterface $output
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

		// deploy temp path
		$tmp = sys_get_temp_dir();//$kernel->getRootDir() . '/deploy/tmp';
		$this->createDir($input, $output, $tmp);
		$output->writeln("Temp root path\n  <comment>{$tmp}</comment>\n");

		// removing old deployment
		$output->writeln("Removing old deployment...");
		exec(sprintf("rm -rf %s/*", $this->escapePath($tmp)));
		$output->writeln("  <info>ok</info>\n");

		// deploy temp path
		$tempPath = "{$tmp}/{$name}";
		$this->createDir($input, $output, $tempPath);
		$output->writeln("Temp folder\n  <comment>{$tempPath}</comment>\n");

		// deploy zip path
		$zipPath = $kernel->getRootDir() . '/deploy';
		$this->createDir($input, $output, $zipPath);
		$output->writeln("Zip folder\n  <comment>{$zipPath}</comment>\n");

		// clearing deploy dir
		$output->writeln("Clearing zip folder...");
		exec("rm -rf {$this->escapePath($zipPath)}/*");
		$output->writeln("  <info>ok</info>\n");

		// copying files
		$root = realpath($kernel->getRootDir() . '/..');
		$output->writeln("Copying files\n  from <comment>{$root}</comment>\n  to   <comment>{$tempPath}</comment>...");
		exec("cp -r {$this->escapePath($root)}/* {$this->escapePath($tempPath)}");
		$output->writeln("  <info>ok</info>\n");

		// removing .git's
		$output->write("Removing <comment>.git</comment> subfolders...");
		exec("rm -rf `find {$this->escapePath($tempPath)} -type d -name .git`");
		$output->writeln(" <info>ok</info>");

		// removing .DS_Store
		$output->write("Removing <comment>.DS_Store</comment> subfolders...");
		exec("rm -rf `find {$this->escapePath($tempPath)} -type d -name .DS_Store`");
		$output->writeln(" <info>ok</info>");

		// removing dirs
		$this
			->removeDir($input, $output, "{$tempPath}/.idea")
			->removeDir($input, $output, "{$tempPath}/app/deploy")
			->removeDir($input, $output, "{$tempPath}/app/cache")
			->removeDir($input, $output, "{$tempPath}/app/config/parameters.yml")
			->removeDir($input, $output, "{$tempPath}/public_html/uploads")
			->removeDir($input, $output, "{$tempPath}/public_html/media")
		;

		// skipping vendors
		if ($input->getOption('no-vendors')) {
			$this->removeDir($input, $output, "{$tempPath}/vendor");
		}

		// zipping
		$output->write("Zipping to <comment>{$zipPath}/{$name}.zip</comment>...");
		$cwd = getcwd();
		exec("cd {$this->escapePath($tempPath)}; zip -r {$name}.zip ./*; cd {$this->escapePath($cwd)};");

		rename("{$tempPath}/{$name}.zip", "{$zipPath}/{$name}.zip");
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
			$output->write("Creating folder <comment>{$dir}</comment>... ");
			if (!@mkdir($dir, 0777, true)) {
				throw new \Exception("Unable to create dir '{$dir}'");
			}
			$output->writeln("<info>ok</info>\n");
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
