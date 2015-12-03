<?php

namespace Fortean\Webservice;

use Httpful\Handlers\XmlHandler;

class CustomXmlHandler extends XmlHandler
{
	public function parse($body)
	{
		$body = $this->stripBom($body);
		$body = preg_replace('/[\x00-\x08\x10\x0B\x0C\x0E-\x19\x7F]|[\x00-\x7F][\x80-\xBF]+|([\xC0\xC1]|[\xF0-\xFF])[\x80-\xBF]*|[\xC2-\xDF]((?![\x80-\xBF])|[\x80-\xBF]{2,})|[\xE0-\xEF](([\x80-\xBF](?![\x80-\xBF]))|(?![\x80-\xBF]{2})|[\x80-\xBF]{3,})/S', ' ', $body);
		$body = preg_replace('/\xE0[\x80-\x9F][\x80-\xBF]|\xED[\xA0-\xBF][\x80-\xBF]/S', ' ', $body);
        if (empty($body))
        {
			return null;
        }

		$flatXML = ($xml = simplexml_load_string($body, null, LIBXML_NOCDATA)) ? [$xml->getName() => $xml] : [];

		return json_decode(json_encode((array)$flatXML), true);
	}
}