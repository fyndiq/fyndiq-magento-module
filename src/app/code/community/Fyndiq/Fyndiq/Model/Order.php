<?php

class Fyndiq_Fyndiq_Model_Order
{
    const FYNDIQ_ORDERS_EMAIL = 'orders_no_reply@fyndiq.com';
    const FYNDIQ_ORDERS_NAME_FIRST = 'Fyndiq';
    const FYNDIQ_ORDERS_NAME_LAST = 'Orders';

    const DEFAULT_PAYMENT_METHOD = 'fyndiq_fyndiq';
    const DEFAULT_SHIPMENT_METHOD = 'fyndiq_fyndiq_standard';

    const ORDERS_ENABLED = 0;
    const ORDERS_DISABLED = 1;

    private $configModel = null;

    public function __construct()
    {
        $this->configModel = Mage::getModel('fyndiq/config');
    }

    /**
     * Check if order already exists.
     *
     * @param array $fyndiqOrder
     *
     * @return bool
     */
    public function orderExists($fyndiqOrderId)
    {
        $collection = Mage::getModel('sales/order')
            ->getCollection()
            ->addFieldToFilter('fyndiq_order_id', $fyndiqOrderId);
        return $collection->getSize() > 0;
    }

    /**
     * Loading Imported orders.
     *
     * @param $page
     *
     * @return array
     */
    public function getFydniqOrderIds($orderIds)
    {
        $result = array();
        $orders = Mage::getModel('sales/order')
            ->getCollection()
            ->addFieldToFilter('fyndiq_order_id', array('neq' => 'NULL'))
            ->addAttributeToFilter(
                'entity_id',
                array('in' => $orderIds)
            );
        $orders->load();
        foreach ($orders as $order) {
            $result[] = $order->getData('fyndiq_order_id');
        }
        return $result;
    }

    private function getShippingAddress($fyndiqOrder)
    {
        //Shipping / Billing information gather
        //if we have a default shipping address, try gathering its values into variables we need
        $shippingAddressArray = array(
            'firstname' => $fyndiqOrder->delivery_firstname,
            'lastname' => $fyndiqOrder->delivery_lastname,
            'street' => $fyndiqOrder->delivery_address,
            'city' => $fyndiqOrder->delivery_city,
            'region_id' => '',
            'region' => '',
            'postcode' => $fyndiqOrder->delivery_postalcode,
            'country_id' => $fyndiqOrder->delivery_country_code,
            'telephone' => $fyndiqOrder->delivery_phone,
        );
        if ($fyndiqOrder->delivery_co) {
            $shippingAddressArray['street'] .= "\n" . Mage::helper('fyndiq_fyndiq')->__('c/o') . ' ' . $fyndiqOrder->delivery_co;
        }

        // Check if country region is required
        $isRequired = Mage::helper('directory')->isRegionRequired($fyndiqOrder->delivery_country_code);
        if ($isRequired) {
            $regionHelper = Mage::helper('region');
            switch ($fyndiqOrder->delivery_country_code) {
                case 'DE':
                    $regionCode = $regionHelper->codeToRegionCode(
                        $fyndiqOrder->delivery_postalcode,
                        Fyndiq_Fyndiq_Helper_Region_Data::CODE_DE
                    );

                    // Try to deduce the region for Germany
                    $region = Mage::getModel('directory/region')->loadByCode(
                        $regionCode,
                        $fyndiqOrder->delivery_country_code
                    );
                    if (is_null($region)) {
                        throw new Exception(sprintf(
                            Mage::helper('fyndiq_fyndiq')->__('Error, could not find region `%s` for `%s.`'),
                            $regionCode,
                            $fyndiqOrder->delivery_country
                        ));
                    }
                    $shippingAddressArray['region_id'] = $region->getId();
                    $shippingAddressArray['region'] = $region->getName();
                    break;
                case 'SE':
                    $regionCode = $regionHelper->codeToRegionCode(
                        $fyndiqOrder->delivery_postalcode,
                        Fyndiq_Fyndiq_Helper_Region_Data::CODE_SE
                    );
                    $regionName = $regionHelper->getRegionName(
                        $regionCode,
                        Fyndiq_Fyndiq_Helper_Region_Data::CODE_SE
                    );
                    $shippingAddressArray['region'] = $regionName;
                    break;
                default:
                    throw new Exception(sprintf(
                        Mage::helper('fyndiq_fyndiq')->__('Error, region is required for `%s`.'),
                        $fyndiqOrder->delivery_country_code
                    ));
            }
        }
        return $shippingAddressArray;
    }

