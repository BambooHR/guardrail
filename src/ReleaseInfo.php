<?php

namespace BambooHR\Guardrail;

final class ReleaseInfo {
	/**
	 * Read release metadata from composer.json.
	 *
	 * @param string $composerFile Path to composer.json.
	 * @return array{name: string, version: string}
	 */
	public static function fromComposer(string $composerFile): array {
		$composer = json_decode(
			file_get_contents($composerFile),
			true,
			flags: JSON_THROW_ON_ERROR
		);

		return [
			'name' => $composer['name'],
			'version' => $composer['version'],
		];
	}

	/**
	 * metadata - returns the metadata from the running this Phar
	 *
	 *
	 * @return array
	 */
	public static function metadata(): array {
		$path = \Phar::running(false);

		if ($path !== '') {
			return (new \Phar($path))->getMetadata();
		}

		return [
		 'name' => 'bamboohr/guardrail',
		 'version' => 'dev',
		];
	}
}
