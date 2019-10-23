<?php

namespace {
	const MY_CONST = 1;
	echo MY_CONST;
}

namespace Space1 {
	const MY_CONST_2;

	echo MY_CONST;
	echo \Space1\MY_CONST_2;
	echo MY_CONST_2;
}

namespace Space2 {
	echo \Space1\MY_CONST_2;
	echo MY_CONST;
}