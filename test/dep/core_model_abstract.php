<?php
/**
 * Created by PhpStorm.
 * User: confact
 * Date: 11/06/15
 * Time: 16:57
 */

class Mage_Core_Model_Abstract
{
    function getCollection()
    {
        return $this;
    }
    function setOrder($id, $sort)
    {
        return new Magdata();
    }
    function addAttributeToSelect($select)
    {
        return $this;
    }
    function addStoreFilter($id)
    {
        return $this;
    }
    function addAttributeToFilter($field, $field)
    {
        return $this;
    }
    function load()
    {
        return $this;
    }
}
class Magdata
{

    function __construct()
    {
        $this->items = array();
        for($i = 0;$i<6;$i++)
        {
            $prod = new MagProd();
            $prod->product_id = $i;
            $prod->id = $i;
            $this->items[] = $prod;
        }
    }

    function getItems()
    {
        return $this->items;
    }
}
class MagProd
{
    function getData()
    {
        return json_decode(json_encode($this), true);
    }
}
