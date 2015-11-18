<?php

class Fyndiq_Fyndiq_Model_Setting extends Mage_Core_Model_Abstract
{

    public function _construct()
    {
        parent::_construct();
        $this->_init('fyndiq/setting');
    }

    private function getKey($storeId, $key)
    {
        return $key . '-' . $storeId;
    }

    public function settingExist($storeId, $key)
    {
        $collection = $this->getCollection()->addFieldToFilter(
            array('main_table.key'),
            array(
                array('like' => $this->getKey($storeId, $key))
            )
        )->load();
        if (count($collection) > 0) {
            $collection = $collection->getFirstItem();
            if ($collection->getId()) {
                return true;
            }
        }

        return false;
    }

    function getSetting($storeId, $key)
    {
        $collection = $this->getCollection()->addFieldToFilter(
            array('main_table.key'),
            array(
                array('eq' => $this->getKey($storeId, $key))
            )
        );
        if (count($collection) > 0) {
            $collection = $collection->getFirstItem();
            if ($collection->getId()) {
                return $collection->getData();
            }
        }

        return false;
    }

    public function saveSetting($storeId, $key, $value)
    {
        $data = array(
            'key' => $this->getKey($storeId, $key),
            'value' => $value
        );
        $model = $this->setData($data);

        return $model->save()->getId();
    }

    public function dropSetting($storeId, $key)
    {
        $collection = $this->getCollection()
            ->addFieldToFilter('main_table.key', $this->getKey($storeId, $key))
            ->getFirstItem();
        try {
            $this->setId($collection->getId())->delete();

            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    public function updateSetting($storeId, $key, $value)
    {
        $collection = $this->getCollection()
            ->addFieldToFilter('main_table.key', $this->getKey($storeId, $key))
            ->getFirstItem();
        $data = array('value' => $value);
        $model = $this->load($collection->getId())->addData($data);
        try {
            $model->setId($collection->getId())->save();

            return true;
        } catch (Exception $e) {
            return false;
        }
    }
}
