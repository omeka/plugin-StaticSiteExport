<?php
class StaticSiteExport_Form_StaticSite extends Omeka_Form
{
    public function init()
    {
        parent::init();
        $this->addElement('select', 'theme', [
            'label' => __('Theme'),
            'multiOptions' => [
                'default' => 'default',
            ],
        ]);
    }
}
