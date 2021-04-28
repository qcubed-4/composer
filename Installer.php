<?php

/**
 * Routines to assist in the installation of various parts of QCubed with composer.
 *
 */

namespace QCubed\Composer;

use Composer\Package\PackageInterface;
use Composer\Installer\LibraryInstaller;
use Composer\Repository\InstalledRepositoryInterface;

$__CONFIG_ONLY__ = true;

class Installer extends LibraryInstaller
{
	/** Overrides **/
	/**
	 * Return the types of packages that this installer is responsible for installing.
	 *
	 * @param $packageType
	 * @return bool
	 */
	public function supports($packageType)
	{
		return ('qcubed-plugin' === $packageType ||
			'qcubed-framework' === $packageType);
	}

	public function getInstallPath(PackageInterface $package) {
		$strDest = parent::getInstallPath($package);
		$parts = explode('_', $package->getName());

		if ('qcubed/plugin' === $parts[0]) {
			$strDest = ($this->vendorDir ? $this->vendorDir . '/' : '') . 'qcubed/plugin/' . $parts[1];
		}
		return $strDest;
	}

	/**
	 * Respond to the install command.
	 *
	 * @param InstalledRepositoryInterface $repo
	 * @param PackageInterface $package
	 */
	public function install(InstalledRepositoryInterface $repo, PackageInterface $package) {
		parent::install($repo, $package);

		$strPackageName = $package->getName();

		if (self::startsWith($strPackageName, 'qcubed/plugin')) {
			$this->composerPluginInstall($package);
		}
		elseif (self::startsWith($strPackageName, 'qcubed/qcubed')) {
			// updating the framework
			$this->composerFrameworkInstall($package);
		}

	}

	/**
	 * Move files out of the vendor directory and into the project directory that are in the plugin's install directory.
	 * @param $strPackageName
	 */
	protected function composerPluginInstall ($package) {
		require_once(($this->vendorDir ? $this->vendorDir . '/' : '') . 'qcubed/qcubed/qcubed.inc.php');	// get the configuration options so we can know where to put the plugin files

		// recursively copy the contents of the install subdirectory in the plugin.
		$strPluginDir = $this->getPackageBasePath($package);
		$strInstallDir = $strPluginDir . '/install';
		$strDestDir = __INCLUDES__ . '/plugins';

		$this->filesystem->ensureDirectoryExists($strDestDir);
		$this->io->write('Copying files from ' . $strInstallDir . ' to ' . $strDestDir);
		self::copy_dir($strInstallDir, $strDestDir);
	}

	protected function NormalizeNonPosixPath($s){
		return str_replace('\\', '/', $s);
	}

	/**
	 * First time installation of framework. For first-time installation, we create the project directory and modify
	 * the configuration file.
	 *
	 * @param $strPackageName
	 */
	protected function composerFrameworkInstall ($package) {
		$extra = $package->getExtra();

		// recursively copy the contents of the install directory, providing each file is not there.
		$strInstallDir = self::NormalizeNonPosixPath($this->getPackageBasePath($package)) . '/install/project';
		$strDestDir = ($this->vendorDir ? $this->vendorDir . '/' : '') . '../project'; // try to find the default project location
		$strDestDir = self::NormalizeNonPosixPath($strDestDir);

		$this->io->write('Copying files from ' . $strInstallDir . ' to ' . $strDestDir);
		self::copy_dir($strInstallDir, $strDestDir);

		// Make sure particular directories are writable by the web server. These are listed in the extra section of the composer.json file.
		// We are assuming that the first time installation is installed in a subdirectory of docroot.
		$strInstallDir = self::NormalizeNonPosixPath(realpath(dirname($strDestDir)));
		$strSubDirectory = '/' . basename($strInstallDir);
		$strDocRoot = self::NormalizeNonPosixPath(realpath($strInstallDir . '/../'));
		$strConfigDirectory = $strDestDir . '/includes/configuration';

		$this->io->write('Updating permissions');
		foreach ($extra['writePermission'] as $strDir) {
			$strTargetDir = $strInstallDir . '/' . $strDir;
			if(!file_exists($strTargetDir)){
				mkdir($strTargetDir, 0777, true);
			}
			chmod ($strTargetDir, 0777);
		}

		// write configuration.inc.php only if it does not exists
		if (!file_exists($strConfigDirectory . '/configuration.inc.php')) {
			// fix up the configuration file
			$strFile = file_get_contents($strConfigDirectory . '/configuration.inc.sample.php');
			if ($strFile) {
				$strFile = str_replace (['{docroot}', '{vd}', '{subdir}'], [$strDocRoot, '', $strSubDirectory], $strFile);
				file_put_contents($strConfigDirectory . '/configuration.inc.php', $strFile);
			}
		}
	}


