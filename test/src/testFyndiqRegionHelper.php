<?php

class FyndiqRegionHelperTest extends PHPUnit_Framework_TestCase
{

    public function testCodeToRegionCodeDeProvider()
    {
        return array(
            array('0233', 'BER'),
            array('97888', 'BAY'),
            array('97896', 'BAW'),
            array('01001', 'SAS'),
        );
    }

    /**
     * @dataProvider testCodeToRegionCodeDeProvider
     */
    public function testCodeToRegionCodeDe($code, $expected)
    {
        $result = FyndiqRegionHelper::codeToRegionCodeDe($code);
        $this->assertEquals($expected, $result);
    }

    public function testGetRegionNameProvider()
    {
        return array(
            array('BER', 'Berlin'),
            array('BAY', 'Bayern'),
            array('SAS', 'Sachsen'),
            array('MEC', 'Mecklenburg-Vorpommern'),
        );
    }

    /**
     * @dataProvider testGetRegionNameProvider
     */
    public function testGetRegionName($code, $expected)
    {
        $result = FyndiqRegionHelper::getRegionName($code);
        $this->assertEquals($expected, $result);
    }

}
