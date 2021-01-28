<?php namespace BambooHR\Guardrail\Tests;

use BambooHR\Guardrail\Filters\EmitFilterApplier;
use PHPUnit\Framework\TestCase;

class EmitFilterApplierTest extends TestCase {
    public function testFindEntry() {
        $this->assertFalse(EmitFilterApplier::findEmitEntry([], 'Standard.Method.Call'));
        $this->assertEquals('Standard.Method.Call', EmitFilterApplier::findEmitEntry([
            'Standard.Method.Call'
        ], 'Standard.Method.Call'));
        $this->assertEquals(['emit' => 'Standard.Method.Call'], EmitFilterApplier::findEmitEntry([
            ['emit' => 'Standard.Method.Call']
        ], 'Standard.Method.Call'));
    }
}