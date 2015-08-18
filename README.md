Facebook Albums App [![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/mangesh/f-album/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/mangesh/f-album/?branch=master) [![Build Status](https://scrutinizer-ci.com/g/mangesh/f-album/badges/build.png?b=master)](https://scrutinizer-ci.com/g/mangesh/f-album/build-status/master)
================================================================================
> Small application to manage your facebook albums

This is a small/sample application to show/view/manage your facebook albums
in easy and smart way.
Features of this application:
* View your album
* Take backup/export of your albums on your machine in just few clicks.
* Easily upload all your facebook albums to your picasa/google+ account.

####Additionally
* Built on Slim Framework
* RESTful routes
* Uses Facebook Graph API v2.4

## Demo

[**http://fbphotos.metroplex.in**](http://fbphotos.metroplex.in)

## Libraries And Framework Used
* [**Mini 2**](https://github.com/panique/mini2) - Uses Slim Framework
* [**Facebook PHP SDK-v4**](https://github.com/facebook/facebook-php-sdk-v4)
* [**Zippy**](https://github.com/alchemy-fr/Zippy) - To Create Archives 


## Installation

This app requires PHP 5.3 or greater.

Clone the repo: `git clone https://github.com/mangesh/f-album.git`.

then install it through composer:

```shell
php composer.phar install --no-dev
```
This application and its dependencies will be installed under `./lib/vendor`.

### Setup config.php ###

config.php file is stored at following location.

```bash
/public/inc/config.php
```
Added your neccessary `database`, `facebook app` and `google app` credentials in `config.php`
You can use sample copy of `config-sample.php` provided in the same folder.

### Setup database ###

Database/table structure file is given in the `database` folder

