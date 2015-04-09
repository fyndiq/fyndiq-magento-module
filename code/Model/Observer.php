<?php
require_once(dirname(dirname(__FILE__)) . '/includes/config.php');
require_once(dirname(dirname(__FILE__)) . '/includes/helpers.php');
require_once(MAGENTO_ROOT . '/fyndiq/shared/src/init.php');

/**
 * Taking care of cron jobs for product feed.
 *
 * @author Håkan Nylén <hakan.nylen@fyndiq.se>
 */
class Fyndiq_Fyndiq_Model_Observer
{

    public function importOrders()
    {
        try {
            $allStoreIds = array_keys(Mage::app()->getStores());
            $time = time();
            foreach ($allStoreIds as $storeId) {
                $this->importOrdersForStore($storeId, $time);
            }
        } catch (Exception $e) {
        }
    }

    public function importOrdersForStore($storeId, $newTime)
    {
        $newDate = date('Y-m-d H:i:s', $newTime);
        $date = false;
        $settingExists = Mage::getModel('fyndiq/setting')->settingExist($storeId, 'order_lastdate');
        if ($settingExists) {
            $date = Mage::getModel('fyndiq/setting')->getSetting($storeId, 'order_lastdate');
        }
        $url = 'orders/' . (empty($date) ? '' : '?min_date=' . urlencode($date['value']));

        $ret = FmHelpers::call_api($storeId, 'GET', $url);
        foreach ($ret['data'] as $order) {
            if (!Mage::getModel('fyndiq/order')->orderExists($order->id)) {
                Mage::getModel('fyndiq/order')->create($storeId, $order);
            }
        }

        if ($settingExists) {
            return Mage::getModel('fyndiq/setting')->updateSetting($storeId, 'order_lastdate', $newDate);
        }
        return Mage::getModel('fyndiq/setting')->saveSetting($storeId, 'order_lastdate', $newDate);
    }


    /**
     * Saving products to the file.
     *
     * @param int $storeId
     * @param bool $print
     */
    public function exportProducts($storeId = 0, $print = true)
    {
        if ($print) {
            print 'Fyndiq :: Saving feed file' . PHP_EOL;
        }
        $this->exportingProducts($storeId);
        if ($print) {
            print 'Fyndiq :: Done saving feed file' . PHP_EOL;
        }

    }

    /**
     * Adding products added for export to the feed file
     *
     * @param $storeId
     * @return bool
     */
    private function exportingProducts($storeId)
    {
        $fileName = FmConfig::getFeedPath($storeId);
        $file = fopen($fileName, 'w+');

        if (!$file) {
            return false;
        }
        $feedWriter = new FyndiqCSVFeedWriter($file);
        $products = Mage::getModel('fyndiq/product')->getCollection()->setOrder('id', 'DESC');
        $products = $products->getItems();
        $idsToExport = array();
        $productInfo = array();
        foreach ($products as $producted) {
            $product = $producted->getData();
            $idsToExport[] = intval($product['product_id']);
            $productInfo[$product['product_id']] = $producted;
        }

        //Initialize models here so it saves memory.
        $productModel = Mage::getModel('catalog/product');

        $productsToExport = $productModel->getCollection()
            ->addAttributeToSelect('*')
            ->addStoreFilter($storeId)
            ->addAttributeToFilter(
                'entity_id',
                array('in' => $idsToExport)
            )->load();

        foreach ($productsToExport as $magProduct) {
            if ($feedWriter->addProduct($this->getProduct($magProduct, $productInfo))
                && $magProduct->getTypeId() != 'simple'
            ) {
                $conf = Mage::getModel('catalog/product_type_configurable')->setProduct($magProduct);
                $simpleCollection = $conf->getUsedProductCollection()
                    ->addAttributeToSelect('*')
                    ->addFilterByRequiredOptions()
                    ->getItems();
                foreach ($simpleCollection as $simpleProduct) {
                    $feedWriter->addProduct($this->getProduct($simpleProduct, $productInfo));
                }
            }
        }

        return $feedWriter->write();
    }


