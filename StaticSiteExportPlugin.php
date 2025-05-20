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

        $indexResource = new Zend_Acl_Resource('StaticSiteExport_Index');
        $acl->add($indexResource);
    }

    public function hookDefineRoutes($args)
    {
        $router = $args['router'];

        $router->addRoute(
            'static-site-export',
            new Zend_Controller_Router_Route(
                'static-site-export/:action',
                [
                    'module' => 'static-site-export',
                    'controller' => 'index',
                    'action' => 'browse',
                ]
            )
        );
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
        if (!self::sitesDirectoryPathIsValid($_POST['sites_directory_path'])) {
            throw new Omeka_Plugin_Installer_Exception('Invalid sites directory path');
        }

        set_option('static_site_export_sites_directory_path', $_POST['sites_directory_path']);
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

    public static function sitesDirectoryPathIsValid($sitesDirectoryPath)
    {
        return (is_dir($sitesDirectoryPath) && is_writable($sitesDirectoryPath));
    }
}
