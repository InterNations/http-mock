<?php
namespace InterNations\Component\HttpMock;

use UnexpectedValueException;

final class Util
{
    /** @return mixed */
    public static function deserialize(string $string)
    {
        $result = self::silentDeserialize($string);

        if ($result === false) {
            throw new UnexpectedValueException('Cannot deserialize string');
        }

        return $result;
    }

    /** @return mixed */
    public static function silentDeserialize(string $string)
    {
        // @codingStandardsIgnoreStart
        return @unserialize($string);
        // @codingStandardsIgnoreEnd
    }
}
