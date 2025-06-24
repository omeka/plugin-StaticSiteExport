<?php
class Job_StaticSiteDelete extends Job_AbstractStaticSite
{
    /**
     * Delete the static site.
     */
    public function perform()
    {
        $this->deleteSiteDirectory();
        $this->deleteSiteZip();
    }
}
