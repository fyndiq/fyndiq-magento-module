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
        $result = false;
        try {
            $result = Mage::helper('fyndiq_fyndiq/connect')->callApi($this->configModel, $storeId, 'PATCH', 'settings/', $data);
        } catch (Exception $e) {
            $this->_getSession()->addError(
                Mage::helper('fyndiq_fyndiq')->
                __('Unfortunately something went wrong. If you keep on getting this message, please contact Fyndiq\'s Integration Support.') . ' (' . $e->getMessage() . ')'
            );
        }
        if ($result) {
            $this->configModel->set('fyndiq/fyndiq_group/username', '', $storeId, false);
            $this->configModel->set('fyndiq/fyndiq_group/apikey', '', $storeId, false);
            $this->configModel->reInit();
        }
        $this->_redirectReferer();
    }

    protected function importOrdersForStore($storeId, $newTime)
    {
        $lastUpdate = $this->configModel->get('fyndiq/fyndiq_group/order_lastdate', $storeId);
        $orderFetchModel = Mage::getModel('fyndiq/orderFetch');
        $orderFetchModel->init($storeId, $lastUpdate);
        $orderFetchModel->getAll();
        return $this->configModel->set('fyndiq/fyndiq_group/order_lastdate', time(), $storeId);
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
                        if ($this->configModel->get('fyndiq/fyndiq_group/apikey', $storeId)) {
                            if ($this->configModel->get('fyndiq/fyndiq_group/import_orders_disabled', $storeId) == Fyndiq_Fyndiq_Model_Order::ORDERS_DISABLED) {
                                $this->_getSession()->addError(
                                    sprintf(
                                        Mage::helper('fyndiq_fyndiq')->__('Orders could not be imported. Order Import from Fyndiq is disabled for store `%s`'),
                                        $store->getName()
                                    )
                                );
                                continue;
                            }
                            $this->importOrdersForStore($storeId, time());
                            $this->_getSession()->addSuccess(
                                sprintf(
                                    Mage::helper('fyndiq_fyndiq')->__('Fyndiq Orders were imported for Store `%s`'),
                                    $store->getName()
                                )
                            );
                        }
                    } catch (Exception $e) {
                        $this->_getSession()->addError(
                            Mage::helper('fyndiq_fyndiq')->
                            __('Unfortunately something went wrong. If you keep on getting this message, please contact Fyndiq\'s Integration Support.') . ' (' . $e->getMessage() . ')'
                        );
                    }
                }
            }
        }
        $this->_redirectReferer();
    }

    public function getDeliveryNoteAction()
    {
        $orderId = $this->getRequest()->getParam('order_id');
        try {
            $result = $this->getDeliveryNotes(array($orderId));
            if ($result) {
                return $result;
            }
        } catch (Exception $e) {
            $this->_getSession()->addError(
                Mage::helper('fyndiq_fyndiq')->
                    __('Unfortunately something went wrong. If you keep on getting this message, please contact Fyndiq\'s Integration Support.') . ' (' . $e->getMessage() . ')'
            );
        }
        $this->_redirectReferer();
    }

    public function getDeliveryNotesAction()
    {
        $orderIds = $this->getRequest()->getParam('order_ids');
        try {
            $result = $this->getDeliveryNotes($orderIds);
            if ($result) {
                return $result;
            }
        } catch (Exception $e) {
            $this->_getSession()->addError(
                Mage::helper('fyndiq_fyndiq')->
                    __('Unfortunately something went wrong. If you keep on getting this message, please contact Fyndiq\'s Integration Support.') . ' (' . $e->getMessage() . ')'
            );
        }
        $this->_redirectReferer();
    }

    protected function getDeliveryNotes($orderIds)
    {
        $fyndiqOrders = Mage::getModel('fyndiq/order')->getFydniqOrders($orderIds);
        // Check if all the orders are from one store
        if (count(array_unique(array_values($fyndiqOrders))) > 1) {
            $this->_getSession()->addError(
                Mage::helper('fyndiq_fyndiq')->__('You can only download Delivery Notes for one store at a time. Please make sure you are not trying to download Delivery Notes for different stores.')
            );
            return false;
        }
        if (count(array_unique(array_values($fyndiqOrders))) == 0) {
            $this->_getSession()->addError(
                Mage::helper('fyndiq_fyndiq')->__('Please select one or more Fyndiq Orders')
            );
            return false;
        }
        $storeId = array_pop(array_values($fyndiqOrders));
        $observer = Mage::getModel('fyndiq/observer');
        $orders = array(
            'orders' => array()
        );
        $fyndiqOrderIds = array_keys($fyndiqOrders);
        foreach ($fyndiqOrderIds as $order) {
            $orders['orders'][] = array('order' => intval($order));
        }
        $ret = Mage::helper('fyndiq_fyndiq/connect')->callApi($this->configModel, $storeId, 'POST', 'delivery_notes/', $orders, true);

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
    }

    protected function exportProductIds($productIds, $storeId)
    {
        $productsExportedReport = array();
        $productModel = Mage::getModel('catalog/product');
        foreach ($productIds as $productId) {
            $product = $productModel->load($productId);
            if ($product) {
                $exportableStatus = Mage::helper('fyndiq_fyndiq/export')->isExportableStatus($product);
                if ($exportableStatus == Fyndiq_Fyndiq_Helper_Export::IS_EXPORTABLE) {
                    $product->setData('fyndiq_exported', Fyndiq_Fyndiq_Model_Export::VALUE_YES)
                        ->getResource()
                        ->saveAttribute($product, 'fyndiq_exported');
                }
                if (!isset($productsExportedReport[$exportableStatus])) {
                    $productsExportedReport[$exportableStatus] = 0;
                }
                $productsExportedReport[$exportableStatus] += 1;
            }

            unset($product);
        }
        return $productsExportedReport;
    }

    protected function detailedReport($productsExported, $productsToExport, $productsExportedReport)
    {
        if ($productsToExport > 0  && $productsExported == 0) {
            $text = Mage::helper('fyndiq_fyndiq')->__('None of the selected products could be exported');
        } elseif ($productsToExport > $productsExported) {
            $text = sprintf(
                Mage::helper('fyndiq_fyndiq')->__('%d products were exported to Fyndiq. %d products could not be exported'),
                $productsExported,
                $productsToExport - $productsExported
            );
        }
        $lines = array();

        if (isset($productsExportedReport[Fyndiq_Fyndiq_Helper_Export::ERR_HAS_OPTIONS]) &&
            $productsExportedReport[Fyndiq_Fyndiq_Helper_Export::ERR_HAS_OPTIONS] > 0
        ) {
            $lines[] = sprintf(
                Mage::helper('fyndiq_fyndiq')->__('%d have custom options set up'),
                $productsExportedReport[Fyndiq_Fyndiq_Helper_Export::ERR_HAS_OPTIONS]
            );
        }

        if (isset($productsExportedReport[Fyndiq_Fyndiq_Helper_Export::ERR_SIMPLE_HAS_PARENT]) &&
            $productsExportedReport[Fyndiq_Fyndiq_Helper_Export::ERR_SIMPLE_HAS_PARENT] > 0
        ) {
            $lines[] = sprintf(
                Mage::helper('fyndiq_fyndiq')->__('%d are part of a configurable product'),
                $productsExportedReport[Fyndiq_Fyndiq_Helper_Export::ERR_SIMPLE_HAS_PARENT]
            );
        }

        if (isset($productsExportedReport[Fyndiq_Fyndiq_Helper_Export::ERR_NOT_SIMPLE_OR_CONFIGURABLE]) &&
            $productsExportedReport[Fyndiq_Fyndiq_Helper_Export::ERR_NOT_SIMPLE_OR_CONFIGURABLE] > 0
        ) {
            $lines[] = sprintf(
                Mage::helper('fyndiq_fyndiq')->__('%d are neither simple nor configurable products'),
                $productsExportedReport[Fyndiq_Fyndiq_Helper_Export::ERR_NOT_SIMPLE_OR_CONFIGURABLE]
            );
        }

        return $this->_getSession()->addNotice(
            $text . ' (' . implode(', ', $lines) . ')'
        );
    }

    protected function showExportedReport($productsToExport, $productsExportedReport)
    {
        $productsExported = isset($productsExportedReport[Fyndiq_Fyndiq_Helper_Export::IS_EXPORTABLE]) ?
            $productsExportedReport[Fyndiq_Fyndiq_Helper_Export::IS_EXPORTABLE]:
            0;
        if ($productsToExport == $productsExported) {
            return $this->_getSession()->addSuccess(
                Mage::helper('fyndiq_fyndiq')->__('The selected products are being exported to Fyndiq.')
            );
        }
        return $this->detailedReport($productsExported, $productsToExport, $productsExportedReport);
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
                $productIds = $productPost['product'];
                $productsToExport = count($productIds);
                $productsExportedReport = $this->exportProductIds($productIds, $storeId);
                $this->showExportedReport($productsToExport, $productsExportedReport);
            }
        } catch (Exception $e) {
            $this->_getSession()->addError(
                Mage::helper('fyndiq_fyndiq')->
                __('Unfortunately something went wrong. If you keep on getting this message, please contact Fyndiq\'s Integration Support.') . ' (' . $e->getMessage() . ')'
            );
        }
        $this->_redirectReferer();
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
                $productModel = Mage::getModel('catalog/product');

                foreach ($productsId as $productid) {
                    $product = $productModel
                        ->setCurrentStore($storeId)
                        ->load($productid);
                    $product->setData('fyndiq_exported', Fyndiq_Fyndiq_Model_Export::VALUE_NO)
                        ->getResource()
                        ->saveAttribute($product, 'fyndiq_exported');

                    unset($product);
                }
                $this->_getSession()->addSuccess(Mage::helper('fyndiq_fyndiq')->__('The selected products are scheduled to be removed from Fyndiq.'));
            }
        } catch (Exception $e) {
            $this->_getSession()->addError(
                Mage::helper('fyndiq_fyndiq')->
                __('Unfortunately something went wrong. If you keep on getting this message, please contact Fyndiq\'s Integration Support.') . ' (' . $e->getMessage() . ')'
            );
        }
        $this->_redirectReferer();
    }

    /**
     * Mark orders as handled
     */
    public function handledFyndiqOrdersAction()
    {
        $this->orderHandling(true);
    }

    /**
     * Mark orders as handled
     */
    public function unhandledFyndiqOrdersAction()
    {
        $this->orderHandling(false);
    }

    protected function orderHandling($handled)
    {
        $orderIds = $this->getRequest()->getParam('order_ids');
        $fyndiqOrders = Mage::getModel('fyndiq/order')->getFydniqOrders($orderIds);
        if ($fyndiqOrders) {
            $work = array();
            foreach ($fyndiqOrders as $orderId => $storeId) {
                if (!isset($work[$storeId])) {
                    $work[$storeId] = array();
                }
                $work[$storeId][] = $orderId;
            }
            foreach ($work as $storeId => $orderIds) {
                try {
                        $data = array(
                            'orders' => array()
                        );
                        foreach ($orderIds as $fyndiqOrderId) {
                            $data['orders'][] = array(
                                'id' => $fyndiqOrderId,
                                'marked' => $handled,
                            );
                        }
                        $ret = Mage::helper('fyndiq_fyndiq/connect')->callApi($this->configModel, $storeId, 'POST', 'orders/marked/', $data);
                } catch (Exception $e) {
                    $this->_getSession()->addError(
                        Mage::helper('fyndiq_fyndiq')->
                        __('Unfortunately something went wrong. If you keep on getting this message, please contact Fyndiq\'s Integration Support') . ' (' . $e->getMessage() . ')'
                    );
                }
            }
        } else {
            $this->_getSession()->addError(
                Mage::helper('fyndiq_fyndiq')->__('No Fyndiq Orders were selected')
            );
        }
        $this->_redirectReferer();
    }

    public function importSKUsAction()
    {
        $productsExported = 0;
        $skus = $this->getRequest()->getParam('skus');
        $skuArray = array_unique(explode("\n", $skus));
        $observer = Mage::getModel('fyndiq/observer');
        $storeId = $observer->getStoreId();
        $product = Mage::getModel('catalog/product');
        $total = count($skuArray);
        $productIds = array();
        try {
            foreach ($skuArray as $sku) {
                $sku = trim($sku);
                if ($sku) {
                    $productId = $product->getIdBySku($sku);
                    if ($productId) {
                        $productIds[] = $productId;
                    }
                }
            }
            $productsExportedReport = $this->exportProductIds($productIds, $storeId);
        } catch (Exception $e) {
            $this->getResponse()->setBody($e->getMessage());
            return;
        }
        $this->getResponse()->setBody(
            json_encode($productsExportedReport)
        );

    }
}
