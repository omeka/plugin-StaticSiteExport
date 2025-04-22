<?php
class Job_StaticSiteExport extends Omeka_Job_AbstractJob
{
    public function perform()
    {
        $staticSiteId = $this->_options['static_site_id'];
        $staticSite = $this->_db->getTable('StaticSite')->find($staticSiteId);

        $staticSite->setStatus('starting');
        $staticSite->save();

        // @todo: export the static site
    }
}
