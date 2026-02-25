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
	// Should fail as Exception doesn't have a getResponse method
	/** @var Exception $previous */
	$previous = $exception->getPrevious();
	$stream = $previous->getResponse()->getBody();

	return $stream;
}

function testInlineVarOneAsterisk() {
	$exception = new Exception("test");

	// Standard format (type first, variable name second) with one asterisk
	// Should fail as Exception doesn't have a getResponse method
	/* @var Exception $previous */
	$previous = $exception->getPrevious();
	$stream = $previous->getResponse()->getBody();

	return $stream;
}

function testInlineVarNoAsterisk() {
	$exception = new Exception("test");

	// Standard format (type first, variable name second) without asterisk
	// Should fail as Exception doesn't have a getResponse method
	// @var Exception $previous
	$previous = $exception->getPrevious();
	$stream = $previous->getResponse()->getBody();

	return $stream;
}

function testInlineVarReversedFormat() {
	$exception = new Exception("test");

	// Reversed format (variable name first, type second) with two asterisks
	// Should fail as Exception doesn't have a getResponse method
	/** @var $previous Exception */
	$previous = $exception->getPrevious();
	$stream = $previous->getResponse()->getBody();

	return $stream;
}

function testInlineVarOneAsteriskReversed() {
	$exception = new Exception("test");

	// Reversed format (variable name first, type second) with one asterisk
	// Should fail as Exception doesn't have a getResponse method
	/* @var $previous Exception */
	$previous = $exception->getPrevious();
	$stream = $previous->getResponse()->getBody();

	return $stream;
}

function testInlineVarNoAsteriskReversed() {
	$exception = new Exception("test");

	// Reversed format (variable name first, type second) without asterisk
	// Should fail as Exception doesn't have a getResponse method
	// @var $previous Exception
	$previous = $exception->getPrevious();
	$stream = $previous->getResponse()->getBody();

	return $stream;
}
