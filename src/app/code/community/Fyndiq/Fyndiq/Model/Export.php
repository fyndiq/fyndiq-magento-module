<?php

class Fyndiq_Fyndiq_Model_Export
{

    const BATCH_SIZE = 30;
    const CATEGORY_SEPARATOR = ' / ';

    const VALUE_NO = 0;
    const VALUE_YES = 1;

    private $configModel = null;
    private $categoryModel = null;
    private $taxCalculationModel = null;

    private $productMediaConfig = null;
    private $categoryCache = array();
    private $productAttrOptions = null;


    public function __construct()
    {
        $this->configModel = Mage::getModel('fyndiq/config');
        $this->categoryModel = Mage::getModel('catalog/category');
    }

    /**
     * Saving products to the file.
     *
     * @param int $storeId
     */
    public function generateFeed($storeId)
    {
        $fileName = $this->configModel->getFeedPath($storeId);
        $tempFileName = FyndiqUtils::getTempFilename(dirname($fileName));

        FyndiqUtils::debug('$fileName', $fileName);
        FyndiqUtils::debug('$tempFileName', $tempFileName);

        $file = fopen($tempFileName, 'w+');

        if (!$file) {
            FyndiqUtils::debug('Cannot create file: ' . $tempFileName);
            return false;
        }
        $feedWriter = new FyndiqCSVFeedWriter($file);

        if ($storeId) {
            FyndiqUtils::debug('Setting current store to ', $storeId);
            Mage::app()->setCurrentStore($storeId);
        }

        $exportResult = $this->exportProducts($storeId, $feedWriter);
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
    }

    protected function getMappedFields($storeId)
    {
        return array(
            FyndiqCSVFeedWriter::PRODUCT_BRAND_NAME => $this->configModel->get('fyndiq/mappings/brand', $storeId),
            FyndiqCSVFeedWriter::ARTICLE_EAN => $this->configModel->get('fyndiq/mappings/ean', $storeId),
            FyndiqCSVFeedWriter::ARTICLE_ISBN => $this->configModel->get('fyndiq/mappings/isbn', $storeId),
            FyndiqCSVFeedWriter::ARTICLE_MPN => $this->configModel->get('fyndiq/mappings/mpn', $storeId),
        );
    }

