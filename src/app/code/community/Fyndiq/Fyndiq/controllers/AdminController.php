<?php

/**
 * Created by PhpStorm.
 * User: confact
 * Date: 18/08/14
 * Time: 09:50
 */

class Fyndiq_Fyndiq_AdminController extends Mage_Adminhtml_Controller_Action
{

    private $allowedCurrencies = array('SEK', 'EUR');

    protected function _construct()
    {
        require_once(MAGENTO_ROOT . '/fyndiq/shared/src/init.php');
        require_once(dirname(dirname(__FILE__)) . '/includes/helpers.php');
        FyndiqTranslation::init(Mage::app()->getLocale()->getLocaleCode());
    }

    /**
     * The page where everything happens.
     */
    public function indexAction()
    {
        $this->loadLayout(array('default'));

        return $this->setTemplate('fyndiq/exportproducts.phtml');
    }


    /**
     * Show order list
     */
    public function orderlistAction()
    {
        $this->loadLayout(array('default'));

        $this->setTemplate('fyndiq/orderlist.phtml');
    }

    function setTemplate($template)
    {
        $isAuthorized = true;
        $message = '';
        try {
            $storeId = $this->getRequest()->getParam('store');
            $this->callAPI($storeId);
        } catch (Exception $e) {
            if ($e instanceof FyndiqAPIAuthorizationFailed) {
                $isAuthorized = false;
            }
            $message = $e->getMessage();
        }
        if ($message && !$isAuthorized) {
            $this->setupTemplate('fyndiq/apierror.phtml', array('message' => $message));

            return false;
        }
        if ($this->getAPIToken() == '' || $this->getUsername() == '') {
            $this->setupTemplate('fyndiq/needapiinfo.phtml');

            return false;
        }
        $currency = Mage::app()->getStore()->getCurrentCurrencyCode();
        if (!in_array($currency, $this->allowedCurrencies)) {
            $this->setupTemplate('fyndiq/currencyerror.phtml');

            return false;
        }

        return $this->setupTemplate($template);

    }

    function disconnectAction()
    {
        $config = new Mage_Core_Model_Config();
        $config->saveConfig('fyndiq/fyndiq_group/apikey', "", 'default', "");
        $config->saveConfig('fyndiq/fyndiq_group/username', "", 'default', "");
        $this->_redirect("fyndiq/admin/index");
    }

    /**
     * Setting up the template with correct block and everything.
     *
     * @param $template
     * @param null $data
     */
    private function setupTemplate($template, $data = null)
    {
        $block = $this->getLayout()
            ->createBlock('Fyndiq_Fyndiq_Block_Admin', 'fyndiq.admin')
            ->setTemplate($template);

        $block->setData('data', $data);
        $this->getLayout()->getBlock('content')->append($block);

        return $this->renderLayout();
    }

    /**
     * Get the username from config
     *
     * @return mixed
     */
    public function callAPI($storeId)
    {
        FmHelpers::callApi($storeId, 'GET', 'settings/');
    }
    public function getUsername()
    {
        return FmConfig::get('username', $this->getRequest()->getParam('store'));
    }

    /**
     * Get APItoken from config
     *
     * @return mixed
     */
    public function getAPIToken()
    {
        return FmConfig::get('apikey', $this->getRequest()->getParam('store'));
    }
}