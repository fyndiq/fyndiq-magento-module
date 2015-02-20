<?php
require_once(dirname(dirname(__FILE__)) . '/includes/config.php');
require_once(dirname(dirname(__FILE__)) . '/includes/helpers.php');

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
        $this->writeOverFile($this->printFile());
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
            $ret = FmHelpers::call_api('GET', $url);
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
    private function printFile()
    {
        $products = Mage::getModel('fyndiq/product')->getCollection()->setOrder('id', 'DESC');
        $products = $products->getItems();
        $return_array = array();
        $ids_to_export = array();
        $productinfo = array();
        foreach ($products as $producted) {
            $product = $producted->getData();
            $ids_to_export[] = intval($product["product_id"]);
            $productinfo[$product["product_id"]] = $producted;
        }

        //Initialize models here so it saves memory.
        $product_model = Mage::getModel('catalog/product');
        $category_model = Mage::getModel('catalog/category');
        $stock_model = Mage::getModel('cataloginventory/stock_item');
        $grouped_model = Mage::getModel('catalog/product_type_grouped');
        $configurable_model = Mage::getModel('catalog/product_type_configurable');
        $image_helper = Mage::helper('catalog/image');

        $store = Mage::app()->getStore();
        $taxCalculation = Mage::getModel('tax/calculation');

        $products_to_export = $product_model->getCollection()->addAttributeToSelect('*')->addAttributeToFilter(
            'entity_id',
            array('in' => $ids_to_export)
        )->load();

        foreach ($products_to_export as $magproduct) {

            // Get the data
            $magarray = $magproduct->getData();
            $feed_product = array();

            $images = $product_model->load($magproduct->getId())->getMediaGalleryImages();

            // Get taxrate
            $request = $taxCalculation->getRateRequest(null, null, null, $store);
            $taxClassId = $magproduct->getTaxClassId();
            $taxpercent = $taxCalculation->getRate($request->setProductClassId($taxClassId));


            // Setting the data
            if (isset($magarray["price"])) {
                $feed_product["product-id"] = $productinfo[$magarray["entity_id"]]["product_id"];

                if ($images) {
                    $imageid = 1;
                    foreach ($images as $_image) {
                        $url = $image_helper->init($magproduct, 'image', $_image->getFile());
                        $feed_product["product-image-" . $imageid . "-url"] = addslashes(strval($url));
                        $feed_product["product-image-" . $imageid . "-identifier"] = addslashes(
                            substr(md5(strval($url)), 0, 10)
                        );
                        $imageid++;
                    }
                }
                $feed_product["product-title"] = addslashes($magarray["name"]);
                $feed_product["product-description"] = addslashes($magproduct->getDescription());
                $feed_product["product-price"] = $magarray["price"] - ($magarray["price"] * ($productinfo[$magarray["entity_id"]]["exported_price_percentage"] / 100));
                $feed_product["product-price"] = number_format((float)$feed_product["product-price"], 2, '.', '');
                $feed_product["product-vat-percent"] = $taxpercent;
                $feed_product["product-oldprice"] = number_format((float)$magarray["price"], 2, '.', '');
                $feed_product["product-market"] = addslashes(Mage::getStoreConfig('general/country/default'));
                $feed_product["product-currency"] = Mage::app()->getStore()->getCurrentCurrencyCode();
                // TODO: plan how to fix this brand issue
                $feed_product["product-brand"] = addslashes($magproduct->getAttributeText('manufacturer'));

                //Category
                $categoryIds = $magproduct->getCategoryIds();

                if (count($categoryIds) > 0) {
                    $firstCategoryId = $categoryIds[0];
                    $_category = $category_model->load($firstCategoryId);

                    $feed_product["product-category-name"] = addslashes($_category->getName());
                    $feed_product["product-category-id"] = $firstCategoryId;
                }


                if ($magproduct->getTypeId() == 'simple') {
                    $qtyStock = $stock_model->loadByProduct($magproduct->getId())->getQty();
                    if (intval($qtyStock) < 0) {
                        $feed_product["article-quantity"] = intval(0);
                    } else {
                        $feed_product["article-quantity"] = intval($qtyStock);
                    }

                    // TODO: fix location to something except test
                    $feed_product["article-location"] = "test";
                    $feed_product["article-sku"] = $magproduct->getSKU();
                    /*$productAttributeOptions = $magproduct->getTypeInstance()->getConfigurableAttributes();
                    $attrid = 1;
                    $tags = "";
                    foreach ($productAttributeOptions as $productAttribute) {
                        $attrValue = $magproduct->getResource()->getAttribute($productAttribute->getProductAttribute()->getAttributeCode())->getFrontend();
                        $attrCode = $productAttribute->getProductAttribute()->getAttributeCode();
                        $value = $attrValue->getValue($magproduct);

                        $feed_product["article-property-name-".$attrid] = $attrCode;
                        $feed_product["article-property-value-".$attrid] = $value[0];
                        if($attrid == 1) {
                            $tags .= $attrCode.": ".$value[0];
                        }
                        else {
                            $tags .= ", ".$attrCode.": ".$value[0];
                        }
                        $attrid++;
                    }*/
                    $feed_product["article-name"] = addslashes($magarray["name"]);
                    $return_array[] = $feed_product;
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

                    // TODO: fix location to something except test
                    $feed_product["article-location"] = "test";
                    $feed_product["article-sku"] = $first_product->getSKU();
                    $productAttributeOptions = $magproduct->getTypeInstance()->getConfigurableAttributes();
                    $attrid = 1;
                    $tags = "";
                    foreach ($productAttributeOptions as $productAttribute) {
                        $attrValue = $magproduct->getResource()->getAttribute(
                            $productAttribute->getProductAttribute()->getAttributeCode()
                        )->getFrontend();
                        $attrCode = $productAttribute->getProductAttribute()->getAttributeCode();
                        $value = $attrValue->getValue($first_product);

                        $feed_article["article-property-name-" . $attrid] = $attrCode;
                        $feed_article["article-property-value-" . $attrid] = $value[0];
                        if ($attrid == 1) {
                            $tags .= $attrCode . ": " . $value[0];
                        } else {
                            $tags .= ", " . $attrCode . ": " . $value[0];
                        }
                        $attrid++;
                    }
                    $feed_product["article-name"] = substr(addslashes($tags), 0, 30);
                    $return_array[] = $feed_product;

                    //Articles
                    foreach ($simple_collection as $simple_product) {
                        $feed_article = $feed_product;
                        $qtyStock = $stock_model->loadByProduct($simple_product->getId())->getQty();
                        if (intval($qtyStock) < 0) {
                            $feed_article["article-quantity"] = intval(0);
                        } else {
                            $feed_article["article-quantity"] = intval($qtyStock);
                        }

                        // TODO: fix location to something except test
                        $feed_article["article-location"] = "test";
                        $feed_article["article-sku"] = $simple_product->getSKU();

                        $images = $product_model->load($simple_product->getId())->getMediaGalleryImages();
                        if ($images) {
                            $imageid = 1;
                            foreach ($images as $_image) {
                                $url = $image_helper->init($simple_product, 'image', $_image->getFile());
                                $feed_article["product-image-" . $imageid . "-url"] = addslashes(strval($url));
                                $feed_article["product-image-" . $imageid . "-identifier"] = addslashes(
                                    substr(md5(strval($url)), 0, 10)
                                );
                                $imageid++;
                            }
                        }
                        $attrid = 1;
                        $tags = "";
                        foreach ($productAttributeOptions as $productAttribute) {
                            $attrValue = $magproduct->getResource()->getAttribute(
                                $productAttribute->getProductAttribute()->getAttributeCode()
                            )->getFrontend();
                            $attrCode = $productAttribute->getProductAttribute()->getAttributeCode();
                            $value = $attrValue->getValue($simple_product);

                            $feed_article["article-property-name-" . $attrid] = $attrCode;
                            $feed_article["article-property-value-" . $attrid] = $value[0];
                            if ($attrid == 1) {
                                $tags .= $attrCode . ": " . $value[0];
                            } else {
                                $tags .= ", " . $attrCode . ": " . $value[0];
                            }
                            $attrid++;
                        }
                        $feed_product["article-name"] = substr(addslashes($tags), 0, 30);
                        $return_array[] = $feed_article;
                    }

                }
            }
        }

        $tempKeys = array();
        foreach ($return_array as $array) {
            if (count($tempKeys) < count(array_keys($array))) {
                $tempKeys = array_keys($array);
            }
        }
        foreach ($return_array as $key => $array) {
            foreach ($tempKeys as $keys) {
                if (!array_key_exists($keys, $array)) {
                    $array[$keys] = "";
                }
            }
            $return_array[$key] = $array;
        }


        array_unshift($return_array, $tempKeys);

        return $return_array;
    }

    /**
     * Write over a existing file if it exists and write all fields.
     *
     * @param $products
     */
    function writeOverFile($products)
    {
        $this->openFile(true);
        $keys = array_shift($products);
        $this->writeheader($keys);
        foreach ($products as $product) {
            $this->writeToFile($product, $keys);
        }
        $this->closeFile();
    }


    /**
     * Write the header to file
     *
     * @param $keys
     */
    function writeHeader($keys)
    {
        fputcsv($this->fileresource, $keys);
    }

    /**
     * simplifying the way to write to the file.
     *
     * @param $fields
     * @return int|boolean
     */
    private function writeToFile($fields, $keys)
    {
        $printarray = array();
        foreach ($keys as $key) {
            $printarray[] = $fields[$key];
        }

        return fputcsv($this->fileresource, $printarray);
    }

    /**
     * opening the file resource
     *
     * @param bool $removeFile
     */
    private function openFile($removeFile = false)
    {
        $path = FmConfig::getFeedPath();
        if ($removeFile && file_exists($path)) {
            unlink($path);
        }
        $this->closeFile();
        $this->fileresource = fopen($path, 'w+');
    }

    /**
     * Closing the file if isn't already closed
     */
    private function closeFile()
    {
        if ($this->fileresource != null) {
            fclose($this->fileresource);
            $this->fileresource = null;
        }
    }
}