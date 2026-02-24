<?php namespace BambooHR\Guardrail\Tests\Checks;

use BambooHR\Guardrail\Checks\DependenciesOnVendorCheck;
use BambooHR\Guardrail\Metrics\Metric;
use BambooHR\Guardrail\Metrics\MetricOutputInterface;
use BambooHR\Guardrail\Output\OutputInterface;
use BambooHR\Guardrail\SymbolTable\InMemorySymbolTable;
use BambooHR\Guardrail\Tests\TestSuiteSetup;
use PhpParser\Node\Stmt\Class_;

/**
 * Class TestDependenciesOnVendorCheck
 *
 * @package BambooHR\Guardrail\Tests\Checks
 */
class TestDependenciesOnVendorCheck extends TestSuiteSetup {
	public function testGetCheckNodeTypes() {
		$check = new DependenciesOnVendorCheck(
			new InMemorySymbolTable('/base'),
			$this->createMock(OutputInterface::class),
			$this->createMock(MetricOutputInterface::class)
		);
		$types = $check->getCheckNodeTypes();
		$this->assertContains(Class_::class, $types);
	}

	public function testGetNameFileSkipsScalarAndUnqualified() {
		$check = new DependenciesOnVendorCheck(
			new InMemorySymbolTable('/base'),
			$this->createMock(OutputInterface::class),
			$this->createMock(MetricOutputInterface::class)
		);

		$this->assertSame('', $check->getNameFile('int'));
		$this->assertSame('', $check->getNameFile('LocalClass'));
		$this->assertSame('', $check->getNameFile('Vendor\\Missing\\Class'));
	}

	public function testEmitsMetricForVendorReference() {
		$testDataDirectory = $this->getCallerTestDataDirectory($this);
		$symbolTable = new InMemorySymbolTable('/base');
		$vendorNode = $this->parseText(file_get_contents($testDataDirectory . '.2.inc'))[0];
		$symbolTable->addClass('Vendor\\Package\\Dep', $vendorNode, 'vendor/acme/package/Dep.php');

		$metricOutput = $this->createMock(MetricOutputInterface::class);
		$metricOutput->expects($this->once())
			->method('emitMetric')
			->with($this->callback(function ($metric) {
				if (!$metric instanceof Metric) {
					return false;
				}
				$data = $metric->getData();
				return $metric->getType() === 'Standard.Metrics.Vendors'
					&& $data['references'] === 'acme/package'
					&& str_contains($data['full_path'], '/vendor/acme/package/Dep.php');
			}));

		$check = new DependenciesOnVendorCheck(
			$symbolTable,
			$this->createMock(OutputInterface::class),
			$metricOutput
		);

		$classNode = $this->parseText(file_get_contents($testDataDirectory . '.4.inc'))[0];
		$check->run('UsesVendor.php', $classNode);
	}

	public function testDoesNotEmitMetricForNonVendorReference() {
		$testDataDirectory = $this->getCallerTestDataDirectory($this);
		$symbolTable = new InMemorySymbolTable('/base');
		$localStatements = $this->parseText(file_get_contents($testDataDirectory . '.3.inc'))[0]->stmts;
		$localNode = $localStatements[0];
		$symbolTable->addClass('App\\LocalDep', $localNode, 'src/LocalDep.php');
		$interfaceNode = $localStatements[1];
		$symbolTable->addInterface('App\\LocalInterface', $interfaceNode, 'src/LocalInterface.php');

		$metricOutput = $this->createMock(MetricOutputInterface::class);
		$metricOutput->expects($this->never())->method('emitMetric');

		$check = new DependenciesOnVendorCheck(
			$symbolTable,
			$this->createMock(OutputInterface::class),
			$metricOutput
		);

		$classNode = $this->parseText(file_get_contents($testDataDirectory . '.5.inc'))[0];
		$check->run('UsesLocal.php', $classNode);
	}
}
