<?php

class CheckClassTesting__ {

	public function myGoodMethodCall() {
		if (1 == 1) {
			return;
		}
		echo "Reachable code";
	}

	public function myOtherGoodMethodCall() {
		if (1 == 1) {
			return;
		}

		if (1 == 1) {
			return;
		} elseif (2 == 2) {
			// handled
		} else {
			exit;
		}
		echo "Reachable code";
	}

	public function myThirdGoodMethodCall() {
		if (false) {
			return;
		} elseif (false) {
			return;
		}

		echo "Reachable code";
	}

	public function myBadMethodCall() {
		if (1 == 1) {
			return;
		} else {
			return;
		}
		echo 'Unreachable code';

		if (2 == 2) {
			return;
		}
	}

	public function myOtherBadMethodCall() {
		if (1 == 1) {
			return;
		} elseif (2 == 2) {
			return;
		} else {
			exit;
		}
		echo 'Unreachable code';
	}

	public function myThirdBadMethodCall() {
		if (false) {
			return;
		} elseif (true) {
			return;
		}

		return;

		echo 'Unreachable code';
	}
}