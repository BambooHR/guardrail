<?php


function myGoodMethodCall() {
	if (1 == 1) {
		return;
	}
	echo "Reachable code";
}


function myOtherGoodMethodCall() {
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


function myBadMethodCall() {
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

function myOtherBadMethodCall() {
	if (1 == 1) {
		return;
	} elseif (2 == 2) {
		return;
	} else {
		exit;
	}
	echo 'Unreachable code';
}