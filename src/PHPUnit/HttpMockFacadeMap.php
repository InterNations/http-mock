<?php
namespace Pagely\Component\HttpMock\PHPUnit;

use ArrayAccess;
use BadMethodCallException;
use OutOfBoundsException;

/** @property-read HttpMockFacade */
class HttpMockFacadeMap implements ArrayAccess
{
    /** @var HttpMockFacade[] */
    private $facadeMap;

    public function __construct(array $facadeMap)
    {
        $this->facadeMap = $facadeMap;
    }

    public function offsetGet($offset)
    {
        if (!$this->offsetExists($offset)) {
            throw new OutOfBoundsException(sprintf('No named facade "%s" configured', $offset));
        }

        return $this->facadeMap[$offset];
    }

    public function offsetExists($offset)
    {
        return isset($this->facadeMap[$offset]);
    }

    public function offsetSet($offset, $value)
    {
        throw new BadMethodCallException(__METHOD__);
    }

    public function offsetUnset($offset)
    {
        throw new BadMethodCallException(__METHOD__);
    }

    public function __clone()
    {
        $this->facadeMap = array_map(
            static function (HttpMockFacade $facade) {
                return clone $facade;
            },
            $this->facadeMap
        );
    }

    public function each(callable $callback)
    {
        array_map($callback, $this->facadeMap);
    }

    public function __get($property)
    {
        if (in_array($property, HttpMockFacade::getProperties(), true)) {
            throw new OutOfBoundsException(
                sprintf(
                    'Tried to access facade property "%1$s" on facade map. First select one of the facades from '
                    . 'the map. Defined facades: "%2$s", try $this->http[\'%s\']->%1$s->…',
                    $property,
                    implode('", "', array_keys($this->facadeMap)),
                    current(array_keys($this->facadeMap))
                )
            );
        }

        throw new OutOfBoundsException(
            sprintf(
                'Tried to access property "%1$s". This is a map of facades, try $this->http[\'%1$s\'] instead.',
                $property
            )
        );
    }

    public function all()
    {
        return $this->facadeMap;
    }
}
