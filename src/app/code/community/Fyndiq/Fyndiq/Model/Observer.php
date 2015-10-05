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

    private $productModel = null;
    private $categoryModel = null;
    private $taxCalculationModel = null;
    private $imageHelper = null;
    private $productImages = array();
    private $productMediaConfig = null;

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
            ->setOrder('id', 'DESC')
            ->getItems();

        $productInfo = array();
        foreach ($products as $product) {
            $productData = $product->getData();
            $productInfo[intval($productData['product_id'])] = $productData;
        }

        FyndiqUtils::debug('$productInfo', $productInfo);

        if ($productInfo) {
            $market = Mage::getStoreConfig('general/country/default');
            $currency = $store->getCurrentCurrencyCode();

            $productIds = array_unique(array_keys($productInfo));
            $batches = array_chunk($productIds, self::BATCH_SIZE);
            foreach ($batches as $batchIds) {
                FyndiqUtils::debug('MEMORY', memory_get_usage(true));
                $productsToExport = Mage::getModel('catalog/product')->getCollection()
                    ->addAttributeToSelect('*')
                    ->addStoreFilter($storeId)
                    ->addAttributeToFilter(
                        'entity_id',
                        array('in' => $batchIds)
                    )->load();

                foreach ($productsToExport as $magProduct) {
                    $productId = $magProduct->getId();
                    $typeId = $magProduct->getTypeId();
                    FyndiqUtils::debug('$magProduct->getTypeId()', $typeId);
                    $discount = intval($productInfo[$productId]['exported_price_percentage']);

                    if ($typeId != 'simple') {
                        //Check if minimumQuantity is > 1, if it is it will skip this product.
                        if ($magProduct->getStockItem()->getMinSaleQty() > 1) {
                            FyndiqUtils::debug('min sale qty is > 1, SKIPPING PRODUCT');
                            continue;
                        }
                        $product = $this->getProduct($store, $magProduct, $discount, $market, $currency);
                        FyndiqUtils::debug('simple product', $product);
                        $feedWriter->addCompleteProduct($product);
                        FyndiqUtils::debug('Any Validation Errors', $feedWriter->getLastProductErrors());
                        continue;
                    }

                    // Configurable product
                    $articles = array();
                    $product = $this->getProduct($store, $magProduct, $discount, $market, $currency);
                    $conf = Mage::getModel('catalog/product_type_configurable')->setProduct($magProduct);
                    $simpleCollection = $conf->getUsedProductCollection()
                        ->addAttributeToSelect('*')
                        ->addFilterByRequiredOptions()
                        ->getItems();
                    foreach ($simpleCollection as $simpleProduct) {
                        if ($simpleProduct->getStockItem()->getMinSaleQty() > 1) {
                            FyndiqUtils::debug('min sale qty is > 1, SKIPPING ARTICLE');
                            continue;
                        }
                        $articles[] = $this->getProduct($store, $simpleProduct, $discount, $market, $currency);
                    }
                    FyndiqUtils::debug('$product, $articles', $product, $articles);
                    $feedWriter->addCompleteProduct($product, $articles);
                    FyndiqUtils::debug('Any Validation Errors', $feedWriter->getLastProductErrors());
                }
                $productsToExport->clear();
            }

        }
        return $feedWriter->write();
    }


    private function getImagesFromArray($articleId = null)
    {
        $product = array();
        $urls = array();
        //If we don't want to add a specific article, add all of them.
        if (is_null($articleId)) {
            foreach ($this->productImages['product'] as $url) {
                if (!in_array($url, $urls)) {
                    $urls[] = $url;
                }
            }
            foreach ($this->productImages['articles'] as $article) {
                foreach ($article as $url) {
                    if (!in_array($url, $urls)) {
                        $urls[] = $url;
                    }
                }
            }
        // If we want to add just the product images and the article's images - run this.
        } else {
            foreach ($this->productImages['articles'][$articleId] as $url) {
                $urls[] = $url;
            }

            foreach ($this->productImages['product'] as $url) {
                $urls[] = $url;
            }
        }
        $imageId = 1;
        foreach ($urls as $url) {
            if ($imageId > FyndiqUtils::NUMBER_OF_ALLOWED_IMAGES) {
                break;
            }
            $product['product-image-' . $imageId . '-url'] = $url;
            $product['product-image-' . $imageId . '-identifier'] = substr(md5($url), 0, 10);
            $imageId++;
        }
        return $product;
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
     * @return array
     */
    protected function getImages($productId, $magProduct)
    {
        $this->productImages = array(
            'articles' => array(),
            'product' => $this->getProductImages($productId, $magProduct),
        );
        $simpleCollection = Mage::getModel('catalog/product_type_configurable')
            ->setProduct($magProduct)
            ->getUsedProductCollection()
            ->addAttributeToSelect('*')
            ->addFilterByRequiredOptions()
            ->getItems();
        foreach ($simpleCollection as $simpleProduct) {
            $urls = $this->getProductImages($simpleProduct->ID, $simpleProduct);
            $sku = $simpleProduct->getSKU();
            $this->productImages['articles'][$sku] = $urls;
        }
        FyndiqUtils::debug('images', $this->productImages);
    }

    private function getProductImages($productId, $product)
    {
        $images = Mage::getModel('catalog/product')->load($productId)->getMediaGalleryImages();
        $hasRealImagesSet = ($product->getImage() != null && $product->getImage() != "no_selection");
        $urls = array();
        $positions = array();
        $newImages = array();
        foreach ($images as $image) {
            $positions[] = $image->getPosition();
            $newImages[] = $image;
        }
        if (count(array_unique($positions)) < count($images)) {
            usort($newImages, array("Fyndiq_Fyndiq_Model_Observer", "sortImages"));
        }
        if (count($newImages)) {
            // Get gallery
            foreach ($newImages as $image) {
                $url = $this->productMediaConfig->getMediaUrl($image->getFile());
                if (!in_array($url, $urls)) {
                    $urls[] = $url;
                }
            }
        } elseif ($hasRealImagesSet) {
            // Fallback to main image
            $url = $this->productMediaConfig->getMediaUrl($product->getImage());
            if (!in_array($url, $urls)) {
                $urls[] = $url;
            }
        }
        if (count($urls) > 0) {
            return $urls;
        }

        // Fallbacks
        $imageHelper = Mage::helper('catalog/image');
        // Check for image or small image
        foreach (array('image', 'small_image') as $imageKey) {
            $image = (string)$imageHelper->init($product, $imageKey);
            if ($image) {
                return array($image);
            }
        }
        // Give up
        return $urls;
    }

    private function sortImages($a, $b)
    {
        if ($a->getId() == $b->getId()) {
            return 0;
        }
        return ($a->getId() < $b->getId()) ? -1 : 1;
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
     * Get product information
     * @param  object $store
     * @param  object $magProduct
     * @param  int $discount
     * @param  string $market
     * @return array
     */
    private function getProduct($store, $magProduct, $discount, $market, $currency)
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
        $discount = $productInfo['exported_price_percentage'];
        $magPrice = FmHelpers::getProductPrice($magProduct);
        $price = FyndiqUtils::getFyndiqPrice($magPrice, $discount);

        $feedProduct = array(
            FyndiqFeedWriter::ID => $productId,
            FyndiqFeedWriter::PRODUCT_TITLE => $magArray['name'],
            FyndiqFeedWriter::PRODUCT_DESCRIPTION =>
                $this->getProductDescription($magProduct, $descrType, $storeId),
            FyndiqFeedWriter::PRICE => FyndiqUtils::formatPrice($price),
            FyndiqFeedWriter::OLDPRICE => FyndiqUtils::formatPrice($magPrice),
            FyndiqFeedWriter::PRODUCT_VAT_PERCENT => $this->getTaxRate($magProduct, $store),
            FyndiqFeedWriter::PRODUCT_CURRENCY = $currency,
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

            $firstCategory = $this->categoryModel->load($firstCategoryId);
            $feedProduct[FyndiqFeedWriter::PRODUCT_CATEGORY_NAME] = $firstCategory->getName();
        }

        if ($magArray['type_id'] === 'simple') {
            $qtyStock = intval($this->getQuantity($magProduct, $store));

            $feedProduct[FyndiqFeedWriter::QUANTITY] = $qtyStock < 0 ? 0 : $qtyStock;
            $feedProduct[FyndiqFeedWriter::SKU] = $magProduct->getSKU();
            $feedProduct[FyndiqFeedWriter::ARTICLE_NAME] = $magArray['name'];



            // FIXME::PROBABLY THIS MUST BE THE PARENT ID not the simple product id
            $parentModel = Mage::getModel('catalog/product')->load($productId);
            if (method_exists($parentModel->getTypeInstance(), 'getConfigurableAttributes')) {
                $productAttrOptions = $parentModel->getTypeInstance()->getConfigurableAttributes();
                $attrId = 1;
                $tags = array();
                foreach ($productAttrOptions as $productAttribute) {
                    if ($attrId > FyndiqUtils::NUMBER_OF_ALLOWED_PROPERTIES) {
                        break;
                    }
                    $attrValue = $parentModel->getResource()->getAttribute(
                        $productAttribute->getProductAttribute()->getAttributeCode()
                    )->getFrontend();
                    $attrLabel = $productAttribute->getProductAttribute()->getFrontendLabel();
                    $value = $attrValue->getValue($magProduct);
                    if (is_array($value)) {
                        $value = $value[0];
                    }
                    $feedProduct['article-property-' . $attrId . '-name'] = $attrLabel;
                    $feedProduct['article-property-' . $attrId . '-value'] = $value;
                    $tags[] = $attrLabel . ': ' . $value;
                    $attrId++;
                }
                $feedProduct['article-name'] = implode(', ', $tags);
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

        $qtyStock = $this->getQuantity($firstProduct, $store);

        $feedProduct['article-quantity'] = intval($qtyStock) < 0 ? 0 : intval($qtyStock);
        $feedProduct['article-sku'] = $firstProduct->getSKU();
        $productAttrOptions = $magProduct->getTypeInstance()->getConfigurableAttributes();
        $attrId = 1;
        $tags = array();
        foreach ($productAttrOptions as $productAttribute) {
            $attrValue = $magProduct->getResource()->getAttribute(
                $productAttribute->getProductAttribute()->getAttributeCode()
            )->getFrontend();
            $attrLabel = $productAttribute->getProductAttribute()->getFrontendLabel();
            $value = $attrValue->getValue($firstProduct);
            if (is_array($value)) {
                $value = $value[0];
            }
            $feedProduct['article-property-' . $attrId . '-name'] = $attrLabel;
            $feedProduct['article-property-' . $attrId . '-value'] = $value;
            $tags[] = $attrLabel . ': ' . $value;
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

    public function getQuantity($product, $store)
    {
        $qtyStock = 0;
        $stock_item = Mage::getModel('cataloginventory/stock_item')->loadByProduct($product);
        if ($product->getStatus() == 1 || $stock_item->getIsInStock() != 0) {
            $qtyStock = $stock_item->getQty();
        }
        FyndiqUtils::debug('$qtystock', $qtyStock);

        //Remove the minstock from quantity.
        $stockmin = FmConfig::get('stockmin', $store);
        if (isset($stockmin)) {
            $qtyStock = $qtyStock - $stockmin;
        }

        return $qtyStock;
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
