<?php

namespace Thumbtack\Crankshaft;

/**
 * Public: Provides many useful methods for dealing with collections. Custom collection classes
 * may inherit from Iterable directly, and any standard PHP array or Traversable object can be
 * made into an Iterable by passing it to `Crankshaft::Iter()`.
 *
 * See also
 *
 *   Crankshaft::Iter() - Turn any array or Traversable object into an Iterable.
 */
abstract class Iterable implements \IteratorAggregate {
    /**
     * Call the given `$each_fn` for each element in the iterable.
     *
     * $each_fn  - A function which will be called for each element in the iterable, with two
     *             arguments `($value, $key)`.
     *
     * Examples
     *
     *   Crankshaft::Iter(['a' => 1, 'b' => 2, 'c' => 4])
     *       ->each(function($value, $key) {
     *           echo "$key: $value\n";
     *       });
     *   // Prints:
     *   //   a: 1
     *   //   b: 2
     *   //   c: 4
     *
     * Returns nothing.
     */
    public function each($each_fn) {
        Crankshaft::AssertCallable($each_fn);

        foreach ($this as $key => $value) {
            call_user_func($each_fn, $value, $key);
        }
    }

    /**
     * Produce a new iterable by mapping each value from this one through a transformation function.
     * Keys in this iterable will be preserved.
     *
     * $map_fn - A function which will be called for each element in this iterable, with two
     *           arguments: `($value, $key)`. It must return the new value that should be used in
     *           the output iterable.
     *
     * Examples
     *
     *   Crankshaft::Iter([1, 2, 3, 4, 5, 6])
     *       ->map(function($n) { return $n * $n; })
     *       ->to_array()
     *   // => [1, 4, 9, 16, 25, 36]
     *
     *   Crankshaft::Iter(['a' => 'hello', 'b' => 'goodbye'])
     *      ->map(function($s) { return strtoupper($s); })
     *       ->to_array()
     *   // => ['a' => 'HELLO', 'b' => 'GOODBYE']
     *
     * Returns an Iterable object containing the newly-mapped values. If this Iterable is countable,
     *   the returned object will also be countable.
     */
    public function map($map_fn) {
        $mapper = new MappingIterator($this, $map_fn);

        return $this instanceof \Countable
            ? new CountableIteratorIterable(count($this), $mapper)
            : new IteratorIterable($mapper);
    }

    /**
     * Produce a new iterable by mapping each key from this one through a transformation function.
     *
     * $map_fn - A function which will be called for each element in this iterable, with two
     *           arguments: `($key, $value)`. It must return the new key that should be used in
     *           the output iterable.
     *
     * Examples
     *
     *   Crankshaft::Iter(['a' => 1, 'b' => 2, 'c' => 3])
     *       ->map_keys(function($key) { return strtoupper($key); })
     *       ->to_array()
     *   // => ['A' => 1, 'B' => 2, 'C' => 3]
     *
     * Returns an Iterable object containing the newly-mapped keys. If this Iterable is countable,
     *   the returned object will also be countable.
     */
    public function map_keys(callable $map_fn) {
        $mapper = new KeyMappingIterator($this, $map_fn);

        return $this instanceof \Countable
            ? new CountableIteratorIterable(count($this), $mapper)
            : new IteratorIterable($mapper);
    }

    /**
     * Reduce an iterable to a single value.
     *
     * $reduce_fn - A function which computes subsequent values of `$memo`. It will be called with
     *              three arguments: `($memo, $current_value, $current_key)`, and it must return the
     *              new value of `$memo`.
     * $memo      - The initial state of the reduction (default: the first element in `$iterable`).
     *
     * Examples
     *
     *   Crankshaft::Iter([2, 7, 5])->reduce(function($sum, $value) { return $sum + $value })
     *   // => 14
     *
     *   Crankshaft::Iter([], 0)->reduce(function($sum, $value) { return $sum + $value })
     *   // => 0
     *
     * Returns the final value of `$memo` after each element has been stepped through.
     * Throws EmptyIterableError if reduce is called on an empty iterable and no explicit
     *   `$memo` is provided.
     */
    public function reduce($reduce_fn, $memo=null) {
        Crankshaft::AssertCallable($reduce_fn);

        // Do this instead of a NULL check so that NULL can be explicitly passed as the memo value.
        $take_element_as_memo = func_num_args() < 2;

        foreach ($this as $key => $value) {
            if ($take_element_as_memo) {
                $memo = $value;
                $take_element_as_memo = false;
            } else {
                $memo = call_user_func($reduce_fn, $memo, $value, $key);
            }
        }

        if ($take_element_as_memo) {
            throw new EmptyIterableError(
                'Cannot reduce an empty iterable without an explicit initial value.'
            );
        }

        return $memo;
    }


