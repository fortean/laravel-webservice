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
		// The first argument should always be an associative array of operation parameters
		if ((count($args) == 0) || !is_array($args[0]))
		{
			throw new WebserviceException('Operations must pass null or an associative array as their first parameter');
		}

		// Build a URI from the passed data
		$uri = $this->description->buildUri($name, $args[0]);

		// Find the response type we're expecting
		$responseType = $this->description->getResponseType($name);

		// Return the results of the request
		return HttpfulRequest::get($uri)->expectsType($responseType)->send()->body;
	}
}