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
        $form = new StaticSiteExport_Form_StaticSite;
        $csrf = new Omeka_Form_SessionCsrf;
        $this->view->form = $form;
        $this->view->csrf = $csrf;

        if ($this->getRequest()->isPost()) {
            if (!$csrf->isValid($_POST)) {
                $this->_helper->flashMessenger(__('There were errors found in your form. Please edit and resubmit.'), 'error');
                return;
            }
            $staticSite = new StaticSite;
            $staticSite->setData([
                'base_url' => $_POST['base_url'],
                'theme' => $_POST['theme'],
            ]);
            if ($staticSite->save(false)) {

                // Dispatch the static site export job.
                $dispatcher = Zend_Registry::get('job_dispatcher');
                $dispatcher->sendLongRunning(
                    'Job_StaticSiteExport',
                    ['static_site_id' => $staticSite->getId()]
                );

                $this->_helper->flashMessenger(__('Exporting the static site "%s".', $staticSite->name), 'success');
                $this->_helper->redirector('browse');
            } else {
                $staticSite->delete();
                $this->_helper->flashMessenger($e);
            }
        }
    }

    protected function _getBrowseDefaultSort()
    {
        return array('added', 'd');
    }

    protected function _getDeleteConfirmMessage($staticSite)
    {
        return __(sprintf('This will delete the static site "%s".', $staticSite->getName()));
    }

    protected function _getDeleteSuccessMessage($staticSite)
    {
        return __(sprintf('The static site "%s" was successfully deleted.', $staticSite->getName()));
    }
}
