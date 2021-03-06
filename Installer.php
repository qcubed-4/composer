<?php

/**
 * Routines to assist in the installation of various parts of QCubed-4 with composer.
 *
 */

namespace QCubed\Composer;

use Composer\Package\PackageInterface;
use Composer\Installer\LibraryInstaller;
use Composer\Repository\InstalledRepositoryInterface;
use React\Promise\PromiseInterface;

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
        return ('qcubed-library' == $packageType);
    }

    /**
     * Respond to the install command.
     *
     * @param InstalledRepositoryInterface $repo
     * @param PackageInterface $package
     */
    public function install(InstalledRepositoryInterface $repo, PackageInterface $package)
    {
        $afterInstall = function () use ($package) {
            if ($package->getType() == 'qcubed-library') {
                $this->composerLibraryInstall($package);
            }
        };

        // install the package the normal composer way
        $promise = parent::install($repo, $package);

        // Composer v2 might return a promise here
        if ($promise instanceof PromiseInterface) {
            return $promise->then($afterInstall);
        }

        // If not, execute the code right away as parent::install executed synchronously (composer v1, or v2 without async)
        $afterInstall();
    }

    /**
     * This installer is for QCubed version 4 and above repositories.
     *
     * It does a non-destructive merge of the contents of the install directory in to the directory above the vendor directory,
     * or the directory above the Project directory if one is specified by config constants.
     *
     * It then will doctor up any registry files needed so that the application knows about the new library.
     *
     * @param $package
     * @throws \Exception
     */
    protected function composerLibraryInstall($package)
    {
        $strDestDir = realpath(dirname(dirname(dirname(__DIR__))));

        $strLibraryDir = $this->getInstallPath($package);
        // recursively copy the contents of the install subdirectory in the plugin.
        $strInstallDir = $strLibraryDir . '/install';

        $this->filesystem->ensureDirectoryExists($strDestDir);
        $this->io->write('Copying files from ' . $strInstallDir . ' to ' . $strDestDir);
        self::copy_dir($strInstallDir, $strDestDir);

        $this->register();
    }

    public function getInstallPath(PackageInterface $package)
    {
        $this->initializeVendorDir();

        $basePath = ($this->vendorDir ? $this->vendorDir.'/' : '') . $package->getPrettyName();
        $targetDir = $package->getTargetDir();

        return $basePath . ($targetDir ? '/'.$targetDir : '');
    }

    protected function initializeVendorDir()
    {
        $this->filesystem->ensureDirectoryExists($this->vendorDir);
        $this->vendorDir = realpath($this->vendorDir);
    }

    protected function register()
    {
    }

    protected function normalizeNonPosixPath($s)
    {
        return str_replace('\\', '/', $s);
    }

    /**
     * Respond to an update command.
     *
     * @param InstalledRepositoryInterface $repo
     * @param PackageInterface $initial
     * @param PackageInterface $target
     */
    public function update(InstalledRepositoryInterface $repo, PackageInterface $initial, PackageInterface $target)
    {
        $afterUpdate = function () use ($initial, $target) {
            $this->unregister($target, true);
            if ($initial->getType() == 'qcubed-library') {
                $this->composerLibraryInstall($initial);
            }
        };

        // update the package the normal composer way
        $promise = parent::update($repo, $initial, $target);

        // Composer v2 might return a promise here
        if ($promise instanceof PromiseInterface) {
            return $promise->then($afterUpdate);
        }

        // If not, execute the code right away as parent::install executed synchronously (composer v1, or v2 without async)
        $afterUpdate();
    }

    /**
     * Return true if the needle starts with the haystack.
     *
     * @param string $haystack
     * @param string $needle
     * @return bool
     */
    protected static function startsWith($haystack, $needle)
    {
        // search backwards starting from haystack length characters from the end
        return $needle === "" || strrpos($haystack, $needle, -strlen($haystack)) !== false;
    }

    /**
     * Uninstalls a plugin if requested.
     *
     * @param InstalledRepositoryInterface $repo
     * @param PackageInterface $package
     */
    public function uninstall(InstalledRepositoryInterface $repo, PackageInterface $package)
    {
        $afterUninstall = function () use ($package) {
            if ($package->getType() == 'qcubed-library') {
                $this->composerLibraryUninstall($package);
            }
        };

        // uninstall the package the normal composer way
        $promise = parent::uninstall($repo, $package);

        // Composer v2 might return a promise here
        if ($promise instanceof PromiseInterface) {
            return $promise->then($afterUninstall);
        }

        // If not, execute the code right away as parent::uninstall executed synchronously (composer v1, or v2 without async)
        $afterUninstall();
    }

    /**
     * Delete the given plugin.
     *
     * @param PackageInterface $package
     */
    public function composerLibraryUninstall(PackageInterface $package)
    {
        // recursively delete the contents of the install directory, providing each file is there.
        $this->unregister($package, false);
    }

    /**
     * Copy the contents of the source directory into the destination directory, creating the destination directory
     * if it does not exist. If the destination file exists, it will NOT overwrite the file.
     *
     * @param string $src source directory
     * @param string $dst destination directory
     */
    protected static function copy_dir($src, $dst)
    {
        if (!$src || !is_dir($src)) {
            return;
        }
        $dir = opendir($src);

        if (!file_exists($dst)) {
            mkdir($dst);
        }
        while (false !== ($file = readdir($dir))) {
            if (($file != '.') && ($file != '..')) {
                if (is_dir($src . '/' . $file)) {
                    self::copy_dir($src . '/' . $file, $dst . '/' . $file);
                } else {
                    if (!file_exists($dst . '/' . $file)) {
                        copy($src . '/' . $file, $dst . '/' . $file);
                    }
                }
            }
        }
        closedir($dir);
    }

    /**
     * Remove files that are related to registries.
     *
     * @param $blnUpdating  True if we are doing this as part of an update operation. False if part of a Remove operation.
     * @throws \Exception
     */
    protected function unregister($package, $blnUpdating)
    {
        require_once(__DIR__ . '/qcubed.inc.php');    // get the configuration options so we can know where to put the plugin files

        if (!defined('QCUBED_CONFIG_DIR')) {
            return; // we can't find the registry
        }

        $targetDir = QCUBED_CONFIG_DIR . '/control_registry';
        $srcDir = $this->getInstallPath($package) . '/install/project/includes/configuration/control_registry';

        self::removeMatchingFiles($srcDir, $targetDir);
    }

    /**
     * Recursively remove the files in the destination directory whose names match the files in the source directory.
     *
     * @param string $src Source directory
     * @param string $dst Destination directory
     */
    protected static function removeMatchingFiles($src, $dst)
    {
        if (!$dst || !$src || !is_dir($src) || !is_dir($dst)) {
            return;
        }    // prevent deleting an entire disk by accidentally calling this with an empty string!
        $dir = opendir($src);

        while (false !== ($file = readdir($dir))) {
            if (($file != '.') && ($file != '..')) {
                if (is_dir($src . '/' . $file)) {
                    self::removeMatchingFiles($src . '/' . $file, $dst . '/' . $file);
                } else {
                    if (file_exists($dst . '/' . $file)) {
                        unlink($dst . '/' . $file);
                    }
                }
            }
        }
        closedir($dir);
    }
}
