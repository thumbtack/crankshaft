<?php

namespace Thumbtack\Crankshaft;

/**
 * Internal: An iterator that implements unique()
 */
class UniqueFilteringIterator extends Generator {
    private $inner_iterator;
    private $grouping_fn;
    private $seen;

    public function __construct(\Iterator $iterator, callable $filter_fn) {
        $this->inner_iterator = $iterator;
        $this->filter_fn = $filter_fn;
    }

    public function setup() {
        $this->seen = Crankshaft::Set();
        $this->inner_iterator->rewind();
    }

    protected function advance() {
        $iterator = $this->inner_iterator;

        while ($iterator->valid()) {
            $value = $iterator->current();
            $key = $iterator->key();
            $group = call_user_func($this->filter_fn, $value, $key);

            $iterator->next();
            if (!$this->seen->contains($group)) {
                $this->seen->add($group);
                return new KeyValuePair($key, $value);
            }
        }

        throw new GeneratorExhausted();
    }
}
