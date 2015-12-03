<?php

namespace Fortean\Webservice;

use Exception;

class OperationNotFoundException extends Exception
{
	protected $service;
	protected $operation;
	protected $parameters;

	public function __construct($service, $operation, $parameters = [])
	{
		$this->service = $service;
		$this->operation = $operation;
		$this->parameters = $parameters;

		parent::__construct("Operation '".$operation."' not found in service '".$service."'");
	}

	public function getService()
	{
		return $this->service;
	}

	public function getOperation()
	{
		return $this->operation;
	}

	public function getParameters()
	{
		return $this->parameters;
	}
}