<? namespace MyTest;

class ConstantClass {
	const A=5;
}

class ConstantUser {

	function myFunction() {
		echo ConstantClass2::A;
		echo ConstantClass::B;

		echo ConstantClass::A;
	}
}

