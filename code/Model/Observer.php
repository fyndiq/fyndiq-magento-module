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

    const UNKNOWN = 'Unknown';

    public function __construct()
    {
        FyndiqTranslation::init(Mage::app()->getLocale()->getLocaleCode());
    }

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
        $settingExists = Mage::getModel('fyndiq/setting')->settingExist($storeId, 'order_lastdate');

        $orderFetch = new FmOrderFetch($storeId, $settingExists);
        $orderFetch->getAll();

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
        self::debug('$fileName', $fileName);
        $file = fopen($fileName, 'w+');

        if (!$file) {
            self::debug('Cannot create file: ' . $fileName);

            return false;
        }
        $feedWriter = new FyndiqCSVFeedWriter($file);
        $products = Mage::getModel('fyndiq/product')->getCollection()->setOrder('id', 'DESC');
        $products = $products->getItems();
        $idsToExport = array();
        $productInfo = array();
        foreach ($products as $product) {
            $productData = $product->getData();
            $idsToExport[] = intval($productData['product_id']);
            $productInfo[$productData['product_id']] = $productData;
        }
        self::debug('$idsToExport', $idsToExport);
        self::debug('$productInfo', $productInfo);

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
            $parent_id = $magProduct->getId();
            self::debug('$magProduct->getTypeId()', $magProduct->getTypeId());
            if ($feedWriter->addProduct($this->getProduct($magProduct, $productInfo[$parent_id]))
                && $magProduct->getTypeId() != 'simple'
            ) {
                $conf = Mage::getModel('catalog/product_type_configurable')->setProduct($magProduct);
                $simpleCollection = $conf->getUsedProductCollection()
                    ->addAttributeToSelect('*')
                    ->addFilterByRequiredOptions()
                    ->getItems();
                foreach ($simpleCollection as $simpleProduct) {
                    $feedWriter->addProduct($this->getProduct($simpleProduct, $productInfo[$parent_id]));
                }
            }
        }

        return $feedWriter->write();
    }

    /**
     * Get tax rate
     *
     * @param $product
     * @return mixed
     */
    private function getTaxRate($product)
    {
        $store = Mage::app()->getStore();
        $taxCalculation = Mage::getModel('tax/calculation');

        $request = $taxCalculation->getRateRequest(null, null, null, $store);
        $taxClassId = $product->getTaxClassId();

        return $taxCalculation->getRate($request->setProductClassId($taxClassId));
    }

    /**
     * Get product information
     *
     * @param array $magProduct
     * @param array $productInfo
     * @return array
     */
    private function getProduct($magProduct, $productInfo)
    {
        self::debug('$productInfo', $productInfo);
        self::debug('$magProduct', $magProduct->getData());
        //Initialize models here so it saves memory.
        $productModel = Mage::getModel('catalog/product');
        $categoryModel = Mage::getModel('catalog/category');
        $stockModel = Mage::getModel('cataloginventory/stock_item');
        $imageHelper = Mage::helper('catalog/image');

        $feedProduct = array();
        $magArray = $magProduct->getData();

        // Setting the data
        if (!isset($magArray['price'])) {
            self::debug('No price is set');

            return $feedProduct;
        }

        $feedProduct['product-id'] = $productInfo['id'];
        $feedProduct['product-title'] = $magArray['name'];
        $feedProduct['product-description'] = $magProduct->getDescription();

        $discount = $productInfo['exported_price_percentage'];
        $price = FyndiqUtils::getFyndiqPrice($magArray['price'], $discount);
        $feedProduct['product-price'] = FyndiqUtils::formatPrice($price);
        $feedProduct['product-vat-percent'] = $this->getTaxRate($magProduct);
        $feedProduct['product-oldprice'] = FyndiqUtils::formatPrice($magArray['price']);
        $feedProduct['product-market'] = Mage::getStoreConfig('general/country/default');
        $feedProduct['product-currency'] = Mage::app()->getStore()->getCurrentCurrencyCode();

        $brand = $magProduct->getAttributeText('manufacturer');
        $feedProduct['product-brand'] = $brand ? $brand : self::UNKNOWN;

        // Category
        $categoryIds = $magProduct->getCategoryIds();

        if (count($categoryIds) > 0) {
            $firstCategoryId = array_shift($categoryIds);
            $firstCategory = $categoryModel->load($firstCategoryId);

            $feedProduct['product-category-name'] = $firstCategory->getName();
            $feedProduct['product-category-id'] = $firstCategoryId;
        }

        // Images
        $imageId = 1;
        $images = $productModel->load($magArray['entity_id'])->getMediaGalleryImages();
        if (isset($images)) {
            foreach ($images as $image) {
                $url = (string)$imageHelper->init($magProduct, 'image', $image->getFile());
                $feedProduct['product-image-' . $imageId . '-url'] = $url;
                $feedProduct['product-image-' . $imageId . '-identifier'] = substr(md5($url), 0, 10);
                $imageId++;
            }
        }

        if ($magArray['type_id'] == 'simple') {
            $qtyStock = $stockModel->loadByProduct($magProduct->getId())->getQty();
            $feedProduct['article-quantity'] = intval($qtyStock) < 0 ? 0 : intval($qtyStock);

            $feedProduct['article-location'] = self::UNKNOWN;
            $feedProduct['article-sku'] = $magProduct->getSKU();
            $feedProduct['article-name'] = $magArray['name'];

            $productParent = $productInfo['product_id'];
            if ($productParent) {
                $parentModel = $productModel->load($productParent);
                if (method_exists($parentModel->getTypeInstance(), 'getConfigurableAttributes')) {
                    $productAttrOptions = $parentModel->getTypeInstance()->getConfigurableAttributes();
                    $attrId = 1;
                    $tags = array();
                    foreach ($productAttrOptions as $productAttribute) {
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
                }
            }

            // We're done
            self::debug('PRODUCT $feedProduct', $feedProduct);

            return $feedProduct;
        }

        //Get child articles
        $conf = Mage::getModel('catalog/product_type_configurable')->setProduct($magProduct);
        $simpleCollection = $conf->getUsedProductCollection()->addAttributeToSelect('*')
            ->addFilterByRequiredOptions()->getItems();
        self::debug('$simpleCollection', $simpleCollection);
        //Get first article to the product.
        $firstProduct = array_shift($simpleCollection);
        if ($firstProduct == null) {
            $firstProduct = $magProduct;
        }
        $qtyStock = $stockModel->loadByProduct($firstProduct->getId())->getQty();

        $feedProduct['article-quantity'] = intval($qtyStock) < 0 ? 0 : intval($qtyStock);

        $images = $productModel->load($firstProduct->getId())->getMediaGalleryImages();
        if (!empty($images)) {
            $imageId = 1;
            foreach ($images as $image) {
                $url = $imageHelper->init($firstProduct, 'image', $image->getFile());
                $feedProduct['product-image-' . $imageId . '-url'] = strval($url);
                $feedProduct['product-image-' . $imageId . '-identifier'] = substr(md5(strval($url)), 0, 10);
                $imageId++;
            }
        }

        $feedProduct['article-location'] = self::UNKNOWN;
        $feedProduct['article-sku'] = $firstProduct->getSKU();
        $productAttrOptions = $magProduct->getTypeInstance()->getConfigurableAttributes();
        $attrId = 1;
        $tags = array();
        foreach ($productAttrOptions as $productAttribute) {
            $attrValue = $magProduct->getResource()->getAttribute(
                $productAttribute->getProductAttribute()->getAttributeCode()
            )->getFrontend();
            $attrCode = $productAttribute->getProductAttribute()->getAttributeCode();
            $value = $attrValue->getValue($firstProduct);

            $feedProduct['article-property-name-' . $attrId] = $attrCode;
            $feedProduct['article-property-value-' . $attrId] = $value[0];
            $tags[] = $attrCode . ': ' . $value[0];
            $attrId++;
        }
        $feedProduct['article-name'] = substr(implode(', ', $tags), 0, 30);

        self::debug('COMBINATION $feedProduct', $feedProduct);

        return $feedProduct;
    }

    public function handle_fyndiqConfigChangedSection()
    {
        $storeId = $this->getStoreId();
        if (FmConfig::get('username', $storeId) !== ''
            && FmConfig::get('apikey', $storeId) !== ''
        ) {
            // Generate and save token
            $pingToken = Mage::helper('core')->uniqHash();;
            FmConfig::set('ping_token', $pingToken);

            $data = array(
                FyndiqUtils::NAME_PRODUCT_FEED_URL => Mage::getUrl(
                        'fyndiq/file/index/store/' . $storeId,
                        array(
                            '_store' => $storeId,
                            '_nosid' => true,
                        )
                    ),
                FyndiqUtils::NAME_NOTIFICATION_URL => Mage::getUrl(
                        'fyndiq/notification/index/store/' . $storeId,
                        array(
                            '_store' => $storeId,
                            '_nosid' => true,
                        )
                    ),
                FyndiqUtils::NAME_PING_URL => Mage::getUrl(
                        'fyndiq/notification/index/store/' . $storeId,
                        array(
                            '_store' => $storeId,
                            '_nosid' => true,
                            '_query' => array(
                                'event' => 'ping',
                                'token' => $pingToken,
                            ),
                        )
                    )
            );

            return FmHelpers::callApi($storeId, 'PATCH', 'settings/', $data);
        }
        throw new Exception(FyndiqTranslation::get('empty-username-token'));
    }

    public function getStoreId()
    {
        $storeCode = Mage::app()->getRequest()->getParam('store');
        if ($storeCode) {
            return Mage::getModel('core/store')->load($storeCode)->getId();
        }

        return 0;
    }

    public static function debug($name, $var, $justPrint = false)
    {
        if (defined('FYNDIQ_DEBUG') && FYNDIQ_DEBUG) {
            if ($justPrint) {
                echo $name . '<br/ ><pre>' . $var . '</pre><hr/>';

                return;
            }
            var_dump($name, $var);
            echo '<hr/>';
        }
    }
}
