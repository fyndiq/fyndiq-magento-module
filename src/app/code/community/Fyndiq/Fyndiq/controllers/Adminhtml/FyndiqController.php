<?php

require_once(MAGENTO_ROOT . '/fyndiq/shared/src/init.php');
require_once(dirname(dirname(dirname(__FILE__))) . '/includes/helpers.php');
require_once(dirname(dirname(dirname(__FILE__))) . '/Model/OrderFetch.php');

class Fyndiq_Fyndiq_Adminhtml_FyndiqController extends Mage_Adminhtml_Controller_Action
{

    private $configModel = null;

    protected function _construct()
    {
        FyndiqTranslation::init(Mage::app()->getLocale()->getLocaleCode());
        $this->configModel = Mage::getModel('fyndiq/config');
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

    protected function _isAllowed()
    {
        return Mage::getSingleton('admin/session')->isAllowed('system/fyndiq');
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
                                        FyndiqTranslation::get('Order import is disabled for store `%s`'),
                                        $store->getName()
                                    )
                                );

                                continue;
                            }
                            $newTime = time();
                            $observer->importOrdersForStore($storeId, $newTime);
                            $time = date('G:i:s', $newTime);
                            $this->_getSession()->addSuccess(
                                sprintf(
                                    FyndiqTranslation::get('Fyndiq Orders were imported for store `%s`'),
                                    $store->getName()
                                )
                            );
                        }
                    } catch (Exception $e) {
                        $this->_getSession()->addError(FyndiqTranslation::get('unhandled-error-message') . ' (' . $e->getMessage() . ')');
                    }
                }
            }
        }

        $this->_redirect('adminhtml/sales_order/index');
    }

    /**
     * Getting a pdf of orders.
     *
     * @param $args
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
            $ret = Mage::getHelper('api')->callApi($this->configModel, $storeId, 'POST', 'delivery_notes/', $orders, true);

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
            $this->_getSession()->addError(FyndiqTranslation::get('unhandled-error-message') . ' (' . $e->getMessage() . ')');
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
                    $product->setData('fyndiq_exported', 1)->getResource()->saveAttribute($product, 'fyndiq_exported');
                }
                $this->_getSession()->addSuccess(FyndiqTranslation::get('products-exported-message'));
            }
        } catch (Exception $e) {
            $this->_getSession()->addError(FyndiqTranslation::get('unhandled-error-message') . ' (' . $e->getMessage() . ')');
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
                    $product->setData('fyndiq_exported', 0)->getResource()->saveAttribute($product, 'fyndiq_exported');
                }
                $this->_getSession()->addSuccess(FyndiqTranslation::get('products-deleted-message'));
            }
        } catch (Exception $e) {
            $this->_getSession()->addError(FyndiqTranslation::get('unhandled-error-message') . ' (' . $e->getMessage() . ')');
        }
        $this->_redirect('adminhtml/catalog_product/index');
    }
}
