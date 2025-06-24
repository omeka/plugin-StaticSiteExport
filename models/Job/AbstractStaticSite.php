<?php
abstract class Job_AbstractStaticSite extends Omeka_Job_AbstractJob
{
    protected $_staticSite;

    protected $_sitesDirectoryPath;

    protected $_siteDirectoryPath;

    /**
     * Make a directory in the static site directory.
     */
    public function makeDirectory($directoryPath)
    {
        mkdir(sprintf('%s/%s', $this->getSiteDirectoryPath(), $directoryPath), 0755, true);
    }

    /**
     * Make a file in the static site directory.
     */
    public function makeFile($filePath, $content = '')
    {
        file_put_contents(
            sprintf('%s/%s', $this->getSiteDirectoryPath(), $filePath),
            $content
        );
    }

    /**
     * Delete the static site directory.
     */
    public function deleteSiteDirectory()
    {
        $path = $this->getSiteDirectoryPath();
        if (is_dir($path) && is_writable($path)) {
            $command = sprintf('rm -r %s', escapeshellarg($path));
            $this->execute($command);
        }
    }

    /**
     * Delete the static site server ZIP file.
     */
    public function deleteSiteZip()
    {
        $path = sprintf('%s.zip', $this->getSiteDirectoryPath());
        if (is_file($path) && is_writable($path)) {
            $command = sprintf('rm -r %s', escapeshellarg($path));
            $this->execute($command);
        }
    }

    /**
     * Get the static site record.
     *
     * @return StaticSite
     */
    public function getStaticSite()
    {
        if (null === $this->_staticSite) {
            $staticSiteId = $this->_options['static_site_id'];
            $this->_staticSite = $this->_db->getTable('StaticSite')->find($staticSiteId);
        }
        return $this->_staticSite;
    }

    /**
     * Get the static site name.
     */
    public function getStaticSiteName()
    {
        return $this->_options['static_site_name'] ?? $this->getStaticSite()->getName();
    }

    /**
     * Set the status of the static site export process.
     */
    public function setStatus($status)
    {
        $this->getStaticSite()->setStatus($status);
        $this->getStaticSite()->save();
    }

    /**
     * Get the directory path where the static sites are created.
     *
     * @return string
     */
    public function getSitesDirectoryPath()
    {
        if (null === $this->_sitesDirectoryPath) {
            $sitesDirectoryPath = get_option('static_site_export_sites_directory_path');
            if (!StaticSiteExportPlugin::sitesDirectoryPathIsValid($sitesDirectoryPath)) {
                throw new Exception\RuntimeException('Invalid directory path');
            }
            $this->_sitesDirectoryPath = $sitesDirectoryPath;
        }
        return $this->_sitesDirectoryPath;
    }

    /**
     * Get the directory path of the static site.
     *
     * @return string
     */
    public function getSiteDirectoryPath()
    {
        if (null === $this->_siteDirectoryPath) {
            $this->_siteDirectoryPath = sprintf(
                '%s/%s',
                $this->getSitesDirectoryPath(),
                $this->getStaticSiteName()
            );
        }
        return $this->_siteDirectoryPath;
    }

    /**
     * Execute a command.
     */
    public function execute($command)
    {
        $output = shell_exec($command);
        if (false === $output) {
            // Stop the job.
            throw new Exception(sprintf('Invalid command: %s', $command));
        }
    }

    /**
     * Fire a plugin hook.
     *
     * @param string $name The hook name
     * @param array $args The hook arguments
     */
    public function fireHook($name, $args)
    {
        fire_plugin_hook($name, array_merge($args, ['job' => $this]));
    }
}
