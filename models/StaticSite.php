<?php
class StaticSite extends Omeka_Record_AbstractRecord implements Zend_Acl_Resource_Interface
{
    public $owner_id;
    public $added;
    public $status;
    public $name;
    public $data;

    protected function _initializeMixins()
    {
        $this->_mixins[] = new Mixin_Owner($this);
        $this->_mixins[] = new Mixin_Timestamp($this);
    }

    protected function beforeSave($args)
    {
        if ($args['insert']) {
            $this->setStatus(Process::STATUS_STARTING);
        }
    }

    protected function afterSave($args)
    {
        if ($args['insert']) {
            // Set a unique name for the static site using the record ID.
            $this->setName(sprintf(
                '%s-%s',
                str_replace(' ', '-', strtolower(get_option('site_title'))),
                $this->getId()
            ));
            $this->save();
        }
    }

    public function getId()
    {
        return $this->id;
    }

    public function setName($name)
    {
        $this->name = $name;
    }

    public function getName()
    {
        return $this->name;
    }

    public function setData($data)
    {
        $this->data = json_encode($data);
    }

    public function getData()
    {
        return json_decode($this->data, true);
    }

    public function getDataValue($key, $default = null)
    {
        $data = $this->getData();
        return isset($data[$key]) ? $data[$key] : $default;
    }

    public function setStatus($status)
    {
        $this->status = $status;
    }

    public function getStatus()
    {
        return $this->status;
    }

    public function getDisplayStatus()
    {
        return $this->status;
    }

    public function getResourceId()
    {
        return 'StaticSiteExport_StaticSite';
    }

    public function getRecordUrl($action = 'show')
    {
        return array('controller' => 'static-site-export', 'action' => $action, 'id' => $this->getId());
    }

    protected function afterDelete()
    {
        if (!in_array($this->getStatus(), [Process::STATUS_COMPLETED, Process::STATUS_ERROR])) {
            return;
        }
        // Dispatch the static site delete job.
        $dispatcher = Zend_Registry::get('job_dispatcher');
        $dispatcher->sendLongRunning(
            'Job_StaticSiteDelete',
            ['static_site_name' => $this->getName()]
        );
    }
}
