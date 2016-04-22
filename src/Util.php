<?php
namespace InterNations\Component\HttpMock;

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
}
