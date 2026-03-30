<?php
class StaticSiteExportPlugin extends Omeka_Plugin_AbstractPlugin
{
    protected $_hooks = [
        'install',
        'uninstall',
        'upgrade',
        'initialize',
        'define_acl',
        'define_routes',
        'config_form',
        'config',
    ];

    protected $_filters = [
        'admin_navigation_main',
        'static_site_export_omeka_shortcode_callbacks',
    ];

    public function hookInstall()
    {
        $db = $this->_db;
        $sql = "
        CREATE TABLE IF NOT EXISTS `$db->StaticSite` (
            `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
            `owner_id` int(10) unsigned NOT NULL,
            `added` timestamp NOT NULL,
            `status` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
            `name` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
            `data` text COLLATE utf8_unicode_ci NOT NULL,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci";
        $db->query($sql);
    }

    public function hookUninstall()
    {
        $db = $this->_db;
        $sql = "DROP TABLE IF EXISTS `$db->StaticSite`";
        $db->query($sql);
    }

    public function hookUpgrade($args)
    {
    }

    public function hookInitialize()
    {
        add_translation_source(dirname(__FILE__) . '/languages');
    }

    public function hookDefineAcl($args)
    {
        $acl = $args['acl'];

        $acl->addResource('StaticSiteExport_StaticSite');

        $indexResource = new Zend_Acl_Resource('StaticSiteExport_Index');
        $acl->add($indexResource);
    }

    public function hookDefineRoutes($args)
    {
        $args['router']->addConfig(new Zend_Config_Ini(dirname(__FILE__) . '/routes.ini', 'routes'));
    }

    public function hookConfigForm()
    {
        $sitesDirectoryPath = get_option('static_site_export_sites_directory_path');
        echo get_view()->partial(
            'static-site-export-config-form.php',
            [
                'sites_directory_path' => $sitesDirectoryPath,
            ]
        );
    }

    public function hookConfig()
    {
        // Enforce an absolute path to avoid potential relative path mismatches
        // between admin and job contexts.
        $path = realpath($_POST['sites_directory_path']);
        if (!self::sitesDirectoryPathIsValid($path)) {
            throw new Omeka_Plugin_Installer_Exception('Invalid sites directory path');
        }

        set_option('static_site_export_sites_directory_path', $path);
    }

    public function filterAdminNavigationMain($nav)
    {
        $nav[] = [
            'label' => __('Static Site Export'),
            'uri' => url('static-site-export'),
            'resource' => 'StaticSiteExport_Index',
        ];
        return $nav;
    }

    public function filterStaticSiteExportOmekaShortcodeCallbacks($callbacks)
    {
        // @see Omeka_View_Helper_Shortcodes::shortcodeItems()
        $callbacks['items'] = function ($args, $job) {
            $params = [];
            if (isset($args['is_featured'])) {
                $params['featured'] = $args['is_featured'];
            }
            if (isset($args['has_image'])) {
                $params['hasImage'] = $args['has_image'];
            }
            if (isset($args['collection'])) {
                $params['collection'] = $args['collection'];
            }
            if (isset($args['item_type'])) {
                $params['item_type'] = $args['item_type'];
            }
            if (isset($args['tags'])) {
                $params['tags'] = $args['tags'];
            }
            if (isset($args['user'])) {
                $params['user'] = $args['user'];
            }
            if (isset($args['ids'])) {
                $params['range'] = $args['ids'];
            }
            if (isset($args['sort'])) {
                $params['sort_field'] = $args['sort'];
            }
            if (isset($args['order'])) {
                $params['sort_dir'] = $args['order'];
            }
            if (isset($args['num'])) {
                $limit = $args['num'];
            } else {
                $limit = 10;
            }
            $content = [];
            $items = get_records('Item', $params, $limit);
            foreach ($items as $item) {
                $content[] = sprintf('{{< omeka-single-item itemPage="items/%s" >}}', $item->id);
            }
            return implode("\n", $content);
        };

        // @see Omeka_View_Helper_Shortcodes::shortcodeRecentItems()
        $callbacks['recent_items'] = function ($args, $job) use ($callbacks) {
            if (!isset($args['num'])) {
                $args['num'] = '5';
            }
            $args['sort'] = 'added';
            $args['order'] = 'd';
            return $callbacks['items']($args, $job);
        };

        // @see Omeka_View_Helper_Shortcodes::shortcodeFeaturedItems()
        $callbacks['featured_items'] = function ($args, $job) use ($callbacks) {
            if (!isset($args['num'])) {
                $args['num'] = '1';
            }
            if (!isset($args['has_image'])) {
                $args['has_image'] = null;
            }
            $args['is_featured'] = 1;
            $args['sort'] = 'random';
            return $callbacks['items']($args, $job);
        };

        // @see Omeka_View_Helper_Shortcodes::shortcodeCollections()
        $callbacks['collections'] = function ($args, $job) {
            $params = [];
            if (isset($args['ids'])) {
                $params['range'] = $args['ids'];
            }
            if (isset($args['sort'])) {
                $params['sort_field'] = $args['sort'];
            }
            if (isset($args['order'])) {
                $params['sort_dir'] = $args['order'];
            }
            if (isset($args['is_featured'])) {
                $params['featured'] = $args['is_featured'];
            }
            if (isset($args['num'])) {
                $limit = $args['num'];
            } else {
                $limit = 10;
            }
            $content = [];
            $collections = get_records('Collection', $params, $limit);
            foreach ($collections as $collection) {
                $content[] = sprintf('{{< omeka-single-collection collectionPage="collections/%s" >}}', $collection->id);
            }
            return implode("\n", $content);
        };

        // @see Omeka_View_Helper_Shortcodes::shortcodeRecentCollections()
        $callbacks['recent_collections'] = function ($args, $job) use ($callbacks) {
            if (!isset($args['num'])) {
                $args['num'] = '5';
            }
            $args['sort'] = 'added';
            $args['order'] = 'd';
            return $callbacks['collections']($args, $job);
        };

        // @see Omeka_View_Helper_Shortcodes::shortcodeFeaturedCollections()
        $callbacks['featured_collections'] = function ($args, $job) use ($callbacks) {
            if (!isset($args['num'])) {
                $args['num'] = '1';
            }
            $args['is_featured'] = '1';
            $args['sort'] = 'random';
            return $callbacks['collections']($args, $job);
        };

        // @see Omeka_View_Helper_Shortcodes::shortcodeFile()
        $callbacks['file'] = function ($args, $job) {
            $file = get_record_by_id('File', $args['id']);
            if (!$file) {
                return;
            }
            // Set the file type.
            $type = strstr($file->mime_type, '/', true);
            // Set the image resource.
            $imgResource = 'square_thumbnail';
            if (isset($args['size']) && in_array($args['size'], ['thumbnail', 'square_thumbnail', 'fullsize'])) {
                $imgResource = $args['size'];
            }
            // Set the width/height.
            $width = $args['width'] ?? '';
            $height = $args['height'] ?? '';
            return sprintf(
                '{{< omeka-figure type="%s" filePage="files/%s" fileResource="file" imgPage="files/%s" imgResource="%s" width="%s" height="%s" >}}',
                $type,
                $file->id,
                $file->id,
                $imgResource,
                $width,
                $height
            );
        };

        return $callbacks;
    }

    public static function sitesDirectoryPathIsValid($sitesDirectoryPath)
    {
        return (is_dir($sitesDirectoryPath) && is_writable($sitesDirectoryPath));
    }
}
