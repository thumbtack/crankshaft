<?php

namespace Thumbtack\Crankshaft;

/**
 * Internal: Wraps an Array to provide the Iterable interface.
 */
class ArrayIterable extends Iterable implements \Countable, \ArrayAccess {
    private $array;

    public function __construct(array $array) {
        $this->array = $array;
    }

    public function getIterator() {
        return new \ArrayIterator($this->array);
    }

    public function count() {
        return count($this->array);
    }

    public function offsetExists($key) {
        return array_key_exists($key, $this->array);
    }

    public function offsetGet($key) {
        return $this->offsetExists($key) ? $this->array[$key] : null;
    }

    public function offsetSet($key, $value) {
        if ($key === null) {
            $this->array[] = $value;
        } else {
            $this->array[$key] = $value;
        }
    }

    public function offsetUnset($key) {
        unset($this->array[$key]);
    }
}
