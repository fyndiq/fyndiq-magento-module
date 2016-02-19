<?php

require_once(Mage::getModuleDir('', 'Fyndiq_Fyndiq') . '/lib/shared/src/init.php');

class Fyndiq_Fyndiq_Model_Observer
{

    private $productModel = null;
    private $configModel = null;

    public function __construct()
    {
        $this->configModel = Mage::getModel('fyndiq/config');
    }

    public function getStoreId()
    {
        $storeCode = Mage::app()->getRequest()->getParam('store');
        if ($storeCode) {
            return Mage::getModel('core/store')->load($storeCode)->getId();
        }
        return 0;
    }

    protected function checkTrackingMethods($storeId)
    {
        $duplicates = Mage::helper('fyndiq_fyndiq/tracking')->getDuplicates($storeId);
        if ($duplicates) {
            Mage::getSingleton('core/session')->addNotice(
                sprintf(
                    Mage::helper('fyndiq_fyndiq')->
                        __('One or more Shipping Methods were selected for more than one Fyndiq Delivery Service (%s). Please make sure that each method is only selected once.'),
                    implode(',', $duplicates)
                )
            );
        }
    }

    public function handle_fyndiqConfigChangedSection()
    {
        $storeId = $this->getStoreId();
        $this->checkTrackingMethods($storeId);
        if ($this->configModel->get('fyndiq/fyndiq_group/username', $storeId) !== ''
            && $this->configModel->get('fyndiq/fyndiq_group/apikey', $storeId) !== ''
        ) {
            // Generate and save token
            $pingToken = Mage::helper('core')->uniqHash();
            $this->configModel->set('fyndiq/fyndiq_group/ping_token', $pingToken, $storeId);
            $this->configModel->reInit();
            $data = array(
                FyndiqUtils::NAME_PRODUCT_FEED_URL => Mage::getUrl(
                    'fyndiq/file/index/store/' . $storeId,
                    array(
                            '_store' => $storeId,
                            '_nosid' => true,
                            '_query' => array(
                                'token' => $pingToken,
                            ),
                        )
                ),
                FyndiqUtils::NAME_PING_URL => Mage::getUrl(
                    'fyndiq/notification/index/store/' . $storeId,
                    array(
                            '_store' => $storeId,
                            '_nosid' => true,
                            '_query' => array(
                                'event' => 'ping',
                                'token' => $pingToken,
                            ),
                        )
                )
            );
            if ($this->configModel->get('fyndiq/fyndiq_group/import_orders_disabled', $storeId) != Fyndiq_Fyndiq_Model_Order::ORDERS_DISABLED) {
                $data[FyndiqUtils::NAME_NOTIFICATION_URL] = Mage::getUrl(
                    'fyndiq/notification/index/store/' . $storeId,
                    array(
                            '_store' => $storeId,
                            '_nosid' => true,
                            '_query' => array(
                                'event' => 'order_created',
                            )
                        )
                );
            }
            try {
                return Mage::helper('fyndiq_fyndiq/connect')->callApi($this->configModel, $storeId, 'PATCH', 'settings/', $data);
            } catch (Exception $e) {
                $message = sprintf(
                    Mage::helper('fyndiq_fyndiq')->__('The configuration could not be sent to Fyndiq (%s)'),
                    $e->getMessage()
                );
                if ($e instanceof FyndiqAPIUnsupportedStatus) {
                    $message = sprintf(
                        Mage::helper('fyndiq_fyndiq')->
                            __('The configuration could not be sent to Fyndiq. Your firewall might be blocking the access to https://api.fyndiq.com (%s)'),
                        $e->getMessage()
                    );
                }
                if ($e instanceof FyndiqAPIAuthorizationFailed) {
                    $message = sprintf(
                        Mage::helper('fyndiq_fyndiq')->__('Incorrect Username or API Token')
                    );
                }
                throw new Exception($message);
            }
        }
        throw new Exception(Mage::helper('fyndiq_fyndiq')->__('Please enter your Fyndiq Username and API Token'));
    }

    protected static function mustRegenerate($generatedTime, $cronInterval)
    {
        if ($generatedTime) {
            return time() > ($generatedTime + $cronInterval);
        }
        return true;
    }

    public function generateAllFeeds()
    {
        $storeIds = array(
            0 // Add Default scope
        );
        $configModel = Mage::getModel('fyndiq/config');
        foreach (Mage::app()->getWebsites() as $website) {
            foreach ($website->getGroups() as $group) {
                $stores = $group->getStores();
                foreach ($stores as $store) {
                    $storeId = $store->getId();
                }
            }
        }
        foreach ($storeIds as $storeId) {
            $cronEnabled = $configModel->get('fyndiq/feed/cron_enabled', $storeId);
            if ($cronEnabled == 1) {
                $generatedTime = intval($configModel->get('fyndiq/feed/generated_time', $storeId));
                $cronInterval = intval($configModel->get('fyndiq/feed/cron_interval', $storeId));
                if (self::mustRegenerate($generatedTime, $cronInterval)) {
                    // Update last generated time
                    $configModel->set('fyndiq/feed/generated_time', time(), $storeId);
                    $configModel->reInit();
                    try {
                        Mage::getModel('fyndiq/export')->generateFeed($storeId);
                    } catch (Exception $e) {
                        Mage::logException($e);
                    }
                }
            }
        }
    }

    public function handleCategoryEditForm(Varien_Event_Observer $observer)
    {
        $form   = $observer->getEvent()->getForm();
        error_log(get_class($form));
        // $attributes = Mage::getConfig()
        //     ->getNode(self::XML_PATH_CATEGORY_ATTRIBUTES);
        // if ($attributes) {
        //     $fields = array_keys($attributes->asArray());
        //     $this->_setMediaChooserFieldRenderer($form, $fields);
        // }
        return $this;
    }

}
