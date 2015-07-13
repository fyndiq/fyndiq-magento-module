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

    const BATCH_SIZE = 30;

    const UNKNOWN = 'Unknown';

    private $productModel = null;
    private $categoryModel = null;
    private $stockModel = null;
    private $taxCalculationModel = null;
    private $imageHelper = null;

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
        $store = Mage::getModel('core/store')->load($storeId);
        $fileName = FmConfig::getFeedPath($storeId);

        FyndiqUtils::debug('$fileName', $fileName);
        $file = fopen($fileName, 'w+');

        if (!$file) {
            FyndiqUtils::debug('Cannot create file: ' . $fileName);

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

        FyndiqUtils::debug('$idsToExport', $idsToExport);
        FyndiqUtils::debug('$productInfo', $productInfo);

        //Initialize models here so it saves memory.
        if (!$this->productModel) {
            $this->productModel = Mage::getModel('catalog/product');
        }

        $batches = array_chunk($idsToExport, self::BATCH_SIZE);
        foreach ($batches as $batchIds) {
            FyndiqUtils::debug('MEMORY', memory_get_usage(true));
            $productsToExport = $this->productModel->getCollection()
                ->addAttributeToSelect('*')
                ->addStoreFilter($storeId)
                ->addAttributeToFilter(
                    'entity_id',
                    array('in' => $batchIds)
                )->load();

            foreach ($productsToExport as $magProduct) {
                $parent_id = $magProduct->getId();
                FyndiqUtils::debug('$magProduct->getTypeId()', $magProduct->getTypeId());
                if ($feedWriter->addProduct($this->getProduct($magProduct, $productInfo[$parent_id], $store))
                    && $magProduct->getTypeId() != 'simple'
                ) {
                    $conf = Mage::getModel('catalog/product_type_configurable')->setProduct($magProduct);
                    $simpleCollection = $conf->getUsedProductCollection()
                        ->addAttributeToSelect('*')
                        ->addFilterByRequiredOptions()
                        ->getItems();
                    foreach ($simpleCollection as $simpleProduct) {
                        $feedWriter->addProduct($this->getProduct($simpleProduct, $productInfo[$parent_id], $store));
                    }
                }
            }
            $productsToExport->clear();
        }
        return $feedWriter->write();
    }

    /**
     * Get tax rate
     *
     * @param $product
     * @return mixed
     */
    private function getTaxRate($product, $store)
    {
        if (!$this->taxCalculationModel) {
            $this->taxCalculationModel = Mage::getModel('tax/calculation');
        }
        $taxClassId = $product->getTaxClassId();
        $request = $this->taxCalculationModel->getRateRequest(null, null, null, $store);
        return $this->taxCalculationModel->getRate($request->setProductClassId($taxClassId));
    }

    /**
     * Get images columns for product
     *
     * @param  int $productId
     * @param  object $magProduct
     * @param  object $productModel
     * @return array
     */
    protected function getImages($productId, $magProduct)
    {
        $result = array();
        $urls = array();
        $imageId = 1;
        $imageHelper = Mage::helper('catalog/image');

        $images = Mage::getModel('catalog/product')->load($productId)->getMediaGalleryImages();
        if (count($images)) {
            // Get gallery
            foreach ($images as $image) {
                $urls[] = (string)$imageHelper->init($magProduct, 'image', $image->getFile());
            }
        } else {
            // Fallback to main image
            $urls[] = $magProduct->getImageUrl();
        }
        unset($images);

        foreach ($urls as $url) {
            $result['product-image-' . $imageId . '-url'] = $url;
            $result['product-image-' . $imageId . '-identifier'] = substr(md5($url), 0, 10);
            $imageId++;
        }
        return $result;
    }

    /**
     * Get product information
     *
     * @param array $magProduct
     * @param array $productInfo
     * @return array
     */
    private function getProduct($magProduct, $productInfo, $store)
    {
        FyndiqUtils::debug('$productInfo', $productInfo);
        FyndiqUtils::debug('$magProduct', $magProduct->getData());
        //Initialize models here so it saves memory.
        if (!$this->productModel) {
            $this->productModel = Mage::getModel('catalog/product');
        }
        if (!$this->categoryModel) {
            $this->categoryModel = Mage::getModel('catalog/category');
        }
        if (!$this->stockModel) {
            $this->stockModel = Mage::getModel('cataloginventory/stock_item');
        }

        $feedProduct = array();
        $magArray = $magProduct->getData();

        // Setting the data
        if (!isset($magArray['price'])) {
            FyndiqUtils::debug('No price is set');

            return $feedProduct;
        }

        $feedProduct['product-id'] = $productInfo['id'];
        $feedProduct['product-title'] = $magArray['name'];
        $description = $magProduct->getDescription();
        if (is_null($description)) {
            $description = $magProduct->getShortDescription();
        }

        $feedProduct['product-description'] = $description;

        $discount = $productInfo['exported_price_percentage'];
        $price = FyndiqUtils::getFyndiqPrice($magArray['price'], $discount);
        $feedProduct['product-price'] = FyndiqUtils::formatPrice($price);
        $feedProduct['product-vat-percent'] = $this->getTaxRate($magProduct, $store);
        $feedProduct['product-oldprice'] = FyndiqUtils::formatPrice($magArray['price']);
        $feedProduct['product-market'] = Mage::getStoreConfig('general/country/default');
        $feedProduct['product-currency'] = $store->getCurrentCurrencyCode();

        $brand = $magProduct->getAttributeText('manufacturer');
        $feedProduct['product-brand-name'] = $brand ? $brand : self::UNKNOWN;

        // Category
        $categoryIds = $magProduct->getCategoryIds();

        if (count($categoryIds) > 0) {
            $firstCategoryId = array_shift($categoryIds);
            $firstCategory = $this->categoryModel->load($firstCategoryId);

            $feedProduct['product-category-name'] = $firstCategory->getName();
            $feedProduct['product-category-id'] = $firstCategoryId;
        }

        // Images
        $images = $this->getImages($magArray['entity_id'], $magProduct);
        $feedProduct = array_merge($feedProduct, $images);

        if ($magArray['type_id'] == 'simple') {
            $qtyStock = $this->stockModel->loadByProduct($magProduct->getId())->getQty();
            $feedProduct['article-quantity'] = intval($qtyStock) < 0 ? 0 : intval($qtyStock);

            $feedProduct['article-location'] = self::UNKNOWN;
            $feedProduct['article-sku'] = $magProduct->getSKU();
            $feedProduct['article-name'] = $magArray['name'];

            $productParent = $productInfo['product_id'];
            if ($productParent) {
                $parentModel = $this->productModel->load($productParent);
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

                        $feedProduct['article-property-' . $attrId . '-name'] = $attrCode;
                        $feedProduct['article-property-' . $attrId . '-value'] = $value[0];
                        $tags[] = $attrCode . ': ' . $value[0];
                        $attrId++;
                    }
                    $feedProduct['article-name'] = implode(', ', $tags);
                }
            }

            // We're done
            FyndiqUtils::debug('PRODUCT $feedProduct', $feedProduct);

            return $feedProduct;
        }

        //Get child articles
        $conf = Mage::getModel('catalog/product_type_configurable')->setProduct($magProduct);
        $simpleCollection = $conf->getUsedProductCollection()->addAttributeToSelect('*')
            ->addFilterByRequiredOptions()->getItems();
        FyndiqUtils::debug('$simpleCollection', $simpleCollection);
        //Get first article to the product.
        $firstProduct = array_shift($simpleCollection);
        if ($firstProduct == null) {
            $firstProduct = $magProduct;
        }
        $qtyStock = $this->stockModel->loadByProduct($firstProduct->getId())->getQty();

        $feedProduct['article-quantity'] = intval($qtyStock) < 0 ? 0 : intval($qtyStock);

        // Images
        $images = $this->getImages($firstProduct->getId(), $firstProduct, $this->productModel);
        $feedProduct = array_merge($feedProduct, $images);

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

            $feedProduct['article-property-' . $attrId . '-name'] = $attrCode;
            $feedProduct['article-property-' . $attrId . '-value'] = $value[0];
            $tags[] = $attrCode . ': ' . $value[0];
            $attrId++;
        }
        $feedProduct['article-name'] = substr(implode(', ', $tags), 0, 30);

        FyndiqUtils::debug('COMBINATION $feedProduct', $feedProduct);

        return $feedProduct;
    }

    public function handle_fyndiqConfigChangedSection()
    {
        $storeId = $this->getStoreId();
        if (FmConfig::get('username', $storeId) !== ''
            && FmConfig::get('apikey', $storeId) !== ''
        ) {
            // Generate and save token
            $pingToken = Mage::helper('core')->uniqHash();
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
                            '_query' => array(
                                'event' => 'order_created',
                            )
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
}
