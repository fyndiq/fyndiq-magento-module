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
    private $fileresource = null;

    /**
     * Saving products to the file.
     */
    public function exportProducts($print = true)
    {
        if ($print) {
            print "Fyndiq :: Saving feed file\n";
        }
        $this->exportingProducts();
        if ($print) {
            print "Fyndiq :: Done saving feed file\n";
        }

    }

    public function importOrders()
    {
        try {
            $url = "orders/";
            $settingexists = Mage::getModel('fyndiq/setting')->settingExist("order_lastdate");
            if ($settingexists) {
                $date = Mage::getModel('fyndiq/setting')->getSetting("order_lastdate");
                $url .= "?min_date=" . urlencode($date["value"]);
            }
            $storeId = $this->getRequest()->getParam('store');
            $ret = FmHelpers::call_api($storeId, 'GET', $url);
            $newdate = date("Y-m-d H:i:s");
            if ($settingexists) {
                Mage::getModel('fyndiq/setting')->updateSetting("order_lastdate", $newdate);
            } else {
                Mage::getModel('fyndiq/setting')->saveSetting("order_lastdate", $newdate);
            }
            foreach ($ret["data"] as $order) {
                if (!Mage::getModel('fyndiq/order')->orderExists($order->id)) {
                    Mage::getModel('fyndiq/order')->create($order);
                }
            }
        } catch (Exception $e) {

        }
    }

    /**
     * Adding products added for export to the feed file
     *
     * @return string
     */
    private function exportingProducts()
    {
        $fileName = FmConfig::getFeedPath();
        $file = @fopen($fileName, 'w+');
        if ($file === false) {
            return false;
        }
        $feedWriter = new FyndiqCSVFeedWriter($file);
        $products = Mage::getModel('fyndiq/product')->getCollection()->setOrder('id', 'DESC');
        $products = $products->getItems();
        $ids_to_export = array();
        $productInfo = array();
        foreach ($products as $producted) {
            $product = $producted->getData();
            $ids_to_export[] = intval($product["product_id"]);
            $productInfo[$product["product_id"]] = $producted;
        }

        //Initialize models here so it saves memory.
        $product_model = Mage::getModel('catalog/product');

        $products_to_export = $product_model->getCollection()->addAttributeToSelect('*')->addAttributeToFilter(
            'entity_id',
            array('in' => $ids_to_export)
        )->load();

        foreach ($products_to_export as $magproduct) {

            if ($feedWriter->addProduct($this->getProduct($magproduct, $productInfo)) && $magproduct->getTypeId(
                ) != "simple"
            ) {
                $conf = Mage::getModel('catalog/product_type_configurable')->setProduct($magproduct);
                $simple_collection = $conf->getUsedProductCollection()->addAttributeToSelect(
                    '*'
                )->addFilterByRequiredOptions()->getItems();
                foreach ($simple_collection as $simple_product) {
                    $feedWriter->addProduct($this->getProduct($simple_product, $productInfo));
                }
            }
        }

        return $feedWriter->write();
    }


    private function getProduct($magproduct, $productInfo)
    {
        //Initialize models here so it saves memory.
        $product_model = Mage::getModel('catalog/product');
        $category_model = Mage::getModel('catalog/category');
        $stock_model = Mage::getModel('cataloginventory/stock_item');
        $image_helper = Mage::helper('catalog/image');

        $store = Mage::app()->getStore();
        $taxCalculation = Mage::getModel('tax/calculation');
        $magarray = $magproduct->getData();

        $feed_product = array();

        // Get taxrate
        $request = $taxCalculation->getRateRequest(null, null, null, $store);
        $taxClassId = $magproduct->getTaxClassId();
        $taxpercent = $taxCalculation->getRate($request->setProductClassId($taxClassId));
        // Setting the data
        if (isset($magarray["price"])) {
            $feed_product["product-id"] = $magarray["entity_id"];

            //Check if product have a parent
            $parent = false;
            if ($magarray["type_id"] == "simple") {
                $parentIds = Mage::getModel('catalog/product_type_grouped')->getParentIdsByChild(
                    $magarray["entity_id"]
                );
                if (!$parentIds) {
                    $parentIds = Mage::getModel('catalog/product_type_configurable')->getParentIdsByChild(
                        $magarray["entity_id"]
                    );
                }

                if ($parentIds) {
                    $parent = $parentIds[0];
                }
            }

            if ($parent != false) {
                $feed_product["product-id"] = $parent;
            }


            //images
            $imageid = 1;
            //trying to get image, if not image will be false
            try {
                $url = $magproduct->getImageUrl();
                $feed_product["product-image-" . $imageid . "-url"] = strval($url);
                $feed_product["product-image-" . $imageid . "-identifier"] =
                    substr(md5(strval($url)), 0, 10);
                $imageid++;
            } catch (Exception $e) {

            }
            $images = $product_model->load($magarray["entity_id"])->getMediaGalleryImages();
            if (isset($images)) {
                foreach ($images as $_image) {
                    $url = $image_helper->init($magproduct, 'image', $_image->getFile());
                    $feed_product["product-image-" . $imageid . "-url"] = strval($url);
                    $feed_product["product-image-" . $imageid . "-identifier"] =
                        substr(md5(strval($url)), 0, 10);
                    $imageid++;
                }
            }
            $feed_product["product-title"] = addslashes($magarray["name"]);
            $feed_product["product-description"] = addslashes($magproduct->getDescription());


            if ($magarray["type_id"] == "simple" AND isset($productInfo[$magarray["entity_id"]])) {
                $discount = $productInfo[$magarray['entity_id']]['exported_price_percentage'];
            } elseif ($magarray["type_id"] == 'simple') {
                if ($parent != false) {
                    $discount = $productInfo[$parent]['exported_price_percentage'];
                }
            } else {
                $discount = $productInfo[$magarray['entity_id']]['exported_price_percentage'];
            }
            $price = FyndiqUtils::getFyndiqPrice($magarray['price'], $discount);
            $feed_product["product-price"] = number_format((float)$price, 2, '.', '');
            $feed_product["product-vat-percent"] = $taxpercent;
            $feed_product["product-oldprice"] = number_format((float)$magarray["price"], 2, '.', '');
            $feed_product["product-market"] = Mage::getStoreConfig('general/country/default');
            $feed_product["product-currency"] = Mage::app()->getStore()->getCurrentCurrencyCode();
            // TODO: plan how to fix this brand issue
            $feed_product["product-brand"] = "Unknown";
            if ($magproduct->getAttributeText('manufacturer') != "") {
                $feed_product["product-brand"] = $magproduct->getAttributeText('manufacturer');
            }

            //Category
            $categoryIds = $magproduct->getCategoryIds();

            if (count($categoryIds) > 0) {
                $firstCategoryId = $categoryIds[0];
                $_category = $category_model->load($firstCategoryId);

                $feed_product["product-category-name"] = $_category->getName();
                $feed_product["product-category-id"] = $firstCategoryId;
            }


            if ($magarray["type_id"] == "simple") {

                $qtyStock = $stock_model->loadByProduct($magproduct->getId())->getQty();
                if (intval($qtyStock) < 0) {
                    $feed_product["article-quantity"] = intval(0);
                } else {
                    $feed_product["article-quantity"] = intval($qtyStock);
                }

                // TODO: fix location to something except test
                $feed_product["article-location"] = "test";
                $feed_product["article-sku"] = $magproduct->getSKU();
                if ($parent != false) {
                    $parentmodel = $product_model->load($parent);
                    if (method_exists($parentmodel->getTypeInstance(), 'getConfigurableAttributes')) {
                        $productAttributeOptions = $parentmodel->getTypeInstance()->getConfigurableAttributes();
                        $attrid = 1;
                        $tags = array();
                        foreach ($productAttributeOptions as $productAttribute) {
                            $attrValue = $parentmodel->getResource()->getAttribute(
                                $productAttribute->getProductAttribute()->getAttributeCode()
                            )->getFrontend();
                            $attrCode = $productAttribute->getProductAttribute()->getAttributeCode();
                            $value = $attrValue->getValue($magproduct);

                            $feed_product["article-property-name-" . $attrid] = $attrCode;
                            $feed_product["article-property-value-" . $attrid] = $value[0];
                            $tags[] = $attrCode . ": " . $value[0];
                            $attrid++;
                        }
                        $feed_product["article-name"] = implode(", ", $tags);
                    } else {
                        $feed_product["article-name"] = $magarray["name"];
                    }
                } else {
                    $feed_product["article-name"] = $magarray["name"];
                }
            } else {
                //Get child articles
                $conf = Mage::getModel('catalog/product_type_configurable')->setProduct($magproduct);
                $simple_collection = $conf->getUsedProductCollection()->addAttributeToSelect(
                    '*'
                )->addFilterByRequiredOptions()->getItems();
                //Get first article to the product.
                $first_product = array_shift($simple_collection);
                $qtyStock = $stock_model->loadByProduct($first_product->getId())->getQty();
                if (intval($qtyStock) < 0) {
                    $feed_product["article-quantity"] = intval(0);
                } else {
                    $feed_product["article-quantity"] = intval($qtyStock);
                }

                $images = $product_model->load($first_product->getId())->getMediaGalleryImages();
                if (isset($images)) {
                    $imageid = 1;
                    foreach ($images as $_image) {
                        $url = $image_helper->init($first_product, 'image', $_image->getFile());
                        $feed_article["product-image-" . $imageid . "-url"] = strval($url);
                        $feed_article["product-image-" . $imageid . "-identifier"] = substr(md5(strval($url)), 0, 10);
                        $imageid++;
                    }
                }

                // TODO: fix location to something except test
                $feed_product["article-location"] = "test";
                $feed_product["article-sku"] = $first_product->getSKU();
                $productAttributeOptions = $magproduct->getTypeInstance()->getConfigurableAttributes();
                $attrid = 1;
                $tags = array();
                foreach ($productAttributeOptions as $productAttribute) {
                    $attrValue = $magproduct->getResource()->getAttribute(
                        $productAttribute->getProductAttribute()->getAttributeCode()
                    )->getFrontend();
                    $attrCode = $productAttribute->getProductAttribute()->getAttributeCode();
                    $value = $attrValue->getValue($first_product);

                    $feed_product["article-property-name-" . $attrid] = $attrCode;
                    $feed_product["article-property-value-" . $attrid] = $value[0];
                    $tags[] = $attrCode . ": " . $value[0];
                    $attrid++;
                }
                $feed_product["article-name"] = substr(implode(", ", $tags), 0, 30);
            }
        }

        return $feed_product;
    }

    public function handle_fyndiqConfigChangedSection()
    {
        $storeId = Mage::app()->getRequest()->getParam('store');
        if (FmConfig::get('username',$storeId) !== ''
            && FmConfig::get('apikey', $storeId) !== '') {
            $data = array(
                'product_feed_url' => Mage::getUrl('fyndiq/file/index', array(
                    '_store' => $storeId,
                    '_nosid' => true,
                ))
            );
            FmHelpers::call_api($storeId, 'PATCH', 'settings/', $data);
        }
    }
}
