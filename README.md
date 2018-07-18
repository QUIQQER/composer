![Readme.jpg](bin/images/Readme.jpg)

Other languages
---------------
* [Deutsch/German](README_de.md)

QUIQQER - Composer
==================

Composer API for QUIQQER

Packagename:
```
    quiqqer/composer
```

Features
--------
* Access composer with PHP
* Allows usage of QUIQQERs Lockserver
* Optimize Composer for usage with QUIQQER

Installation
------------

```bash
composer require quiqqer/composer 
```

Usage
-----

`QUI\Composer\Composer`  serves as central accessor for 
a customized Composer instance, which is optimized for usage with QUIQQER.

```php
$Composer = new Composer('/path/to/your/composerjson/directory/');
$Composer->update();
```

Contribute
----------

- Issue Tracker: https://dev.quiqqer.com/quiqqer/composer/issues
- Source Code: https://dev.quiqqer.com/quiqqer/composer


Support
-------

Feel free to send us an email, if you encountered an error,want to provide feedback or suggest an idea.
Our E-Mail is: support@pcsg.de


