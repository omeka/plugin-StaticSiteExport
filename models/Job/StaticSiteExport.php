<?php
class Job_StaticSiteExport extends Omeka_Job_AbstractJob
{
    protected $_staticSite;
    protected $_sitesDirectoryPath;
    protected $_siteDirectoryPath;
    protected $_itemIds;

    /**
     * Export the static site.
     */
    public function perform()
    {
        try {
            $this->setStatus(Process::STATUS_IN_PROGRESS);

            $this->createSiteDirectory();
            $this->createItemsSection();
            // $this->createCollectionsSection();
            $this->createSiteArchive();
            $this->deleteSiteDirectory();

            $this->setStatus(Process::STATUS_COMPLETED);

        } catch (Exception $e) {
            $this->setStatus(Process::STATUS_ERROR);
            _log($e->getMessage(), Zend_Log::ERR);
        }
    }

    /**
     * Create the items section.
     */
    public function createItemsSection()
    {
        $frontMatter = [
            'title' => __('Items'),
            'params' => [],
        ];
        $this->makeFile('content/items/_index.md', json_encode($frontMatter, JSON_PRETTY_PRINT | JSON_FORCE_OBJECT));

        $page = 1;
        do {
            $items = get_db()->getTable('Item')->findBy([], 100, $page++);
            foreach ($items as $item) {

                $this->makeDirectory(sprintf('content/items/%s', $item->id));

                $frontMatterPage = [
                    'date' => (new DateTime(metadata($item, 'added')))->format('c'),
                    'title' => metadata($item, 'display_title'),
                    'draft' => $item->public ? false : true,
                    'params' => [],
                ];

                // Build the markdown.
                $markdown = '';
                /*
                 * @todo: Add the following to the markdown:
                 *  - gallery of files (if configured)
                 *  - element texts
                 *  - links to files
                 *  - link to collection
                 *  - tags (hook into Hugo tags)
                 *  - citation
                 *  - content added by other plugins
                 */

                // Make the markdown file.
                $this->makeFile(
                    sprintf('content/items/%s/index.md', $item->id),
                    sprintf("%s\n%s", json_encode($frontMatterPage, JSON_PRETTY_PRINT | JSON_FORCE_OBJECT), $markdown)
                );
            }
        } while ($items);
    }

    /**
     * Create the static site directory.
     */
    public function createSiteDirectory()
    {
        $this->makeDirectory('archetypes');
        $this->makeDirectory('assets');
        $this->makeDirectory('assets/thumbnails');
        $this->makeDirectory('content');
        $this->makeDirectory('content/items');
        $this->makeDirectory('content/collections');
        $this->makeDirectory('data');
        $this->makeDirectory('i18n');
        $this->makeDirectory('layouts');
        $this->makeDirectory('layouts/partials');
        $this->makeDirectory('layouts/shortcodes');
        $this->makeDirectory('static');
        $this->makeDirectory('static/js');
        $this->makeDirectory('themes');

        // Unzip the Omeka theme into the Hugo themes directory.
        $pluginPath = sprintf('%s/StaticSiteExport', PLUGIN_DIR);
        $command = sprintf(
            'unzip %s -d %s',
            sprintf('%s/data/gohugo-theme-omeka-classic.zip', $pluginPath),
            sprintf('%s/themes/', $this->getSiteDirectoryPath())
        );
        $this->execute($command);

        // @todo: Copy shortcodes provided by plugins?
        // @todo: Copy vendor packages provided by plugins?
        // @todo: Build the Hugo menu from Omeka site navigation.
        // @todo: Get the homepage.

        // Make the hugo.json configuration file.
        $siteConfig = new ArrayObject([
            'baseURL' => $this->getStaticSite()->getDataValue('base_url'),
            'theme' => 'gohugo-theme-omeka-classic',
            'title' => get_option('site_title'),
            // 'menus' => [
            //     'main' => $menu->getArrayCopy(),
            // ],
            // 'params' => [
            //     'homepage' => $homepage,
            //     'theme' => $this->getStaticSite()->dataValue('theme'),
            // ],
            'pagination' => [
                'pagerSize' => 25,
            ],
        ]);

        $this->makeFile('hugo.json', json_encode($siteConfig->getArrayCopy(), JSON_PRETTY_PRINT));
    }

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
     * Execute a command.
     */
    public function execute($command): void
    {
        $output = shell_exec($command);
        if (false === $output) {
            // Stop the job.
            throw new Exception(sprintf('Invalid command: %s', $command));
        }
    }

    /**
     * Create the static site archive (ZIP).
     */
    public function createSiteArchive(): void
    {
        $command = sprintf(
            'cd %s && zip --recurse-paths %s %s',
            $this->getSitesDirectoryPath(),
            sprintf('%s.zip', $this->getStaticSite()->getName()),
            $this->getStaticSite()->getName()
        );
        $this->execute($command);
    }

    /**
     * Delete the static site directory.
     */
    public function deleteSiteDirectory(): void
    {
        $command = sprintf(
            'rm -r %s',
            escapeshellarg($this->getSiteDirectoryPath())
        );
        $this->execute($command);
    }

    /**
     * Get the static site record.
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
     * Set the status of the static site export process.
     */
    public function setStatus($status)
    {
        $this->getStaticSite()->setStatus($status);
        $this->getStaticSite()->save();
    }

    /**
     * Get the directory path where the static sites are created.
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
     */
    public function getSiteDirectoryPath()
    {
        if (null === $this->_siteDirectoryPath) {
            $this->_siteDirectoryPath = sprintf(
                '%s/%s',
                $this->getSitesDirectoryPath(),
                $this->getStaticSite()->getName()
            );
        }
        return $this->_siteDirectoryPath;
    }
}
