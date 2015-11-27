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
    const CATEGORY_SEPARATOR = ' / ';

    private $productModel = null;
    private $categoryModel = null;
    private $taxCalculationModel = null;
    private $imageHelper = null;
    private $productImages = array();
    private $productMediaConfig = null;
    private $categoryCache = array();
    private $productAttrOptions = null;

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

        Mage::getModel('fyndiq/order')->clearReservations();
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
        try {
            $fileName = FmConfig::getFeedPath($storeId);
            $tempFileName = FyndiqUtils::getTempFilename(dirname($fileName));

            FyndiqUtils::debug('$fileName', $fileName);
            FyndiqUtils::debug('$tempFileName', $tempFileName);

            $file = fopen($tempFileName, 'w+');

            if (!$file) {
                FyndiqUtils::debug('Cannot create file: ' . $tempFileName);
                return false;
            }

            FyndiqUtils::debug('new FyndiqCSVFeedWriter');
            $feedWriter = new FyndiqCSVFeedWriter($file);
            FyndiqUtils::debug('FyndiqCSVFeedWriter::exportingProducts');
            $exportResult = $this->exportingProducts($storeId, $feedWriter);
            FyndiqUtils::debug('Closing file');
            fclose($file);
            if ($exportResult) {
                // File successfully generated
                FyndiqUtils::debug('Moving file', $tempFileName, $fileName);
                return FyndiqUtils::moveFile($tempFileName, $fileName);
            }
            // Something wrong happened, clean the file
            FyndiqUtils::debug('Deleting temp file', $tempFileName);
            FyndiqUtils::deleteFile($tempFileName);
        } catch (Exception $e) {
            $file = false;
            FyndiqUtils::debug('UNHANDLED ERROR ' . $e->getMessage());
        }
        return false;
    }

    protected function getExportedProductsCollection($entityIds, $storeId)
    {
        $productsModel = Mage::getModel('catalog/product')->getCollection()
            ->addAttributeToSelect('*')
            ->addAttributeToFilter(
                'entity_id',
                array('in' => $entityIds)
            );
        if ($storeId) {
            $productsModel->setStoreId($storeId)
                ->addStoreFilter($storeId);
        }
        return $productsModel->load();
    }

    protected function getConfigurableProductsCollection($product, $storeId)
    {
        $confModel = Mage::getModel('catalog/product_type_configurable')
            ->setProduct($product)
            ->getUsedProductCollection()
            ->addAttributeToSelect('*')
            ->addFilterByRequiredOptions();
        if ($storeId) {
            $confModel->setStoreId($storeId)
            ->addStoreFilter($storeId);
        }
        return  $confModel->load();
    }

    /**
     * Adding products added for export to the feed file
     *
     * @param $storeId
     * @return bool
     */
    private function exportingProducts($storeId, $feedWriter)
    {
        FyndiqUtils::debug('exportingProducts');

        $store = Mage::getModel('core/store')->load($storeId);

        $this->productMediaConfig = Mage::getModel('catalog/product_media_config');

        $products = Mage::getModel('fyndiq/product')->getCollection()
            ->addFieldToSelect(
                array(
                    'id',
                    'product_id',
                    'exported_price_percentage',
                )
            )
            ->setOrder('id', 'DESC')
            ->load();

        $productInfo = array();
        foreach ($products->getData() as $productData) {
            $productInfo[intval($productData['product_id'])] = $productData;
        }
        $products->clear();

        FyndiqUtils::debug('$productInfo', $productInfo);

        if ($productInfo) {
            $market = Mage::getStoreConfig('general/country/default');
            $currency = $store->getCurrentCurrencyCode();
            $stockMin = intval(FmConfig::get('stockmin', $storeId));

            $productIds = array_unique(array_keys($productInfo));
            $batches = array_chunk($productIds, self::BATCH_SIZE);
            foreach ($batches as $entityIds) {
                FyndiqUtils::debug('MEMORY', memory_get_usage(true));

                $productsToExport = $this->getExportedProductsCollection($entityIds, $storeId);

                foreach ($productsToExport as $magProduct) {
                    $productId = $magProduct->getId();
                    $typeId = $magProduct->getTypeId();
                    $ourProductId = $productInfo[$productId]['id'];

                    FyndiqUtils::debug('$magProduct->getTypeId()', $typeId);
                    $discount = intval($productInfo[$productId]['exported_price_percentage']);

                    if ($typeId === 'simple') {
                        //Check if minimumQuantity is > 1, if it is it will skip this product.
                        if ($magProduct->getStockItem()->getMinSaleQty() > 1) {
                            FyndiqUtils::debug('min sale qty is > 1, SKIPPING PRODUCT');
                            continue;
                        }

                        $product = $this->getProduct($store, $magProduct, $ourProductId, $discount, $market, $currency, $stockMin);
                        FyndiqUtils::debug('simple product', $product);
                        $feedWriter->addCompleteProduct($product);
                        FyndiqUtils::debug('Any Validation Errors', $feedWriter->getLastProductErrors());
                        continue;
                    }

                    // Configurable product
                    $articles = array();
                    $simpleCollection = $this->getConfigurableProductsCollection($magProduct, $storeId);
                    $product = $this->getProduct($store, $magProduct, $ourProductId, $discount, $market, $currency);
                    $index = 1;
                    foreach ($simpleCollection as $simpleProduct) {
                        if ($simpleProduct->getStockItem()->getMinSaleQty() > 1) {
                            FyndiqUtils::debug('min sale qty is > 1, SKIPPING ARTICLE');
                            continue;
                        }
                        FyndiqUtils::debug('$simpleProduct', $simpleProduct);
                        $article = $this->getArticle($store, $simpleProduct, $discount, $productId, $index, $stockMin);
                        if ($article) {
                            $articles[] = $article;
                        }
                        $index++;
                    }
                    $simpleCollection->clear();
                    FyndiqUtils::debug('$product, $articles', $product, $articles);
                    $feedWriter->addCompleteProduct($product, $articles);
                    FyndiqUtils::debug('Any Validation Errors', $feedWriter->getLastProductErrors());
                }
                $productsToExport->clear();
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
    private function getTaxRate($product, $store)
    {
        if (!$this->taxCalculationModel) {
            $this->taxCalculationModel = Mage::getModel('tax/calculation');
        }
        $taxClassId = $product->getTaxClassId();
        $request = $this->taxCalculationModel->getRateRequest(null, null, null, $store);
        return $this->taxCalculationModel->getRate($request->setProductClassId($taxClassId));
    }

    protected function getProductImages($product)
    {
        $images = Mage::getModel('catalog/product')
            ->load($product->getId())
            ->getMediaGalleryImages();
        $newImages = array();
        foreach ($images as $image) {
            $url = $this->productMediaConfig->getMediaUrl($image->getFile());
            if (!in_array($url, $newImages)) {
                $newImages[$image->getPosition()] = $url;
            }
        }
        $images->clear();
        if (count($newImages)) {
            ksort($newImages);
            return  array_values($newImages);
        }
        foreach (array($product->getImage(), $product->getSmallImage()) as $image) {
            if ($image != null &&  $image != 'no_selection') {
                // Fall-back to main image
                $url = $this->productMediaConfig->getMediaUrl($image);
                return array($url);
            }
        }
        return array();
    }

    /**
     * getDescription returns product's long description string
     *
     * @param  object $magProduct
     * @param  integer $storeId
     * @return string
     */
    protected function getDescription($magProduct, $storeId)
    {
        $description = $magProduct->getDescription();
        if (is_null($description)) {
            $description = Mage::getResourceModel('catalog/product')
                ->getAttributeRawValue($magProduct->getId(), 'description', $storeId);
        }
        return $description;
    }

    /**
     * getProductDescription returns product description based on $descrType
     * @param  object $magProduct
     * @param  integer $descrType
     * @param  integer $storeId
     * @return string
     */
    protected function getProductDescription($magProduct, $descrType, $storeId)
    {
        switch ($descrType) {
            case 1:
                return $this->getDescription($magProduct, $storeId);
            case 2:
                return $magProduct->getShortDescription();
            case 3:
                return $magProduct->getShortDescription() . "\n\n" . $this->getDescription($magProduct, $storeId);
        }
        return $this->getDescription($magProduct, $storeId);
    }

    /**
     * getCategoryName returns the full category path
     *
     * @param  int $categoryId
     * @return string
     */
    protected function getCategoryName($categoryId)
    {
        if (isset($this->categoryCache[$categoryId])) {
            return $this->categoryCache[$categoryId];
        }
        $category = $this->categoryModel->load($categoryId);
        $pathIds = explode('/', $category->getPath());
        if (!$pathIds) {
            $this->categoryCache[$categoryId] = $firstCategory->getName();
            return $this->categoryCache[$categoryId];
        }
        $name = array();
        foreach ($pathIds as $id) {
            $name[] = $this->categoryModel->load($id)->getName();
        }
        $this->categoryCache[$categoryId] = implode(self::CATEGORY_SEPARATOR, $name);
        return $this->categoryCache[$categoryId];
    }


    // Add Tax to the price if required
    protected function includeTax($product, $price)
    {
        if (!Mage::helper('tax')->priceIncludesTax()) {
            return Mage::helper('tax')->getPrice($product, $price);
        }
        return $price;
    }

    public function getProductPrice($product)
    {
        $price = $product->getFinalPrice();
        return $this->includeTax($product, $price);
    }

    /**
     * Get product information
     * @param  object $store
     * @param  object $magProduct
     * @param  int $discount
     * @param  string $market
     * @return array
     */
    private function getProduct($store, $magProduct, $ourProductId, $discount, $market, $currency, $stockMin)
    {
        $storeId = intval($store->getId());
        $magArray = $magProduct->getData();

        FyndiqUtils::debug('$magProduct', $magArray);

        //Initialize models here so it saves memory.
        if (!$this->categoryModel) {
            $this->categoryModel = Mage::getModel('catalog/category');
        }

        // Setting the data
        if (!isset($magArray['price'])) {
            FyndiqUtils::debug('No price is set');
            return array();
        }

        if ($magProduct->getStatus() != Mage_Catalog_Model_Product_Status::STATUS_ENABLED) {
            FyndiqUtils::debug('product is not enabled');
            return array();
        }

        $productId = $magProduct->getId();
        $descrType = intval(FmConfig::get('description', $storeId));
        $magPrice = $this->getProductPrice($magProduct);
        $price = FyndiqUtils::getFyndiqPrice($magPrice, $discount);

        // Old price is always the product base price
        $oldPrice = $this->includeTax($magProduct, $magProduct->getPrice());

        $feedProduct = array(
            FyndiqFeedWriter::ID => $ourProductId,
            FyndiqFeedWriter::PRODUCT_TITLE => $magArray['name'],
            FyndiqFeedWriter::PRODUCT_DESCRIPTION =>
                $this->getProductDescription($magProduct, $descrType, $storeId),
            FyndiqFeedWriter::PRICE => FyndiqUtils::formatPrice($price),
            FyndiqFeedWriter::OLDPRICE => FyndiqUtils::formatPrice($oldPrice),
            FyndiqFeedWriter::PRODUCT_VAT_PERCENT => $this->getTaxRate($magProduct, $store),
            FyndiqFeedWriter::PRODUCT_CURRENCY => $currency,
            FyndiqFeedWriter::PRODUCT_MARKET => $market,
        );

        $brand = $magProduct->getAttributeText('manufacturer');
        if ($brand) {
            $feedProduct[FyndiqFeedWriter::PRODUCT_BRAND_NAME] = $brand;
        }

        // Category
        $categoryIds = $magProduct->getCategoryIds();
        if (count($categoryIds) > 0) {
            $firstCategoryId = array_shift($categoryIds);
            $feedProduct[FyndiqFeedWriter::PRODUCT_CATEGORY_ID] = $firstCategoryId;
            $feedProduct[FyndiqFeedWriter::PRODUCT_CATEGORY_NAME] = $this->getCategoryName($firstCategoryId);
        }

        if ($magArray['type_id'] === 'simple') {
            $feedProduct[FyndiqFeedWriter::QUANTITY] = $this->getQuantity($magProduct, $stockMin);
            $feedProduct[FyndiqFeedWriter::SKU] = $magProduct->getSKU();
            $feedProduct[FyndiqFeedWriter::PROPERTIES] = array();

            if (method_exists($magProduct->getTypeInstance(), 'getConfigurableAttributes')) {
                if (!$this->productAttrOptions) {
                    $this->productAttrOptions = $parentProduct->getTypeInstance()->getConfigurableAttributes();
                }
                foreach ($this->productAttrOptions as $productAttribute) {
                    $attrValue = $magProduct->getResource()->getAttribute(
                        $productAttribute->getProductAttribute()->getAttributeCode()
                    )->getFrontend();
                    $attrLabel = $productAttribute->getProductAttribute()->getFrontendLabel();
                    $value = $attrValue->getValue($magProduct);
                    if (is_array($value)) {
                        $value = $value[0];
                    }
                    $feedProduct[FyndiqFeedWriter::PROPERTIES][] = array(
                        FyndiqFeedWriter::PROPERTY_NAME => $attrLabel,
                        FyndiqFeedWriter::PROPERTY_VALUE => $value,
                    );
                }
            }
        }
        $feedProduct[FyndiqFeedWriter::IMAGES] = $this->getProductImages($magProduct);
        return $feedProduct;
    }

    private function getArticle($store, $magProduct, $discount, $parentProductId, $index, $stockMin)
    {
        // Setting the data
        if (!$magProduct->getPrice()) {
            FyndiqUtils::debug('No price is set');
            return array();
        }

        if ($magProduct->getStatus() != Mage_Catalog_Model_Product_Status::STATUS_ENABLED) {
            FyndiqUtils::debug('product is not enabled');
            return array();
        }

        if ($magProduct->getTypeId() !== 'simple') {
            FyndiqUtils::debug('article is not simple product');
            return array();
        }

        $magPrice = $this->getProductPrice($magProduct);
        $price = FyndiqUtils::getFyndiqPrice($magPrice, $discount);

        $feedProduct = array(
            FyndiqFeedWriter::ID => $index,
            FyndiqFeedWriter::PRICE => FyndiqUtils::formatPrice($price),
            FyndiqFeedWriter::OLDPRICE => FyndiqUtils::formatPrice($magPrice),
            FyndiqFeedWriter::ARTICLE_NAME => $magProduct->getName(),
            FyndiqFeedWriter::QUANTITY => $this->getQuantity($magProduct, $stockMin),
            FyndiqFeedWriter::SKU => $magProduct->getSKU(),
            FyndiqFeedWriter::IMAGES => $this->getProductImages($magProduct),
            FyndiqFeedWriter::PROPERTIES => array(),
        );

        $parentProduct = Mage::getModel('catalog/product')->load($parentProductId);
        if (method_exists($parentProduct->getTypeInstance(), 'getConfigurableAttributes')) {
            if (!$this->productAttrOptions) {
                $this->productAttrOptions = $parentProduct->getTypeInstance()->getConfigurableAttributes();
            }
            foreach ($this->productAttrOptions as $productAttribute) {
                $attrValue = $parentProduct->getResource()->getAttribute(
                    $productAttribute->getProductAttribute()->getAttributeCode()
                )->getFrontend();
                $attrLabel = $productAttribute->getProductAttribute()->getFrontendLabel();
                $value = $attrValue->getValue($magProduct);
                if (is_array($value)) {
                    $value = $value[0];
                }
                $feedProduct[FyndiqFeedWriter::PROPERTIES][] = array(
                    FyndiqFeedWriter::PROPERTY_NAME => $attrLabel,
                    FyndiqFeedWriter::PROPERTY_VALUE => $value,
                );
            }
            FyndiqUtils::debug('-+OPTIONS', $productAttrOptions);
        }
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
            FmConfig::set('ping_token', $pingToken, $storeId);
            FmConfig::reInit();
            $data = array(
                FyndiqUtils::NAME_PRODUCT_FEED_URL => Mage::getUrl(
                    'fyndiq/file/index/store/' . $storeId,
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
            if (FmConfig::get('import_orders_disabled', $storeId) != FmHelpers::ORDERS_DISABLED) {
                $data[FyndiqUtils::NAME_NOTIFICATION_URL] = Mage::getUrl(
                    'fyndiq/notification/index/store/' . $storeId,
                    array(
                            '_store' => $storeId,
                            '_nosid' => true,
                            '_query' => array(
                                'event' => 'order_created',
                            )
                        )
                );
            }
            return FmHelpers::callApi($storeId, 'PATCH', 'settings/', $data);
        }
        throw new Exception(FyndiqTranslation::get('empty-username-token'));
    }

    public function getQuantity($magProduct, $stockMin)
    {
        $qtyStock = 0;
        $stockItem = Mage::getModel('cataloginventory/stock_item')
            ->loadByProduct($magProduct);
        if ($magProduct->getStatus() == 1 && $stockItem->getIsInStock() != 0) {
            $qtyStock = $stockItem->getQty();
        }
        // Reserved qty
        $minQty = intval($stockItem->getMinQty());
        $qtyStock = intval($qtyStock - max(array($stockMin - $minQty)));
        return $qtyStock < 0 ? 0 : $qtyStock;
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