    /**
     * Create a new iterable with only the values from this iterable that pass a truth test.
     * Keys in the original iterable will be preserved.
     *
     * $filter_fn - A function `($value, $key) -> bool` that will be called to decide whether each
     *              element in this iterable should be included in the result.
     *              (default: include values in the result if they evaluate to `true`)
     *
     * Examples
     *
     *   Crankshaft::Iter([1, 4, 2, 5, 6, 3])
     *       ->filter(function($num) { return $num % 2 == 0; })
     *       ->to_array()
     *   // => [1 => 4, 2 => 2, 4 => 6]
     *
     *   Crankshaft::Iter(['hi', false, 0, 1, null])
     *       ->filter()
     *       ->to_array()
     *   // => [0 => 'hi', 3 => 1]
     *
     * See also
     *
     *   select() - Implements a common use case of filter more conveniently.
     *
     * Returns an Iterable that will yield the filtered values.
     */
    public function filter($filter_fn=null) {
        if ($filter_fn === null) {
            $filter_fn = 'Thumbtack\Crankshaft\Crankshaft::ToBool';
        }

        return new IteratorIterable(new FilteringIterator($this->getIterator(), $filter_fn));
    }

    /**
     * Create a new iterable that *excludes* the values from this iterable that pass a truth test.
     * Keys in the original iterable will be preserved.
     *
     * $filter_fn - A function `($value, $key) -> bool` that will be called to decide whether each
     *              element in this iterable should be included in the result.
     *              (default: include values in the result if they evaluate to `false`)
     *
     * Examples
     *
     *   Crankshaft::Iter([1, 4, 2, 5, 6, 3])
     *       ->reject(function($num) { return $num % 2 == 0; })
     *       ->to_array()
     *   // => [0 => 1, 3 => 5, 5 => 3]
     *
     *   Crankshaft::Iter(['hi', false, 0, 1, null])
     *       ->reject()
     *       ->to_array()
     *   // => [1 => false, 2 => 0, 4 => null]
     *
     * Returns an Iterable that will yield the values for which `$filter_fn` returned false.
     */
    public function reject($filter_fn=null) {
        if ($filter_fn === null) {
            $filter_fn = 'Thumbtack\Crankshaft\Crankshaft::ToBool';
        }

        return $this->filter(function($value, $key) use ($filter_fn) {
            return !call_user_func($filter_fn, $value, $key);
        });
    }

    /**
     * Filter the iterable to only the values with matching properties. Values in the iterable can
     * be anything that `pluck` understands: arrays, objects with public instance variables, or
     * PropertyContainer objects. Property value comparisons are made using strict equality
     * (`===`).
     *
     * $properties - An array or Traversable object listing the properties and associated values
     *               to match.
     *
     * Examples
     *
     *   Crankshaft::Iter([['n' => 1, 'x' => 1], ['n' => 2, 'x' => 'b'], ['n' => 3, 'x' => 'c']])
     *       ->select(['n' => 2])
     *       ->to_array()
     *   // => [1 => ['n' => 2, 'x' => 'b']]
     *
     * Returns an Iterable that will yield the matching values.
     * Throws UnpluckableError if any of the values in the iterable are not arrays or objects.
     */
    public function select($properties) {
        return $this->filter(function($container) use ($properties) {
            foreach ($properties as $name => $expected_value) {
                if ($this->pluck_property($container, $name) !== $expected_value) {
                    return false;
                }
            }

            return true;
        });
    }

