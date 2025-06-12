<?php
class Job_StaticSiteExport extends Omeka_Job_AbstractJob
{
    protected $_staticSite;

    protected $_sitesDirectoryPath;

    protected $_siteDirectoryPath;

    protected $_renderersByMimeType = [
        'audio/ogg' => 'audio',
        'audio/x-ogg' => 'audio',
        'audio/aac' => 'audio',
        'audio/x-aac' => 'audio',
        'audio/aiff' => 'audio',
        'audio/x-aiff' => 'audio',
        'audio/mp3' => 'audio',
        'audio/mpeg' => 'audio',
        'audio/mpeg3' => 'audio',
        'audio/mpegaudio' => 'audio',
        'audio/mpg' => 'audio',
        'audio/x-mp3' => 'audio',
        'audio/x-mpeg' => 'audio',
        'audio/x-mpeg3' => 'audio',
        'audio/x-mpegaudio' => 'audio',
        'audio/x-mpg' => 'audio',
        'audio/mp4' => 'audio',
        'audio/x-mp4' => 'audio',
        'audio/x-m4a' => 'audio',
        'audio/wav' => 'audio',
        'audio/x-wav' => 'audio',
        'video/mp4' => 'video',
        'video/x-m4v' => 'video',
        'video/ogg' => 'video',
        'video/webm' => 'video',
        'video/quicktime' => 'video',
    ];
    protected $_renderersByExtension = [
        'ogx' => 'audio',
        'aac' => 'audio',
        'aif' => 'audio',
        'aiff' => 'audio',
        'aifc' => 'audio',
        'mpga' => 'audio',
        'mp2' => 'audio',
        'mp2a' => 'audio',
        'mp3' => 'audio',
        'm2a' => 'audio',
        'm3a' => 'audio',
        'mp4a' => 'audio',
        'm4a' => 'audio',
        'oga' => 'audio',
        'ogg' => 'audio',
        'spx' => 'audio',
        'opus' => 'audio',
        'wav' => 'audio',
        'mp4' => 'video',
        'mp4v' => 'video',
        'mpg4' => 'video',
        'm4v' => 'video',
        'ogv' => 'video',
        'webm' => 'video',
        'mov' => 'video',
    ];

    /**
     * Export the static site.
     */
    public function perform()
    {
        try {
            $this->setStatus(Process::STATUS_IN_PROGRESS);

            $this->fireHook('static_site_export_site_export_pre', []);
            $this->createSiteDirectory();
            $this->createFilesSection();
            $this->createItemsSection();
            $this->createCollectionsSection();
            $this->fireHook('static_site_export_site_export_post', []);

            $this->createSiteArchive();
            $this->deleteSiteDirectory();
            $this->setStatus(Process::STATUS_COMPLETED);
        } catch (Exception $e) {
            $this->setStatus(Process::STATUS_ERROR);
            _log($e->getMessage(), Zend_Log::ERR);
        }
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

        $vendorPackages = apply_filters('static_site_export_vendor_packages', [], ['job' => $this]);
        foreach ($vendorPackages as $packageName => $fromDirectoryPath) {
            if (!is_dir($fromDirectoryPath)) {
                continue; // Skip non-directories.
            }
            if (in_array($packageName, ['jquery'])) {
                continue; // Skip existing packages.
            }
            // Make the package directory under vendor.
            $toDirectoryPath = sprintf('static/vendor/%s', $packageName);
            $this->makeDirectory($toDirectoryPath);
            // Copy packages into the vendor directory.
            $command = sprintf(
                'cp --recursive %s %s',
                sprintf('%s/*', escapeshellarg($fromDirectoryPath)),
                escapeshellarg(sprintf('%s/%s', $this->getSiteDirectoryPath(), $toDirectoryPath))
            );
            $this->execute($command);
        }

        $shortcodes = apply_filters('static_site_export_shortcodes', [], ['job' => $this]);
        // @todo: Copy shortcodes provided by plugins

        // Make the hugo.json configuration file.
        $siteConfig = new ArrayObject([
            'theme' => 'gohugo-theme-omeka-classic',
            'title' => get_option('site_title'),
            'pagination' => [
                'pagerSize' => 25,
            ],
            'menus' => [
                'main' => [
                    [
                        'name' => __('Browse items'),
                        'pageRef' => '/items',
                        'weight' => 10,
                    ],
                    [
                        'name' => __('Browse collections'),
                        'pageRef' => '/collections',
                        'weight' => 20,
                    ],
                    [
                        'name' => __('Browse tags'),
                        'pageRef' => '/tags',
                        'weight' => 30,
                    ],
                ],
            ],
        ]);

        $this->fireHook('static_site_export_site_config', ['site_config' => $siteConfig]);

        $this->makeFile('hugo.json', json_encode($siteConfig->getArrayCopy(), JSON_PRETTY_PRINT));
    }

