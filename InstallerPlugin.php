<?php

namespace QCubed\Composer;

use Composer\Composer;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;

class InstallerPlugin implements PluginInterface
{
	public function activate(Composer $composer, IOInterface $io)
	{
		$installer = new \QCubed\Composer\Installer($io, $composer);
		$composer->getInstallationManager()->addInstaller($installer);
	}
	public function deactivate(Composer $composer, IOInterface $io)
	{
		// Not needed ???
	}
	public function unistall(Composer $composer, IOInterface $io)
	{
		// Not needed ???
		if ($this->uninstall($io, $composer)) 
			return;
	}
}
