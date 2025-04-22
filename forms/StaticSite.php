<?php
class StaticSiteExport_Form_StaticSite extends Omeka_Form
{
    public function init()
    {
        parent::init();
        $this->addElement('text', 'base_url', [
            'label' => __('Base URL'),
            'description' => __('The absolute URL of your published site including the protocol, host, path, and a trailing slash (e.g. https://example.org/)'),
        ]);
    }
}
