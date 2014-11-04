<?php

/**
 * Taking care of the feed file
 *
 * @author Håkan Nylén <hakan.nylen@fyndiq.se>
 */
class FmFileHandler
{
    private $filepath = "fyndiq/files/feed.csv";
    private $fileresource = null;

    /**
     * Add lines to a already used file
     *
     * @param $products
     */
    function appendToFile($products)
    {
        $this->openFile();
        foreach ($products as $product) {
            $this->writeToFile($product);
        }
        $this->closeFile();
    }

    /**
     * Write over a existing file if it exists and write all fields.
     *
     * @param $products
     */
    function writeOverFile($products)
    {
        $this->openFile(true);
        foreach ($products as $product) {
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
        if ($removeFile && file_exists($this->filepath)) {
            unlink($this->filepath);
        }
        $this->closeFile();
        $this->fileresource = fopen($this->filepath, 'w+');
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

    /**
     * Closing the file if it isn't already closed when destructing the class.
     */
    function __destruct()
    {
        if ($this->fileresource != null) {
            $this->closeFile();
        }
    }

}