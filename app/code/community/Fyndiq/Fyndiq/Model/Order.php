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

        //$product_id = $_POST['id']; //id of product we want to purchase that was posted to this script

        //Shipping / Billing information gather
        $firstName = $customer->getFirstname(); //get customers first name
        $lastName = $customer->getLastname(); //get customers last name
        $customerAddressId = $customer->getDefaultBilling(); //get default billing address from session

        //if we have a default billing addreess, try gathering its values into variables we need
        if ($customerAddressId){
            $address = Mage::getModel('customer/address')->load($customerAddressId);
            $street = $address->getStreet();
            $city = $address->getCity();
            $postcode = $address->getPostcode();
            $phoneNumber = $address->getTelephone();
            $countryId = $address->getCountryId();
            $regionId = "";
            // otherwise, setup some custom entry values so we don't have a bunch of confusing un-descriptive orders in the backend
        }else{
            $address = 'No address';
            $street = 'No street';
            $city = 'No City';
            $postcode = 'No post code';
            $phoneNumber = 'No phone';
            $countryId = 'No country';
            $regionId = 'No region';
        }

        //Start a new order quote and assign current customer to it.
        $quote = Mage::getModel('sales/quote')->setStoreId(Mage::app('default')->getStore('default')->getId());
        $quote->assignCustomer($customer);


        $_product = Mage::getModel('catalog/product')->load(1); //getting product model

        // Add the product with the product options
        $quote->addProduct($_product);

        //Low lets setup a shipping / billing array of current customer's session
        $addressData = array(
            'firstname' => $firstName,
            'lastname' => $lastName,
            'street' => $street,
            'city' => $city,
            'postcode'=>$postcode,
            'telephone' => $phoneNumber,
            'country_id' => $countryId,
            'region_id' => $regionId
        );
        //Add address array to both billing AND shipping address objects.
        //$billingAddress = $quote->getBillingAddress()->addData($addressData);
        $shipping = $customer->getDefaultShippingAddress();
        $shippingAddress = Mage::getModel('sales/order_address')
            ->setStoreId(Mage::app()->getWebsite()->getId())
            ->setAddressType(Mage_Sales_Model_Quote_Address::TYPE_SHIPPING)
            ->setCustomerId($customer->getId())
            ->setCustomerAddressId($customer->getDefaultShipping())
            ->setCustomer_address_id($shipping->getEntityId())
            ->setPrefix($shipping->getPrefix())
            ->setFirstname($shipping->getFirstname())
            ->setMiddlename($shipping->getMiddlename())
            ->setLastname($shipping->getLastname())
            ->setSuffix($shipping->getSuffix())
            ->setCompany($shipping->getCompany())
            ->setStreet($shipping->getStreet())
            ->setCity($shipping->getCity())
            ->setCountry_id($shipping->getCountryId())
            ->setRegion($shipping->getRegion())
            ->setRegion_id($shipping->getRegionId())
            ->setPostcode($shipping->getPostcode())
            ->setTelephone($shipping->getTelephone())
            ->setFax($shipping->getFax());

        $delivery = $customer->getDefaultShippingAddress();
        $deliveryAddress = Mage::getModel('sales/order_address')
            ->setStoreId(Mage::app()->getWebsite()->getId())
            ->setAddressType(Mage_Sales_Model_Quote_Address::TYPE_SHIPPING)
            ->setCustomerId($customer->getId())
            ->setCustomerAddressId($customer->getDefaultDelivery())
            ->setCustomer_address_id($delivery->getEntityId())
            ->setPrefix($delivery->getPrefix())
            ->setFirstname($delivery->getFirstname())
            ->setMiddlename($delivery->getMiddlename())
            ->setLastname($delivery->getLastname())
            ->setSuffix($delivery->getSuffix())
            ->setCompany($delivery->getCompany())
            ->setStreet($delivery->getStreet())
            ->setCity($delivery->getCity())
            ->setCountry_id($delivery->getCountryId())
            ->setRegion($delivery->getRegion())
            ->setRegion_id($delivery->getRegionId())
            ->setPostcode($delivery->getPostcode())
            ->setTelephone($delivery->getTelephone())
            ->setFax($delivery->getFax());

        //Set shipping objects rates to true to then gather any accrued shipping method costs a product main contain
        //$shippingAddress->setCollectShippingRates(true)->collectShippingRates()->
        //    setShippingMethod('flatrate_flatrate');

        //Set quote object's payment method to check / money order to allow progromatic entries of orders
        //(kind of hard to programmatically guess and enter a customer's credit/debit cart so only money orders are allowed to be entered via api)
        //$quote->getPayment()->importData(array('method' => 'checkmo'));

        // Set the payment method
        $paymentMethod = 'checkmo';

        // Set the shipping method
        $shippingMethod = 'flatrate_flatrate';

        // Add the address data to the billing address
        $billingAddress  = $quote->getBillingAddress()->addData($addressData);

        // Add the adress data to the shipping address
        $shippingAddress = $quote->getShippingAddress()->addData($addressData);

        // Collect the shipping rates
        $shippingAddress->setCollectShippingRates(true)->collectShippingRates();

        // Set the shipping method /////////// Here i set my own shipping method
        $shippingAddress->setShippingMethod($shippingMethod);
        $quote->getShippingAddress()->setFreeShipping(true);

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
    }
}