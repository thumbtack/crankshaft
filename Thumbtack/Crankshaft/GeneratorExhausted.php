<?php

namespace Thumbtack\Crankshaft;

/**
 * Public: A special exception type used to signal that the end of a generator has been reached.
 *
 * Equivalent to `StopIteration` in Python.
 */
class GeneratorExhausted extends \RuntimeException {}
