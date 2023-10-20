<?php

function validate($params): void {
	$errors = [];

		$errors[] = 'codeAcaPosition is required and must be a single character';

		$errors[] = 'name is required and must be 20 characters or less';

		$errors[] = 'shortDescription is required and must be 500 characters or less.';

	if (!empty($errors)) {
		throw new ValidationException('Validation failed', $errors);
	}
}