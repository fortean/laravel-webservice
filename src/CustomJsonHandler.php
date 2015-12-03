<?php

namespace Fortean\Webservice;

use Httpful\Handlers\JsonHandler;

class CustomJsonHandler extends JsonHandler
{
	public function parse($body)
	{
        $body = $this->stripBom($body);
        if (empty($body))
        {
			return null;
        }

        $parsed = json_decode($body, true);
        if (is_null($parsed) && 'null' !== strtolower($body))
        {
			throw new WebserviceException("Unable to parse response as JSON");
        }

        return $parsed;
	}
}