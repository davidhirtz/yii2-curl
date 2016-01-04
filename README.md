yii2-curl extension
===================
cURL extension for Yii2 based on Nils Gajsek's [curl extension](https://github.com/linslin/Yii2-Curl), including RESTful support:

 - POST
 - GET
 - HEAD
 - PUT
 - DELETE

Requirements
------------
- Yii2
- PHP 5.4+
- Curl and php-curl installed


Installation
------------

The preferred way to install this extension is through [composer](http://getcomposer.org/download/).

```bash
php composer.phar require --prefer-dist davidhirtz/yii2-curl "*"
```


Usage
-----

Once the extension is installed, simply use it in your code. The following example shows you how to handling a simple GET Request. 

```php
$curl=new Curl;
$response = $curl->get('http://example.com/');
```