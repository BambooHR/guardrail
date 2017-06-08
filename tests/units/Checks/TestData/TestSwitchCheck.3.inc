<?php
switch($size) {
	case 'small': $size=1; $originalWidth=$originalHeight=$width=$height=150; break;
	case 'tiny' : $size=1; $originalWidth=$originalHeight=150; $width=$height=20; break;
	case 'original': $size=0; break;
	case 'large': $size=2; break;
	case 'xs': $size=3; break; // xs is our tiny of 50 by 50
	case 'medium': $size=4; break;
	default:
		$response->responseCodeHeader(404,"Not found");
		$response->errorMessage("size not found must be small or tiny");
		return true;
}