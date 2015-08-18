View/Manage Facebook Albums
================================================================================
> Small application to manage your facebook albums

This is a small/sample application to show/view/manage your facebook albums
in easy and smart way.
Features of this application:
* View your album
* Take backup/export of your albums on your machine in just few clicks.
* Easily upload all your facebook albums to your picasa/google+ account.

## Demo

[**http://fbphotos.metroplex.in**](http://fbphotos.metroplex.in)


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

