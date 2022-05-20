<?php

namespace BambooHR\Guardrail\Tests\units\Checks;

use BambooHR\Guardrail\NodeVisitors\PromotedPropertyVisitor;
use BambooHR\Guardrail\Tests\TestSuiteSetup;
use PhpParser\Node\Stmt\Property;
use PhpParser\NodeTraverser;
use PhpParser\ParserFactory;

/**
 *
 * @package BambooHR\Guardrail\Tests\units\Checks
 */
class TestPromotedProperties extends TestSuiteSetup {
	/**
	 * Constructor paramaters with a "public/private/protected" in front of the variable get automatically
	 * expanded before indexing.
	 *
	 * @return void
	 * @rapid-unit Checks:PropertyFetchCheck:Cannot access private or protected member variables directly without __get() method
	 */
	public function testPromotedPropertyParsing() {
		$factory=new ParserFactory();
		$parser = $factory->create(ParserFactory::ONLY_PHP7);
		$file = file_get_contents("./units/Checks/TestData/TestPromotedProperties.1.inc");
		$tokens = $parser->parse($file);

		$traverser=new NodeTraverser();
		$traverser->addVisitor(new PromotedPropertyVisitor());
		$tokens = $traverser->traverse($tokens);

		$class=$tokens[0];
		$this->assertTrue( $class->stmts[0] instanceof Property);
		$this->assertEquals( $class->stmts[0]->props[0]->name->name, "a");

		$this->assertTrue( $class->stmts[0] instanceof Property);
		$this->assertEquals( $class->stmts[1]->props[0]->name->name, "b");
	}
}