<?php

namespace BambooHR\Guardrail;

use BambooHR\Guardrail\Exceptions\SocketException;

class Socket {
	/* This function adapted from the PHP documentation on php.net */
	/**
	 * @param resource $fp
	 * @param string   $string
	 * @return int
	 */
	public static function writeComplete($fp, $string) {
		$length = strlen($string);
		$fwrite = 0;
		for ($written = 0; $written < $length; $written += $fwrite) {
			$fwrite = static::retryOnFalse(function () use ($fp, $string, $written) {
				return @socket_write($fp, substr($string, $written));
			}, 3);
			if ($fwrite === false) {
				throw new SocketException(socket_strerror(socket_last_error($fp)));
			}
		}
		return $written;
	}

	/**
	 * @param $callable
	 * @param $retries
	 *
	 * @return false|mixed
	 * @guardrail-ignore Standard.VariableFunctionCall:Variable
	 */
	public static function retryOnFalse($callable, $retries) {
		$succeeded = false;
		$tries = 0;
		while ($succeeded === false && $tries < $retries) {
			$succeeded = $callable();
			$tries++;
		}
		return $succeeded;
	}
}
