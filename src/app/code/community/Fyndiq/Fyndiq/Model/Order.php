<?php

class Fyndiq_Fyndiq_Model_Order extends Mage_Core_Model_Abstract
{

    const FYNDIQ_ORDERS_EMAIL = 'info@fyndiq.se';
    const FYNDIQ_ORDERS_NAME_FIRST = 'Fyndiq';
    const FYNDIQ_ORDERS_NAME_LAST = 'Orders';

    const PAYMENT_METHOD = 'fyndiq_fyndiq';
    const SHIPPING_METHOD = 'fyndiq_fyndiq';

    public function _construct()
    {
        parent::_construct();
        $this->_init('fyndiq/order');
    }

    /**
     * Check if order already exists
     *
     * @param array $fyndiqOrder
     * @return bool
     */
    public function orderExists($fyndiqOrder)
    {
        $collection = $this->getCollection()
            ->addFieldToFilter('fyndiq_orderid', $fyndiqOrder)
            ->getFirstItem();
        if ($collection->getId()) {
            return true;
        }

        return false;
    }

    /**
     * Add to check table to check if order already exists.
     *
     * @param int $fyndiqOrderId
     * @param int $orderId
     * @return mixed
     */
    public function addCheckData($fyndiqOrderId, $orderId)
    {
        $data = array(
            'fyndiq_orderid' => $fyndiqOrderId,
            'order_id' => $orderId
        );
        $model = $this->setData($data);

        return $model->save()->getId();
    }


    /**
     * Loading Imported orders
     * @param $page
     * @return array
     */
    public function getImportedOrders($page, $itemsPerPage)
    {
        $result = array();
        $orders = $this->getCollection()->setOrder('id', 'DESC');
        if ($page != -1) {
            $orders->setCurPage($page);
            $orders->setPageSize($itemsPerPage);
        }
        foreach ($orders->load()->getItems() as $order) {
            $orderArray = array();
            $order = $order->getData();
            $magOrder = Mage::getModel('sales/order')->load($order['order_id']);
            $magArray = $magOrder->getData();
            $url = Mage::helper('adminhtml')->getUrl(
                "adminhtml/sales_order/view",
                array('order_id' => $order['order_id'])
            );
            $orderArray['order_id'] = $magArray['entity_id'];
            $orderArray['fyndiq_orderid'] = $order['fyndiq_orderid'];
            $orderArray['entity_id'] = $magArray['entity_id'];
            $orderArray['price'] = number_format((float)$magArray['base_grand_total'], 2, '.', '');
            $orderArray['total_products'] = intval($magArray['total_qty_ordered']);
            $orderArray['state'] = $magArray['status'];
            $orderArray['created_at'] = date('Y-m-d', strtotime($magArray['created_at']));
            $orderArray['created_at_time'] = date("G:i:s", strtotime($magArray['created_at']));
            $orderArray['link'] = $url;
            $result[] = $orderArray;
        }

        return $result;
    }

    private function getCountryId($countryName)
    {
        $countryId = '';
        $countryCollection = Mage::getModel('directory/country')->getCollection();
        foreach ($countryCollection as $country) {
            if ($countryName == $country->getName()) {
                $countryId = $country->getCountryId();
                break;
            }
        }
        $countryCollection = null;
        return $countryId;
    }

    protected function getRegionHelper()
    {
        if (!class_exists('FyndiqRegionHelper')) {
            require_once dirname(dirname(__FILE__)) . '/includes/FyndiqRegionHelper.php';
        }
    }

    private function getShippingAddress($fyndiqOrder)
    {
        //Shipping / Billing information gather
        //if we have a default shipping address, try gathering its values into variables we need
        $countryId = $this->getCountryId($fyndiqOrder->delivery_country);
        $shippingAddressArray = array(
            'firstname' => $fyndiqOrder->delivery_firstname,
            'lastname' => $fyndiqOrder->delivery_lastname,
            'street' => $fyndiqOrder->delivery_address,
            'city' => $fyndiqOrder->delivery_city,
            'region_id' => '',
            'region' => '',
            'postcode' => $fyndiqOrder->delivery_postalcode,
            'country_id' => $countryId,
            'telephone' => $fyndiqOrder->delivery_phone,
        );

        // Check if country region is required
        $isRequired = Mage::helper('directory')->isRegionRequired($countryId);
        if ($isRequired) {
            // Get and set Region
            if ($countryId != 'DE') {
                throw new Exception(sprintf('Error, region is required for `%s`.', $countryId));
            }

            $this->getRegionHelper();
            $regionCode = FyndiqRegionHelper::codeToRegionCodeDe($fyndiqOrder->delivery_postalcode);

            // Try to deduce the region for Germany
            $region = Mage::getModel('directory/region')->loadByCode($regionCode, $countryId);
            if (is_null($region)) {
                throw new Exception(sprintf(
                    'Error, could not find region `%s` for `%s.`',
                    $regionCode,
                    $fyndiqOrder->delivery_country
                ));
            }
            $shippingAddressArray['region_id'] = $region->getId();
            $shippingAddressArray['region'] = $region->getName();
        }

        return $shippingAddressArray;
    }

