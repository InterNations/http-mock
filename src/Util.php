<?php

namespace InterNations\Component\HttpMock;

use function GuzzleHttp\Psr7\parse_response;
use function GuzzleHttp\Psr7\str;
use UnexpectedValueException;

final class Util
{
    public static function deserialize($string)
    {
        $result = static::silentDeserialize($string);

        if ($result === false) {
            throw new UnexpectedValueException('Cannot deserialize string');
        }

        return $result;
    }

    public static function silentDeserialize($string)
    {
        // @codingStandardsIgnoreStart
        return @unserialize($string);
        // @codingStandardsIgnoreEnd
    }

    public static function responseDeserialize($string)
    {
        return parse_response($string);
    }

    public static function serializePsrMessage($message)
    {
        $headers = $message->getHeaders();
        foreach ($headers as $key => $list) {
            foreach ($list as $value) {
                if (substr($key, 0, 5) === 'HTTP_') {
                    $newKey = substr($key, 5);
                    $newKey = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', $newKey))));
                    $message = $message->withoutHeader($key)->withHeader($newKey, $value);
                } else {
                    $newKey = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', $key))));
                    $message = $message->withoutHeader($key)->withHeader($newKey, $value);
                }
            }
        }

        return str($message);
    }
}
