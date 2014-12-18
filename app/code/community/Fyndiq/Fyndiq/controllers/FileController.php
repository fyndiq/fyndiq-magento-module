<?php

class Fyndiq_Fyndiq_FileController extends Mage_Core_Controller_Front_Action {


    function indexAction() {
        $this->getResponse()->setHeader('Content-type', 'text/csv');
        $this->getResponse()->setBody($this->printFile());
    }

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

}