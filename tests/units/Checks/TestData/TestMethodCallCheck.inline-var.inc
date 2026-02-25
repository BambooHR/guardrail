<?php

class ClientException extends Exception {
	public function getResponse(): Response {
		return new Response();
	}
}

class Response {
	public function getBody(): string {
		return "body";
	}
}

function testInlineVarStandardFormat() {
	$exception = new Exception("test");

	// Standard format (type first, variable name second) with two asterisks
	// Should allow `getBody` to be called on $previous
	/** @var ClientException $previous */
	$previous = $exception->getPrevious();
	$stream = $previous->getResponse()->getBody();

	return $stream;
}

function testInlineVarOneAsterisk() {
	$exception = new Exception("test");

	// Standard format (type first, variable name second) with one asterisk
	// Should allow `getBody` to be called on $previous
	/* @var ClientException $previous */
	$previous = $exception->getPrevious();
	$stream = $previous->getResponse()->getBody();

	return $stream;
}

function testInlineVarReversedFormat() {
	$exception = new Exception("test");

	// Reversed format (variable name first, type second) with two asterisks
	// Should allow `getBody` to be called on $previous
	/** @var $previous ClientException */
	$previous = $exception->getPrevious();
	$stream = $previous->getResponse()->getBody();

	return $stream;
}

function testInlineVarOneAsteriskReversed() {
	$exception = new Exception("test");

	// Reversed format (variable name first, type second) with one asterisk
	// Should allow `getBody` to be called on $previous
	/* @var $previous ClientException */
	$previous = $exception->getPrevious();
	$stream = $previous->getResponse()->getBody();

	return $stream;
}
