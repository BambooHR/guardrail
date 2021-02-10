<?php namespace BambooHR\Guardrail;

use BambooHR\Guardrail\Checks\ErrorConstants;

ini_set('xdebug.max_nesting_level', 3000);

$interestingNamespaces = [
    '#([^\\\\]*)\\\\#',
    '#BambooHR\\\\([^\\\\]*)\\\\#',
    '#BambooHR\\\\Silo\\\\([^\\\\]*)\\\\#',
];

// Deals with installation inside /vendor or out.
foreach ([__DIR__ . '/../../../../autoload.php', __DIR__ . '/../../vendor/autoload.php'] as $file) {
	if (file_exists($file)) {
		require $file;
		break;
	}
}

$metricFile = fopen($argv[1], 'r');
$deprecatedAggregatesByClass = [];
while ($metricLine = fgets($metricFile)) {
    $metric = json_decode($metricLine);
    switch ($metric->type) {
        case ErrorConstants::TYPE_DEPRECATED_USER:
            aggregateDeprecatedCalls($metric, $deprecatedAggregatesByClass);
            break;
    }
}

$namespaceCounts = [];
foreach ($deprecatedAggregatesByClass as $className => $classAggregate) {
    foreach ($interestingNamespaces as $namespace) {
        var_dump($namespace);
        $found = preg_match($namespace, $className, $matches);
        if ($found) {
            $namespace = $matches[0];
            $namespaceCounts[$namespace] += $classAggregate['callsToDeprecatedMethods'];
        }
    }
}
ksort($namespaceCounts);
var_dump($namespaceCounts);

function aggregateDeprecatedCalls($metric, &$deprecatedAggregatesByClass) {
    if (!isset($deprecatedAggregatesByClass[$metric->data->class])) {
        $deprecatedAggregatesByClass[$metric->data->class] = [
            'class' => $metric->data->class,
            'callsToDeprecatedMethods' => 0
        ];
    }
    if (!isset($deprecatedAggregatesByClass[$metric->data->class]['methods'][$metric->data->method])) {
        $deprecatedAggregatesByClass[$metric->data->class]['methods'][$metric->data->method] = [
            'method' => $metric->data->method,
            'calls' => 0
        ];
    }
    $deprecatedAggregatesByClass[$metric->data->class]['callsToDeprecatedMethods']++;
    $deprecatedAggregatesByClass[$metric->data->class]['methods'][$metric->data->method]['calls']++;
}
