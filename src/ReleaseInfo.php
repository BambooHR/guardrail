<?php

namespace BambooHR\Guardrail;

final class ReleaseInfo {

	/**
	 * metadata - returns the metadata from the running this Phar
	 *
	 *
	 * @return array
	 */
   public static function metadata(): array
   {
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
