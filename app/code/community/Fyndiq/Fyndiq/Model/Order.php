<?php
class Fyndiq_Fyndiq_Model_Order extends Mage_Core_Model_Abstract
{

    public function _construct()
    {
        parent::_construct();
        $this->_init('fyndiq/order');
    }

    public function orderExists($fyndiq_order) {
        $collection = $this->getCollection()
            ->addFieldToFilter('fyndiq_orderid', $fyndiq_order)
            ->getFirstItem();
        if($collection->getId()){
            return true;
        }
        else {
            return false;
        }
    }

    public function addCheckData($fyndiq_orderid, $orderid) {
        $data = array('fyndiq_orderid'=>$fyndiq_orderid,'order_id'=>$orderid);
        $model = $this->setData($data);
        return $model->save()->getId();
    }

    public function create($fyndiq_order) {

        //get customer by mail
        $customer = Mage::getModel("customer/customer");
        $customer->setWebsiteId(Mage::app()->getWebsite()->getId());
        $customer->loadByEmail($fyndiq_order->customer_email); //load customer by email id - See more at: http://www.techdilate.com/code/magento-get-customer-details-by-email-id/#sthash.1wlIxutE.dpuf
        if (!$customer->getId()) {
            $customer->setEmail($fyndiq_order->customer_email);
            $customer->setFirstname($fyndiq_order->delivery_firstname);
            $customer->setLastname($fyndiq_order->delivery_lastname);
            $customer->setPassword(md5(uniqid(rand(), true)));
            try {
                $customer->save();
                $customer->setConfirmation(null);
                $customer->save();
            }
            catch (Exception $ex) {
                Zend_Debug::dump($ex->getMessage());
                echo "ERROR1";
            }

            $delivery_address = array (
                'firstname' => $fyndiq_order->delivery_firstname,
                'lastname' => $fyndiq_order->delivery_lastname,
                'street' => array (
                    '0' => $fyndiq_order->delivery_address,
                ),

                'city' => $fyndiq_order->delivery_city,
                'region_id' => '',
                'region' => '',
                'postcode' => $fyndiq_order->delivery_postalcode,
                'country_id' => 'SE', /* SWEDEN */
                'telephone' => $fyndiq_order->customer_phone,
            );
            $invoice_address = array (
                'firstname' => $fyndiq_order->invoice_firstname,
                'lastname' => $fyndiq_order->invoice_lastname,
                'street' => array (
                    '0' => $fyndiq_order->invoice_address,
                ),

                'city' => $fyndiq_order->invoice_city,
                'region_id' => '',
                'region' => '',
                'postcode' => $fyndiq_order->invoice_postalcode,
                'country_id' => 'SE', /* SWEDEN */
                'telephone' => $fyndiq_order->customer_phone,
            );

            $delivery_addressmodel = Mage::getModel('customer/address');
            $delivery_addressmodel->setData($delivery_address)
                ->setCustomerId($customer->getId())
                ->setIsDefaultBilling('0')
                ->setIsDefaultShipping('1')
                ->setSaveInAddressBook('1');

            $invoice_addressmodel = Mage::getModel('customer/address');
            $invoice_addressmodel->setData($invoice_address)
                ->setCustomerId($customer->getId())
                ->setIsDefaultBilling('1')
                ->setIsDefaultShipping('0')
                ->setSaveInAddressBook('1');

            try {
                $delivery_addressmodel->save();
                $invoice_addressmodel->save();
            }
            catch (Exception $ex) {
                //Zend_Debug::dump($ex->getMessage());
            }
        }


        //Start a new order quote and assign current customer to it.
        $quote = Mage::getModel('sales/quote')->setStoreId(Mage::app('default')->getStore('default')->getId());
        $quote->assignCustomer($customer);


        //TODO: add product from fyndiq here
        $_product = Mage::getModel('catalog/product')->load(1); //getting product model
        // Add the product with the product options
        $quote->addProduct($_product);

        //$product_id = $_POST['id']; //id of product we want to purchase that was posted to this script

        //Shipping / Billing information gather
        $firstName = $customer->getFirstname(); //get customers first name
        $lastName = $customer->getLastname(); //get customers last name
        $billingaddressId = $customer->getDefaultBilling(); //get default billing address from session
        $shippingAddressId = $customer->getDefaultShipping(); //get default billing address from session

        //if we have a default billing address, try gathering its values into variables we need
        if ($billingaddressId){
            $address = Mage::getModel('customer/address')->load($billingaddressId);
            $billingAddressArray = array(
                'firstname' => $firstName,
                'lastname' => $lastName,
                'street' => $address->getStreet(),
                'city' => $address->getCity(),
                'postcode'=>$address->getPostcode(),
                'telephone' => $address->getTelephone(),
                'country_id' => $address->getCountryId(),
                'region_id' => ""
            );
            // otherwise, setup some custom entry values so we don't have a bunch of confusing un-descriptive orders in the backend
        }else{
            $billingAddressArray = array(
                'firstname' => $firstName,
                'lastname' => $lastName,
                'street' => 'No address',
                'city' => 'No City',
                'postcode'=>'No post code',
                'telephone' => 'No phone',
                'country_id' => 'No country',
                'region_id' => "No region"
            );
        }

        //if we have a default shipping address, try gathering its values into variables we need
        if ($shippingAddressId){
            $address = Mage::getModel('customer/address')->load($shippingAddressId);
            $shippingAddressArray = array(
                'firstname' => $firstName,
                'lastname' => $lastName,
                'street' => $address->getStreet(),
                'city' => $address->getCity(),
                'postcode'=>$address->getPostcode(),
                'telephone' => $address->getTelephone(),
                'country_id' => $address->getCountryId(),
                'region_id' => ""
            );
            // otherwise, setup some custom entry values so we don't have a bunch of confusing un-descriptive orders in the backend
        }else{
            $shippingAddressArray = array(
                'firstname' => $firstName,
                'lastname' => $lastName,
                'street' => 'No address',
                'city' => 'No City',
                'postcode'=>'No post code',
                'telephone' => 'No phone',
                'country_id' => 'No country',
                'region_id' => "No region"
            );
        }


        //Set shipping objects rates to true to then gather any accrued shipping method costs a product main contain
        //$shippingAddress->setCollectShippingRates(true)->collectShippingRates()->
        //    setShippingMethod('flatrate_flatrate');

        //Set quote object's payment method to check / money order to allow progromatic entries of orders
        //(kind of hard to programmatically guess and enter a customer's credit/debit cart so only money orders are allowed to be entered via api)
        //$quote->getPayment()->importData(array('method' => 'checkmo'));

        // Set the payment method
        $paymentMethod = 'checkmo';

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
        $order->setStatus('complete');

        //Finally we save our order after setting it's status to complete.
        $order->save();

        //add it to the table for check
        $this->addCheckData($fyndiq_order->id,$order->getIncrementId());
    }
}