<?php

class FyndiqRegionHelperTest extends PHPUnit_Framework_TestCase
{

    public function testCodeToRegionCodeProvider()
    {
        return array(
            array('0233', 'BER', Fyndiq_Fyndiq_Helper_Region::CODE_DE),
            array('97888', 'BAY', Fyndiq_Fyndiq_Helper_Region::CODE_DE),
            array('97896', 'BAW', Fyndiq_Fyndiq_Helper_Region::CODE_DE),
            array('01001', 'SAS', Fyndiq_Fyndiq_Helper_Region::CODE_DE),
            array('80337', 'BAY', Fyndiq_Fyndiq_Helper_Region::CODE_DE),
            array('23332', '2xxxx', Fyndiq_Fyndiq_Helper_Region::CODE_SE),
        );
    }

    /**
     * @dataProvider testCodeToRegionCodeProvider
     */
    public function testCodeToRegionCode($code, $expected, $countryCode)
    {
        $result = Fyndiq_Fyndiq_Helper_Region::codeToRegionCode($code, $countryCode);
        $this->assertEquals($expected, $result);
    }

    public function testGetRegionNameProvider()
    {
        return array(
            array('BER', 'Berlin', Fyndiq_Fyndiq_Helper_Region::CODE_DE),
            array('BAY', 'Bayern', Fyndiq_Fyndiq_Helper_Region::CODE_DE),
            array('SAS', 'Sachsen', Fyndiq_Fyndiq_Helper_Region::CODE_DE),
            array('MEC', 'Mecklenburg-Vorpommern', Fyndiq_Fyndiq_Helper_Region::CODE_DE),
            array('2xxxx', 'Skåne', Fyndiq_Fyndiq_Helper_Region::CODE_SE),
        );
    }

    /**
     * @dataProvider testGetRegionNameProvider
     */
    public function testGetRegionName($code, $expected, $countryCode)
    {
        $result = Fyndiq_Fyndiq_Helper_Region::getRegionName($code, $countryCode);
        $this->assertEquals($expected, $result);
    }
}
