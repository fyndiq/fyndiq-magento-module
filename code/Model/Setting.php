<?php
class Fyndiq_Fyndiq_Model_Setting extends Mage_Core_Model_Abstract
{

    public function _construct()
    {
        parent::_construct();
        $this->_init('fyndiq/setting');
    }

    public function settingExist($key) {
        $collection = $this->getCollection()->addFieldToFilter(array('main_table.key'),
            array(
                array('like'=>$key)
            ))->load();
        if(count($collection) > 0) {
            $collection = $collection->getFirstItem();
            if ($collection->getId()) {
                return true;
            } else {
                return false;
            }
        }
        else {
            return false;
        }
    }

    function getSetting($key) {
        $collection = $this->getCollection()->addFieldToFilter(array('main_table.key'),
            array(
                array('eq'=>$key)
            ));
        Mage::log((string) $collection->getSelect());
        if(count($collection) > 0) {
            $collection = $collection->getFirstItem();
            if ($collection->getId()) {
                return $collection->getData();
            } else {
                return false;
            }
        }
        else {
            return false;
        }
    }

    public function saveSetting($key, $value) {
        $data = array('key' => $key, 'value' => $value);
        $model = $this->setData($data);

        return $model->save()->getId();
    }
    public function dropSetting($key) {
        $collection = $this->getCollection()->addFieldToFilter('main_table.key', $key)->getFirstItem();
        try {
            $this->setId($collection->getId())->delete();

            return true;

        } catch (Exception $e) {
            return false;
        }
    }
    public function updateSetting($key, $value) {
        $collection = $this->getCollection()->addFieldToFilter('main_table.key', $key)->getFirstItem();
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