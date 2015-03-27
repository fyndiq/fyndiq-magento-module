<?php

class Fyndiq_Fyndiq_Model_Order extends Mage_Core_Model_Abstract
{

    const FYNDIQ_ORDERS_EMAIL = 'info@fyndiq.se';
    const FYNDIQ_ORDERS_NAME_FIRST = 'Fyndiq';
    const FYNDIQ_ORDERS_NAME_LAST = 'Orders';

    public function _construct()
    {
        parent::_construct();
        $this->_init('fyndiq/order');
    }

    /**
     * Check if order already exists
     *
     * @param $fyndiq_order
     * @return bool
     */
    public function orderExists($fyndiq_order)
    {
        $collection = $this->getCollection()
            ->addFieldToFilter('fyndiq_orderid', $fyndiq_order)
            ->getFirstItem();
        if ($collection->getId()) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Add to check table to check if order already exists.
     *
     * @param $fyndiq_orderid
     * @param $orderid
     * @return mixed
     */
    public function addCheckData($fyndiq_orderid, $orderid)
    {
        $data = array('fyndiq_orderid' => $fyndiq_orderid, 'order_id' => $orderid);
        $model = $this->setData($data);

        return $model->save()->getId();
    }


    /**
     * Loading Imported orders
     * @param $page
     * @return array
     */
    public function getImportedOrders($page = -1)
    {
        $result = array();
        $orders = $this->getCollection()->setOrder('id', 'DESC');
        if($page != -1) {
            $orders->setCurPage($page);
            // TODO: this should be parameter
            $orders->setPageSize(10);
        }
        $orders = $orders->load()->getItems();
        foreach($orders as $order){
            $orderArray = array();
            $order = $order->getData();
            $magOrder = Mage::getModel('sales/order')->load($order['order_id']);
            $magArray = $magOrder->getData();
            $url = Mage::helper('adminhtml')->getUrl("adminhtml/sales_order/view", array('order_id'=>$order['order_id']));
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

    private function getDeliveryCountry($countryName) {
        switch ($countryName) {
            case 'Germany': return 'DE';
            default: return 'SE';
        }
    }

    /**
     * Create a order in magento based on Fyndiq Order
     *
     * @param $fyndiq_order
     * @throws Exception
     */
    public function create($fyndiq_order)
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

        //Adding products to order
        $articles = $fyndiq_order->order_rows;
        foreach ($articles as $row) {

            // get sku of the product
            $sku = $row->sku;

            $id = Mage::getModel('catalog/product')->getResource()->getIdBySku($sku);
            if($id != false) {
                $_product = Mage::getModel('catalog/product')->load($id);
                //add product to the cart
                $product_info = array('qty' => $row->quantity);
                $quote->addProduct($_product, new Varien_Object($product_info));
            }
        }
        if(count($quote->getAllItems()) === 0) {
            throw new Exception('Couldn\'t find product for order #' . $fyndiq_order->id);
        }

        //Shipping / Billing information gather

        //if we have a default billing address, try gathering its values into variables we need
        $billingAddressArray = array(
                'firstname' => $fyndiq_order->delivery_firstname,
                'lastname' => $fyndiq_order->delivery_lastname,
                'street' => $fyndiq_order->delivery_address,
                'city' => $fyndiq_order->delivery_city,
                'region_id' => '',
                'region' => '',
                'postcode' => $fyndiq_order->delivery_postalcode,
                'country_id' => $this->getDeliveryCountry($fyndiq_order->delivery_country),
                'telephone' => $fyndiq_order->delivery_phone,
        );

        //if we have a default shipping address, try gathering its values into variables we need
        $shippingAddressArray = array(
                'firstname' => $fyndiq_order->delivery_firstname,
                'lastname' => $fyndiq_order->delivery_lastname,
                'street' => $fyndiq_order->delivery_address,
                'city' => $fyndiq_order->delivery_city,
                'region_id' => '',
                'region' => '',
                'postcode' => $fyndiq_order->delivery_postalcode,
                'country_id' => $this->getDeliveryCountry($fyndiq_order->delivery_country),
                'telephone' => $fyndiq_order->delivery_phone,
        );

        // Set the payment method
        $paymentMethod = 'fyndiq_fyndiq';

        // Set the shipping method
        $shippingMethod = 'fyndiq_fyndiq';

        // Add the address data to the billing address
        $quote->getBillingAddress()->addData($billingAddressArray);

        // Add the adress data to the shipping address
        $shippingAddress = $quote->getShippingAddress()->addData($shippingAddressArray);

        // Collect the shipping rates
        $shippingAddress->setCollectShippingRates(true)->collectShippingRates();

        // Set the shipping method /////////// Here i set my own shipping method
        $shippingAddress->setShippingMethod($shippingMethod);

        // Set the payment method
        $shippingAddress->setPaymentMethod($paymentMethod);

        // Set the payment method
        $quote->getPayment()->importData(array('method' => $paymentMethod));


        //Feed quote object into sales model
        $service = Mage::getModel('sales/service_quote', $quote);

        //submit all orders to MAGE
        $service->submitAll();

        //Setup order object and gather newly entered order
        $order = $service->getOrder();

        //Now set newly entered order's status to complete so customers can enjoy their goods.
        $importStatus = FmConfig::get('import_state');
        $order->setStatus($importStatus);

        //Add delivery note as comment
        $comment = "Fyndiq delivery note: http://fyndiq.se" . $fyndiq_order->delivery_note . " \n just copy url and paste in the browser to download the delivery note.";
        $order->addStatusHistoryComment($comment);

        //Finally we save our order after setting it's status to complete.
        $order->save();

        //add it to the table for check
        $this->addCheckData($fyndiq_order->id, $order->getId());
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