    /**
     * Create the files section.
     */
    public function createFilesSection()
    {
        $frontMatter = [
            'title' => __('Browse files'),
            'params' => [],
        ];
        $this->makeDirectory('content/files');
        $this->makeFile('content/files/_index.md', json_encode($frontMatter, JSON_PRETTY_PRINT | JSON_FORCE_OBJECT));

        $page = 1;
        do {
            $files = get_db()->getTable('File')->findBy([], 100, $page++);
            foreach ($files as $file) {
                $this->createFileBundle($file);
            }
        } while ($files);
    }

    /**
     * Create a file bundle.
     */
    public function createFileBundle($file)
    {
        $item = $file->getItem();

        $this->makeDirectory(sprintf('content/files/%s', $file->id));
        $this->makeDirectory(sprintf('content/files/%s/blocks', $file->id));

        $frontMatterPage = new ArrayObject([
            'date' => (new DateTime(metadata($file, 'added')))->format('c'),
            'title' => $file->original_filename,
            'draft' => $item->public ? false : true,
            'params' => [
                'fileID' => $file->id,
                'thumbnailSpec' => $this->getThumbnailSpec($file, 'square_thumbnail'),
            ],
        ]);
        $blocks = new ArrayObject;

        // @todo: Trigger the file bundle event.

        $this->makeBundleFiles(sprintf('files/%s', $file->id), $file, $frontMatterPage, $blocks);

        // Copy original and thumbnail files, if any. Use copy() if the installation
        // uses the Filesystem storage adapter, otherwise use HTTP client data streaming.
        $storage = Zend_Registry::get('storage');
        $filePath = sprintf('content/files/%s/file.%s', $file->id, $file->getExtension());
        $toPath = sprintf('%s/%s', $this->getSiteDirectoryPath(), $filePath);
        if ($storage->getAdapter() instanceof Omeka_Storage_Adapter_Filesystem) {
            $fromPath = sprintf('%s/files/%s', BASE_DIR, $file->getStoragePath('original'));
            copy($fromPath, $toPath);
        } else {
            $fromPath = $file->getWebPath('original');
            $this->makeFile($filePath);
            $client = new Omeka_Http_Client;
            $client->setUri($fromPath)->setStream($toPath)->send();
        }
        if ($file->has_derivative_image) {
            foreach (['fullsize', 'thumbnail', 'square_thumbnail'] as $type) {
                $filePath = sprintf('content/files/%s/%s.jpg', $file->id, $type);
                $toPath = sprintf('%s/%s', $this->getSiteDirectoryPath(), $filePath);
                if ($storage->getAdapter() instanceof Omeka_Storage_Adapter_Filesystem) {
                    $fromPath = sprintf('%s/files/%s', BASE_DIR, $file->getStoragePath($type));
                    copy($fromPath, $toPath);
                } else {
                    $fromPath = $file->getWebPath($type);
                    $this->makeFile($filePath);
                    $client = new Omeka_Http_Client;
                    $client->setUri($fromPath)->setStream($toPath)->send();
                }
            }
        }
    }

