<?php

echo exec('whoami');
echo shell_exec('whoami');
echo proc_open($command, $descriptorSpec, $pipes);
echo passthru("cat myfile.zip",$err);
echo popen($command, $mode);
echo system('some command');
echo create_function('someNewFunction', 'my code');
// This should throw error
function newFunctionCall($str) {
	eval("\$str = \"$str\";");
}
