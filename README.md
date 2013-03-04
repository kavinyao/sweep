# Sweep

Sweep is sweet PHP template.

Sweep transforms template file in simplified jinja-like syntax to PHP, by PHP.

## Features

Sweep supports the following features:

### Variable Block

* `{{ foo }}` translates to `<?php echo $foo; ?>`
* `{{ foo.bar }}` translates to `<?php echo $foo['bar']; ?>`
* `{{ foo.1 }}` translates to `<?php echo $foo[1]; ?>`
* `{{ foo:baz }}` translates to `<?php echo $foo->baz; ?>`
* `{{ foo.bar:baz }}` translates to `<?php echo $foo['bar']->baz; ?>`
* ...and on

### Filters

* `{{ foo|filter }}` translates to `<?php echo php_func($foo); ?>`
* `{{ foo|filter1|filter2 }}` translates to `<?php echo php_func2(php_func1($foo)); ?>`

Builtin filters include:

* `upper`, uppercase whole string
* `lower`, lowercase whole string
* `capfirst`, capitalize the first character of string
* `title`, capitalize the first character of every word
* `striptags`, remove HTML or PHP tags in string
* `urlencode`, URL-encode string
* `linkify`, transform a URL string to `<a>` link
* `escape`, escape HTML special characters
* `random`, select one random element from array
* `length`, output length of string or array

NOTE: if you use `linkify`, `random` or `length`, please include `sweep.extra.php`.

### Control Block

Sweep currently supports `if` and `for` blocks:

```
{% if foo %}
... stuff if foo is evaluated as true
{% endif %}
```

```
{% for item in collection %}
... fiddle with item
{% endfor %}
```

And of course you can have an optional `else` block:

```
{% if foo %}
...
{% else %}
... stuff if foo is evaluated as false
{% endif %}
```

### Comments

You can use `{# ... #}` to include comments. The comments translates to PHP comments like `<php // ... ?>`.

## Usage

`php sweep.php <template_file> [output_file]`.

Full example please refer to `example` directory.

## To-Do

* direct rendering
* performance improvement

## Acknowledgement

This project is a rebound of [`phptemplate`](https://github.com/lutaf/phptemplate).
