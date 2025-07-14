<?php
class StaticSiteExport_Form_StaticSite extends Omeka_Form
{
    public function init()
    {
        parent::init();
        $this->addElement('text', 'base_url', [
            'label' => __('Base URL'),
            'description' => __('The absolute URL of your published site including the protocol, host, path, and a trailing slash. This is optional and can be set after export, prior to build, in hugo.json under baseURL.'),
        ]);
        $this->addElement('select', 'theme', [
            'label' => __('Theme'),
            'description' => 'Select an Omeka theme to use to style your site. This is optional and can be set after export, prior to build, in hugo.json under params.theme.',
            'multiOptions' => [
                'default' => 'default',
            ],
        ]);
    }
}
