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
        $date = false;
        $settingExists = Mage::getModel('fyndiq/setting')->settingExist($storeId, 'order_lastdate');
        if ($settingExists) {
            $date = Mage::getModel('fyndiq/setting')->getSetting($storeId, 'order_lastdate');
        }
        $url = 'orders/' . (empty($date) ? '' : '?min_date=' . urlencode($date['value']));

        $ret = FmHelpers::callApi($storeId, 'GET', $url);
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
        foreach ($products as $product) {
            $productData = $product->getData();
            $idsToExport[] = intval($productData['product_id']);
            $productInfo[$productData['product_id']] = $productData;
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
            $parent_id = $magProduct->getId();
            $singleProductInfo = $productInfo[$parent_id];
            if ($feedWriter->addProduct($this->getProduct($magProduct, $singleProductInfo))
                && $magProduct->getTypeId() != 'simple'
            ) {
                $conf = Mage::getModel('catalog/product_type_configurable')->setProduct($magProduct);
                $simpleCollection = $conf->getUsedProductCollection()
                    ->addAttributeToSelect('*')
                    ->addFilterByRequiredOptions()
                    ->getItems();
                foreach ($simpleCollection as $simpleProduct) {
                    $feedWriter->addProduct($this->getProduct($simpleProduct, $singleProductInfo));
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
    private function getTaxRate($product) {
        //
        $store = Mage::app()->getStore();
        $taxCalculation = Mage::getModel('tax/calculation');

        $request = $taxCalculation->getRateRequest(null, null, null, $store);
        $taxClassId = $product->getTaxClassId();
        return  $taxCalculation->getRate($request->setProductClassId($taxClassId));
    }

    private function getProductImages($productModel, $magProduct) {
        $images = array();
        $imageHelper = Mage::helper('catalog/image');
        //trying to get image, if not image will be false
        try {
            $images[] = $magProduct->getImageUrl();
        } catch (Exception $e) {
        }

        $imageList = $productModel->load($magProduct->entity_id)->getMediaGalleryImages();
        if (is_array($imageList)) {
            foreach ($imageList as $image) {
                $images[] = $imageHelper->init($magProduct, 'image', $image->getFile());
            }
        }
        return $images;
    }

    function getAttributes($productAttrOptions, $parentModel, $product)
    {
        $attributes = array();
        foreach ($productAttrOptions as $productAttribute) {
            $attrValue = $parentModel->getResource()->getAttribute(
                $productAttribute->getProductAttribute()->getAttributeCode()
            )->getFrontend();
            $attrCode = $productAttribute->getProductAttribute()->getAttributeCode();
            $value = $attrValue->getValue($product);
            $attributes[] = array(
                'name' => $attrCode,
                'value' => array_shift($value),
            );
        }
        return $attributes;
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
        //Initialize models here so it saves memory.
        $productModel = Mage::getModel('catalog/product');
        $categoryModel = Mage::getModel('catalog/category');
        $stockModel = Mage::getModel('cataloginventory/stock_item');
        $imageHelper = Mage::helper('catalog/image');

        $feedProduct = array();
        $magArray = $magProduct->getData();

        if (!isset($magArray['price'])) {
            return $feedProduct;
        }

        // Setting the data
        $feedProduct['product-id'] = $productInfo['id'];

        //images
        $images = $this->getProductImages($productModel, $magProduct);
        foreach ($images as $imageId => $url) {
            $feedProduct['product-image-' . $imageId + 1 . '-url'] = $url;
            $feedProduct['product-image-' . $imageId + 1 . '-identifier'] = substr(md5($url), 0, 10);
        }

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
        $feedProduct['product-brand'] = $brand ? strval($brand): self::UNKNOWN;

        //Category
        $categoryIds = $magProduct->getCategoryIds();
        if (count($categoryIds) > 0) {
            $firstCategoryId = array_shift($categoryIds);
            $firstCategory = $categoryModel->load($firstCategoryId);
            $feedProduct['product-category-id'] = $firstCategoryId;
            $feedProduct['product-category-name'] = $firstCategory->getName();
        }

        if ($magArray['type_id'] == 'simple') {
            $qtyStock = $stockModel->loadByProduct($magProduct->getId())->getQty();
            $feedProduct['article-quantity'] = intval($qtyStock) < 0 ? 0 : intval($qtyStock);

            $location = $magProduct->getAttributeText('location');
            $feedProduct['product-location'] = $location ? strval($location) : self::UNKNOWN;
            $feedProduct['article-sku'] = $magProduct->getSKU();
            $feedProduct['article-name'] = $magArray['name'];

            $parentModel = $productModel->load($productInfo['product_id']);
            if (method_exists($parentModel->getTypeInstance(), 'getConfigurableAttributes')) {
                $productAttrOptions = $parentModel->getTypeInstance()->getConfigurableAttributes();
                $attributes = getAttributes($productAttrOptions, $parentModel, $magProduct);
                $tags = array();
                foreach($attributes as $id => $row) {
                    $feedProduct['article-property-name-' . $id + 1] = $row['name'];
                    $feedProduct['article-property-value-' . $id + 1] = $row['value'];
                    $tags[] = implode(': ', $row);
                }
            }

            // We're done
            return $feedProduct;
        }

        //Get child articles
        $conf = Mage::getModel('catalog/product_type_configurable')->setProduct($magProduct);
        $simpleCollection = $conf->getUsedProductCollection()->addAttributeToSelect('*')
            ->addFilterByRequiredOptions()->getItems();

        //Get first article to the product.
        $firstProduct = array_shift($simpleCollection);
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

        $location = $magProduct->getAttributeText('location');
        $feedProduct['product-location'] = $location ? strval($location) : self::UNKNOWN;
        $feedProduct['article-sku'] = $firstProduct->getSKU();
        $productAttrOptions = $magProduct->getTypeInstance()->getConfigurableAttributes();

        $attributes = getAttributes($productAttrOptions, $magProduct, $firstProduct);
        $tags = array();
        foreach($attributes as $id => $row) {
            $feedProduct['article-property-name-' . $id + 1] = $row['name'];
            $feedProduct['article-property-value-' . $id + 1] = $row['value'];
            $tags[] = implode(': ', $row);
        }
        $feedProduct['article-name'] = substr(implode(', ', $tags), 0, 30);

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
            return FmHelpers::callApi($storeId, 'PATCH', 'settings/', $data);
        }
        throw new Exception(FyndiqTranslation::get('empty-username-token'));
    }
}