    private function getProduct($magProduct, $productInfo)
    {
        //Initialize models here so it saves memory.
        $productModel = Mage::getModel('catalog/product');
        $categoryModel = Mage::getModel('catalog/category');
        $stockModel = Mage::getModel('cataloginventory/stock_item');
        $imageHelper = Mage::helper('catalog/image');

        $store = Mage::app()->getStore();
        $taxCalculation = Mage::getModel('tax/calculation');
        $magArray = $magProduct->getData();

        $feedProduct = array();

        // Get taxrate
        $request = $taxCalculation->getRateRequest(null, null, null, $store);
        $taxClassId = $magProduct->getTaxClassId();
        $taxPercent = $taxCalculation->getRate($request->setProductClassId($taxClassId));
        // Setting the data
        if (isset($magArray['price'])) {
            $feedProduct['product-id'] = $magArray['entity_id'];

            //Check if product have a parent
            $parent = false;
            if ($magArray['type_id'] == 'simple') {
                $parentIds = Mage::getModel('catalog/product_type_grouped')->getParentIdsByChild(
                    $magArray['entity_id']
                );
                if (!$parentIds) {
                    $parentIds = Mage::getModel('catalog/product_type_configurable')->getParentIdsByChild(
                        $magArray['entity_id']
                    );
                }

                if ($parentIds) {
                    $parent = $parentIds[0];
                }
            }

            if ($parent != false) {
                $feedProduct['product-id'] = $parent;
            }


            //images
            $imageId = 1;
            //trying to get image, if not image will be false
            try {
                $url = $magProduct->getImageUrl();
                $feedProduct['product-image-' . $imageId . '-url'] = strval($url);
                $feedProduct['product-image-' . $imageId . '-identifier'] =
                    substr(md5(strval($url)), 0, 10);
                $imageId++;
            } catch (Exception $e) {

            }
            $images = $productModel->load($magArray['entity_id'])->getMediaGalleryImages();
            if (isset($images)) {
                foreach ($images as $_image) {
                    $url = $imageHelper->init($magProduct, 'image', $_image->getFile());
                    $feedProduct['product-image-' . $imageId . '-url'] = strval($url);
                    $feedProduct['product-image-' . $imageId . '-identifier'] =
                        substr(md5(strval($url)), 0, 10);
                    $imageId++;
                }
            }
            $feedProduct['product-title'] = $magArray['name'];
            $feedProduct['product-description'] = $magProduct->getDescription();


            if ($magArray['type_id'] == 'simple' && isset($productInfo[$magArray['entity_id']])) {
                $discount = $productInfo[$magArray['entity_id']]['exported_price_percentage'];
            } elseif ($magArray['type_id'] == 'simple') {
                if ($parent != false) {
                    $discount = $productInfo[$parent]['exported_price_percentage'];
                }
            } else {
                $discount = $productInfo[$magArray['entity_id']]['exported_price_percentage'];
            }
            $price = FyndiqUtils::getFyndiqPrice($magArray['price'], $discount);
            $feedProduct['product-price'] = number_format((float)$price, 2, '.', '');
            $feedProduct['product-vat-percent'] = $taxPercent;
            $feedProduct['product-oldprice'] = number_format((float)$magArray['price'], 2, '.', '');
            $feedProduct['product-market'] = Mage::getStoreConfig('general/country/default');
            $feedProduct['product-currency'] = Mage::app()->getStore()->getCurrentCurrencyCode();

            // TODO: plan how to fix this brand issue
            $feedProduct['product-brand'] = 'Unknown';
            if ($magProduct->getAttributeText('manufacturer') != '') {
                $feedProduct['product-brand'] = $magProduct->getAttributeText('manufacturer');
            }

            //Category
            $categoryIds = $magProduct->getCategoryIds();

            if (count($categoryIds) > 0) {
                $firstCategoryId = $categoryIds[0];
                $firstCategory = $categoryModel->load($firstCategoryId);

                $feedProduct['product-category-name'] = $firstCategory->getName();
                $feedProduct['product-category-id'] = $firstCategoryId;
            }

            if ($magArray['type_id'] == 'simple') {

                $qtyStock = $stockModel->loadByProduct($magProduct->getId())->getQty();
                $feedProduct['article-quantity'] = intval($qtyStock) < 0 ? 0 : intval($qtyStock);

                // TODO: fix location to something except test
                $feedProduct['article-location'] = 'test';
                $feedProduct['article-sku'] = $magProduct->getSKU();
                if ($parent != false) {
                    $parentModel = $productModel->load($parent);
                    if (method_exists($parentModel->getTypeInstance(), 'getConfigurableAttributes')) {
                        $productAttributeOptions = $parentModel->getTypeInstance()->getConfigurableAttributes();
                        $attrId = 1;
                        $tags = array();
                        foreach ($productAttributeOptions as $productAttribute) {
                            $attrValue = $parentModel->getResource()->getAttribute(
                                $productAttribute->getProductAttribute()->getAttributeCode()
                            )->getFrontend();
                            $attrCode = $productAttribute->getProductAttribute()->getAttributeCode();
                            $value = $attrValue->getValue($magProduct);

                            $feedProduct['article-property-name-' . $attrId] = $attrCode;
                            $feedProduct['article-property-value-' . $attrId] = $value[0];
                            $tags[] = $attrCode . ': ' . $value[0];
                            $attrId++;
                        }
                        $feedProduct['article-name'] = implode(', ', $tags);
                    } else {
                        $feedProduct['article-name'] = $magArray['name'];
                    }
                } else {
                    $feedProduct['article-name'] = $magArray['name'];
                }
            } else {
                //Get child articles
                $conf = Mage::getModel('catalog/product_type_configurable')->setProduct($magProduct);
                $simple_collection = $conf->getUsedProductCollection()->addAttributeToSelect('*')
                    ->addFilterByRequiredOptions()->getItems();
                //Get first article to the product.
                $first_product = array_shift($simple_collection);
                $qtyStock = $stockModel->loadByProduct($first_product->getId())->getQty();

                $feedProduct['article-quantity'] = intval($qtyStock) < 0 ? 0 : intval($qtyStock);

                $images = $productModel->load($first_product->getId())->getMediaGalleryImages();
                if (!empty($images)) {
                    $imageId = 1;
                    foreach ($images as $_image) {
                        $url = $imageHelper->init($first_product, 'image', $_image->getFile());
                        $feed_article['product-image-' . $imageId. '-url'] = strval($url);
                        $feed_article['product-image-' . $imageId . '-identifier'] = substr(md5(strval($url)), 0, 10);
                        $imageId++;
                    }
                }

                // TODO: fix location to something except test
                $feedProduct['article-location'] = 'test';
                $feedProduct['article-sku'] = $first_product->getSKU();
                $productAttributeOptions = $magProduct->getTypeInstance()->getConfigurableAttributes();
                $attrId = 1;
                $tags = array();
                foreach ($productAttributeOptions as $productAttribute) {
                    $attrValue = $magProduct->getResource()->getAttribute(
                        $productAttribute->getProductAttribute()->getAttributeCode()
                    )->getFrontend();
                    $attrCode = $productAttribute->getProductAttribute()->getAttributeCode();
                    $value = $attrValue->getValue($first_product);

                    $feedProduct['article-property-name-' . $attrId] = $attrCode;
                    $feedProduct['article-property-value-' . $attrId] = $value[0];
                    $tags[] = $attrCode . ': ' . $value[0];
                    $attrId++;
                }
                $feedProduct['article-name'] = substr(implode(', ', $tags), 0, 30);
            }
        }

        return $feedProduct;
    }

    public function handle_fyndiqConfigChangedSection()
    {
        $storeId = Mage::app()->getRequest()->getParam('store');
        if (FmConfig::get('username', $storeId) !== ''
            && FmConfig::get('apikey', $storeId) !== ''
        ) {
            $data = array(
                'product_feed_url' => Mage::getUrl(
                        'fyndiq/file/index',
                        array(
                            '_store' => $storeId,
                            '_nosid' => true,
                        )
                    ),
                'notification_url' => Mage::getUrl(
                        'fyndiq/notification/index',
                        array(
                            '_store' => $storeId,
                            '_nosid' => true,
                        )
                    )
            );
            FmHelpers::call_api($storeId, 'PATCH', 'settings/', $data);
        }
    }
}