	/**
	 * Respond to an update command.
	 *
	 * @param InstalledRepositoryInterface $repo
	 * @param PackageInterface $initial
	 * @param PackageInterface $target
	 */
	public function update(InstalledRepositoryInterface $repo, PackageInterface $initial, PackageInterface $target) {
		parent::install($repo, $initial, $target);

		$strPackageName = $target->getName();
		if (self::startsWith($strPackageName, 'qcubed/plugin')) {
			$this->composerPluginInstall($target);
		}
		elseif (self::startsWith($strPackageName, 'qcubed/qcubed')) {
			// updating the framework
			$this->composerFrameworkUpdate($target);
		}

	}

	/**
	 * Return true if the needle starts with the haystack.
	 *
	 * @param string $haystack
	 * @param string $needle
	 * @return bool
	 */
	protected static function startsWith($haystack, $needle) {
		// search backwards starting from haystack length characters from the end
		return $needle === "" || strrpos($haystack, $needle, -strlen($haystack)) !== FALSE;
	}

	/**
	 * Update a package. In particular, new install files will be moved to the correct location.
	 *
	 * @param $package
	 */
	protected function composerFrameworkUpdate ($package) {
		require_once(($this->vendorDir ? $this->vendorDir . '/' : '') . 'qcubed/qcubed/qcubed.inc.php');	// get the configuration options so we can know where to put the plugin files

		// recursively copy the contents of the install directory, providing each file is not there.
		$strInstallDir = $this->getPackageBasePath($package) . '/install/project';
		$strDestDir = __PROJECT__;

		// copy_dir will not overwrite files, but will add any new stub files
		$this->io->write('Copying files from ' . $strInstallDir . ' to ' . $strDestDir);
		self::copy_dir($strInstallDir, $strDestDir);
	}

	/**
	 * Uninstalls a plugin if requested.
	 *
	 * @param InstalledRepositoryInterface $repo
	 * @param PackageInterface $package
	 */
	public function uninstall(InstalledRepositoryInterface $repo, PackageInterface $package)
	{
		$strPackageName = $package->getName();
		if (self::startsWith($strPackageName, 'qcubed/plugin')) {
			$this->composerPluginUninstall($package);
		}
		parent::uninstall ($repo, $package);
	}


	/**
	 * Delete the given plugin.
	 *
	 * @param PackageInterface $package
	 */
	public function composerPluginUninstall (PackageInterface $package) {
		require_once(($this->vendorDir ? $this->vendorDir . '/' : '') . 'qcubed/qcubed/qcubed.inc.php');	// get the configuration options so we can know where the plugin files are

		// recursively delete the contents of the install directory, providing each file is there.
		$strPluginDir = $this->getPackageBasePath($package) . '/install';
		$strDestDir = __INCLUDES__ . '/plugins';

		$this->io->write('Removing files from ' . $strPluginDir);
		self::remove_matching_dir($strPluginDir, $strDestDir);
	}

	/**
	 * Copy the contents of the source directory into the destination directory, creating the destination directory
	 * if it does not exist. If the destination file exists, it will NOT overwrite the file.
	 *
	 * @param string $src	source directory
	 * @param string $dst	destination directory
	 */
	protected static function copy_dir($src,$dst) {
		if (!$src || !is_dir($src)) {
			return;
		}
		$dir = opendir($src);

		if (!file_exists($dst)) {
			mkdir($dst);
		}
		while(false !== ( $file = readdir($dir)) ) {
			if (( $file != '.' ) && ( $file != '..' )) {
				if ( is_dir($src . '/' . $file) ) {
					self::copy_dir($src . '/' . $file,$dst . '/' . $file);
				}
				else {
					if (!file_exists($dst . '/' . $file)) {
						copy($src . '/' . $file,$dst . '/' . $file);
					}
				}
			}
		}
		closedir($dir);
	}

	/**
	 * Remove the files in the destination directory whose names match the files in the source directory.
	 *
	 * @param string $src	Source directory
	 * @param string $dst	Destination directory
	 */
	protected static function remove_matching_dir($src,$dst) {
		if (!$dst || !$src || !is_dir($src) || !is_dir($dst)) return;	// prevent deleting an entire disk by accidentally calling this with an empty string!
		$dir = opendir($src);

		while(false !== ( $file = readdir($dir)) ) {
			if (( $file != '.' ) && ( $file != '..' )) {
				if ( is_dir($src . '/' . $file) ) {
					self::remove_dir($dst . '/' . $file);
				}
				else {
					if (file_exists($dst . '/' . $file)) {
						unlink($dst . '/' . $file);
					}
				}
			}
		}
		closedir($dir);
	}

	/**
	 * Delete a directory and all of its contents.
	 *
	 * @param string $dst Directory to delete
	 */
	protected static function remove_dir($dst) {
		if (!$dst || !is_dir($dst)) return;	// prevent deleting an entire disk by accidentally calling this with an empty string!
		$dir = opendir($dst);

		while(false !== ( $file = readdir($dir)) ) {
			if (( $file != '.' ) && ( $file != '..' )) {
				if ( is_dir($dst . '/' . $file) ) {
					self::remove_dir($dst . '/' . $file);
				}
				else {
					unlink($dst . '/' . $file);
				}
			}
		}
		closedir($dir);
		rmdir($dst);
	}
}
