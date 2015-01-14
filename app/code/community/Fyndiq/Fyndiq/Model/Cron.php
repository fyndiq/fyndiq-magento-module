<?php

/**
 * Taking care of cron jobs for product feed.
 *
 * @author Håkan Nylén <hakan.nylen@fyndiq.se>
 */
require_once(dirname(dirname(__FILE__)) . '/includes/config.php');
class Fyndiq_Fyndiq_Model_Cron
{
    private $fileresource = null;

    /**
     * Saving products to the file.
     */
    function exportProducts() {
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
            $magorder = Mage::getModel('catalog/product')->load($product["product_id"]);

            // Get image
            try {
                $imgSrc = (string)Mage::helper('catalog/image')->init($magorder, 'image');
            }
            catch(Exception $e) {
                $imgSrc = "";
            }

            // Setting the data
            $magarray = $magorder->getData();
            $real_array = array();
            $real_array["product-id"] = $product["product_id"];
            $real_array["product-image-1"] = strval($imgSrc);
            $real_array["product-title"] = $magarray["name"];
            $real_array["product-description"] = $magorder->getDescription();
            $real_array["product-price"] = $magarray["price"]-($magarray["price"]*($product["exported_price_percentage"] / 100));
            $real_array["product-oldprice"] = $magarray["price"];
            $real_array["product-market"] = "SE"; // TODO: fix so this use the settings or being get from Magento itself.
            $real_array["product-currency"] = "SEK"; // TODO: same here as above

            //Articles
            $real_array["article-quantity"] = $product["exported_qty"];
            $return_array[] = $real_array;
        }
        $first_array = array_values($return_array)[0];
        $key_values = array_keys($first_array);
        array_unshift($return_array, $key_values);
        $data = "";
        foreach ($return_array as $product_array) {
            $data .='"' . implode('", "', $product_array) . '"' . "\n";
        }
        return $data;
    }

    /**
     * Write over a existing file if it exists and write all fields.
     *
     * @param $products
     */
    function writeOverFile($products)
    {
        $this->openFile(true);
        $this->writeToFile($products);
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
        return fputs($this->fileresource, $fields);
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