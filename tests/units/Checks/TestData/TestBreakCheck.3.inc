<?php
foreach ($foobar as $foo) {
	foreach ($foo as $bar) {
		while ($bar < 5) {
			continue 3;
		}
	}
}