    /**
     * Create the items section.
     */
    public function createItemsSection()
    {
        $frontMatter = [
            'title' => __('Browse items'),
            'params' => [],
        ];
        $this->makeDirectory('content/items');
        $this->makeFile('content/items/_index.md', json_encode($frontMatter, JSON_PRETTY_PRINT | JSON_FORCE_OBJECT));

        $page = 1;
        do {
            $items = get_db()->getTable('Item')->findBy([], 100, $page++);
            foreach ($items as $item) {
                $this->createItemBundle($item);
            }
        } while ($items);
    }

    /**
     * Create an item bundle.
     */
    public function createItemBundle($item)
    {
        $this->makeDirectory(sprintf('content/items/%s', $item->id));
        $this->makeDirectory(sprintf('content/items/%s/blocks', $item->id));

        $collection = $item->getCollection();
        $frontMatterPage = new ArrayObject([
            'date' => (new DateTime(metadata($item, 'added')))->format('c'),
            'title' => metadata($item, 'display_title'),
            'draft' => $item->public ? false : true,
            'params' => [
                'itemID' => $item->id,
                'collectionID' => $collection ? $collection->id : null,
                'description' => metadata($item, array('Dublin Core', 'Description'), array('snippet' => 250)),
                'thumbnailSpec' => $this->getThumbnailSpec($item, 'square_thumbnail'),
            ],
        ]);

        // Set the files data to the page front matter.
        $files = $item->getFiles();
        if ($files) {
            $filesParam = [];
            foreach ($files as $file) {
                $mimeType = $file->mime_type;
                $extension = $file->getExtension();
                if (isset($this->_renderersByMimeType[$mimeType])) {
                    $renderer = $this->_renderersByMimeType[$mimeType];
                } elseif (isset($this->_renderersByExtension[$extension])) {
                    $renderer = $this->_renderersByExtension[$extension];
                } elseif ($file->hasThumbnail()) {
                    $renderer = 'image';
                } else {
                    $renderer = 'default';
                }
                $filesParam[] = [
                    'id' => $file->id,
                    'renderer' => $renderer,
                ];
            }
            $frontMatterPage['params']['files'] = $filesParam;
        }

        // Add the blocks.
        $blocks = new ArrayObject;
        $this->addBlockFiles($item, $frontMatterPage, $blocks);
        $this->addBlockElementTexts($item, $frontMatterPage, $blocks);
        $this->addBlockFilesGallery($item, $frontMatterPage, $blocks);
        $this->addBlockTags($item, $frontMatterPage, $blocks);

        // @todo: Trigger the item bundle event.

        $this->makeBundleFiles(sprintf('items/%s', $item->id), $item, $frontMatterPage, $blocks);

        // Make the element texts resource file.
        $this->makeFile(
            sprintf('content/items/%s/element_texts.json', $item->id),
            json_encode($this->getAllElementTexts($item))
        );
    }

    /**
     * Create the collections section.
     */
    public function createCollectionsSection()
    {
        $frontMatter = [
            'title' => __('Browse collections'),
            'params' => [],
        ];
        $this->makeDirectory('content/collections');
        $this->makeFile('content/collections/_index.md', json_encode($frontMatter, JSON_PRETTY_PRINT | JSON_FORCE_OBJECT));

        $page = 1;
        do {
            $collections = get_db()->getTable('Collection')->findBy([], 100, $page++);
            foreach ($collections as $collection) {
                $this->createCollectionBundle($collection);
            }
        } while ($collections);
    }

