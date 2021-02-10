<?php namespace BambooHR\Guardrail\Tests;

use BambooHR\Guardrail\Filters\EmitFilterApplier;
use PHPUnit\Framework\TestCase;

class EmitFilterApplierTest extends TestCase {
    public function testFindEntry() {
        $emitList = [];
        $fileName = 'asdf.php';
        $emittedMetric = 'Standard.Method.Call';
        $this->assertFalse(EmitFilterApplier::findEmitEntry($emitList, $fileName, $emittedMetric));
        $emitList = [
            'Standard.Method.Call'
        ];
        $this->assertEquals($emitList[0], EmitFilterApplier::findEmitEntry($emitList, $fileName, $emittedMetric));
        $emitList = [
            ['emit' => 'Standard.Method.Call']
        ];
        $this->assertEquals($emitList[0], EmitFilterApplier::findEmitEntry($emitList, $fileName, $emittedMetric));
        $emitList = [
            ['emit' => 'Standard.*']
        ];
        $this->assertEquals($emitList[0], EmitFilterApplier::findEmitEntry($emitList, $fileName, $emittedMetric));
        $emitList = [
            [
                'emit' => 'Standard.*',
                'ignore' => '*.php'
            ]
        ];
        $this->assertFalse(EmitFilterApplier::findEmitEntry($emitList, $fileName, $emittedMetric));
        $emitList = [
            [
                'emit' => 'Standard.*',
                'ignore' => '1234.php'
            ]
        ];
        $this->assertEquals($emitList[0], EmitFilterApplier::findEmitEntry($emitList, $fileName, $emittedMetric));
        $emitList = [
            [
                'emit' => 'Standard.*',
                'glob' => '1234.php'
            ]
        ];
        $this->assertFalse(EmitFilterApplier::findEmitEntry($emitList, $fileName, $emittedMetric));
        $emitList = [
            [
                'emit' => 'Standard.*',
                'glob' => '*.php'
            ]
        ];
        $this->assertEquals($emitList[0], EmitFilterApplier::findEmitEntry($emitList, $fileName, $emittedMetric));
    }

    //public function test
}