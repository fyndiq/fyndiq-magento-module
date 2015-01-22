<?php
require_once(dirname(dirname(__FILE__)) . '/includes/config.php');
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
    public function exportProducts($print = true) {
        if($print) {
            print "Fyndiq :: Saving feed file\n";
        }
        $this->writeOverFile($this->printFile());
        if($print) {
            print "Fyndiq :: Done saving feed file\n";
        }

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
        $ids_to_export = array();
        $productinfo = array();
        foreach($products as $producted) {
            $product = $producted->getData();
            $ids_to_export[] = intval($product["product_id"]);
            $productinfo[$product["product_id"]] = $producted;
        }

        //Initialize models here so it saves memory.
        $product_model = Mage::getModel('catalog/product');
        $category_model = Mage::getModel('catalog/category');
        $stock_model = Mage::getModel('cataloginventory/stock_item');

        $products_to_export = $product_model->getCollection()->addAttributeToSelect('*')->addAttributeToFilter( 'entity_id', array( 'in' => $ids_to_export))->load();

        foreach ($products_to_export as $magproduct) {

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
            if(isset($magarray["price"])) {
                $real_array["product-id"] = $productinfo[$magarray["entity_id"]]["product_id"];
                $real_array["product-image-1"] = addslashes(strval($imgSrc));
                $real_array["product-title"] = addslashes($magarray["name"]);
                $real_array["product-description"] = addslashes($magproduct->getDescription());
                $real_array["product-price"] = $magarray["price"]-($magarray["price"]*($productinfo[$magarray["entity_id"]]["exported_price_percentage"] / 100));
                $real_array["product-price"] = number_format((float)$real_array["product-price"], 2, '.', '');
                $real_array["product-vat-percent"] = "25";
                $real_array["product-oldprice"] = number_format((float)$magarray["price"], 2, '.', '');
                $real_array["product-market"] = addslashes(FmConfig::get('country'));
                $real_array["product-currency"] = FmConfig::get('currency');
                // TODO: plan how to fix this brand issue
                $real_array["product-brand"] = "test";

                //Category
                $categoryIds = $magproduct->getCategoryIds();

                if(count($categoryIds) > 0){
                    $firstCategoryId = $categoryIds[0];
                    $_category = $category_model->load($firstCategoryId);

                    $real_array["product-category-name"] = addslashes($_category->getName());
                    $real_array["product-category-id"] = $firstCategoryId;
                }

                //Articles
                $qtyStock = $stock_model->loadByProduct($real_array["product-id"])->getQty();
                $real_array["article-quantity"] = intval($qtyStock);
                $real_array["article-name"] = addslashes($magarray["name"]);
                // TODO: fix location to something except test
                $real_array["article-location"] = "test";
                $real_array["article-sku"] = $magproduct->getSKU();
                $return_array[] = $real_array;
            }
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