<?php
require_once dirname(__FILE__) . '/../forms/StaticSite.php';

class StaticSiteExport_IndexController extends Omeka_Controller_AbstractActionController
{
    protected $_browseRecordsPerPage = 10;

    public function init()
    {
        $this->_helper->db->setDefaultModelName('StaticSite');
    }

    public function exportAction()
    {
        $staticSite = new StaticSite;
        $form = new StaticSiteExport_Form_StaticSite;

        if ($this->getRequest()->isPost()) {
            if ($form->isValid($_POST)) {
                try {
                    // Save the static site record.
                    $staticSite = new StaticSite;
                    $data = $staticSite->setData([
                        'base_url' => $form->getValue('base_url'),
                    ]);
                    $staticSite->save();

                    // Dispatch the static site export job.
                    $dispatcher = Zend_Registry::get('job_dispatcher');
                    $dispatcher->sendLongRunning(
                        'Job_StaticSiteExport',
                        ['static_site_id' => $staticSite->getId()]
                    );

                    $this->_helper->flashMessenger(__('Exporting the static site "%s".', $staticSite->name), 'success');
                    $this->_helper->redirector('browse');
                } catch (Omeka_Validate_Exception $e) {
                    $staticSite->delete();
                    $this->_helper->flashMessenger($e);
                }
            } else {
                $this->_helper->flashMessenger(__('There were errors found in your form. Please edit and resubmit.'), 'error');
            }
        }

        $this->view->form = $form;
    }

    protected function _getBrowseDefaultSort()
    {
        return array('added', 'd');
    }
}
