# Donkey

[![Latest Version on Packagist][ico-version]][link-packagist]
[![Total Downloads][ico-downloads]][link-downloads]
[![Build Status][ico-travis]][link-travis]
[![StyleCI][ico-styleci]][link-styleci]

Donkey is a Laravel package to modify your Laravel `config()` in code/any environment without giving access to the `.env` file.

Any key of the config can be modified like this
```PHP
Donkey::set('app.debug', true); // config('app.debug') will return true until changed again
```
## What problems does this solve?
1. If you had to change a value in `config()` that is not exposed in `.env`, how do you do it? Does it have to go through the entire CI/CD pipeline before reaching production?

2. If you had to enable Laravel's debug mode temporarily in production, how would you do it? Modify `.env`?
Who will be editing it? Will they accidentally edit something else? And does that person have SSH access? Even if you trust them, do you really want the other API keys to show up on their screens?

3. Perhaps our biggest pain point was coming up with the UI to expose configurable parts of ours projects to the users, there just wasn't an elegant way to do it. Our companion Filament package lets you create a configuration page blazingly fast and in your own namespace
   ```PHP
   Donkey::set('project.advanced_mode', true); // calling config('project.advanced_mode') anywhere will return true

   // You can even give users their own configuration space
   Donkey::set(auth()->user()->id.'-user.advanced_mode', true);
   ```

## How does it work?

1. `Donkey::set('app.debug', true)` stores the key-value pair into the database
2. The package then injects pairs from the database into the project automatically
3. You can blacklist keys using regular expression, e.g. `app.*`, `database.*` etc
4. Next, a whilelist will allow specific keys like `app.debug`, this way, you prevent really sensitive keys from being overwritten, e.g. `app.key`
5. This approach plays nicely with the Laravel `config:cache` and requires no changes in your project

## Installation

Via Composer

``` bash
$ composer require avexsoft/donkey
```

## Usage

## Change log

Please see the [changelog](changelog.md) for more information on what has changed recently.

## Testing

``` bash
$ composer test
```

## Contributing

Please see [contributing.md](contributing.md) for details and a todolist.

## Security

If you discover any security related issues, please email author@email.com instead of using the issue tracker.

## Credits

- [Author Name][link-author]
- [All Contributors][link-contributors]

## License

MIT. Please see the [license file](license.md) for more information.

[ico-version]: https://img.shields.io/packagist/v/avexsoft/donkey.svg?style=flat-square
[ico-downloads]: https://img.shields.io/packagist/dt/avexsoft/donkey.svg?style=flat-square
[ico-travis]: https://img.shields.io/travis/avexsoft/donkey/master.svg?style=flat-square
[ico-styleci]: https://styleci.io/repos/12345678/shield

[link-packagist]: https://packagist.org/packages/avexsoft/donkey
[link-downloads]: https://packagist.org/packages/avexsoft/donkey
[link-travis]: https://travis-ci.org/avexsoft/donkey
[link-styleci]: https://styleci.io/repos/12345678
[link-author]: https://github.com/avexsoft
[link-contributors]: ../../contributors
