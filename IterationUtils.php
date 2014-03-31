<?php

namespace IterationUtils;

class Error extends \Exception {}

class NotTraversableError extends Error {}

class EmptyIterableError extends Error {}

class CannotRewindError extends Error {}

class UnpluckableError extends Error {}

/**
 * Public: A standard way to represent a key/value pair.
 */
class KeyValuePair {
    public $key;
    public $value;

    public function __construct($key, $value) {
        $this->key = $key;
        $this->value = $value;
    }
}

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

/**
 * Public: Provides many useful methods for dealing with collections. Custom collection classes
 * may inherit from Iterable directly, and any standard PHP array or Traversable object can be
 * made into an Iterable by passing it to `tt_iter()`.
 *
 * See also
 *
 *   tt_iter() - Turn any array or Traversable object into an Iterable.
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
     *   tt_iter(['a' => 1, 'b' => 2, 'c' => 4])
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
        tt_assert_callable($each_fn);

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
     *   tt_iter([1, 2, 3, 4, 5, 6])
     *       ->map(function($n) { return $n * $n; })
     *       ->to_array()
     *   // => [1, 4, 9, 16, 25, 36]
     *
     *   tt_iter(['a' => 'hello', 'b' => 'goodbye'])
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
     *   tt_iter(['a' => 1, 'b' => 2, 'c' => 3])
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
     *   tt_iter([2, 7, 5])->reduce(function($sum, $value) { return $sum + $value })
     *   // => 14
     *
     *   tt_iter([], 0)->reduce(function($sum, $value) { return $sum + $value })
     *   // => 0
     *
     * Returns the final value of `$memo` after each element has been stepped through.
     * Throws EmptyIterableError if reduce is called on an empty iterable and no explicit
     *   `$memo` is provided.
     */
    public function reduce($reduce_fn, $memo=null) {
        tt_assert_callable($reduce_fn);

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
     *   tt_iter([1, 4, 2, 5, 6, 3])
     *       ->filter(function($num) { return $num % 2 == 0; })
     *       ->to_array()
     *   // => [1 => 4, 2 => 2, 4 => 6]
     *
     *   tt_iter(['hi', false, 0, 1, null])
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
            $filter_fn = 'tt_to_bool';
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
     *   tt_iter([1, 4, 2, 5, 6, 3])
     *       ->reject(function($num) { return $num % 2 == 0; })
     *       ->to_array()
     *   // => [0 => 1, 3 => 5, 5 => 3]
     *
     *   tt_iter(['hi', false, 0, 1, null])
     *       ->reject()
     *       ->to_array()
     *   // => [1 => false, 2 => 0, 4 => null]
     *
     * Returns an Iterable that will yield the values for which `$filter_fn` returned false.
     */
    public function reject($filter_fn=null) {
        if ($filter_fn === null) {
            $filter_fn = 'tt_to_bool';
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
     *   tt_iter([['n' => 1, 'x' => 1], ['n' => 2, 'x' => 'b'], ['n' => 3, 'x' => 'c']])
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
     *   tt_iter([0, 1, 2, 3, 4, 5])->slice(0, 3)->to_array()
     *   // => [0, 1, 2]
     *
     *   tt_iter([0, 1, 2, 3, 4, 5])->slice(3)->to_array()
     *   // => [3 => 3, 4 => 4, 5 => 5]
     *
     *   tt_iter([0, 1, 2, 3, 4, 5])->slice(-2)->to_array()
     *   // => [4 => 4, 5 => 5]
     *
     *   tt_iter([0, 1, 2, 3, 4, 5])->slice(1, -2)->to_array()
     *   // => [1 => 1, 2 => 2, 3 => 3, 4 => 4]
     *
     *   tt_iter([0, 1, 2, 3, 4, 5])->slice(-3, -2)->to_array()
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
     *   tt_iter([2, 7, 5])->first()
     *   // => 2
     *
     *   tt_iter([])->first()
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
     *   tt_iter([2, 7, 5])->last()
     *   // => 5
     *
     *   tt_iter([])->last()
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
     *   tt_iter([[1, 2, 3], [4, 5], [6, 7]])->chain()->to_array()
     *   # => [1, 2, 3, 4, 5, 6, 7]
     *
     * See also
     *
     *   tt_chain() - accepts the traversables to chain as arguments
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
     *   tt_iter([1, 2, 3, 4])->any(function($v) { return $v == 3; })
     *   // => true
     *
     *   tt_iter([1, 3, 5, 7, 9])->any(function($v) { return $v % 2 == 0; })
     *   // => false
     *
     *   tt_iter([false, false, true, false])->any()
     *   // => true
     *
     *   tt_iter([])->any()
     *   // => false
     *
     * Returns true if any element in this iterable passed the truth test; false if none did.
     */
    public function any($test_fn=null) {
        if ($test_fn === null) {
            $test_fn = 'tt_to_bool';
        } else {
            tt_assert_callable($test_fn);
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
     *   tt_iter([1, 2, 3, 4])->all(function($v) { return $v > 0; })
     *   // => true
     *
     *   tt_iter([2, 4, 6, 7, 8, 10])->all(function($v) { return $v % 2 == 0; })
     *   // => false
     *
     *   tt_iter([1, true, 'hi'])->all()
     *   // => true
     *
     *   tt_iter([])->all()
     *   // => true
     *
     * Returns true if all elements in this iterable passed the truth test; false if any did not.
     */
    public function all($test_fn=null) {
        if ($test_fn === null) {
            $test_fn = 'tt_to_bool';
        } else {
            tt_assert_callable($test_fn);
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
     *   tt_iter([1, 2, 3, 4, 5, 6])->find(function($num) { return $num > 3; })
     *   // => 4
     *
     * Returns the matching element, or `$default` if no elements passed the truth test.
     */
    public function find($test_fn, $default=null) {
        tt_assert_callable($test_fn);

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
     *      tt_iter([1, 2, 3, 4, 5, 6])->partition(function($num) { return $num % 3; })
     *      // => [1 => [1, 4], 2 => [2, 5], 0 => [3, 6]]
     *
     *      tt_iter([1, null, 2, 3, null])->partition('tt_identity')
     *      // => [1 => [1], 2 => [2], 3 => [3]],
     *
     *      tt_iter([1, null, 2, 3, null])->partition('tt_identity', true)
     *      // => [[1 => [1], 2 => [2], 3 => [3]], [null, null]],
     *
     *      tt_iter([1, 2, 3])->partition('tt_identity', true)
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
            ? [tt_iter($partitions), tt_iter($null_group)]
            : tt_iter($partitions);
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
     *     tt_iter([
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
     *                to get the key for determining whether it is unique. (default: tt_identity)
     *
     * Examples
     *
     *      tt_iter([1, 2, 3, 1, 2, 3, 1, 2, 3])->unique()
     *      // => [1, 2, 3]
     *
     *      tt_iter(['a', 'a', 'b', 'b', 'c', 'c'])->unique()
     *      // => [0 => 'a', 2 => 'b', 4 => 'c']
     *
     *      tt_range(1, 9)->unique(function ($value, $key) { return intval($value / 3); })
     *      // => [0 => 1, 2 => 3, 5 => 6]
     *
     * Returns an Iterable of unique elements.
     */
    public function unique(callable $grouping_fn=null) {
        if ($grouping_fn === null) {
            $grouping_fn = 'tt_identity';
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
     *   tt_iter(['a' => 1, 'b' => 2, 'c' => 3, 'd' => 4])->key_of(3)
     *   // => 'c'
     *
     *   tt_iter(['a' => 1, 'b' => 2, 'c' => 3, 'd' => 4])->key_of(5)
     *   // => null
     *
     *   tt_iter(['a' => 1, 'b' => 2, 'c' => 3, 'd' => 4])->key_of('3')
     *   // => null
     *
     *   tt_iter(['a' => 1, 'b' => 2, 'c' => 3, 'd' => 4])->key_of(5, 'x')
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
     *   tt_iter(['a', 'b', 'c', 'd', 'e', 'f'])->index_of('d')
     *   // => 3
     *
     *   tt_iter(['a', 'b', 'c', 'd', 'e', 'f'])->index_of('q')
     *   // => -1
     *
     *   tt_iter([1, 2, 3, 4, 5])->index_of('3')
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
     *   tt_iter(['a' => 97, 'b' => 98, 'c' => 99])->has_key('b')
     *   // => true
     *
     *   tt_iter(['a' => 97, 'b' => 98, 'c' => 99])->has_key('d')
     *   // => false
     *
     *   tt_iter(['a' => 97, 'b' => 98, 'c' => 99])->has_key(98)
     *   // => false
     *
     *   tt_iter(['x', 'y', 'z'])-has_key(2)
     *   // => true
     *
     *   tt_iter(['x', 'y', 'z'])-has_key('2')
     *   // => true
     *
     * See also
     *
     *   tt_has_key() - If the only Iterable call you want to make is this one, you can use
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
     *   tt_iter([97, 98, 99])->contains(98)
     *   // => true
     *
     *   tt_iter([97, 98, 99])->contains('98')
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
     *   tt_iter([
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
     *   tt_iter(['a', 'b', 'c', 'd', 'e'])->reverse()->to_array()
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

        return tt_iter($reversed);
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
     *   tt_iter([1, 5, 3, 9, 4])->max()
     *   // => 9
     *
     *   tt_iter([1, 5, 3, 9, 4])->max(function($num) { return -$num; })
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
     *   tt_iter([5, 2, 7, 4, 9])->min()
     *   // => 2
     *
     *   tt_iter([5, 2, 7, 4, 9])->min(function($num) { return -$num; })
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
     *   tt_iter([3, 1, 2])->sum()
     *   // => 6
     *
     *   tt_iter([3, 1, 2])->sum(0.0)
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
     *   tt_iter(['foo', 'bar', 'baz'])->join(', ')
     *   // => 'foo, bar, baz'
     *
     *   tt_iter([1, 2, 3])->join(' + ')
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
     *   tt_iter([1, 2, 3, 4, 5])->count()
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
     *   tt_iter(['a' => 97, 'b' => 98, 'c' => 99, 'd' => 100])->keys()->to_array()
     *   // => ['a', 'b', 'c', 'd']
     *
     * Returns an Iterable whose yielded values will be the keys of `$iterable`, and whose keys
     *   will be the integers, starting from 0.
     */
    public function keys() {
        return tt_combine(
            tt_count(),
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
     *   tt_iter(['a' => 97, 'b' => 98, 'c' => 99, 'd' => 100])->values()->to_array()
     *   // => [97, 98, 99, 100]
     *
     * Returns an Iterable whose yielded values will be the values of `$iterable`, and whose keys
     *   will be the integer, starting from 0.
     */
    public function values() {
        return tt_combine(
            tt_count(),
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
     *   tt_iter(['a' => 97, 'b' => 98, 'c' => 99])->flip()->to_array()
     *   // => [97 => 'a', 98 => 'b', 99 => 'c']
     *
     *   tt_iter(['a' => 1, 'b' => 2, 'c' => 1, 'd' => 2, 'e' => 3])->flip()->to_set()
     *   // => tt_set(['a', 'b', 'c', 'd', 'e'])
     *
     *   tt_iter(['a' => 1, 'b' => 2, 'c' => 1, 'd' => 2, 'e' => 3])->flip()->to_array()
     *   // => [1 => 'c', 2 => 'd', 3 => 'e']
     *
     * Returns an Iterable.
     */
    public function flip() {
        return tt_iter(new FlippingIterator($this->getIterator()));
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
     *   tt_iter(['x', 'y', 'z'])->cycle()
     *   // => 'x', 'y', 'z', 'x', 'y', 'z', 'x', 'y', ...
     *
     * Returns an Iterable.
     */
    public function cycle() {
        return tt_iter(new CyclingIterator($this->getIterator()));
    }

    /**
     * Sort values of the iterator by the sort function. This sort is not stable and does not
     * preserve keys.
     *
     * $sort_fn - A function taking two elements which returns an integer greater than 0 if the
     *            first argument is greater than the second, an integer less than 0 if the first is
     *            less than the second, and 0 if the two arguments are equal in sort order.
     *            (default: tt_cmp())
     *
     * Examples
     *
     *     tt_iter([2, 3, 2, 1])->sort()
     *     // => 1, 2, 2, 3
     *
     *     tt_iter(['aaa', 'a', 'aa', 'aaaa'])->sort(function ($a, $b) {
     *         return strlen($a) - strlen($b);
     *     })
     *     // => 'a', 'aa', 'aaa', 'aaaa'
     *
     * See also
     *
     *     tt_cmp() - The default sort order function
     *
     * Returns an Iterable.
     */
    public function sort(callable $sort_fn=null) {
        if ($sort_fn === null) {
            $sort_fn = 'tt_cmp';
        }

        $values = $this->to_array();
        usort($values, $sort_fn);
        return tt_iter($values);
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
     *     tt_iter([['a' => 30], ['a' => 10], ['a' => 20]])->sort_by_properties(['a' => 1])
     *     // => ['a' => 10], ['a' => 20], ['a' => 30]
     *
     *     tt_iter([['a' => 30], ['a' => 10], ['a' => 20]])->sort_by_properties(['a' => -1])
     *     // => ['a' => 30], ['a' => 20], ['a' => 10]
     *
     *     tt_iter([['a' => 2, 'b' => 3], ['a' => 1, 'b' => 2], ['a' => 1, 'b' => 1]])
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

                $ordering = tt_cmp($a_value, $b_value);
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
     *     tt_iter([1, 2, 3, 4])->shuffle()->to_array()
     *     // => [3, 2, 1, 4] // or something entirely else
     *
     * Returns an Iterable.
     */
    public function shuffle() {
        $values = $this->to_array();
        shuffle($values);
        return tt_iter($values);
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
     * Returns a TTSet containing every remaining value in this iterable.
     */
    public function to_set() {
        return tt_set($this);
    }

    public abstract function getIterator();

    /**
     * Internal: Used to implement pluck().
     */
    private function pluck_property($container, $property) {
        $is_object = is_object($container);

        if (is_array($container) || ($is_object && $container instanceof \ArrayAccess)) {
            return tt_has_key($container, $property) ? $container[$property] : null;
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
        tt_assert_callable($is_better_fn);

        if ($key_fn === null) {
            $key_fn = 'tt_identity';
        } else {
            tt_assert_callable($key_fn);
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

/**
 * Public: Wraps an iterator and gives each element sequential integer keys starting from 0.
 */
class SequentialKeyIterator implements \OuterIterator {
    private $iterator;
    private $position;

    public function __construct(\Iterator $iterator) {
        $this->iterator = $iterator;
    }

    public function rewind() {
        $this->iterator->rewind();
        $this->position = 0;
    }

    public function current() {
        return $this->iterator->current();
    }

    public function key() {
        return $this->position;
    }

    public function valid() {
        return $this->iterator->valid();
    }

    public function next() {
        $this->iterator->next();
        $this->position++;
    }

    public function getInnerIterator() {
        return $this->iterator;
    }
}

/**
 * Public: A special exception type used to signal that the end of a generator has been reached.
 *
 * Equivalent to `StopIteration` in Python.
 */
class GeneratorExhausted extends \RuntimeException {}

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

/**
 * Internal: Wraps an Iterator to make the Iterable methods available.
 */
class IteratorIterable extends Iterable {
    private $iterator;

    public function __construct(\Iterator $iterator) {
        $this->iterator = $iterator;
    }

    public function getIterator() {
        return $this->iterator;
    }
}

/**
 * Internal: An IteratorIterable that is also countable.
 */
class CountableIteratorIterable extends IteratorIterable implements \Countable {
    private $count;

    public function __construct($count, \Iterator $iterator) {
        parent::__construct($iterator);
        $this->count = $count;
    }

    public function count() {
        return $this->count;
    }
}

/**
 * Internal: Wraps an IteratorAggregate to make the Iterable methods available.
 */
class IteratorAggregateIterable extends Iterable {
    private $aggregate;

    public function __construct(\IteratorAggregate $aggregate) {
        $this->aggregate = $aggregate;
    }

    public function getIterator() {
        return $this->aggregate->getIterator();
    }
}

/**
 * Internal: An IteratorAggregateIterable that is also countable.
 */
class CountableIteratorAggregateIterable extends IteratorAggregateIterable implements \Countable {
    private $count;

    public function __construct($count, \IteratorAggregate $aggregate) {
        parent::__construct($aggregate);
        $this->count = $count;
    }

    public function count() {
        return $this->count;
    }
}

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

/**
 * Internal: An iterator that implements map().
 */
class MappingIterator extends \IteratorIterator {
    private $map_fn;

    public function __construct($traversable, $map_fn) {
        tt_assert_callable($map_fn);

        parent::__construct($traversable);
        $this->map_fn = $map_fn;
    }

    public function current() {
        $iterator = $this->getInnerIterator();
        return call_user_func($this->map_fn, $iterator->current(), $iterator->key());
    }
}

/**
 * Internal: An iterator that implements map_keys().
 */
class KeyMappingIterator extends \IteratorIterator {
    private $map_fn;

    public function __construct($traversable, callable $map_fn) {
        parent::__construct($traversable);
        $this->map_fn = $map_fn;
    }

    public function key() {
        $iterator = $this->getInnerIterator();
        return call_user_func($this->map_fn, $iterator->key(), $iterator->current());
    }
}

/**
 * Internal: An iterator that implements filter().
 */
class FilteringIterator extends \FilterIterator {
    private $filter_fn;

    public function __construct(\Iterator $iterator, $filter_fn) {
        tt_assert_callable($filter_fn);

        parent::__construct($iterator);
        $this->filter_fn = $filter_fn;
    }

    public function accept() {
        $iterator = $this->getInnerIterator();
        return call_user_func($this->filter_fn, $iterator->current(), $iterator->key());
    }
}

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
        $this->seen = tt_set();
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

/**
 * Internal: An iterator that implements chain().
 */
class ChainingIterator implements \Iterator {
    private $chain;
    private $current_iterator;
    private $count;

    /**
     * Create a chaining iterator.
     *
     * $chain - An iterator of arrays or Traversable objects.
     */
    public function __construct(\Iterator $chain) {
        $this->chain = $chain;
    }

    public function rewind() {
        $this->chain->rewind();
        $this->count = 0;

        $this->update_current_iterator();
        while ($this->current_iterator && !$this->current_iterator->valid()) {
            $this->chain->next();
            $this->update_current_iterator();
        }
    }

    public function valid() {
        return $this->current_iterator !== null;
    }

    public function key() {
        return $this->count;
    }

    public function current() {
        return $this->valid() ? $this->current_iterator->current() : null;
    }

    public function next() {
        $this->current_iterator->next();
        $this->count++;

        while ($this->current_iterator && !$this->current_iterator->valid()) {
            $this->chain->next();
            $this->update_current_iterator();
        }
    }

    private function update_current_iterator() {
        $this->current_iterator = $this->chain->valid()
            ? tt_to_iterator($this->chain->current())
            : null;

        if ($this->current_iterator !== null) {
            $this->current_iterator->rewind();
        }
    }
}

/**
 * Internal: An iterator that implements tt_count().
 */
class SequenceIterator implements \SeekableIterator {
    protected $start;
    protected $step;

    private $value;
    private $position;

    public function __construct($start, $step) {
        $this->start = $start;
        $this->step = $step;
    }

    public function rewind() {
        $this->value = $this->start;
        $this->position = 0;
    }

    public function current() {
        return $this->value;
    }

    public function key() {
        return $this->position;
    }

    public function next() {
        $this->value += $this->step;
        $this->position++;
    }

    public function valid() {
        return true;
    }

    public function seek($position) {
        if (!is_integer($position) || $position < 0) {
            throw new \OutOfBoundsException('Cannot seek to ' . var_dump($position, true) . '.');
        }

        $old_position = $this->position;
        $old_value = $this->value;

        $this->position = $position;
        $this->value = $this->start + ($position * $this->step);

        if (!$this->valid()) {
            $this->position = $old_position;
            $this->value = $old_value;

            throw new \OutOfBoundsException(var_dump($position, true) . ' is out of bounds.');
        }
    }
}

/**
 * Internal: An iterator that implements tt_range().
 */
class BoundedSequenceIterator extends SequenceIterator implements \Countable {
    private $stop;
    private $impossible;

    public function __construct($start, $stop, $step) {
        parent::__construct($start, $step);
        $this->stop = $stop;
        $this->impossible = (
            ($this->stop > $this->start && $this->step < 0) ||
            ($this->stop < $this->start && $this->step > 0)
        );
    }

    public function valid() {
        if ($this->impossible) {
            return false;
        } else {
            return $this->stop >= $this->start
                ? $this->current() < $this->stop
                : $this->current() > $this->stop;
        }
    }

    public function count() {
        return intval(($this->stop - $this->start) / $this->step);
    }
}

/**
 * Internal: An iterator that implements slice().
 */
class SliceIterator extends \IteratorIterator implements \SeekableIterator {
    private $index_iterator;
    private $position;

    public function __construct(\SeekableIterator $index_iterator, $traversable) {
        parent::__construct($traversable);
        $this->index_iterator = $index_iterator;
        $this->position = null;
    }

    public function seek($index) {
        $this->index_iterator->seek($index);
        $this->update_position();
    }

    public function current() {
        return $this->getInnerIterator()->current();
    }

    public function key() {
        return $this->getInnerIterator()->key();
    }

    public function rewind() {
        $this->seek(0);
    }

    public function valid() {
        return $this->getInnerIterator()->valid() && $this->index_iterator->valid();
    }

    public function next() {
        $this->index_iterator->next();

        if ($this->index_iterator->valid()) {
            $this->update_position();
        }
    }

    private function update_position() {
        $desired = $this->index_iterator->current();
        $iterator = $this->getInnerIterator();

        if ($iterator instanceof \SeekableIterator) {
            try {
                $iterator->seek($desired);
            } catch (\OutOfBoundsException $e) {
                while ($iterator->valid()) {
                    $iterator->next();
                }
            }
        } else {
            if ($this->position === null || $desired < $this->position) {
                $iterator->rewind();
                $this->position = 0;
            }

            while ($iterator->valid() && $desired > $this->position) {
                $iterator->next();
                $this->position++;
            }
        }
    }
}

/**
 * Internal: Used to implement tt_combine().
 */
class CombiningIterator implements \Iterator {
    private $key_iterator;
    private $value_iterator;

    public function __construct(\Iterator $key_iterator, \Iterator $value_iterator) {
        $this->key_iterator = $key_iterator;
        $this->value_iterator = $value_iterator;
    }

    public function rewind() {
        $this->key_iterator->rewind();
        $this->value_iterator->rewind();
    }

    public function current() {
        return $this->value_iterator->current();
    }

    public function key() {
        return $this->key_iterator->current();
    }

    public function valid() {
        return $this->key_iterator->valid() && $this->value_iterator->valid();
    }

    public function next() {
        $this->key_iterator->next();
        $this->value_iterator->next();
    }
}

/**
 * Internal: Used to implement flip().
 */
class FlippingIterator extends \IteratorIterator {
    public function current() {
        return parent::key();
    }

    public function key() {
        return parent::current();
    }
}

/**
 * Internal: Used to implement cycle().
 */
class CyclingIterator implements \OuterIterator {
    private $iterator;
    private $count;

    public function __construct(\Iterator $iterator) {
        $this->iterator = $iterator;
    }

    public function rewind() {
        $this->iterator->rewind();
        $this->count = 0;
    }

    public function key() {
        return $this->count;
    }

    public function current() {
        return $this->iterator->current();
    }

    public function valid() {
        return $this->iterator->valid();
    }

    public function next() {
        $this->iterator->next();
        $this->count++;

        if (!$this->iterator->valid()) {
            $this->iterator->rewind();
        }
    }

    public function getInnerIterator() {
        return $this->iterator;
    }
}

/**
 * Internal: Used to implement tt_repeat().
 */
class RepeatValueGenerator extends Generator {
    private $value;

    public function __construct($value) {
        $this->value = $value;
    }

    protected function setup() {
        // Nothing to set up.
    }

    protected function advance() {
        return $this->value;
    }
}

/**
 * Internal: Used to implement tt_repeat().
 */
class BoundedRepeatValueGenerator extends RepeatValueGenerator implements \Countable {
    private $position;
    private $count;

    public function __construct($value, $count) {
        parent::__construct($value);
        $this->count = $count;
    }

    public function count() {
        return $this->count;
    }

    protected function setup() {
        parent::setup();

        $this->position = 0;
    }

    protected function advance() {
        if ($this->position < $this->count) {
            $this->position++;
            return parent::advance();
        } else {
            throw new GeneratorExhausted();
        }
    }
}

/**
 * Internal: Used to implement tt_zip() and tt_zip_longest().
 */
class ZipGenerator extends Generator {
    private $iterators;
    private $longest;
    private $fill;

    public function __construct(array $iterators, $longest, $fill=null) {
        $this->iterators = $iterators;
        $this->longest = $longest;
        $this->fill = $fill;
    }

    protected function setup() {
        foreach ($this->iterators as $iterator) {
            $iterator->rewind();
        }
    }

    protected function advance() {
        $invalid = 0;
        $values = [];

        foreach ($this->iterators as $iterator) {
            if ($iterator->valid()) {
                $values[] = $iterator->current();
                $iterator->next();
            } else {
                $invalid++;
                $values[] = $this->fill;
            }
        }

        $done = $this->longest ? $invalid == count($this->iterators) : $invalid > 0;
        if ($done) {
            throw new GeneratorExhausted();
        } else {
            return $values;
        }
    }
}

class TTSet extends Iterable implements \Countable {
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

    public function intersection(TTSet $other) {
        $intersection_set = new TTSet();
        foreach ($this as $item) {
            if ($other->contains($item)) {
                $intersection_set->add($item);
            }
        }
        return $intersection_set;
    }

    public function union(TTSet $other) {
        $union_set = new TTSet();
        $union_set->update($other);
        $union_set->update($this);
        return $union_set;
    }

    public function difference(TTSet $other) {
        $difference_set = new TTSet();
        foreach ($this as $item) {
            if (!$other->contains($item)) {
                $difference_set->add($item);
            }
        }
        return $difference_set;
    }

    public function equals(TTSet $other) {
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

    public function is_subset(TTSet $other) {
        foreach ($this as $item) {
            if (!$other->contains($item)) {
                return false;
            }
        }
        return true;
    }

    public function is_superset(TTSet $other) {
        return $other->is_subset($this);
    }

    public function contains($item) {
        $key = $this->get_hash_key($item);
        return array_key_exists($key, $this->hash) && $this->hash[$key] === $item;
    }

    public function getIterator() {
        return new SequentialKeyIterator(new \ArrayIterator($this->hash));
    }

    /**
     * Deprecated: Use the standard `to_array()` method from Iterable.
     */
    public function as_array() {
        return $this->to_array();
    }

    public function count() {
        return count($this->hash);
    }

    private function get_hash_key($item) {
        if (!is_scalar($item) && $item !== null) {
             throw new InvalidArgumentException('Only scalar values can be stored in a TTSet.');
        }

        return gettype($item) . ":$item";
    }
}