    /**
     * Take a subsequence of an iterable. Keys in the original iterable are preserved.
     *
     * $start - The offset into this iterable to start at, or if negative, a relative offset from
     *          the end of this iterable
     * $end   - The offset into this iterable to end at, or, if negative, a relative offset from the
     *          end of this iterable. (default: -1, the last element)
     * $step  - The value by which the slice index will be incremented on each step. (default: 1)
     *
     * Examples
     *
     *   Crankshaft::Iter([0, 1, 2, 3, 4, 5])->slice(0, 3)->to_array()
     *   // => [0, 1, 2]
     *
     *   Crankshaft::Iter([0, 1, 2, 3, 4, 5])->slice(3)->to_array()
     *   // => [3 => 3, 4 => 4, 5 => 5]
     *
     *   Crankshaft::Iter([0, 1, 2, 3, 4, 5])->slice(-2)->to_array()
     *   // => [4 => 4, 5 => 5]
     *
     *   Crankshaft::Iter([0, 1, 2, 3, 4, 5])->slice(1, -2)->to_array()
     *   // => [1 => 1, 2 => 2, 3 => 3, 4 => 4]
     *
     *   Crankshaft::Iter([0, 1, 2, 3, 4, 5])->slice(-3, -2)->to_array()
     *   // => [3 => 3, 4 => 4]
     *
     * Returns an Iterable that will yield each value in the subsequence.
     * Throws InvalidArgumentException if a negative start or end is used and this iterable is not
     *   Countable.
     */
    public function slice($start, $end=null, $step=1) {
        $iterator = $this->getIterator();

        if ($start < 0) {
            if (!($this instanceof \Countable)) {
                throw new \InvalidArgumentException(
                    'Cannot use a negative start point on a non-countable iterable.'
                );
            }

            $start = count($this) + $start;
        }

        if ($end < 0) {
            if (!($this instanceof \Countable)) {
                throw new \InvalidArgumentException(
                    'Cannot use a negative end point on a non-countable iterable.'
                );
            }

            $end = count($this) + $end;
        } else if ($end === null && $this instanceof \Countable) {
            $end = count($this);
        }

        $indexes = $end === null
            ? new SequenceIterator($start, $step)
            : new BoundedSequenceIterator($start, $end, $step);

        $slice_iterator = new SliceIterator($indexes, $iterator);

        return $indexes instanceof \Countable
            ? new CountableIteratorIterable(count($indexes), $slice_iterator)
            : new IteratorIterable($slice_iterator);
    }

    /**
     * Get the first element.
     *
     * Examples
     *
     *   Crankshaft::Iter([2, 7, 5])->first()
     *   // => 2
     *
     *   Crankshaft::Iter([])->first()
     *   // => null
     *
     * Returns the value of the first element from this iterable or null if it's empty.
     */
    public function first() {
        foreach ($this as $value) {
            return $value;
        }

        return null;
    }

    /**
     * Get the last element.
     *
     * Examples
     *
     *   Crankshaft::Iter([2, 7, 5])->last()
     *   // => 5
     *
     *   Crankshaft::Iter([])->last()
     *   // => null
     *
     * Returns the value of the last element from this iterable or null if it's empty.
     */
    public function last() {
        return $this->reverse()->first();
    }

    /**
     * Make an iterable that yields elements from the first iterable until it is exhausted, then
     * from the second iterable, and so on until they are all exhausted. Used for treating
     * consecutive sequences as a single sequence. Keys from the underlying iterables are
     * necessarily **not** preserved.
     *
     * Examples
     *
     *   Crankshaft::Iter([[1, 2, 3], [4, 5], [6, 7]])->chain()->to_array()
     *   # => [1, 2, 3, 4, 5, 6, 7]
     *
     * See also
     *
     *   Crankshaft::Chain() - accepts the traversables to chain as arguments
     *
     * Returns an Iterable.
     */
    public function chain() {
        return new IteratorIterable(new ChainingIterator($this->getIterator()));
    }

