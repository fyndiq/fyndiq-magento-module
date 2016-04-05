<?php
/**
 * Created by PhpStorm.
 * User: confact
 * Date: 11/06/15
 * Time: 16:57
 */

class Mage_Core_Model_Abstract
{
    public function getCollection()
    {
        return $this;
    }
    public function setOrder($id, $sort)
    {
        return new Magdata();
    }
    public function addAttributeToSelect($select)
    {
        return $this;
    }
    public function addStoreFilter($id)
    {
        return $this;
    }
    public function addAttributeToFilter($field, $field)
    {
        return $this;
    }
    public function load()
    {
        return $this;
    }
}
class Magdata
{

    public function __construct()
    {
        $this->items = array();
        for ($i = 0; $i<6; $i++) {
            $prod = new MagProd();
            $prod->product_id = $i;
            $prod->id = $i;
            $this->items[] = $prod;
        }
    }

    public function getItems()
    {
        return $this->items;
    }
}
class MagProd
{
    public function getData()
    {
        return json_decode(json_encode($this), true);
    }
}
