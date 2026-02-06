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

	// Standard format: @var Type $variable
	/** @var ClientException $previous */
	$previous = $exception->getPrevious();
	$stream = $previous->getResponse()->getBody();

	return $stream;
}

function testInlineVarOneAsterisk() {
	$exception = new Exception("test");

	// Standard format: @var Type $variable
	/* @var ClientException $previous */
	$previous = $exception->getPrevious();
	$stream = $previous->getResponse()->getBody();

	return $stream;
}

function testInlineVarReversedFormat() {
	$exception = new Exception("test");

	// Reversed format: @var $variable Type
	/** @var $previous ClientException */
	$previous = $exception->getPrevious();
	$stream = $previous->getResponse()->getBody();

	return $stream;
}

function testInlineVarOneAsteriskReversed() {
	$exception = new Exception("test");

	// Reversed format: @var $variable Type
	/* @var $previous ClientException */
	$previous = $exception->getPrevious();
	$stream = $previous->getResponse()->getBody();

	return $stream;
}
