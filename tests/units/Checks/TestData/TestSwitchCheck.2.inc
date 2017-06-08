<?php
switch($foo) {
	case 0:
	case 5: // Empty, but with comment (This is ok)
	case 1:
		echo "Error!\n";
	// Comment
	case 2:
		echo "Another error, but no comment\n";
	case 2:
		echo "Not error\n";
		break;
	case 3:
		// Last case, also not an error
}