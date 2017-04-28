<?

class DocBlockTest {
	/** var DocBlockTest */
	private $instance;


	function foo() {
		$this->instance->foo(); // Ok
		$this->instance->bar(); // Not ok.
	}
}
