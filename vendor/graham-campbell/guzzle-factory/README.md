Guzzle Factory
==============

Guzzle Factory was created by, and is maintained by [Graham Campbell](https://github.com/GrahamCampbell), and provides a simple Guzzle factory, with good defaults. Feel free to check out the [change log](CHANGELOG.md), [releases](https://github.com/GrahamCampbell/Guzzle-Factory/releases), [security policy](https://github.com/GrahamCampbell/Guzzle-Factory/security/policy), [license](LICENSE), [code of conduct](.github/CODE_OF_CONDUCT.md), and [contribution guidelines](.github/CONTRIBUTING.md).

![Banner](https://user-images.githubusercontent.com/2829600/71477092-0f3c7780-27e0-11ea-95cb-22c5ff39ab29.png)

<p align="center">
<a href="https://github.com/GrahamCampbell/Guzzle-Factory/actions?query=workflow%3ATests"><img src="https://img.shields.io/github/actions/workflow/status/GrahamCampbell/Guzzle-Factory/tests.yml?label=Tests&style=flat-square" alt="Build Status"></img></a>
<a href="https://github.styleci.io/repos/88412277"><img src="https://github.styleci.io/repos/88412277/shield" alt="StyleCI Status"></img></a>
<a href="LICENSE"><img src="https://img.shields.io/badge/license-MIT-brightgreen?style=flat-square" alt="Software License"></img></a>
<a href="https://packagist.org/packages/graham-campbell/guzzle-factory"><img src="https://img.shields.io/packagist/dt/graham-campbell/guzzle-factory?style=flat-square" alt="Packagist Downloads"></img></a>
<a href="https://github.com/GrahamCampbell/Guzzle-Factory/releases"><img src="https://img.shields.io/github/release/GrahamCampbell/Guzzle-Factory?style=flat-square" alt="Latest Version"></img></a>
</p>


## Installation

This version requires [PHP](https://www.php.net/) 7.4-8.4.

To get the latest version, simply require the project using [Composer](https://getcomposer.org/):

```bash
$ composer require "graham-campbell/guzzle-factory:^7.0"
```


## Usage

```php
<?php

use GrahamCampbell\GuzzleFactory\GuzzleFactory;

$client = GuzzleFactory::make(['base_uri' => 'https://example.com']);
```


## Security

If you discover a security vulnerability within this package, please send an email to security@tidelift.com. All security vulnerabilities will be promptly addressed. You may view our full security policy [here](https://github.com/GrahamCampbell/Guzzle-Factory/security/policy).


## License

Guzzle Factory is licensed under [The MIT License (MIT)](LICENSE).


## For Enterprise

Available as part of the Tidelift Subscription

The maintainers of `graham-campbell/guzzle-factory` and thousands of other packages are working with Tidelift to deliver commercial support and maintenance for the open source dependencies you use to build your applications. Save time, reduce risk, and improve code health, while paying the maintainers of the exact dependencies you use. [Learn more.](https://tidelift.com/subscription/pkg/packagist-graham-campbell-guzzle-factory?utm_source=packagist-graham-campbell-guzzle-factory&utm_medium=referral&utm_campaign=enterprise&utm_term=repo)