    /**
     * Adding products added for export to the feed file
     *
     * @param $storeId
     * @return bool
     */
    protected function exportProducts($storeId, $feedWriter)
    {
        FyndiqUtils::debug('exportProducts');

        $store = Mage::getModel('core/store')->load($storeId);

        $this->productMediaConfig = Mage::getModel('catalog/product_media_config');

        $products = Mage::getModel('catalog/product')->getCollection();

        if ($storeId != Mage_Core_Model_App::ADMIN_STORE_ID) {
            $products->setStoreId($storeId);
        }

        $products->addAttributeToFilter(
            'fyndiq_exported',
            array('eq' => self::VALUE_YES)
        );

        $productIds = $products->getAllIds();
        FyndiqUtils::debug('$productIds', $productIds);

        if ($productIds) {
            $config = array(
                'market' => Mage::getStoreConfig('general/country/default'),
                'priceGroup' => intval($this->configModel->get('fyndiq/fyndiq_group/price_group', $storeId)),
                'discountPercentage' => floatval($this->configModel->get('fyndiq/fyndiq_group/price_percentage', $storeId)),
                'discountPrice' => floatval($this->configModel->get('fyndiq/fyndiq_group/price_absolute', $storeId)),
                'currency' => $store->getCurrentCurrencyCode(),
                'stockMin' => intval($this->configModel->get('fyndiq/fyndiq_group/stockmin', $storeId)),
                'mappedFields' => $this->getMappedFields($storeId),
                'descrType' => intval($this->configModel->get('fyndiq/mappings/description', $storeId)),
            );
            FyndiqUtils::debug('$config', $config);

            $batches = array_chunk($productIds, self::BATCH_SIZE);
            foreach ($batches as $entityIds) {
                FyndiqUtils::debug('MEMORY', memory_get_usage(true));

                $productsToExport = $this->getExportedProductsCollection($entityIds, $storeId);

                foreach ($productsToExport as $magProduct) {
                    $productId = $magProduct->getId();
                    $typeId = $magProduct->getTypeId();

                    FyndiqUtils::debug('$magProduct->getTypeId()', $typeId);

                    if ($typeId === 'simple') {
                        //Check if minimumQuantity is > 1, if it is it will skip this product.
                        if ($magProduct->getStockItem()->getMinSaleQty() > 1) {
                            FyndiqUtils::debug('min sale qty is > 1, SKIPPING PRODUCT');
                            continue;
                        }

                        $product = $this->getProduct(
                            $store,
                            $magProduct,
                            $productId,
                            $config
                        );
                        FyndiqUtils::debug('simple product', $product);
                        if (!$feedWriter->addCompleteProduct($product)) {
                            FyndiqUtils::debug('Validation Errors', $feedWriter->getLastProductErrors());
                        }
                        continue;
                    }

                    // Configurable product
                    $articles = array();
                    $simpleCollection = $this->getConfigurableProductsCollection($magProduct, $storeId);
                    $product = $this->getProduct($store, $magProduct, $productId, $config);
                    $index = 1;
                    foreach ($simpleCollection as $simpleProduct) {
                        if ($simpleProduct->getStockItem()->getMinSaleQty() > 1) {
                            FyndiqUtils::debug('min sale qty is > 1, SKIPPING ARTICLE');
                            continue;
                        }
                        FyndiqUtils::debug('$simpleProduct', $simpleProduct);
                        $article = $this->getArticle(
                            $store,
                            $simpleProduct,
                            $productId,
                            $index,
                            $config
                        );
                        if ($article) {
                            $articles[] = $article;
                        }
                        $index++;
                    }
                    $simpleCollection->clear();
                    FyndiqUtils::debug('$product, $articles', $product, $articles);
                    if (!$feedWriter->addCompleteProduct($product, $articles)) {
                        FyndiqUtils::debug('Validation Errors', $feedWriter->getLastProductErrors());
                    }
                }
                $productsToExport->clear();
            }

        }
        FyndiqUtils::debug('$feedWriter->getProductCount()', $feedWriter->getProductCount());
        FyndiqUtils::debug('$feedWriter->getArticleCount()', $feedWriter->getArticleCount());
        return $feedWriter->write();
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

    protected function getComparisonUnit($product)
    {
        $attribute = Mage::getModel('eav/entity_attribute')->loadByCode('catalog_product', 'base_price_unit');
        $unit = $attribute->getFrontend()->getValue($product);
        if ($unit) {
            $unit = strtolower(trim($unit));
            if (in_array($unit, FyndiqFeedWriter::$validContent[FyndiqFeedWriter::PRODUCT_COMPARISON_UNIT])) {
                return $unit;
            }
        }
        return false;
    }

    protected function getMappedValues($mappedFields, $product)
    {
        $result = array();
        $codes = array_filter(array_values($mappedFields));
        if ($codes) {
            $attributes = Mage::getModel('eav/entity_attribute')
                ->getCollection()
                ->setEntityTypeFilter(Mage::getResourceModel('catalog/product')->getEntityType()->getData('entity_type_id'))
                ->setCodeFilter($codes);
            foreach ($mappedFields as $field => $code) {
                if (empty($code)) {
                    $result[$field] = '';
                    continue;
                }
                if ($attribute = $attributes->getItemByColumnValue('attribute_code', $code)) {
                    if ($attribute->getFrontendInput() == 'select' || $attribute->getFrontendInput() == 'multiselect') {
                        $result[$field] = implode(', ', (array)$product->getAttributeText($code));
                        continue;
                    }
                }
                $result[$field] = $product->getData($code);
            }
        }
        return $result;
    }

    /**
     * Get product information
     * @param  object $store
     * @param  object $magProduct
     * @param  int $discount
     * @param  string $market
     * @return array
     */
    private function getProduct($store, $magProduct, $ourProductId, $config)
    {
        $storeId = intval($store->getId());
        $magArray = $magProduct->getData();

        FyndiqUtils::debug('$magProduct', $magArray);

        // Setting the data
        if (!isset($magArray['price'])) {
            FyndiqUtils::debug('No price is set');
            return array();
        }

        if (Mage::helper('fyndiq_fyndiq/export')->hasCustomOptions($magProduct)) {
            FyndiqUtils::debug('Product has custom options');
            return array();
        }

        $magPrice = $this->getProductPrice($magProduct, $config['priceGroup'], $storeId);
        $price = FyndiqUtils::getFyndiqPrice($magPrice, $config['discountPercentage'], $config['discountPrice']);

        // Old price is always the product base price
        $oldPrice = $this->includeTax($magProduct, $magProduct->getPrice(), $storeId);

        $feedProduct = array(
            FyndiqFeedWriter::ID => $ourProductId,
            FyndiqFeedWriter::PAUSED => $magProduct->getStatus() != Mage_Catalog_Model_Product_Status::STATUS_ENABLED ? 1 : 0,
            FyndiqFeedWriter::PRODUCT_TITLE => $this->getProductTitle($magArray['name'], $ourProductId, $storeId),
            FyndiqFeedWriter::PRODUCT_DESCRIPTION =>
                $this->getProductDescription($magProduct, $config['descrType'], $storeId),
            FyndiqFeedWriter::PRICE => FyndiqUtils::formatPrice($price),
            FyndiqFeedWriter::OLDPRICE => FyndiqUtils::formatPrice($oldPrice),
            FyndiqFeedWriter::PRODUCT_VAT_PERCENT => $this->getTaxRate($magProduct, $store),
            FyndiqFeedWriter::PRODUCT_CURRENCY => $config['currency'],
            FyndiqFeedWriter::PRODUCT_MARKET => $config['market'],
        );

        if (isset($magArray['base_price_amount']) && $magArray['base_price_amount']) {
            $comparisonUnit = $this->getComparisonUnit($magProduct);
            if ($comparisonUnit) {
                $feedProduct[FyndiqFeedWriter::PRODUCT_PORTION] =
                    number_format((float)$magArray['base_price_amount'], 2, '.', '');
                $feedProduct[FyndiqFeedWriter::PRODUCT_COMPARISON_UNIT] = $comparisonUnit;
            }
        }

        // Category
        $categoryIds = $magProduct->getCategoryIds();
        if (count($categoryIds) > 0) {
            $cateogrySetup = $this->getCategorySetup($storeId, $categoryIds);
            if (is_array($cateogrySetup)) {
                $feedProduct = array_merge($feedProduct, $cateogrySetup);
            }
        }

        if ($magArray['type_id'] === 'simple') {
            $feedProduct[FyndiqFeedWriter::QUANTITY] = $this->getQuantity($magProduct, $config['stockMin']);
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
        $feedProduct = array_merge($feedProduct, $this->getMappedValues($config['mappedFields'], $magProduct));
        return $feedProduct;
    }

    // Add Tax to the price if required
    protected function includeTax($product, $price, $storeId)
    {
        if (!Mage::helper('tax')->priceIncludesTax($storeId)) {
            return Mage::helper('tax')->getPrice($product, $price, null, null, null, null, $storeId);
        }
        return $price;
    }

    public function getProductPrice($product, $priceGroup, $storeId)
    {
        $product->setCustomerGroupId($priceGroup);
        $price = $product->getFinalPrice();
        return $this->includeTax($product, $price, $storeId);
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
        $description = Mage::getResourceModel('catalog/product')->getAttributeRawValue($magProduct->getId(), 'fyndiq_description', $storeId);
        if (!empty($description)) {
            return $description;
        }

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

    protected function getProductTitle($title, $productId, $storeId)
    {
        $fyndiqTitle = Mage::getResourceModel('catalog/product')->getAttributeRawValue($productId, 'fyndiq_title', $storeId);
        if (!empty($fyndiqTitle)) {
            return $fyndiqTitle;
        }
        return $title;
    }

    protected function getCategorySetup($storeId, $categoryIds)
    {
        $categoryId = array_shift($categoryIds);
        if (!isset($this->categoryCache[$categoryId])) {
            $fyndiqCategoryId = $this->getCategoryFyndiqId($storeId, $categoryId);
            if (empty($fyndiqCategoryId)) {
                $this->categoryCache[$categoryId] = array(
                    FyndiqFeedWriter::PRODUCT_CATEGORY_ID => $categoryId,
                    FyndiqFeedWriter::PRODUCT_CATEGORY_NAME => $this->getCategoryName($categoryId),
                    FyndiqFeedWriter::PRODUCT_CATEGORY_FYNDIQ_ID => '',
                );
            } else {
                $this->categoryCache[$categoryId] = array(
                    FyndiqFeedWriter::PRODUCT_CATEGORY_ID => '',
                    FyndiqFeedWriter::PRODUCT_CATEGORY_NAME => '',
                    FyndiqFeedWriter::PRODUCT_CATEGORY_FYNDIQ_ID => $fyndiqCategoryId,
                );
            }
        }
        return $this->categoryCache[$categoryId];
    }

    protected function getCategoryFyndiqId($storeId, $categoryId)
    {
        $category = Mage::getModel('catalog/category')
            ->load($categoryId);
        return $category->getFyndiqCategoryId();
    }

    /**
     * getCategoryName returns the full category path
     *
     * @param  int $categoryId
     * @return string
     */
    public function getCategoryName($categoryId)
    {
        $category = $this->categoryModel->load($categoryId);
        $pathIds = explode('/', $category->getPath());
        if (!$pathIds) {
            $this->categoryCache[$categoryId] = $category->getName();
            return $this->categoryCache[$categoryId];
        }
        $name = array();
        foreach ($pathIds as $id) {
            $name[] = $this->categoryModel->load($id)->getName();
        }
        return implode(self::CATEGORY_SEPARATOR, $name);
    }

    private function getArticle($store, $magProduct, $parentProductId, $index, $config)
    {
        // Setting the data
        if (!$magProduct->getPrice()) {
            FyndiqUtils::debug('No price is set');
            return array();
        }

        if ($magProduct->getTypeId() !== 'simple') {
            FyndiqUtils::debug('article is not simple product');
            return array();
        }

        if (Mage::helper('fyndiq_fyndiq/export')->hasCustomOptions($magProduct)) {
            FyndiqUtils::debug('Article has custom options');
            return array();
        }

        $storeId = intval($store->getId());

        $magPrice = $this->getProductPrice($magProduct, $config['priceGroup'], $storeId);
        $price = FyndiqUtils::getFyndiqPrice($magPrice, $config['discountPercentage'], $config['discountPrice']);

        $feedProduct = array(
            FyndiqFeedWriter::ID => $index,
            FyndiqFeedWriter::PAUSED => $magProduct->getStatus() != Mage_Catalog_Model_Product_Status::STATUS_ENABLED ? 1 : 0,
            FyndiqFeedWriter::PRICE => FyndiqUtils::formatPrice($price),
            FyndiqFeedWriter::OLDPRICE => FyndiqUtils::formatPrice($magPrice),
            FyndiqFeedWriter::ARTICLE_NAME => $magProduct->getName(),
            FyndiqFeedWriter::QUANTITY => $this->getQuantity($magProduct, $config['stockMin']),
            FyndiqFeedWriter::SKU => $magProduct->getSKU(),
            FyndiqFeedWriter::IMAGES => $this->getProductImages($magProduct),
            FyndiqFeedWriter::PROPERTIES => array(),
        );

        if (isset($magArray['base_price_amount']) && $magArray['base_price_amount']) {
            $comparisonUnit = $this->getComparisonUnit($magProduct);
            if ($comparisonUnit) {
                $feedProduct[FyndiqFeedWriter::PRODUCT_PORTION] =
                    number_format((float)$magArray['base_price_amount'], 2, '.', '');
                $feedProduct[FyndiqFeedWriter::PRODUCT_COMPARISON_UNIT] = $comparisonUnit;
            }
        }

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
        }
        $feedProduct = array_merge($feedProduct, $this->getMappedValues($config['mappedFields'], $magProduct));
        return $feedProduct;
    }

    protected function getConfigurableProductsCollection($product, $storeId)
    {
        $confModel = Mage::getModel('catalog/product_type_configurable')
            ->setProduct($product)
            ->getUsedProductCollection()
            ->addAttributeToSelect('*')
            ->addFilterByRequiredOptions();
        if ($storeId) {
            $confModel->setStoreId($storeId);
        }
        return  $confModel->load();
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
}
