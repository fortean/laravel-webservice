<?php

namespace Fortean\Webservice;

class Webservice
{
	/**
	 * Instatiated clients keyed by service name
	 * 
	 * @var array
	 */
	protected $clients = [];

	public function create($service)
	{
		// Check if the service has already been initialized
		if (!isset($this->clients[$service]))
		{
			// Instantiate the client
			$this->clients[$service] = new Client($service);
		}

		return $this->clients[$service];
	}
}