# phergie/phergie-irc-plugin-react-usermode

[Phergie](http://github.com/phergie/phergie-irc-bot-react/) plugin for monitoring and providing access to user mode information.

[![Build Status](https://secure.travis-ci.org/phergie/phergie-irc-plugin-react-usermode.png?branch=master)](http://travis-ci.org/phergie/phergie-irc-plugin-react-usermode)

## Install

The recommended method of installation is [through composer](http://getcomposer.org).

```JSON
{
    "require": {
        "phergie/phergie-irc-plugin-react-usermode": "dev-master"
    }
}
```

See Phergie documentation for more information on
[installing and enabling plugins](https://github.com/phergie/phergie-irc-bot-react/wiki/Usage#plugins).

## Configuration

```php
new \Phergie\Irc\Plugin\React\UserMode\Plugin(array(

    // All configuration is optional

    'prefixes' => array(
        '@' => 'o',
        '+' => 'v',
    ),

))
```

When the bot joins a channel, it receives a `343 RPL_NAMREPLY` server event
containing user nicks prefixed with characters indicative of their respective
channel-specific user modes.

This plugin's only configuration setting allows
[this mapping](https://github.com/phergie/phergie-irc-plugin-react-usermode/blob/6ff691a2559c02b1b37ef555fc780b131898fe8a/src/Plugin.php#L40-46)
of prefix to user mode characters to be overridden in cases where a network
uses non-standard mappings. The plugin's default mapping includes several
standard prefixes, which are shown in the example above, and several commonly
used non-standard prefixes.

## Usage



## Tests

To run the unit test suite:

```
curl -s https://getcomposer.org/installer | php
php composer.phar install
cd tests
../vendor/bin/phpunit
```

## License

Released under the BSD License. See `LICENSE`.
