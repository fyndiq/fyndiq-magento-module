<?php

/**
 * Taking care of cron jobs for product feed.
 *
 * @author Håkan Nylén <hakan.nylen@fyndiq.se>
 */
require_once(dirname(dirname(__FILE__)) . '/includes/config.php');
class Fyndiq_Fyndiq_Model_FyndiqCron
{
    private $fileresource = null;

    /**
     * Saving products to the file.
     */
    public function exportProducts() {
        $this->writeOverFile($this->printFile());
    }

    /**
     * Adding products added for export to the feed file
     *
     * @return string
     */
    private function printFile() {
        $products = Mage::getModel('fyndiq/product')->getCollection()->setOrder('id', 'DESC');
        $products = $products->getItems();
        $return_array = array();
        foreach ($products as $producted) {

            //Getting more data from Magento.
            $product = $producted->getData();
            $magproduct = Mage::getModel('catalog/product')->load($product["product_id"]);

            // Get image
            try {
                $imgSrc = (string)Mage::helper('catalog/image')->init($magproduct, 'image');
            }
            catch(Exception $e) {
                $imgSrc = "";
            }

            // Setting the data
            $magarray = $magproduct->getData();
            $real_array = array();
            $real_array["product-id"] = $product["product_id"];
            $real_array["product-image-1"] = strval($imgSrc);
            $real_array["product-title"] = $magarray["name"];
            $real_array["product-description"] = $magproduct->getDescription();
            $real_array["product-price"] = $magarray["price"]-($magarray["price"]*($product["exported_price_percentage"] / 100));
            $real_array["product-price"] = number_format((float)$real_array["product-price"], 2, '.', '');
            $real_array["product-vat-percent"] = "25";
            $real_array["product-oldprice"] = number_format((float)$magarray["price"], 2, '.', '');
            $real_array["product-market"] = FmConfig::get('country');
            $real_array["product-currency"] = FmConfig::get('currency');
            // TODO: plan how to fix this brand issue
            $real_array["product-brand"] = "test";

            //Category
            $categoryIds = $magproduct->getCategoryIds();

            if(count($categoryIds) ){
                $firstCategoryId = $categoryIds[0];
                $_category = Mage::getModel('catalog/category')->load($firstCategoryId);

                $real_array["product-category-name"] = $_category->getName();
                $real_array["product-category-id"] = $firstCategoryId;
            }

            //Articles
            $real_array["article-quantity"] = $product["exported_qty"];
            $real_array["article-name"] = $magarray["name"];
            // TODO: fix location to something except test
            $real_array["article-location"] = "test";
            $real_array["article-sku"] = $magproduct->getSKU();
            $return_array[] = $real_array;
        }
        $first_array = array_values($return_array)[0];
        $key_values = array_keys($first_array);
        array_unshift($return_array, $key_values);
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
        foreach($products as $product) {
            $this->writeToFile($product);
        }
        $this->closeFile();
    }

    /**
     * simplifying the way to write to the file.
     *
     * @param $fields
     * @return int|boolean
     */
    private function writeToFile($fields)
    {
        return fputcsv($this->fileresource, $fields);
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