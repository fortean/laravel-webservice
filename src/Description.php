<?php

namespace Fortean\Webservice;

class Description
{
	protected $serviceName;
	protected $service;
	protected $defaults;
	protected $parameters;
	protected $operations;

	/**
	 * Service description constructor
	 * 
	 * @param array $serviceName
	 * @return void
	 */
	public function __construct($serviceName)
	{
		$this->serviceName = $serviceName;
		$this->service = config('webservice.'.$serviceName.'.service', []);
		$this->defaults = config('webservice.'.$serviceName.'.defaults', []);
		$this->parameters = config('webservice.'.$serviceName.'.parameters', []);
		$this->operations = config('webservice.'.$serviceName.'.operations', []);

		// Sanity check the service
		if (!isset($this->service['baseUrl']))
		{
			throw new WebserviceException("baseURL is a required field");
		}

		// Sanity check operations
		foreach ($this->operations as $opName => &$opConfig)
		{
			// Check for basic operations requirements
			if (!is_array($opConfig) || !isset($opConfig['httpMethod']) || !isset($opConfig['uri']) ||
				!isset($opConfig['parameters']) || !is_array($opConfig['parameters']) ||
				!isset($opConfig['responseType']) || !in_array($opConfig['responseType'], ['json', 'xml']))
			{
				throw new WebserviceException("Invalid operation configuration for '".$opName."'");
			}

			// Substitute named parameter definitions into operation parameter fields
			foreach ($opConfig['parameters'] as $parmName => &$parmConfig)
			{
				// Parameter names are formatted as 'namespace:parameter'
				if (is_string($parmConfig) && preg_match('/^(.*?):(.*)$/', $parmConfig, $regs))
				{
					list($match, $namespace, $parameter) = $regs;

					// Fail if the named parameter doesn't exist
					if (!isset($this->parameters[$namespace][$parameter]))
					{
						throw new WebserviceException("Named parameter '".$parmConfig."' not found in service '".$this->serviceName."'");
					}

					// Substitute the config
					$parmConfig = $this->parameters[$namespace][$parameter];
				}

				// Check the resulting configuration
				if (!isset($parmConfig['type']) || !in_array(gettype($parmConfig['type']), ['string', 'array']) ||
					!isset($parmConfig['location']) || !in_array($parmConfig['location'], ['uri', 'query']))
				{
					throw new WebserviceException("Invalid parameter configuration for '".$opName.":".$parmName."'");
				}

				// Defaults in the parameter configuration override service-wide defaults
				if (isset($parmConfig['default']))
				{
					$this->defaults[$parmName] = $parmConfig['default'];
				}
			}
		}
	}

	/**
	 * Build a uri based on the named operation and provided parameters
	 * 
	 * @param string $operation
	 * @param array $parameters
	 * @return string
	 */
	public function buildUri($operation, $parameters = [])
	{
		// Check if we have this operation
		if (!isset($this->operations[$operation]))
		{
			throw new OperationNotFoundException($this->serviceName, $operation, $parameters);
		}

		// Overlay passed parameters onto the any provided defaults
		$parameters = array_merge($this->defaults, $parameters);

		// Assemble the uri first so substitutions can take place on the base as well
		$uri = rtrim($this->service['baseUrl'], '/').'/'.ltrim($this->operations[$operation]['uri']);

		// Step through the operation parameters substituting parameters in the uri and tracking query parameters
		$query = [];
		foreach ($this->operations[$operation]['parameters'] as $parmName => $parmConfig)
		{
			// Test the parameter against the provided specs
			$value = isset($parameters[$parmName]) ? $parameters[$parmName] : null;
			$this->testParameter($parmName, $parmConfig, $value);

			// Replace or track parameters
			switch($parmConfig['location'])
			{
				case 'uri':
					$uri = preg_replace('/\{'.$parmName.'\}/', $value, $uri);
					break;

				case 'query':
					$query[$parmName] = $value;
					break;
			}
		}

		// If there are query parameters assemble them and attach them to the uri
		if (count($query))
		{
			$uri .= '?'.http_build_query($query);
		}

		return $uri;
	}

	/**
	 * Return the response type of the named operation
	 * 
	 * @param string $operation
	 * @return string
	 */
	public function getResponseType($operation)
	{
		// Check if we have this operation
		if (!isset($this->operations[$operation]))
		{
			throw new OperationNotFoundException($this->serviceName, $operation, $parameters);
		}

		return $this->operations[$operation]['responseType'];
	}

	protected function testParameter($parmName, $parmConfig, $value = null)
	{
		// Check existance first
		if (!isset($value))
		{
			// If required, throw an exception
			if (isset($parmConfig['required']) && $parmConfig['required']) throw new WebserviceException($parmName.' is a required parameter');

			// If not, don't bother with any other tests
			return;
		}

		// Run all defined tests and throw an exception on failure
		foreach ($parmConfig as $rule => $criteria)
		{
			switch($rule)
			{
				case 'type':
					if (!$this->valueTypeValid($criteria, $value)) throw new WebserviceException($parmName.' is not the correct type');
					break;

				case 'enum':
					if (!in_array($value, $criteria)) throw new WebserviceException($parmName.' is not one of the accepted values');
					break;

				case 'pattern':
					if (!preg_match($criteria, $value)) throw new WebserviceException($parmName.' does not match the expected pattern');
					break;

				case 'minimum':
				case 'maximum':
					if (in_array($parmConfig['type'], ['numeric', 'number', 'integer']))
					{
						if (($rule == 'minimum') && ($value < $criteria)) throw new WebserviceException($parmName.' is less than the minimum value');
						if (($rule == 'maximum') && ($value > $criteria)) throw new WebserviceException($parmName.' is more than the maximum value');
					}
					break;
			}
		}
	}

    protected function valueTypeValid($type, $value)
    {
    	// We may have been provided multiple types
    	$types = is_array($type) ? $type : [$type];

    	// First matching type is good enough
    	foreach ($types as $checkType)
    	{
	        if (($checkType == 'string') && (is_string($value) || (is_object($value) && method_exists($value, '__toString'))))
	        {
	            return true;
	        }
	        elseif (($checkType == 'object') && (is_array($value) || is_object($value)))
	        {
	            return true;
	        }
	        elseif (($checkType == 'array') && is_array($value))
	        {
	            return true;
	        }
	        elseif (($checkType == 'integer') && is_integer($value))
	        {
	            return true;
	        }
	        elseif (($checkType == 'boolean') && is_bool($value))
	        {
	            return true;
	        }
	        elseif ((($checkType == 'number') || ($checkType == 'numeric')) && is_numeric($value))
	        {
	            return true;
	        }
	        elseif (($checkType == 'null') && !is_null($value))
	        {
	            return true;
	        }
	        elseif ($checkType == 'any')
	        {
	            return true;
	        }
    	}

        return false;
    }
}