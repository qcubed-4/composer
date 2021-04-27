<?php

namespace QCubed\Composer;

use Composer\Composer;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;

class InstallerPlugin implements PluginInterface
{
	private $installer;
	
	public function activate(Composer $composer, IOInterface $io)
	{
		$this->installer = new \QCubed\Composer\Installer($io, $composer);
		$composer->getInstallationManager()->addInstaller($this->installer);
	}
//	public function deactivate(Composer $composer, IOInterface $io)
//	{
//		$composer->getInstallationManager()->removeInstaller($this->installer);
//	}
//	public function unistall(Composer $composer, IOInterface $io)
//	{
//		// Not needed ???
//	}
}
