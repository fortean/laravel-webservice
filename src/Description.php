<?php

namespace Fortean\Webservice;

class Description
{
	protected $serviceName;
	protected $client;
	protected $service;
	protected $defaults;
	protected $parameters;
	protected $operations;

	/**
	 * Service description constructor
	 * 
	 * @param array $service
	 * @return void
	 */
	public function __construct($serviceName)
	{
		$this->serviceName = $serviceName;
		$this->client = config('webservice.'.$serviceName.'.client', []);
		$this->service = config('webservice.'.$serviceName.'.service', []);
		$this->defaults = config('webservice.'.$serviceName.'.defaults', []);
		$this->parameters = config('webservice.'.$serviceName.'.parameters', []);
		$this->operations = config('webservice.'.$serviceName.'.operations', []);

		// Substitute parameter definitions into named operation parameter fields
		foreach ($this->operations as $opName => $opConfig)
		{
			if (is_array($opConfig) && isset($opConfig['parameters']) && is_array($opConfig['parameters']))
			{
				foreach ($opConfig['parameters'] as $parmName => $parmConfig)
				{
					if (is_string($parmConfig) && preg_match('/^(.*?):(.*)$/', $parmConfig, $regs))
					{
						list($match, $namespace, $key) = $regs;

						if (!isset($this->parameters[$namespace][$key]))
						{
							throw new WebserviceException("Named parameter '".$parmConfig."' not found in service '".$this->serviceName."'");
						}

						$this->operations[$opName]['parameters'][$parmName] = $this->parameters[$namespace][$key];
					}
				}
			}
		}

		// Sanity check the service
		if (!isset($this->service['baseUrl']))
		{
			throw new WebserviceException("baseURL is a required field");
		}

		// Sanuty check the operations
		foreach ($this->operations as $opName => $opConfig)
		{
			if (!isset($opConfig['httpMethod']) || !isset($opConfig['uri']) ||
				!isset($opConfig['responseType']) || !in_array($opConfig['responseType'], ['json', 'xml']) ||
				!isset($opConfig['parameters']) || !is_array($opConfig['parameters']))
			{
				throw new WebserviceException("Invalid operation configuration for '".$opName."'");
			}
			foreach ($opConfig['parameters'] as $parmName => $parmConfig)
			{
				if (!isset($parmConfig['type']) || !isset($parmConfig['location']) || !in_array($parmConfig['location'], ['uri', 'query']))
				{
					throw new WebserviceException("Invalid parameter configuration for '".$opName.":".$parmName."'");
				}

				if (isset($parmConfig['default']) && !isset($this->defaults[$parmName]))
				{
					$this->defaults[$parmName] = $parmConfig['default'];
				}
			}
		}
	}

	public function buildOperationUri($operation, $parameters = [])
	{
		// Check if we have this operation
		if (!isset($this->operations[$operation]))
		{
			throw new OperationNotFoundException($this->serviceName, $operation, $parameters);
		}

		// Overlay passed parameters onto the any provided defaults
		$parameters = array_merge($this->defaults, $parameters);

		// Step through the operation parameters
		$uri = rtrim($this->service['baseUrl'], '/').'/'.ltrim($this->operations[$operation]['uri']);
		$query = [];
		foreach ($this->operations[$operation]['parameters'] as $parmName => $parmConfig)
		{
			// Test the parameter against the provided specs
			$value = isset($parameters[$parmName]) ? $parameters[$parmName] : null;
			$this->testParameter($parmName, $parmConfig, $value);

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

		if (count($query))
		{
			$uri .= '?'.http_build_query($query);
		}

		return $uri;
	}

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

		// Assume we're valid
		foreach ($parmConfig as $rule => $criteria)
		{
			switch($rule)
			{
				case 'type':
					if (!$this->checkParameterType($criteria, $value)) throw new WebserviceException($parmName.' is not the correct type');
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

    protected function checkParameterType($type, $value)
    {
        if ($type == 'string' && (is_string($value) || (is_object($value) && method_exists($value, '__toString')))) {
            return true;
        } elseif ($type == 'object' && (is_array($value) || is_object($value))) {
            return true;
        } elseif ($type == 'array' && is_array($value)) {
            return true;
        } elseif ($type == 'integer' && is_integer($value)) {
            return true;
        } elseif ($type == 'boolean' && is_bool($value)) {
            return true;
        } elseif ($type == 'number' && is_numeric($value)) {
            return true;
        } elseif ($type == 'numeric' && is_numeric($value)) {
            return true;
        } elseif ($type == 'null' && !$value) {
            return true;
        } elseif ($type == 'any') {
            return true;
        }
        return false;
    }
}