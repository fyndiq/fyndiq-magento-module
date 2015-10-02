<?php

class FyndiqRegionHelperTest extends PHPUnit_Framework_TestCase
{

    public function testCodeToRegionCodeProvider()
    {
        return array(
            array('0233', 'BER', FyndiqRegionHelper::CODE_DE),
            array('97888', 'BAY', FyndiqRegionHelper::CODE_DE),
            array('97896', 'BAW', FyndiqRegionHelper::CODE_DE),
            array('01001', 'SAS', FyndiqRegionHelper::CODE_DE),
            array('80337', 'BAY', FyndiqRegionHelper::CODE_DE),
            array('23332', '2xxxx', FyndiqRegionHelper::CODE_SE),
        );
    }

    /**
     * @dataProvider testCodeToRegionCodeProvider
     */
    public function testCodeToRegionCode($code, $expected, $countryCode)
    {
        $result = FyndiqRegionHelper::codeToRegionCode($code, $countryCode);
        $this->assertEquals($expected, $result);
    }

    public function testGetRegionNameProvider()
    {
        return array(
            array('BER', 'Berlin', FyndiqRegionHelper::CODE_DE),
            array('BAY', 'Bayern', FyndiqRegionHelper::CODE_DE),
            array('SAS', 'Sachsen', FyndiqRegionHelper::CODE_DE),
            array('MEC', 'Mecklenburg-Vorpommern', FyndiqRegionHelper::CODE_DE),
            array('2xxxx', 'Skåne', FyndiqRegionHelper::CODE_SE),
        );
    }

    /**
     * @dataProvider testGetRegionNameProvider
     */
    public function testGetRegionName($code, $expected, $countryCode)
    {
        $result = FyndiqRegionHelper::getRegionName($code, $countryCode);
        $this->assertEquals($expected, $result);
    }
}