    /**
     * Create a order in Magento based on Fyndiq Order.
     *
     * @param int   $storeId
     * @param array $fyndiqOrder
     *
     * @throws Exception
     */
    public function create($storeId, $fyndiqOrder, $reservationId = false)
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
                throw new Exception(
                    Mage::helper('fyndiq_fyndiq')->__('Error, creating Fyndiq customer:') .' '.$e->getMessage()
                );
            }
        }

        //Start a new order quote and assign current customer to it.
        $quote = Mage::getModel('sales/quote')->setStoreId($storeId);
        $quote->assignCustomer($customer);
        $quote->setStore(Mage::getModel('core/store')->load($storeId));

        //The currency
        $currency = null;

        //Adding products to order
        foreach ($fyndiqOrder->order_rows as $row) {
            // get sku of the product
            $sku = $row->sku;
            if (is_null($currency)) {
                $currency = $row->unit_price_currency;
            }

            $id = Mage::getModel('catalog/product')->getResource()->getIdBySku($sku);
            if (!$id) {
                throw new Exception(
                    sprintf(
                        Mage::helper('fyndiq_fyndiq')->__('Product with SKU "%s", from order #%d cannot be found.'),
                        $sku,
                        $fyndiqOrder->id
                    )
                );
            }
            $product = Mage::getModel('catalog/product')->load($id);

            //Set price minus VAT:
            $price = $row->unit_price_amount;
            if (!Mage::helper('tax')->priceIncludesTax()) {
                $price = $row->unit_price_amount / ((100+intval($row->vat_percent)) / 100);
            }

            //add product to the cart
            $productInfo = array('qty' => $row->quantity);
            $item = $quote->addProduct($product, new Varien_Object($productInfo));
            if (!is_object($item)) {
                throw new Exception($item);
            }
            $item->setOriginalCustomPrice($price);
        }

        $shippingAddressArray = $this->getShippingAddress($fyndiqOrder);

        //if we have a default billing address, try gathering its values into variables we need
        $billingAddressArray = $shippingAddressArray;

        // Ignore billing address validation
        $quote->getBillingAddress()->setShouldIgnoreValidation(true);

        // Add the address data to the billing address
        $quote->getBillingAddress()->addData($billingAddressArray);

        // Set the correct currency for order
        $quote->setBaseCurrencyCode($currency);
        $quote->setQuoteCurrencyCode($currency);

        // Ignore shipping address validation
        $quote->getShippingAddress()->setShouldIgnoreValidation(true);

        // Add the address data to the shipping address
        $shippingAddress = $quote->getShippingAddress()->addData($shippingAddressArray);

        // Collect the shipping rates
        $shippingAddress->setCollectShippingRates(true)->collectShippingRates();

        $shipmentMethod = trim($this->configModel->get('fyndiq/fyndiq_group/fyndiq_shipment_method', $storeId));
        $shipmentMethod = $shipmentMethod ? $shipmentMethod : self::DEFAULT_SHIPMENT_METHOD;

        // Set the shipping method
        $shippingAddress->setShippingMethod($shipmentMethod);

        $paymentMethod = $this->configModel->get('fyndiq/fyndiq_group/fyndiq_payment_method', $storeId);
        $paymentMethod = $paymentMethod ? $paymentMethod : self::DEFAULT_PAYMENT_METHOD;

        // Set the payment method
        $shippingAddress->setPaymentMethod($paymentMethod);

        // Set the payment method
        $quote->getPayment()->importData(array('method' => $paymentMethod));

        // Feed quote object into sales model
        $service = Mage::getModel('sales/service_quote', $quote);

        // Submit all orders to MAGE
        $service->submitAll();

        // Setup order object and gather newly entered order
        $order = $service->getOrder();

        // Now set newly entered order's status to complete so customers can enjoy their goods.
        $importStatus = $this->configModel->get('fyndiq/fyndiq_group/import_state', $storeId);
        $order->setStatus($importStatus);

        // Add fyndiqOrder id as comment
        $comment = sprintf(
            Mage::helper('fyndiq_fyndiq')->__('Fyndiq order id: %s'),
            $fyndiqOrder->id
        );
        $order->addStatusHistoryComment($comment);

        // Add delivery note as comment
        $comment = sprintf(
            Mage::helper('fyndiq_fyndiq')->__('Fyndiq delivery note: %s'),
            $fyndiqOrder->delivery_note
        );
        $comment .= PHP_EOL;
        $comment .= Mage::helper('fyndiq_fyndiq')->__(
            'Copy the URL and paste it in the browser to download the delivery note.'
        );
        $order->addStatusHistoryComment($comment);

        $order->setData('fyndiq_order_id', $fyndiqOrder->id);

        //Finally we save our order after setting it's status to complete.
        $order->save();
    }
}
