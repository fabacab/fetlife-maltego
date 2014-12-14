# FetLife Maltego

FetLife Maltego is a package of PHP-based [Local Transforms](https://www.paterva.com/web6/documentation/developer-local.php) for the [Maltego OSINT and forensics data mining tool](https://en.wikipedia.org/wiki/Maltego) that act on FetLife.com.

## Prerequisites

This tool requires PHP version 5.3 or greater, with [PHP's CURL extension](https://php.net/manual/en/book.curl.php) installed. Additionally, this tool requires two libraries:

* [libFetLife](https://github.com/meitar/libFetLife)

We'll get these in the next step.

## Installing

This section documents how to install FetLife Maltego.

### Installing from source

    git clone git://github.com/meitar/fetlife-maltego.git # Clone the code.
    cd fetlife-maltego
    git submodule init                                    # Install the libs.
    git submodule update
    cp fl-mt-config.ini.php-sample fl-mt-config.ini.php   # Create the config file.
    vi fl-mt-config.ini.php                               # Edit the config file.

## Configuration

This section documents configuration options availalbe to FetLife iCalendar.

### File format

The configuration file uses [PHP's `ini` file](http://php.net/parse_ini_file) syntax.

FetLife Maltego looks for a configuration file named `fl-mt-config.ini.php` in the same directory as its main workhorse script, `FetLifeTransform.php`. The program ships with a sample configuration file called `fl-mt-config.ini.php-sample`. Rename or copy the sample file to the expected name, then enter your preferred settings.

## Running

To run FetLife Maltego's transformations in your Maltego client, you first need to add them to your list of available transforms. Follow Paterva's instructions for [Adding a new transform](https://www.paterva.com/web6/documentation/developer-local.php#6). When adding a new transform in the Local Transform Wizard, be mindful of the following settings:

* In the `Input entity type` field, choose the entity you'd like to subject to a transform. FetLife Maltego currently has transforms for the following input entity types:
    * Alias
* In the **Command line** step, enter the following details, adjusted for your environment:
    * **Command**: `/usr/bin/php`
    * **Parameters**: `fetlifetransform-ENTITY_TYPE.php`, where *ENTITY_TYPE* is the input entity type you selected in the previous step.
    * **Working directory**: `/path/to/fetlife-maltego`

## Troubleshooting

* Ensure your `lib/FetLife` directory and its `fl_sessions` is read and writable by your user.
