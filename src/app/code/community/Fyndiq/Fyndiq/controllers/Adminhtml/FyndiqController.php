<?php

require_once(Mage::getModuleDir('', 'Fyndiq_Fyndiq') . '/lib/shared/src/init.php');

class Fyndiq_Fyndiq_Adminhtml_FyndiqController extends Mage_Adminhtml_Controller_Action
{

    private $configModel = null;

    protected function _construct()
    {
        $this->configModel = Mage::getModel('fyndiq/config');
    }

    protected function _isAllowed()
    {
        return Mage::getSingleton('admin/session')->isAllowed('system/fyndiq');
    }

    public function disconnectAction()
    {
        $observer = new Fyndiq_Fyndiq_Model_Observer();
        $storeId = $observer->getStoreId();
        $data = array(
            FyndiqUtils::NAME_PRODUCT_FEED_URL => '',
            FyndiqUtils::NAME_PING_URL => '',
            FyndiqUtils::NAME_NOTIFICATION_URL => '',
        );
        if (Mage::getModel('fyndiq/config')->callApi($this->configModel, $storeId, 'PATCH', 'settings/', $data)) {
            $this->configModel->set('username', '', $storeId, false);
            $this->configModel->set('apikey', '', $storeId, false);
            $this->configModel->reInit();
        }
        $this->_redirect('fyndiq/admin/index');
    }

    protected function importOrdersForStore($storeId, $newTime)
    {
        $lastUpdate = $this->configModel->get('order_lastdate', $storeId);
        $orderFetchModel = Mage::getModel('fyndiq/orderFetch');
        $orderFetchModel->init($storeId, $lastUpdate);
        $orderFetchModel->getAll();
        return $this->configModel->set('order_lastdate', time(), $storeId);
    }

    public function importFyndiqOrdersAction()
    {

        foreach (Mage::app()->getWebsites() as $website) {
            foreach ($website->getGroups() as $group) {
                $stores = $group->getStores();
                foreach ($stores as $store) {
                    try {
                        $observer = Mage::getModel('fyndiq/observer');
                        $storeId = $store->getId();
                        if ($this->configModel->get('apikey', $storeId)) {
                            if ($this->configModel->get('import_orders_disabled', $storeId) == Fyndiq_Fyndiq_Model_Order::ORDERS_DISABLED) {
                                $this->_getSession()->addError(
                                    sprintf(
                                        Mage::helper('fyndiq_fyndiq')->__('Order import is disabled for store `%s`'),
                                        $store->getName()
                                    )
                                );
                                continue;
                            }
                            $this->importOrdersForStore($storeId, time());
                            $this->_getSession()->addSuccess(
                                sprintf(
                                    Mage::helper('fyndiq_fyndiq')->__('Fyndiq Orders were imported for store `%s`'),
                                    $store->getName()
                                )
                            );
                        }
                    } catch (Exception $e) {
                        $this->_getSession()->addError(
                            Mage::helper('fyndiq_fyndiq')->
                            __('An unhandled error occurred. If this persists, please contact Fyndiq integration support.') . ' (' . $e->getMessage() . ')'
                        );
                    }
                }
            }
        }
        $this->_redirect('adminhtml/sales_order/index');
    }

    /**
     * Getting a PDF of orders.
     *
     */
    public function getDeliveryNotesAction()
    {
        $orderIds = $this->getRequest()->getParam('order_ids');
        $fyndiqOrderIds = Mage::getModel('fyndiq/order')->getFydniqOrderIds($orderIds);
        $observer = Mage::getModel('fyndiq/observer');
        try {
            $orders = array(
                'orders' => array()
            );
            foreach ($fyndiqOrderIds as $order) {
                $orders['orders'][] = array('order' => intval($order));
            }
            $storeId = $observer->getStoreId();
            $ret = Mage::helper('api')->callApi($this->configModel, $storeId, 'POST', 'delivery_notes/', $orders, true);

            if ($ret['status'] == 200) {
                $fileName = 'delivery_notes-' . implode('-', $fyndiqOrderIds) . '.pdf';

                header('Content-Type: application/pdf');
                header('Content-Disposition: attachment; filename="' . $fileName . '"');
                header('Content-Transfer-Encoding: binary');
                header('Content-Length: ' . strlen($ret['data']));
                header('Expires: 0');
                $handler = fopen('php://temp', 'wb+');
                // Saving data to file
                fputs($handler, $ret['data']);
                rewind($handler);
                fpassthru($handler);
                fclose($handler);
                die();
            }
            return $this->response(true);
        } catch (Exception $e) {
            $this->_getSession()->addError(
                Mage::helper('fyndiq_fyndiq')->
                __('An unhandled error occurred. If this persists, please contact Fyndiq integration support.') . ' (' . $e->getMessage() . ')'
            );
            $this->_redirect('adminhtml/sales_order/index');
        }
    }
    /**
    * Export products from Magento
    */
    public function exportProductsAction()
    {
        try {
            $observer = Mage::getModel('fyndiq/observer');
            $storeId = $observer->getStoreId();
            $productPost = $this->getRequest()->getPost();
            if ($productPost) {
                $productsId = $productPost['product'];
                foreach ($productsId as $productid) {
                    $product = Mage::getModel('catalog/product')
                                ->setCurrentStore($storeId)
                                ->load($productid);
                    $product->setData('fyndiq_exported', Fyndiq_Fyndiq_Model_Attribute_Exported::PRODUCT_EXPORTED)
                        ->getResource()
                        ->saveAttribute($product, 'fyndiq_exported');
                }
                $this->_getSession()->addSuccess(Mage::helper('fyndiq_fyndiq')->__('products-exported-message'));
            }
        } catch (Exception $e) {
            $this->_getSession()->addError(
                Mage::helper('fyndiq_fyndiq')->
                __('An unhandled error occurred. If this persists, please contact Fyndiq integration support.') . ' (' . $e->getMessage() . ')'
            );
        }
        $this->_redirect('adminhtml/catalog_product/index');
    }

    /**
    * Remove products from Fyndiq export
    */
    public function removeProductsAction()
    {
        try {
            $observer = Mage::getModel('fyndiq/observer');
            $storeId = $observer->getStoreId();
            $productPost = $this->getRequest()->getPost();
            if ($productPost) {
                $productsId = $productPost['product'];
                foreach ($productsId as $productid) {
                    $product = Mage::getModel('catalog/product')
                                ->setCurrentStore($storeId)
                                ->load($productid);
                    $product->setData('fyndiq_exported', Fyndiq_Fyndiq_Model_Attribute_Exported::PRODUCT_NOT_EXPORTED)
                        ->getResource()
                        ->saveAttribute($product, 'fyndiq_exported');
                }
                $this->_getSession()->addSuccess(Mage::helper('fyndiq_fyndiq')->__('products-deleted-message'));
            }
        } catch (Exception $e) {
            $this->_getSession()->addError(
                Mage::helper('fyndiq_fyndiq')->
                __('An unhandled error occurred. If this persists, please contact Fyndiq integration support.') . ' (' . $e->getMessage() . ')'
            );
        }
        $this->_redirect('adminhtml/catalog_product/index');
    }
}
