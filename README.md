# Infection - Extensions Installer

Composer plugin for automatic registering of [Infection](https://github.com/infection/infection) extensions.

## How to install extension

Extension installer is bundled together with Infection core. All you need to register a custom extension is just to install a composer package.

Extension will be registered _automatically_.

Infection Extension Installer listens `post-install-cmd` and `post-update-cmd` events and as soon as it finds an Infection extension, it automatically registers it in Infection.

```bash
composer require --dev infection/codecetion-adapter

Using version 1.0.0 for infection/codeception-adapter
./composer.json has been updated
Loading composer repositories with package information
Updating dependencies (including require-dev)
Package operations: 1 installs, 0 updates, 0 removals
  - Installing infection/codeception-adapter (1.0.0): Downloading 100%
Writing lock file
Generating autoload files

infection/extension-installer: Extensions installed
> infection/codeception-adapter: installed
``` 

## How to write an extension for Infection

Infection extension is a composer-based plugin. Basically it is a composer package which conforms to the following requirements:

* its type field is set to `infection-extension`
* it has `extra.infection.class` subkey in its `composer.json` that references a class that will be invoked in the Infection runtime.

Example:

```json
{
    "name": "infection/codeception-adapter",
    "type": "infection-extension",
    "extra": {
        "infection": {
            "class": "Infection\\TestFramework\\Codeception\\CodeceptionAdapterFactory"
        }
    }
}
```

### Supported extensions

Currently, Infection supports only Test Framework extensions.

## Infection - Mutation Testing Framework

Please read documentation here: [infection.github.io](http://infection.github.io)

Twitter: [@infection_php](http://twitter.com/infection_php)
