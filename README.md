Crankshaft
===============

A PHP version of Python's itertools.

Supported functions include `map`, `filter`, `each`, and `reduce`.

Basic usage:

```php
require_once('crankshaft.php');

$a = tt_iter([1, 2, 3])
    ->map(function($x) { return 2 * x; })
    ->to_array();

// $a is now [2, 4, 8]
```

Running Tests
-------------

This requires [simpletest](http://simpletest.org/) to be installed.
