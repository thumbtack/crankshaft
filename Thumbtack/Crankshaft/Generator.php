<?php

namespace Thumbtack\Crankshaft;

/**
 * Public: A simplified Iterator type that only requires subclasses to implement two methods
 * instead of five, and will automatically generate integer keys if the subclass doesn't require
 * specific keys for its values.
 */
abstract class Generator implements \Iterator {
    private $current;
    private $count;
    private $valid;

    /**
     * Internal: Called at the start of an iteration run to initialize or reset the generator's
     * state.
     *
     * If your generator has no state to initialize, simply implement an empty method.
     *
     * Unlike a Python generator, this class upholds PHP's assumption that all iterators are
     * rewindable: that at any point, you can start iteration over from the beginning of the
     * sequence.
     *
     * Returns nothing.
     */
    protected abstract function setup();

    /**
     * Internal: Called to generate the next value.
     *
     * Returns a KeyValuePair if the generator wants to specify a key with the current value, or
     *   any other value to use an automatically incrementing integer as the key.
     * Throws GeneratorExhausted if there are no more values to generate.
     */
    protected abstract function advance();

    /**
     * Public: Implements the PHP Iterator interface.
     */
    public final function rewind() {
        $this->setup();

        $this->count = 0;
        $this->current = null;
        $this->valid = true;

        $this->next();
    }

    /**
     * Public: Implements the PHP Iterator interface.
     */
    public final function next() {
        try {
            $next = $this->advance();
        } catch (GeneratorExhausted $e) {
            $this->valid = false;
            $this->current = null;

            return;
        }

        $this->current = $next instanceof KeyValuePair
            ? $next
            : new KeyValuePair($this->count, $next);
        $this->count++;
    }

    /**
     * Public: Implements the PHP Iterator interface.
     */
    public final function valid() {
        return $this->valid;
    }

    /**
     * Public: Implements the PHP Iterator interface.
     */
    public final function current() {
        return $this->current->value;
    }

    /**
     * Public: Implements the PHP Iterator interface.
     */
    public final function key() {
        return $this->current->key;
    }
}
