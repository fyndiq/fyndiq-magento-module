<?php

class Fyndiq_Fyndiq_Model_Order extends Mage_Core_Model_Abstract
{

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
        $return_array = array();
        $orders = $this->getCollection()->setOrder('id', 'DESC');
        if($page != -1) {
            $orders->setCurPage($page);
            $orders->setPageSize(10);
        }
        $orders = $orders->load()->getItems();
        foreach($orders as $order){
            $order = $order->getData();
            $magorder = Mage::getModel('sales/order')->load($order["order_id"]);
            $magarray = $magorder->getData();
            $magarray["fyndiq_order"] = $order["fyndiq_orderid"];
            $return_array[] = $magarray;
        }
        return $return_array;
    }

    /**
     * Create a order in magento based on Fyndiq Order
     *
     * @param $fyndiq_order
     */
    public function create($fyndiq_order)
    {

        //get customer by mail
        $customer = Mage::getModel("customer/customer");
        $customer->setWebsiteId(Mage::app()->getWebsite()->getId());
        $customer->loadByEmail("info@fyndiq.se");
        if (!$customer->getId()) {
            $customer->setEmail("info@fyndiq.se");
            $customer->setFirstname("Fyndiq");
            $customer->setLastname("Orders");
            $customer->setPassword(md5(uniqid(rand(), true)));
            try {
                $customer->save();
                $customer->setConfirmation(null);
                $customer->save();
            } catch (Exception $ex) {
                Zend_Debug::dump($ex->getMessage());
                echo "ERROR1";
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


        //Shipping / Billing information gather

        //if we have a default billing address, try gathering its values into variables we need
        $billingAddressArray = array(
                'firstname' => $fyndiq_order->invoice_firstname,
                'lastname' => $fyndiq_order->invoice_lastname,
                'street' => $fyndiq_order->invoice_address,
                'city' => $fyndiq_order->invoice_city,
                'region_id' => '',
                'region' => '',
                'postcode' => $fyndiq_order->invoice_postalcode,
                'country_id' => 'SE', /* SWEDEN */
                'telephone' => $fyndiq_order->invoice_phone,
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
                'country_id' => 'SE', /* SWEDEN */
                'telephone' => $fyndiq_order->invoice_phone,
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
        //(optional of course, but most would like their orders created this way to be set to complete automagicly)
        $order->setStatus(Mage_Sales_Model_Order::STATE_COMPLETE);


        //Add delivery note as comment
        $comment = "Fyndiq delivery note: http://fyndiq.se" . $fyndiq_order->delivery_note . " \n just copy url and paste in the browser to download the delivery note.";
        $order->addStatusHistoryComment($comment);

        //Finally we save our order after setting it's status to complete.
        $order->save();

        //add it to the table for check
        $this->addCheckData($fyndiq_order->id, $order->getId());
    }
}