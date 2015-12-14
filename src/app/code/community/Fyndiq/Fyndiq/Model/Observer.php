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

    public function handle_fyndiqConfigChangedSection()
    {
        $storeId = $this->getStoreId();
        if ($this->configModel->get('username', $storeId) !== ''
            && $this->configModel->get('apikey', $storeId) !== ''
        ) {
            // Generate and save token
            $pingToken = Mage::helper('core')->uniqHash();
            $this->configModel->set('ping_token', $pingToken, $storeId);
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
            if ($this->configModel->get('import_orders_disabled', $storeId) != Fyndiq_Fyndiq_Model_Order::ORDERS_DISABLED) {
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
            try{
                return Mage::helper('api')->callApi($this->configModel, $storeId, 'PATCH', 'settings/', $data);
            } catch (Exception $e) {
                throw new Exception(
                    sprintf(
                        Mage::helper('fyndiq_fyndiq')->
                            __('Error setting the configuration on Fyndiq. Possible reason: Access to https://api.fyndiq.com is restricted (%s)'),
                        $e->getMessage()
                    )
                );
            }
        }
        throw new Exception(Mage::helper('fyndiq_fyndiq')->__('Please specify a Username and API token.'));
    }
}
