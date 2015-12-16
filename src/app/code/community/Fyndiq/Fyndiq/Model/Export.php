<?php

class Fyndiq_Fyndiq_Model_Export
{

    const BATCH_SIZE = 30;
    const CATEGORY_SEPARATOR = ' / ';

    private $configModel = null;
    private $categoryModel = null;
    private $taxCalculationModel = null;

    private $imageHelper = null;
    private $productImages = array();
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

        $fyndiq_exported = Mage::getResourceModel('catalog/product')->getAttributeRawValue(403, 'fyndiq_exported', $storeId);

        FyndiqUtils::debug('$fyndiq_exported', $fyndiq_exported);

        //TODO: Find a better way to do that
        $products = Mage::getModel('catalog/product')->getCollection()
            ->addStoreFilter($storeId)
            ->addAttributeToFilter(
                'fyndiq_exported',
                array('eq' => "Exported")
            )
            ->setOrder('id', 'DESC')
            ->load();

        FyndiqUtils::debug('$idsToExport', $products);

        $productInfo = array();
        foreach ($products->getData() as $productData) {
            $productInfo[intval($productData['entity_id'])] = $productData;
        }
        $products->clear();

        FyndiqUtils::debug('$productInfo', $productInfo);

        if ($productInfo) {
            $market = Mage::getStoreConfig('general/country/default');
            $currency = $store->getCurrentCurrencyCode();
            $stockMin = intval($this->configModel->get('stockmin', $storeId));
            $price_group = intval($this->configModel->get('price_group', $storeId));

            $productIds = array_unique(array_keys($productInfo));
            $batches = array_chunk($productIds, self::BATCH_SIZE);
            foreach ($batches as $entityIds) {
                FyndiqUtils::debug('MEMORY', memory_get_usage(true));

                $productsToExport = $this->getExportedProductsCollection($entityIds, $storeId);

                foreach ($productsToExport as $magProduct) {
                    $productId = $magProduct->getId();
                    $typeId = $magProduct->getTypeId();

                    FyndiqUtils::debug('$magProduct->getTypeId()', $typeId);
                    // FIXME: get global discount
                    //$discount = intval($productInfo[$productId]['exported_price_percentage']);
                    $discount = 0;

                    if ($typeId === 'simple') {
                        //Check if minimumQuantity is > 1, if it is it will skip this product.
                        if ($magProduct->getStockItem()->getMinSaleQty() > 1) {
                            FyndiqUtils::debug('min sale qty is > 1, SKIPPING PRODUCT');
                            continue;
                        }

                        $product = $this->getProduct($store, $magProduct, $productId, $discount, $market, $currency, $stockMin, $price_group);
                        FyndiqUtils::debug('simple product', $product);
                        if (!$feedWriter->addCompleteProduct($product)) {
                            FyndiqUtils::debug('Validation Errors', $feedWriter->getLastProductErrors());
                        }
                        continue;
                    }

                    // Configurable product
                    $articles = array();
                    $simpleCollection = $this->getConfigurableProductsCollection($magProduct, $storeId);
                    $product = $this->getProduct($store, $magProduct, $productId, $discount, $market, $currency, 0, $price_group);
                    $index = 1;
                    foreach ($simpleCollection as $simpleProduct) {
                        if ($simpleProduct->getStockItem()->getMinSaleQty() > 1) {
                            FyndiqUtils::debug('min sale qty is > 1, SKIPPING ARTICLE');
                            continue;
                        }
                        FyndiqUtils::debug('$simpleProduct', $simpleProduct);
                        $article = $this->getArticle($store, $simpleProduct, $discount, $productId, $index, $stockMin, $price_group);
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

    /**
     * Get product information
     * @param  object $store
     * @param  object $magProduct
     * @param  int $discount
     * @param  string $market
     * @return array
     */
    private function getProduct($store, $magProduct, $ourProductId, $discount, $market, $currency, $stockMin, $price_group)
    {
        $storeId = intval($store->getId());
        $magArray = $magProduct->getData();

        FyndiqUtils::debug('$magProduct', $magArray);

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
        $descrType = intval($this->configModel->get('description', $storeId));
        $magPrice = $this->getProductPrice($magProduct, $price_group);
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

    // Add Tax to the price if required
    protected function includeTax($product, $price)
    {
        if (!Mage::helper('tax')->priceIncludesTax()) {
            return Mage::helper('tax')->getPrice($product, $price);
        }
        return $price;
    }

    public function getProductPrice($product, $price_group)
    {
        $product->setCustomerGroupId($price_group);
        $price = $product->getFinalPrice();
        return $this->includeTax($product, $price);
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

    private function getArticle($store, $magProduct, $discount, $parentProductId, $index, $stockMin, $price_group)
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

        $magPrice = $this->getProductPrice($magProduct, $price_group);
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
