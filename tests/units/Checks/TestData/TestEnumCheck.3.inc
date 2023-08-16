<?php

enum Foo:int {
	case Bar=1;
	case Baz=2;

	function toInt():int {
		return $this->value;
	}
}

$foo=Foo::Bar;
echo $foo->name."\n";
echo count(Foo::cases());
$bar=Foo::tryFrom(1);
try {
	$bar2 = Foo::from(2);
}
catch(ValueError) {
	echo "Illegal Foo::from()\n";
}
echo "done\n";
