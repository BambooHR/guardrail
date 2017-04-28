<?

class ScopeTest {
	function a(ScopeTest $a) {
		$a->c();
		$a->d($a);
	}

	function b(UnknownMethod $a) {
	}

	function d(ScopeTest2 $c) {
	}
}

$test=new ScopeTest();
$test->a($test);
$test->b($test);