    /**
     * Test if any element in this iterable passes a truth test. Iteration will stop immediately
     * after the first element which passes the test is encountered.
     *
     * If this iterable is empty, the result will always be false.
     *
     * $test_fn - A function `($value, $key) -> bool` that will be called to perform the truth
     *            test on each element. (default: simply interpret the values as booleans)
     *
     * Examples
     *
     *   Crankshaft::Iter([1, 2, 3, 4])->any(function($v) { return $v == 3; })
     *   // => true
     *
     *   Crankshaft::Iter([1, 3, 5, 7, 9])->any(function($v) { return $v % 2 == 0; })
     *   // => false
     *
     *   Crankshaft::Iter([false, false, true, false])->any()
     *   // => true
     *
     *   Crankshaft::Iter([])->any()
     *   // => false
     *
     * Returns true if any element in this iterable passed the truth test; false if none did.
     */
    public function any($test_fn=null) {
        if ($test_fn === null) {
            $test_fn = 'Thumbtack\Crankshaft\Crankshaft::ToBool';
        } else {
            Crankshaft::AssertCallable($test_fn);
        }

        foreach ($this as $key => $value) {
            if (call_user_func($test_fn, $value, $key)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Test if every element in `$iterable` passes a truth test. Iteration will stop immediately
     * after the first element which fails the truth test is encountered.
     *
     * If this iterable is empty, the result will always be true.
     *
     * $test_fn - A function `($value, $key) -> bool` that will be called to perform the truth
     *            test on each element. (default: simply interpret the values as booleans)
     *
     * Examples
     *
     *   Crankshaft::Iter([1, 2, 3, 4])->all(function($v) { return $v > 0; })
     *   // => true
     *
     *   Crankshaft::Iter([2, 4, 6, 7, 8, 10])->all(function($v) { return $v % 2 == 0; })
     *   // => false
     *
     *   Crankshaft::Iter([1, true, 'hi'])->all()
     *   // => true
     *
     *   Crankshaft::Iter([])->all()
     *   // => true
     *
     * Returns true if all elements in this iterable passed the truth test; false if any did not.
     */
    public function all($test_fn=null) {
        if ($test_fn === null) {
            $test_fn = 'Thumbtack\Crankshaft\Crankshaft::ToBool';
        } else {
            Crankshaft::AssertCallable($test_fn);
        }

        foreach ($this as $key => $value) {
            if (!call_user_func($test_fn, $value, $key)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Find the first element in this iterable that passes a truth test.
     *
     * $test_fn - A function `($value, $key) -> bool` that will be called to perform the truth
     *            test on each element.
     * $default - The value to return if no elements pass the truth test. (default: null)
     *
     * Examples
     *
     *   Crankshaft::Iter([1, 2, 3, 4, 5, 6])->find(function($num) { return $num > 3; })
     *   // => 4
     *
     * Returns the matching element, or `$default` if no elements passed the truth test.
     */
    public function find($test_fn, $default=null) {
        Crankshaft::AssertCallable($test_fn);

        foreach ($this as $key => $value) {
            if (call_user_func($test_fn, $value, $key)) {
                return $value;
            }
        }

        return $default;
    }

    /**
     * Group the elements by the result of a function application.
     *
     * $partition_fn      - A function `($value, $key) -> scalar` that will be called to get the
     *                      key for the group into which $value will be stored.
     * $include_ungrouped - If true, also return a second array (the null group) containing the
     *                      values for which the function returned null. (default: false)
     *
     * Examples
     *
     *      Crankshaft::Iter([1, 2, 3, 4, 5, 6])->partition(function($num) { return $num % 3; })
     *      // => [1 => [1, 4], 2 => [2, 5], 0 => [3, 6]]
     *
     *      Crankshaft::Iter([1, null, 2, 3, null])->partition('tt_identity')
     *      // => [1 => [1], 2 => [2], 3 => [3]],
     *
     *      Crankshaft::Iter([1, null, 2, 3, null])->partition('tt_identity', true)
     *      // => [[1 => [1], 2 => [2], 3 => [3]], [null, null]],
     *
     *      Crankshaft::Iter([1, 2, 3])->partition('tt_identity', true)
     *      // => [[1 => [1], 2 => [2], 3 => [3]], []]
     *
     * Returns an array of the partitions and the null group if including ungrouped values;
     *   otherwise returns the partitions.
     */
    public function partition(callable $partition_fn, $include_ungrouped=false) {
        $partitions = [];
        $null_group = [];

        foreach ($this as $key => $value) {
            $bucket = call_user_func($partition_fn, $value, $key);

            if ($bucket !== null) {
                if (!array_key_exists($bucket, $partitions)) {
                    $partitions[$bucket] = [];
                }
                $partitions[$bucket][] = $value;
            } else if ($include_ungrouped) {
                $null_group[] = $value;
            }
        }

        return $include_ungrouped
            ? [Crankshaft::Iter($partitions), Crankshaft::Iter($null_group)]
            : Crankshaft::Iter($partitions);
    }

    /**
     * Group elements by any pluckable property.
     *
     * $property          - The value of this property will be used to group the elements.
     * $include_ungrouped - Whether to have a separate group for elements for which the property
     *                      is null.
     *
     * Examples
     *
     *     Crankshaft::Iter([
     *         ['a' => 1, 'b' => 'qqx'],
     *         ['a' => 1, 'b' => 'bar'],
     *         ['a' => 2, 'b' => 'zzy']
     *     ])->partition_by('a')
     *     // =>
     *     [
     *         1 => [['a' => 1, 'b' => 'qqx'], ['a' => 1, 'b' => 'bar']],
     *         2 => [['a' => 2, 'b' => 'zzy']],
     *     ]
     *
     * See also
     *
     *      partition()
     *
     * Returns an array of the partitions and the null group if including ungrouped values;
     *   otherwise returns the partitions.
     */
    public function partition_by($property, $include_ungrouped=false) {
        return $this->partition(function ($value) use ($property) {
            return $this->pluck_property($value, $property);
        }, $include_ungrouped);
    }

    /**
     * Filter out duplicate elements as determined by the output of $grouping_fn. A value is
     * filtered out if the result of calling $grouping_fn with that value has been seen before.
     *
     * Elements that are not filtered out are returned with their original key.
     *
     * $grouping_fn - A function `($value, $key) -> scalar` that will be called on each element
     *                to get the key for determining whether it is unique.
     *                (default: Crankshaft::Identity)
     *
     * Examples
     *
     *      Crankshaft::Iter([1, 2, 3, 1, 2, 3, 1, 2, 3])->unique()
     *      // => [1, 2, 3]
     *
     *      Crankshaft::Iter(['a', 'a', 'b', 'b', 'c', 'c'])->unique()
     *      // => [0 => 'a', 2 => 'b', 4 => 'c']
     *
     *      Crankshaft::Range(1, 9)->unique(function ($value, $key) { return intval($value / 3); })
     *      // => [0 => 1, 2 => 3, 5 => 6]
     *
     * Returns an Iterable of unique elements.
     */
    public function unique(callable $grouping_fn=null) {
        if ($grouping_fn === null) {
            $grouping_fn = 'Thumbtack\Crankshaft\Crankshaft::Identity';
        }

        return new IteratorIterable(
            new UniqueFilteringIterator($this->getIterator(), $grouping_fn));
    }

    /**
     * Find the first key in this iterable which has `$target` as its value. Comparisons are made
     * using strict equality (`===`).
     *
     * $target  - The value to search for within this iterable.
     * $default - The value to return if no elements pass the truth test. (default: null)
     *
     * Examples
     *
     *   Crankshaft::Iter(['a' => 1, 'b' => 2, 'c' => 3, 'd' => 4])->key_of(3)
     *   // => 'c'
     *
     *   Crankshaft::Iter(['a' => 1, 'b' => 2, 'c' => 3, 'd' => 4])->key_of(5)
     *   // => null
     *
     *   Crankshaft::Iter(['a' => 1, 'b' => 2, 'c' => 3, 'd' => 4])->key_of('3')
     *   // => null
     *
     *   Crankshaft::Iter(['a' => 1, 'b' => 2, 'c' => 3, 'd' => 4])->key_of(5, 'x')
     *   // => 'x'
     *
     * Returns the matching key, or `$default` if `$target` was not found.
     */
    public function key_of($target, $default=null) {
        foreach ($this as $key => $value) {
            if ($value === $target) {
                return $key;
            }
        }

        return $default;
    }

    /**
     * Find the first index in this iterable which has `$target` as its value. Comparisons are made
     * using strict equality (`===`).
     *
     * $target - The value to search for within this iterable.
     *
     * Examples
     *
     *   Crankshaft::Iter(['a', 'b', 'c', 'd', 'e', 'f'])->index_of('d')
     *   // => 3
     *
     *   Crankshaft::Iter(['a', 'b', 'c', 'd', 'e', 'f'])->index_of('q')
     *   // => -1
     *
     *   Crankshaft::Iter([1, 2, 3, 4, 5])->index_of('3')
     *   // => -1
     *
     * Returns the matching index, or -1 if `$target` was not found.
     */
    public function index_of($target) {
        return $this->key_of($target, -1);
    }

    /**
     * Check if this iterable contains a particular key. Comparisons are made using **loose**
     * equality (`==`), in order to match the behavior of `array_key_exists`.
     *
     * $target - The key to search for within this iterable.
     *
     * Examples
     *
     *   Crankshaft::Iter(['a' => 97, 'b' => 98, 'c' => 99])->has_key('b')
     *   // => true
     *
     *   Crankshaft::Iter(['a' => 97, 'b' => 98, 'c' => 99])->has_key('d')
     *   // => false
     *
     *   Crankshaft::Iter(['a' => 97, 'b' => 98, 'c' => 99])->has_key(98)
     *   // => false
     *
     *   Crankshaft::Iter(['x', 'y', 'z'])-has_key(2)
     *   // => true
     *
     *   Crankshaft::Iter(['x', 'y', 'z'])-has_key('2')
     *   // => true
     *
     * See also
     *
     *   Crankshaft::HasKey() - If the only Iterable call you want to make is this one, you can use
     *                  the more convenient global form.
     *
     * Returns true if the key was found; false if it was not.
     */
    public function has_key($target) {
        $iterator = $this->getIterator();
        if ($iterator instanceof \ArrayAccess) {
            return $iterator->offsetExists($target);
        } else {
            return $this->any(function($value, $key) use ($target) {
                return $key == $target;
            });
        }
    }

    /**
     * Check if this iterable contains a particular value. Comparisons are made using strict
     * equality (`===`).
     *
     * $target - The value to search for within this iterable.
     *
     * Examples
     *
     *   Crankshaft::Iter([97, 98, 99])->contains(98)
     *   // => true
     *
     *   Crankshaft::Iter([97, 98, 99])->contains('98')
     *   // => false
     *
     * Returns true if the value was found; false if it was not.
     */
    public function contains($target) {
        return $this->any(function($value) use ($target) {
            return $value === $target;
        });
    }

    /**
     * Extract a property or array value from each element in this iterable.
     *
     * Each member of this iterable must be an array, an ArrayAccess object, an object with a
     * public instance variable named `$property`, or a PropertyContainer object.
     *
     * $property - The name of the property to extract from each element of this iterable.
     *
     * Examples
     *
     *   Crankshaft::Iter([
     *       ['name' => 'Alan', age => 39],
     *       ['name' => 'Sue', age => 26],
     *       ['name' => 'Ehud', age => 63],
     *   ])
     *       ->pluck('name')
     *       ->to_array()
     *  // => ['Alan', 'Sue', 'Ehud']
     *
     * Returns an Iterable that will yield the extracted properties.
     * Throws UnpluckableError if any of the values in the iterable are not arrays or objects.
     */
    public function pluck($property) {
        return $this->map(function($value) use ($property) {
            return $this->pluck_property($value, $property);
        });
    }

    /**
     * Make an iterable that will yield this iterable's values in reverse order. Keys in the
     * original iterable will be preserved.
     *
     * This iterable will be consumed in its entirety when `reverse()` is called.
     *
     * Examples
     *
     *   Crankshaft::Iter(['a', 'b', 'c', 'd', 'e'])->reverse()->to_array()
     *   // => [4 => 'e', 3 => 'd, 2 => 'c', 1 => 'b', 0 => 'a']
     *
     * Returns an Iterable.
     */
    public function reverse() {
        $keys = new \SplDoublyLinkedList();
        $values = new \SplDoublyLinkedList();

        foreach ($this as $key => $value) {
            $keys->push($key);
            $values->push($value);
        }

        $reversed = [];
        while (!$values->isEmpty()) {
            $reversed[$keys->pop()] = $values->pop();
        }

        return Crankshaft::Iter($reversed);
    }

    /**
     * Find the maximal element in this iterable.
     *
     * If more than one element in the input is considered to be maximal, the first one encountered
     * will be returned.
     *
     * $key_fn - An optional function `($value, $key)` that will be used to get the comparison
     *             (default: use the element values themselves).
     *
     * Examples
     *
     *   Crankshaft::Iter([1, 5, 3, 9, 4])->max()
     *   // => 9
     *
     *   Crankshaft::Iter([1, 5, 3, 9, 4])->max(function($num) { return -$num; })
     *   // => 1
     *
     * Returns the maximal value from this iterable.
     * Throws EmptyIterableError if this iterable is empty.
     */
    public function max($key_fn=null) {
        return $this->optimal(
            function($best, $current) {
                return $current > $best;
            },
            $key_fn
        );
    }

    /**
     * Find the minimal element in this iterable.
     *
     * If more than one element in the input is considered to be minimal, the first one encountered
     * will be returned.
     *
     * $key_fn - An optional function `($value, $key)` that will be used to get the comparison
     *             (default: use the element values themselves).
     *
     * Examples
     *
     *   Crankshaft::Iter([5, 2, 7, 4, 9])->min()
     *   // => 2
     *
     *   Crankshaft::Iter([5, 2, 7, 4, 9])->min(function($num) { return -$num; })
     *   // => 9
     *
     * Returns the minimal value from this iterable.
     * Throws EmptyIterableError if this iterable is empty.
     */
    public function min($key_fn=null) {
        return $this->optimal(
            function($best, $current) {
                return $current < $best;
            },
            $key_fn
        );
    }

    /**
     * Compute the sum of this iterable.
     *
     * $zero - What to consider the "zero" value. (default: 0)
     *
     * Examples
     *
     *   Crankshaft::Iter([3, 1, 2])->sum()
     *   // => 6
     *
     *   Crankshaft::Iter([3, 1, 2])->sum(0.0)
     *   // => 6.0
     *
     * Returns the sum if this iterable was not empty, or `$zero` if it was empty.
     */
    public function sum($zero=0) {
        return $this->reduce(
            function($memo, $value) {
                return $memo + $value;
            },
            $zero
        );
    }

    /**
     * Join the elements of this iterable together into one string. Elements within the iterable
     * will be automatically coerced to strings as part of joining.
     *
     * $joiner - The string to insert between adjacent elements of the iterable.
     *
     * Examples
     *
     *   Crankshaft::Iter(['foo', 'bar', 'baz'])->join(', ')
     *   // => 'foo, bar, baz'
     *
     *   Crankshaft::Iter([1, 2, 3])->join(' + ')
     *   // => '1 + 2 + 3'
     *
     * Returns the joined string.
     */
    public function join($joiner) {
        return implode($joiner, $this->to_array());
    }

    /**
     * Count the number of elements in this iterable.
     *
     * Note that this method intentionally has the same name as the method from PHP's
     * `Countable` interface. This means that Iterables that are also Countable will naturally
     * override this method with an implementation that is presumably constant-time.
     *
     * Examples
     *
     *   Crankshaft::Iter([1, 2, 3, 4, 5])->count()
     *   // => 5
     *
     * Returns the integer number of elements.
     */
    public function count() {
        $count = 0;

        foreach ($this as $value) {
            $count++;
        }

        return $count;
    }

    /**
     * Extract just the keys from this iterable.
     *
     * Examples
     *
     *   Crankshaft::Iter(['a' => 97, 'b' => 98, 'c' => 99, 'd' => 100])->keys()->to_array()
     *   // => ['a', 'b', 'c', 'd']
     *
     * Returns an Iterable whose yielded values will be the keys of `$iterable`, and whose keys
     *   will be the integers, starting from 0.
     */
    public function keys() {
        return Crankshaft::Combine(
            Crankshaft::Count(),
            $this->map(function($value, $key) {
                return $key;
            })
        );
    }

    /**
     * Extract just the values from this iterable.
     *
     * Examples
     *
     *   Crankshaft::Iter(['a' => 97, 'b' => 98, 'c' => 99, 'd' => 100])->values()->to_array()
     *   // => [97, 98, 99, 100]
     *
     * Returns an Iterable whose yielded values will be the values of `$iterable`, and whose keys
     *   will be the integer, starting from 0.
     */
    public function values() {
        return Crankshaft::Combine(
            Crankshaft::Count(),
            $this
        );
    }

    /**
     * Make an iterable that will take its keys from the given iterable's values, and vice-versa.
     *
     * The values of this iterable must be valid as PHP array keys; i.e., they must be scalars.
     * If any values are not scalars, PHP will emit a warning as that key is iterated over, and
     * the effective value of that key is undefined.
     *
     * If this iterable contains duplicate values, it will still be possible to iterate over the
     * resulting iterable without losing data. The duplicate keys will be yielded. If the
     * flipped iterable is converted to an array, however, only the last value for a duplicated
     * key will be kept, and it will appear in the output array as the position where that
     * key was first seen.
     *
     * Examples
     *
     *   Crankshaft::Iter(['a' => 97, 'b' => 98, 'c' => 99])->flip()->to_array()
     *   // => [97 => 'a', 98 => 'b', 99 => 'c']
     *
     *   Crankshaft::Iter(['a' => 1, 'b' => 2, 'c' => 1, 'd' => 2, 'e' => 3])->flip()->to_set()
     *   // => Set(['a', 'b', 'c', 'd', 'e'])
     *
     *   Crankshaft::Iter(['a' => 1, 'b' => 2, 'c' => 1, 'd' => 2, 'e' => 3])->flip()->to_array()
     *   // => [1 => 'c', 2 => 'd', 3 => 'e']
     *
     * Returns an Iterable.
     */
    public function flip() {
        return Crankshaft::Iter(new FlippingIterator($this->getIterator()));
    }

    /**
     * Convert this iterable into one that will infinitely repeat its sequence of values.
     * Keys from the underlying iterable are necessarily **not** preserved.
     *
     * This method only works correctly if the Iterator produced by its `getIterator()` method
     * has a working `rewind()` implementation. If not, behavior is undefined.
     *
     * Examples
     *
     *   Crankshaft::Iter(['x', 'y', 'z'])->cycle()
     *   // => 'x', 'y', 'z', 'x', 'y', 'z', 'x', 'y', ...
     *
     * Returns an Iterable.
     */
    public function cycle() {
        return Crankshaft::Iter(new CyclingIterator($this->getIterator()));
    }

    /**
     * Sort values of the iterator by the sort function. This sort is not stable and does not
     * preserve keys.
     *
     * $sort_fn - A function taking two elements which returns an integer greater than 0 if the
     *            first argument is greater than the second, an integer less than 0 if the first is
     *            less than the second, and 0 if the two arguments are equal in sort order.
     *            (default: Crankshaft::Compare())
     *
     * Examples
     *
     *     Crankshaft::Iter([2, 3, 2, 1])->sort()
     *     // => 1, 2, 2, 3
     *
     *     Crankshaft::Iter(['aaa', 'a', 'aa', 'aaaa'])->sort(function ($a, $b) {
     *         return strlen($a) - strlen($b);
     *     })
     *     // => 'a', 'aa', 'aaa', 'aaaa'
     *
     * See also
     *
     *     Crankshaft::Compare() - The default sort order function
     *
     * Returns an Iterable.
     */
    public function sort(callable $sort_fn=null) {
        if ($sort_fn === null) {
            $sort_fn = 'Thumbtack\Crankshaft\Crankshaft::Compare';
        }

        $values = $this->to_array();
        usort($values, $sort_fn);
        return Crankshaft::Iter($values);
    }

    /**
     * Sort elements in this iterable by their properties or array values.
     *
     * $properties - A mapping between field names and sort direction (1 or -1). The order of keys
     *               determines the precedence of a field in determining the relative ordering of
     *               two objects.
     *
     * Examples
     *
     *     Crankshaft::Iter([['a' => 30], ['a' => 10], ['a' => 20]])->sort_by_properties(['a' => 1])
     *     // => ['a' => 10], ['a' => 20], ['a' => 30]
     *
     *     Crankshaft::Iter([['a' => 30], ['a' => 10], ['a' => 20]])->sort_by_properties(['a' => -1])
     *     // => ['a' => 30], ['a' => 20], ['a' => 10]
     *
     *     Crankshaft::Iter([['a' => 2, 'b' => 3], ['a' => 1, 'b' => 2], ['a' => 1, 'b' => 1]])
     *         ->sort_by_properties(['a' => 1, 'b' => -1])
     *     // => ['a' => 1, 'b' => 2], ['a' => 1, 'b' => 1], ['a' => 2, 'b' => 3]
     *
     * See also
     *
     *     pluck() - For the requirements placed on the elements in this iterable.
     *     sort() - For properties of the sort.
     *
     * Returns an Iterable
     */
    public function sort_by_properties(array $properites) {
        return $this->sort(function ($a, $b) use ($properites) {
            foreach ($properites as $property => $direction) {
                $a_value = $this->pluck_property($a, $property);
                $b_value = $this->pluck_property($b, $property);

                $ordering = Crankshaft::compare($a_value, $b_value);
                if ($ordering !== 0) {
                    return $direction * $ordering;
                }
            }
            return 0;
        });
    }

    /**
     * Randomly re-order values. This operation does not preserve keys.
     *
     * Examples
     *
     *     Crankshaft::Iter([1, 2, 3, 4])->shuffle()->to_array()
     *     // => [3, 2, 1, 4] // or something entirely else
     *
     * Returns an Iterable.
     */
    public function shuffle() {
        $values = $this->to_array();
        shuffle($values);
        return Crankshaft::Iter($values);
    }

    /**
     * Public: Load all values from this iterable into an array.
     *
     * Returns an array of every remaining value in this iterable. Keys are preserved.
     */
    public function to_array() {
        $values = [];

        foreach ($this as $key => $value) {
            $values[$key] = $value;
        }

        return $values;
    }

    /**
     * Public: Load all values from this iterable into a set.
     *
     * Returns a Set containing every remaining value in this iterable.
     */
    public function to_set() {
        return Crankshaft::Set($this);
    }

    public abstract function getIterator();

    /**
     * Internal: Used to implement pluck().
     */
    private function pluck_property($container, $property) {
        $is_object = is_object($container);

        if (is_array($container) || ($is_object && $container instanceof \ArrayAccess)) {
            return Crankshaft::HasKey($container, $property) ? $container[$property] : null;
        } else if ($is_object) {
            // property_exists() will return true if there's a defined public property for the
            // object's class, even if the instance value of that property is null -- but it
            // will return false if the container object uses __get magic.
            //
            // isset() will return true if magic is in use and __isSet($property) is true,
            // but will return false if the value of a magic or real property is null.
            //
            // So, we perform both tests, which catches most cases. If the object defines a
            // magic property whose value is null, we assume we'll fall through to the else
            // case where we return null anyway.
            if (property_exists($container, $property) || isset($container->$property)) {
                return $container->$property;
            } else if ($container instanceof PropertyContainer) {
                return $container->get($property);
            } else {
                return null;
            }
        } else {
            throw new UnpluckableError(
                'Cannot pluck ' . var_dump($property, true) . ' from ' . var_dump($container, true));
        }
    }

    /**
     * Internal: Used to implement max() and min().
     */
    private function optimal($is_better_fn, $key_fn=null) {
        Crankshaft::AssertCallable($is_better_fn);

        if ($key_fn === null) {
            $key_fn = 'Thumbtack\Crankshaft\Crankshaft::Identity';
        } else {
            Crankshaft::AssertCallable($key_fn);
        }

        $initializing = true;
        $result = null;
        $best_key = null;

        foreach ($this as $value) {
            $key = call_user_func($key_fn, $value);

            if ($initializing) {
                $initializing = false;
                $current_value_is_better = true;
            } else {
                $current_value_is_better = call_user_func($is_better_fn, $best_key, $key);
            }

            if ($current_value_is_better) {
                $result = $value;
                $best_key = $key;
            }
        }

        if ($initializing) {
            throw new EmptyIterableError('Cannot take the max or min of an empty iterable.');
        }

        return $result;
    }
}
