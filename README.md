# PharCompiler

Compiles a CLI PHP project into a self-executing Phar archive.

[![CC0](http://i.creativecommons.org/p/zero/1.0/88x31.png)](http://creativecommons.org/publicdomain/zero/1.0/)

## Usage

Assuming a project structure as follows...

```
$ pwd
/home/steven.hilder/foo
$ tree
├── bin
│   └── foo
├── src
│   ├── MyClass1.php
│   └── MyClass2.php
└── vendor
    ├── autoload.php
    └── composer
        └── ...
```

...where `bin/foo` is the executable entry point to your CLI application; you can compile the project by passing
the executable, target build directory and array of include directories to `SevenPercent\PharCompiler::compile()`:

```php
<?php declare(strict_types = 1);

use SevenPercent\PharCompiler;
require_once 'vendor/autoload.php';

PharCompiler::compile('bin/foo', 'build/', [
    'src/',
    'vendor/',
]);
```