    /**
     * Create a collection bundle.
     */
    public function createCollectionBundle($collection)
    {
        $this->makeDirectory(sprintf('content/collections/%s', $collection->id));
        $this->makeDirectory(sprintf('content/collections/%s/blocks', $collection->id));

        $frontMatterPage = new ArrayObject([
            'date' => (new DateTime(metadata($collection, 'added')))->format('c'),
            'title' => metadata($collection, 'display_title'),
            'draft' => $collection->public ? false : true,
            'params' => [
                'collectionID' => $collection->id,
                'description' => metadata($collection, array('Dublin Core', 'Description'), array('snippet' => 250)),
                'thumbnailSpec' => $this->getThumbnailSpec($collection, 'square_thumbnail'),
            ],
        ]);

        // Add the blocks.
        $blocks = new ArrayObject;
        $this->addBlockElementTexts($collection, $frontMatterPage, $blocks);

        // @todo: Trigger the collection bundle event.

        $this->makeBundleFiles(sprintf('collections/%s', $collection->id), $collection, $frontMatterPage, $blocks);

        // Make the element texts resource file.
        $this->makeFile(
            sprintf('content/collections/%s/element_texts.json', $collection->id),
            json_encode($this->getAllElementTexts($collection))
        );
    }

    /**
     * Make bundle files, including the index page and its block resources.
     *
     * Each block in $blocks must be an array containing the following elements:
     *   - "name" (string): the unique block name
     *   - "frontMatter" (ArrayObject): the block front matter
     *   - "markdown" (string): the block markdown
     */
    public function makeBundleFiles($resourceContentPath, $resource, $frontMatterPage, $blocks)
    {
        // Make the block files.
        $blockPosition = 0;
        foreach ($blocks as $block) {
            // Pad block numbers to get natural sorting for free.
            $blockNumber = str_pad($blockPosition++, 4, '0', STR_PAD_LEFT);
            $this->makeFile(
                sprintf('content/%s/blocks/%s-%s.md', $resourceContentPath, $blockNumber, $block['name']),
                sprintf("%s\n%s", json_encode($block['frontMatter'], JSON_PRETTY_PRINT), $block['markdown'])
            );
        }
        // Make the markdown file.
        $this->makeFile(
            sprintf('content/%s/index.md', $resourceContentPath),
            json_encode($frontMatterPage, JSON_PRETTY_PRINT)
        );
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
    public function execute($command)
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
    public function createSiteArchive()
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
    public function deleteSiteDirectory()
    {
        $command = sprintf(
            'rm -r %s',
            escapeshellarg($this->getSiteDirectoryPath())
        );
        $this->execute($command);
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
                $this->getStaticSite()->getName()
            );
        }
        return $this->_siteDirectoryPath;
    }

    /**
     * Get all element texts of the passed record.
     *
     * Here we restructure the data as an array of maps. We must do this because
     * Hugo automatically sorts arrays by key.
     *
     * @param Omeka_Record_AbstractRecord $record
     * @return array
     */
    public function getAllElementTexts($record)
    {
        $allElementTexts = [];
        foreach (all_element_texts($record, ['return_type' => 'array']) as $elementSetName => $elements) {
            $elementSet = [
                'name' => $elementSetName,
                'elements' => [],
            ];
            $elementKey = 0;
            foreach ($elements as $elementName => $elementTexts) {
                $elementSet['elements'][$elementKey] = [
                    'name' => $elementName,
                    'texts' => [],
                ];
                foreach ($elementTexts as $elementText) {
                    $elementSet['elements'][$elementKey]['texts'][] = $elementText;
                }
                $elementKey++;
            }
            $allElementTexts[] = $elementSet;
        }
        return $allElementTexts;
    }

    /**
     * Add the files gallery block.
     *
     * @param Item $item
     * @param ArrayObject $frontMatterPage
     * @param ArrayObject $blocks
     */
    public function addBlockFiles($item, $frontMatterPage, $blocks)
    {
        if (!metadata($item, 'has files')) {
            return;
        }
        if (get_theme_option('Item FileGallery')) {
            return;
        }
        $blocks[] = [
            'name' => 'files',
            'frontMatter' => new ArrayObject,
            'markdown' => sprintf('{{< omeka-files itemPage="items/%s" >}}', $item->id),
        ];
    }

    /**
     * Add the element texts block.
     *
     * @param Omeka_Record_AbstractRecord $record
     * @param ArrayObject $frontMatterPage
     * @param ArrayObject $blocks
     */
    public function addBlockElementTexts($record, $frontMatterPage, $blocks)
    {
        if ($record instanceof Item) {
            $section = 'items';
        } elseif ($record instanceof Collection) {
            $section = 'collections';
        } else {
            return;
        }
        $blocks[] = [
            'name' => 'elementTexts',
            'frontMatter' => new ArrayObject,
            'markdown' => sprintf('{{< omeka-element-texts page="%s/%s" >}}', $section, $record->id),
        ];
    }

    /**
     * Add the files gallery block.
     *
     * @param Item $item
     * @param ArrayObject $frontMatterPage
     * @param ArrayObject $blocks
     */
    public function addBlockFilesGallery($item, $frontMatterPage, $blocks)
    {
        if (!metadata($item, 'has files')) {
            return;
        }
        if (!get_theme_option('Item FileGallery')) {
            return;
        }
        $frontMatterBlock = new ArrayObject([
            'params' => [
                'id' => 'itemfiles',
                'blockHeading' => __('Files'),
            ],
        ]);
        $blocks[] = [
            'name' => 'filesGallery',
            'frontMatter' => $frontMatterBlock,
            'markdown' => sprintf('{{< omeka-files-gallery itemPage="items/%s" >}}', $item->id),
        ];
    }

    /**
     * Add the tags block.
     *
     * @param Item $item
     * @param ArrayObject $frontMatterPage
     * @param ArrayObject $blocks
     */
    public function addBlockTags($item, $frontMatterPage, $blocks)
    {
        if (!metadata($item, 'has tags')) {
            return;
        }

        // Add item tags to page front matter.
        $tags = [];
        foreach ($item->Tags as $tag) {
            $tags[] = $tag['name'];
        }
        $frontMatterPage['tags'] = $tags;

        $frontMatterBlock = new ArrayObject([
            'params' => [
                'id' => 'item-tags',
                'blockHeading' => __('Tags'),
            ],
        ]);
        $blocks[] = [
            'name' => 'tags',
            'frontMatter' => $frontMatterBlock,
            'markdown' => sprintf('{{< omeka-tags itemPage="items/%s" >}}', $item->id),
        ];
    }

    /**
     * Get the thumbnail specification (page and resource).
     *
     * @param Omeka_Record_AbstractRecord $record Item or File
     * @param string $thumbnailType
     * @return array
     */
    public function getThumbnailSpec($record, $thumbnailType)
    {
        $thumbnailSpec = [
            'page' => null,
            'resource' => null,
        ];
        // Get the primary file.
        $file = $record->getFile();
        if (!$file) {
            return $thumbnailSpec;
        }
        // Set the spec.
        if ($file->has_derivative_image) {
            $thumbnailType = in_array($thumbnailType, ['square_thumbnail', 'thumbnail', 'fullsize']) ? $thumbnailType : 'fullsize';
            $thumbnailSpec['page'] = sprintf('/files/%s', $file->id);
            $thumbnailSpec['resource'] = sprintf('%s.jpg', $thumbnailType);
        } else {
            $topLevelType = explode('/', $file->mime_type)[0];
            switch ($topLevelType) {
                case 'audio':
                    $thumbnailSpec['resource'] = '/thumbnails/fallback-audio.png';
                    break;
                case 'video':
                    $thumbnailSpec['resource'] = '/thumbnails/fallback-video.png';
                    break;
                case 'image':
                    $thumbnailSpec['resource'] = '/thumbnails/fallback-image.png';
                    break;
                default:
                    $thumbnailSpec['resource'] = '/thumbnails/fallback-file.png';
                    break;
            }
        }
        return $thumbnailSpec;
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
