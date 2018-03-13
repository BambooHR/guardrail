<?php

use Illuminate\Support\Collection;

class Service {
	public function get() {
		return new TypedCollection([1]);
	}
}

class TypedCollection extends Collection {

}

/**
 * Class TestMethodCallClass
 */
class TestMethodCallClass {
	/**
	 * @var Service
	 */
	private $service;

	/**
	 * TestMethodCallClass constructor.
	 *
	 * @param Service $service
	 */
	public function __construct(Service $service) {
		$this->service = $service;
	}

	/**
	 * get
	 *
	 * @return mixed
	 * @throws Exception
	 */
	public function get() {
		return $this->getData();
	}

	/**
	 * Get data
	 *
	 * @return mixed
	 * @throws Exception
	 */
	protected function getData() {
		$data = $this->service->get()->sortByDesc(
			function($data) {
				return $data;
			}
		)->first();

		if (!$data) {
			throw new Exception('Nothing found');
		}

		return $data;
	}
}