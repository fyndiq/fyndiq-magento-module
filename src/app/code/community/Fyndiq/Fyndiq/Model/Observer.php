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
        $this->productMediaConfig = Mage::getModel('catalog/product_media_config');

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

        $batches = array_chunk($idsToExport, self::BATCH_SIZE);
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
                $parent_id = $magProduct->getId();
                FyndiqUtils::debug('$magProduct->getTypeId()', $magProduct->getTypeId());


                if ($magProduct->getTypeId() != 'simple') {
                    $articles = array();
                    $prices = array();
                    $articles[] = $this->getProduct($magProduct, $productInfo[$parent_id], $store);

                    $this->getImages($parent_id, $magProduct, $productInfo[$parent_id]['id']);

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
                        $prices[] = FmHelpers::getProductPrice($simpleProduct);
                        $articles[] = $this->getProduct($simpleProduct, $productInfo[$parent_id], $store);
                    }
                    $price = null;
                    $differentPrice = count(array_unique($prices)) > 1;

                    FyndiqUtils::debug('differentPrice', $differentPrice);
                    FyndiqUtils::debug('Product images', $this->productImages['product']);
                    FyndiqUtils::debug('articles images', $this->productImages['articles']);

                    //Need to remove the mainProduct so we won't get duplicates
                    reset($articles);
                    $articlekey = key($articles);
                    unset($articles[$articlekey]);

                    //If price is different, make all articles products and add specific images.
                    if ($differentPrice == true) {
                        //Make the rest of the articles look like products.
                        foreach ($articles as $key => $article) {
                            $imageId = 1;
                            $id = $article['article-sku'];
                            $article['product-id'] .= '-'.$key;

                            //We want to just add article's and the main products images to articles if split.
                            $images = $this->getImagesFromArray($id);
                            $article = array_merge($article, $images);

                            $articles[$key] = $article;
                        }
                    } else {
                        reset($articles);
                        //If the price is not differnet - add all images to product and articles.
                        foreach ($articles as $key => $article) {
                            $images = $this->getImagesFromArray();
                            $article = array_merge($article, $images);
                            $articles[$key] = $article;
                        }
                    }
                    FyndiqUtils::debug('articles to feed', $articles);
                    foreach ($articles as $article) {
                        $feedWriter->addProduct($article);
                    }
                } else {
                    //No configurable products or anything, just a lonely product

                    //Check if minimumQuantity is > 1, if it is it will skip this product.
                    if ($magProduct->getStockItem()->getMinSaleQty() > 1) {
                        FyndiqUtils::debug('min sale qty is > 1, SKIPPING PRODUCT');
                        continue;
                    }

                    //Just get the products images and add them all to the product.
                    $imageId = 1;
                    $product = $this->getProduct($magProduct, $productInfo[$parent_id], $store);
                    $this->getImages($magProduct->getId(), $magProduct, $productInfo[$parent_id]['id']);

                    $images = $this->getImagesFromArray();
                    $product = array_merge($product, $images);

                    FyndiqUtils::debug('simpleproduct images', $this->productImages);

                    $feedWriter->addProduct($product);
                }
            }
            $productsToExport->clear();
        }
        return $feedWriter->write();
    }


    private function getImagesFromArray($articleId = null)
    {
        $product = array();
        $imageId = 1;
        //If we don't want to add a specific article, add all of them.
        if (is_null($articleId)) {
            foreach ($this->productImages['product'] as $url) {
                if (!in_array($url, $product)) {
                    $product['product-image-' . $imageId . '-url'] = $url;
                    $product['product-image-' . $imageId . '-identifier'] = substr(md5($url), 0, 10);
                    $imageId++;
                }
            }
            foreach ($this->productImages['articles'] as $article) {
                foreach ($article as $url) {
                    if (!in_array($url, $product)) {
                        $product['product-image-' . $imageId . '-url'] = $url;
                        $product['product-image-' . $imageId . '-identifier'] = substr(md5($url), 0, 10);
                        $imageId++;
                    }
                }
            }
        // If we want to add just the product images and the article's images - run this.
        } else {
            foreach ($this->productImages['articles'][$articleId] as $url) {
                $product['product-image-' . $imageId . '-url'] = $url;
                $product['product-image-' . $imageId . '-identifier'] = substr(md5($url), 0, 10);
                $imageId++;
            }

            foreach ($this->productImages['product'] as $url) {
                $product['product-image-' . $imageId . '-url'] = $url;
                $product['product-image-' . $imageId . '-identifier'] = substr(md5($url), 0, 10);
                $imageId++;
            }
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
     * @param  object $productModel
     * @return array
     */
    protected function getImages($productId, $magProduct)
    {
        $this->productImages = array();
        $this->productImages['articles'] = array();
        $urls = $this->getProductImages($productId, $magProduct);
        $this->productImages['product'] = $urls;

        $simpleCollection = Mage::getModel('catalog/product_type_configurable')->setProduct($magProduct)->getUsedProductCollection()
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
        $images = Mage::getModel('catalog/product')->load($productId)->getMediaGalleryImages()->setOrder('position', 'ASC');
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
        if (!$this->categoryModel) {
            $this->categoryModel = Mage::getModel('catalog/category');
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

        $descriptionSetting = intval(FmConfig::get('description', $store));

        switch ($descriptionSetting) {
            case 1:
                $description = $magProduct->getDescription();
                break;
            case 2:
                $description = $magProduct->getShortDescription();
                break;
            case 3:
                $description = $magProduct->getShortDescription() . "\n\n" . $description = $magProduct->getDescription();
                break;
            default:
                $description = $magProduct->getDescription();
                break;
        }

        $magPrice = FmHelpers::getProductPrice($magProduct);

        $feedProduct['product-description'] = $description;

        $discount = $productInfo['exported_price_percentage'];
        $price = FyndiqUtils::getFyndiqPrice($magPrice, $discount);
        $feedProduct['product-price'] = FyndiqUtils::formatPrice($price);
        $feedProduct['product-vat-percent'] = $this->getTaxRate($magProduct, $store);
        $feedProduct['product-oldprice'] = FyndiqUtils::formatPrice($magPrice);
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

        if ($magArray['type_id'] == 'simple') {
            $qtyStock = $this->get_quantity($magProduct);

            $feedProduct['article-quantity'] = intval($qtyStock) < 0 ? 0 : intval($qtyStock);

            $feedProduct['article-location'] = self::UNKNOWN;
            $feedProduct['article-sku'] = $magProduct->getSKU();
            $feedProduct['article-name'] = $magArray['name'];

            $productParent = $productInfo['product_id'];
            if ($productParent) {
                $parentModel = Mage::getModel('catalog/product')->load($productParent);
                if (method_exists($parentModel->getTypeInstance(), 'getConfigurableAttributes')) {
                    $productAttrOptions = $parentModel->getTypeInstance()->getConfigurableAttributes();
                    $attrId = 1;
                    $tags = array();
                    foreach ($productAttrOptions as $productAttribute) {
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

        $qtyStock = $this->get_quantity($firstProduct);

        $feedProduct['article-quantity'] = intval($qtyStock) < 0 ? 0 : intval($qtyStock);

        $feedProduct['article-location'] = self::UNKNOWN;
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

    private function get_quantity($product)
    {
        $stock_item = Mage::getModel('cataloginventory/stock_item')->loadByProduct($product);
        if ($product->getStatus() != 1 || $stock_item->getIsInStock()== 0) {
            $qtyStock = 0;
        } else {
            $qtyStock = $stock_item->getQty();
        }
        FyndiqUtils::debug('$qtystock', $qtyStock);
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
