<?php

class MyCheckableTestClass__ {

	public function myGoodMethodCall() {
		switch ($a) {
			case 'A':
				return;
			case 'B':
				return;
			default:
				echo 'Reachable condition';
		}
		echo "Reachable code";
	}

	public function myBadMethodCall() {
		switch ($a) {
			case 'A':
				return;
			case 'B':
				return;
			default:
				return;
		}
		echo 'Unreachable code';
		switch ($a) {
			case 'A':
				return;
			case 'B':
				return;
			default:
				echo 'Reachable condition';
		}
	}
}