    /**
     * Create a order in magento based on Fyndiq Order
     *
     * @param int $storeId
     * @param array $fyndiqOrder
     * @throws Exception
     */
    public function create($storeId, $fyndiqOrder)
    {
        //get customer by mail
        $customer = Mage::getModel('customer/customer');
        $customer->setWebsiteId(Mage::app()->getWebsite()->getId());
        $customer->loadByEmail(self::FYNDIQ_ORDERS_EMAIL);
        if (!$customer->getId()) {
            $customer->setEmail(self::FYNDIQ_ORDERS_EMAIL);
            $customer->setFirstname(self::FYNDIQ_ORDERS_NAME_FIRST);
            $customer->setLastname(self::FYNDIQ_ORDERS_NAME_LAST);
            $customer->setPassword(md5(uniqid(rand(), true)));
            try {
                $customer->save();
                $customer->setConfirmation(null);
                $customer->save();
            } catch (Exception $e) {
                throw new Exception('Error, creating Fyndiq customer: ' . $e->getMessage());
            }
        }
        //Start a new order quote and assign current customer to it.
        $quote = Mage::getModel('sales/quote')->setStoreId(Mage::app('default')->getStore('default')->getId());
        $quote->assignCustomer($customer);
        $quote->setStore(Mage::getModel('core/store')->load($storeId));

        //Adding products to order
        foreach ($fyndiqOrder->order_rows as $row) {
            // get sku of the product
            $sku = $row->sku;

            $id = Mage::getModel('catalog/product')->getResource()->getIdBySku($sku);
            if (!$id) {
                throw new Exception(
                    sprintf(
                        FyndiqTranslation::get('error-import-product-not-found'),
                        $sku,
                        $fyndiqOrder->id
                    )
                );
            }
            $product = Mage::getModel('catalog/product')->load($id);
            //add product to the cart
            $productInfo = array('qty' => $row->quantity);
            $quote->addProduct($product, new Varien_Object($productInfo))->setOriginalCustomPrice($row->unit_price_amount);
        }


        $shippingAddressArray = $this->getShippingAddress($fyndiqOrder);

        //if we have a default billing address, try gathering its values into variables we need
        $billingAddressArray = $shippingAddressArray;

        // Add the address data to the billing address
        $quote->getBillingAddress()->addData($billingAddressArray);

        // Add the adress data to the shipping address
        $shippingAddress = $quote->getShippingAddress()->addData($shippingAddressArray);

        // Collect the shipping rates
        $shippingAddress->setCollectShippingRates(true)->collectShippingRates();

        // Set the shipping method /////////// Here i set my own shipping method
        $shippingAddress->setShippingMethod(self::SHIPPING_METHOD);

        // Set the payment method
        $shippingAddress->setPaymentMethod(self::PAYMENT_METHOD);

        // Set the payment method
        $quote->getPayment()->importData(array('method' => self::PAYMENT_METHOD));

        // Feed quote object into sales model
        $service = Mage::getModel('sales/service_quote', $quote);

        // Submit all orders to MAGE
        $service->submitAll();

        // Setup order object and gather newly entered order
        $order = $service->getOrder();

        // Now set newly entered order's status to complete so customers can enjoy their goods.
        $importStatus = FmConfig::get('import_state', $storeId);
        $order->setStatus($importStatus);

        // Add delivery note as comment
        $comment = sprintf(
            "Fyndiq delivery note: http://fyndiq.se%s \n just copy url and paste in the browser to download the delivery note.",
            $fyndiqOrder->delivery_note
        );
        $order->addStatusHistoryComment($comment);

        //Finally we save our order after setting it's status to complete.
        $order->save();

        //add it to the table for check
        $this->addCheckData($fyndiqOrder->id, $order->getId());
    }

    /**
     * Try to update the order state
     *
     * @param int $orderId
     * @param string $statusId
     * @return bool
     */
    public function updateOrderStatuses($orderId, $statusId)
    {
        $order = Mage::getModel('sales/order')->load(intval($orderId));
        if ($order) {
            //$order->setState(Mage_Sales_Model_Order::STATE_PROCESSING, true);
            $order->setStatus($statusId, true);

            return $order->save();
        }

        return false;
    }

    /**
     * Get Order state name
     * @param $statusId
     * @return mixed
     */
    public function getStatusName($statusId)
    {
        $status = Mage::getModel('sales/order_status')->loadDefaultByState($statusId);

        return $status->getStoreLabel();
    }
}
