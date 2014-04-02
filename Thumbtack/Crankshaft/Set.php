<?php

namespace Thumbtack\Crankshaft;

class Set extends Iterable implements \Countable {
    private $hash = [];

    public function __construct($iterable=null) {
        if ($iterable !== null) {
            $this->update($iterable);
        }
    }

    public function add($item) {
        $this->hash[$this->get_hash_key($item)] = $item;
    }

    public function remove($item) {
        unset($this->hash[$this->get_hash_key($item)]);
    }

    public function update($items) {
        foreach ($items as $item) {
            $this->add($item);
        }
    }

    public function intersection(Set $other) {
        $intersection_set = new Set();
        foreach ($this as $item) {
            if ($other->contains($item)) {
                $intersection_set->add($item);
            }
        }
        return $intersection_set;
    }

    public function union(Set $other) {
        $union_set = new Set();
        $union_set->update($other);
        $union_set->update($this);
        return $union_set;
    }

    public function difference(Set $other) {
        $difference_set = new Set();
        foreach ($this as $item) {
            if (!$other->contains($item)) {
                $difference_set->add($item);
            }
        }
        return $difference_set;
    }

    public function equals(Set $other) {
        if (count($other) !== count($this)) {
            return false;
        }

        foreach ($this as $item) {
            if (!$other->contains($item)) {
                return false;
            }
        }

        return true;
    }

    public function is_subset(Set $other) {
        foreach ($this as $item) {
            if (!$other->contains($item)) {
                return false;
            }
        }
        return true;
    }

    public function is_superset(Set $other) {
        return $other->is_subset($this);
    }

    public function contains($item) {
        $key = $this->get_hash_key($item);
        return array_key_exists($key, $this->hash) && $this->hash[$key] === $item;
    }

    public function getIterator() {
        return new SequentialKeyIterator(new \ArrayIterator($this->hash));
    }

    public function count() {
        return count($this->hash);
    }

    private function get_hash_key($item) {
        if (!is_scalar($item) && $item !== null) {
             throw new \InvalidArgumentException('Only scalar values can be stored in a Set.');
        }

        return gettype($item) . ":$item";
    }
}
