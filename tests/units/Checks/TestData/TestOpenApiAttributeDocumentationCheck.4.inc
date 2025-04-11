<?php
use OpenApi\Attributes as OA;


class MyController extends BaseController {
	#[OA\Get(path: "/test", x: ['team-name' => 'my-team'])]
	public function withoutDescription() {
		return false;
	}

	#[OA\Get(path: "/test", description: '', x: ['team-name' => 'my-team'])]
	public function withEmptyDescription() {
		return false;
	}

	#[OA\Get(path: "/test", description: 'test')]
	public function withoutXTag() {
		return false;
	}

	#[OA\Get(path: "/test", description: 'test', x: [])]
	public function withoutTeamName() {
		return false;
	}

	#[OA\Get(path: "/test", description: 'test', x: ['team-name' => ''])]
	public function withEmptyTeamName() {
		return false;
	}

	#[OA\Get(path: "/test", description: 'this is my great description',  x: ['team-name' => 'my-team'])]
	public function withAllProperties() {
		return false;
	}

	// We don't support non type hinted arguments. This should emit one error for description
	#[\Onsen\SecurityAudit\Sensitivity\Low]
	#[OA\Get('path',
		'operationId',
		'description',
		'summary',
		[['oauth' => []]],
		x: ['team-name' => 'my-team'],
	)]
	public function testWithoutDescriptionParamHint() {
		return false;
	}

	// We don't support non type hinted arguments. This should emit two errors for description and team name
	#[\Onsen\SecurityAudit\Sensitivity\Low]
	#[OA\Get('path',
		'operationId',
		'description',
		'summary',
		[['oauth' => []]],
		'servers',
		'requestBody',
		'tags',
		'parameters',
		'responses',
		'callbacks',
		'externalDocs',
		'deprecated',
		['team-name' => 'my-team'],
	)]
	public function testWithoutParamHints() {
		return false;
	}
}