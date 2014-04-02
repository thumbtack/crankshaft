<?php

namespace Thumbtack\Crankshaft;

/**
 * Public: An interface for classes that allow access to properties through a `get` method. Objects
 * implementing this interface will be treated specially by `Iterable->pluck()` and
 * `Iterable->select()`.
 */
interface PropertyContainer {
    /**
     * Public: Get this object's value for the given property.
     *
     * If this object does not possibly contain a property with the given name, it may throw an
     * exception.
     *
     * $property_name - The string name of the property to access.
     *
     * Returns the property's value.
     */
    public function get($property_name);
}
