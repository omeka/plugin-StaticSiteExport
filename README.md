# Static Site Export

An [Omeka Classic](https://omeka.org/classic/) plugin for exporting static sites.

## End user notes

This section is meant for end users. If you are using this plugin only to export
and build a static site, the following applies to you.

### Configuring the plugin

After installing this plugin, you will need to add a "Sites directory path" in the
plugin configuration page. This is the path to the directory where your static sites
will be saved on your server. The path must exist and be writable by the web server.

### Exporting a static site

After installing and configuring this plugin, click on "Static Site Export" in the
navigation, and click the "Export static site" button.

On this page you will configure the export. **Currently there are no configuration
options.**

After configuring the export, click the "Export static site" button. The new export
will be the first in the list and will include the status of the export job. The
export is finished once the status is marked as "completed".

Note that an export may take a long time, depending on the size of the site.

Note that the export runs several widely used commands on your server: `cd`, `cp`,
`rm`, `unzip`, and `zip`. While these commands are required during export, they
are likely already installed on your server, so there's nothing you need to do.

### Building a static site

After exporting a static site, you can unzip the resulting ZIP file and immediately
use [Hugo](https://gohugo.io/) to build the site, run a local testing server, and
view the site:

```
cd /path/to/static-sites/
unzip <export-name>.zip
cd <export-name>/
hugo server
```

After the build is complete, follow the instructions in your terminal and go to
the specified web server in your browser. If your site is very large, you may need
to disable the default "watch for changes and recreate" behavior by running
`hugo server --watch=false`.

When you are ready to deploy your site, just run `hugo` in your project directory.
See Hugo's documentation to learn more about how to use the [command line interface (CLI)](https://gohugo.io/commands/)
to manage your site, and how to [host and deploy](https://gohugo.io/host-and-deploy/)
your site.

## Developer notes

This section is meant for developers. If you are using this plugin only to export
and build a static site, none of the following should apply to you.

### Modifying the Hugo theme

To modify the Hugo theme, first clone it in the plugin's data/ directory:

```
$ cd /path/to/omeka/plugins/StaticSiteExport/data/
$ git clone git@github.com:omeka/gohugo-theme-omeka-classic.git
```

It's not required, but we recommend that you clone the repository in the plugins's
data/ direcotry, alongside the theme's ZIP file. Here, Git will ignore the theme
directory so you don't accidentally commit it to the plugin repository.

After modifying the theme, make sure you update the theme's ZIP file before pushing
changes:

```
$ cd /path/to/omeka/plugins/StaticSiteExport/data/
$ zip --recurse-paths gohugo-theme-omeka-classic.zip gohugo-theme-omeka-classic/ --exclude '*.git*'
```

Note that we're excluding the .git/ directory from the ZIP file.

### Adding vendor packages

Plugins can add vendor packages (e.g. scripts, styles, images) by using the "static_site_export_vendor_packages"
filter:

```php
// Remember to register "static_site_export_vendor_packages" in your plugin code.

function filterStaticSiteExportVendorPackages($vendorPackages, $args)
{
    $vendorPackages['package-name'] = '/path/to/vendor/directory';
    return $vendorPackages;
}
```

Where "package-name" is the name of the directory that will be created in the
static site vendor directory; and "/path/to/vendor/directory" is the absolute path
of the directory that contains all assets needed for the package. These assets will
be copied to the newly created static site vendor directory.

### Adding JS and CSS

To include JS on a page, add the JS path the "js" array on a page's front matter:

```php
$frontMatter['js'][] = 'vendor/path/to/script.js';
```

To include CSS on a page, add the CSS path the "css" array on a page's front matter:

```php
$frontMatter['css'][] = 'vendor/path/to/style.css';
```

These can be set in several events (see below).

### Adding Hugo shortcodes

Plugins can add Hugo shortcodes by using the "static_site_export_shortcodes" filter:

```php
// Remember to register the "static_site_export_shortcodes" filter in your plugin code.
function filterStaticSiteExportShortcodes($shortcodes, $args)
{
    $shortcodes['shortcode-name'] = '/path/to/shortcode-name.html';
    return $vendorPackages;
}
```

Where "shortcode-name" is the name of the shortcode; and "/path/to/shortcode-name.html"
is the absolute path of the shortcode file. These shortcodes will be copied to the
newly created static site directory.

#### Using events to modify page (resource) bundles

Plugins can modify page bundles via the following events:

- `static_site_export_file_bundle`
    - `file`: The file record
    - `front_matter_page`: An `ArrayObject` containing page front matter
    - `blocks`: An `ArrayObject` containing resource page blocks, if any
- `static_site_export_item_bundle`
    - `item`: The item record
    - `front_matter_page`: An `ArrayObject` containing page front matter
    - `blocks`: An `ArrayObject` containing resource page blocks, if any
- `static_site_export_collection_bundle`
    - `collection`: The collection record
    - `front_matter_page`: An `ArrayObject` containing page front matter
    - `blocks`: An `ArrayObject` containing resource page blocks, if any

You may modify Hugo page front matter using the `front_matter_page` parameter, like so:

```php
// Remember to register the "static_site_export_item_bundle" hook in your plugin code.
public function hookStaticSiteExportItemBundle($args)
{
    $frontMatterPage = $args['front_matter_page'];
    $frontMatterPage['params']['myParam'] = 'foobar';
}
```

You may add content (blocks) to pages using the `blocks` parameter, like so:

```php
// Remember to register the "static_site_export_item_bundle" hook in your plugin code.
public function hookStaticSiteExportItemBundle($args)
{
    $blocks = $args['blocks'];
    $frontMatter = [];
    $markdown = 'My block';
    $blocks[] = [
        'name' => 'my-block',
        'frontMatter' => $frontMatter,
        'markdown' => $markdown,
    ];
}
```

#### Other events

You may do things immediately before and after exporting using these events (in
this case, "exporting" means to create the site directory):

- `static_site_export_site_export_pre`:
    - Runs before the site directory is created
- `static_site_export_site_export_post`
    - Runs after the site directory is created, but before it is archived

The `export_post` event is particularly useful to create directories and files needed
by your plugin.

You may modify the Hugo site configuration using this event:

- `static_site_export_site_config`:
    - `site_config`: An `ArrayObject` of Hugo site configuration

Use the `site_config` parameter, like so:

```php
// Remember to register the "static_site_export_site_config" hook in your plugin code.
public function hookStaticSiteExportSiteConfig($args)
{
    $siteConfig = $args['site_config'];
    $siteConfig['params']['myParam'] = 'foobar';
}
```

The [ExhibitBuilder plugin](https://github.com/omeka/plugin-ExhibitBuilder) introduces
a hook to add blocks to exhibit pages:

- `exhibit_builder_static_site_export_exhibit_page_block`
    - `frontMatterExhibitPage`: An `ArrayObject` containing page front matter
    - `frontMatterExhibitPageBlock`: An `ArrayObject` containing block front matter
    - `block`: The block record
    - `markdown`: An `ArrayObject` containing markdown (set block markdown here)
