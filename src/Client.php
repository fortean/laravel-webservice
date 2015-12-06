<?php

namespace Fortean\Webservice;

use Httpful\Request as HttpfulRequest;

class Client
{
	protected $serviceName;
	protected $description;
	protected $config;

	/**
	 * Webservice client constructor
	 * 
	 * @param array $service
	 * @return void
	 */
	public function __construct($serviceName)
	{
		$this->serviceName = $serviceName;
		$this->description = new Description($serviceName);
		$this->config = config('webservice.'.$serviceName.'.client', []);
	}

	/**
	 * The magic function handler used to execute service operations
	 * 
	 * @param string $name
	 * @param array $args
	 * @return variant
	 */
	public function __call($name, $args)
	{
		// Operation calls should only have one parameter
		$params = array_shift($args);

		// And that parameter should be either a null, an empty array or an associative array of parameters
		if (!is_null($params) && !is_array($params))
		{
			throw new WebserviceException('Operations must pass null or an associative array as their first parameter');
		}

		// Build a URI from the passed data
		$uri = $this->description->buildUri($name, $params);

		// Find the response type we're expecting
		$responseType = $this->description->getResponseType($name);

		// Return the results of the request
		return HttpfulRequest::get($uri)->expectsType($responseType)->send()->body;
	}
}