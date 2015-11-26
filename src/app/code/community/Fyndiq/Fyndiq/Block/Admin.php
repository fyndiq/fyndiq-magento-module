<?php
require_once(dirname(dirname(__FILE__)) . '/includes/config.php');


/**
 * Admin block handling all the data to the view
 */
class Fyndiq_Fyndiq_Block_Admin extends Mage_Core_Block_Template
{

    /**
     * Get frontend path
     *
     * @return string
     */
    public function getFrontendPath()
    {
        return Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_WEB) . "fyndiq/";
    }

    /**
     * Get shared path
     *
     * @return string
     */
    public function getSharedPath()
    {
        return Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_WEB) . 'fyndiq/shared/';
    }

    /**
     * Get service path
     *
     * @return string
     */
    public function getServicePath()
    {
        return $this->getAdminPath('fyndiq/service/index') . '?isAjax=true';
    }

    /**
     * Get admin path
     *
     * @param $path
     * @param null $section
     * @return mixed
     */
    function getAdminPath($path, $section = null)
    {
        // Add scope
        $request = $this->getRequest();
        $segments = array(
            $path,
            'website',
            $request->getParam('website'),
            'store',
            $request->getParam('store'),
        );

        return Mage::helper('adminhtml')->getUrl(implode('/', $segments), $section);
    }

    public function getLanguage()
    {
        return Mage::getStoreConfig('general/country/default');
    }

    public function getCurrency()
    {
        return Mage::app()->getStore()->getCurrentCurrencyCode();
    }

    public function getStoreId()
    {
        return $this->getRequest()->getParam('store');
    }

    public function getPercentage()
    {
        return FmConfig::get('percentage', $this->getStoreId());
    }

    public function getUsername()
    {
        return FmConfig::get('username', $this->getStoreId());
    }

    public function getMessage($key)
    {
        return FyndiqTranslation::get($key);
    }

    public function getMessages()
    {
        return FyndiqTranslation::getAll();
    }

    public function getVersion()
    {
        return FyndiqUtils::getVersionLabel(FmConfig::getVersion(), FmConfig::COMMIT);
    }

    public function getLastUpdatedDate($storeId)
    {
        $date = Mage::getModel('fyndiq/setting')->getSetting($storeId, 'order_lastdate');
        if ($date != false) {
            return $date['value'];
        }

        return false;
    }

    public function getStoreSelectOptions()
    {
        $switcher = new Mage_Adminhtml_Block_System_Config_Switcher();

        return $switcher->getStoreSelectOptions();
    }

    public function getRepositoryPath()
    {
        return FmConfig::REPOSITORY_PATH;
    }

    public function getModuleVersion()
    {
        return FmConfig::getVersion();
    }

    public function getDisableUpdateCheck()
    {
        return FmConfig::DISABLE_UPDATE_CHECK;
    }

    public function getProbes()
    {
        $probes = array(
            array(
                'label' => $this->getMessage('Checking file permissions'),
                'action' => 'probe_file_permissions',
            ),
            array(
                'label' => $this->getMessage('Checking database'),
                'action' => 'probe_database',
            ),
            array(
                'label' => $this->getMessage('Module integrity'),
                'action' => 'probe_module_integrity',
            ),
            array(
                'label' => $this->getMessage('Connection to Fyndiq'),
                'action' => 'probe_connection',
            ),
        );
        return json_encode($probes);
    }

    public function ordersEnabled($storeId)
    {
        return FmConfig::get('import_orders_disabled', $storeId) != FmHelpers::ORDERS_DISABLED;
    }
}
