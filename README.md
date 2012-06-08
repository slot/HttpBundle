SlotHttpClient - A simple socket based HTTP Client
==================================================

[![Build Status](https://secure.travis-ci.org/slot/HttpBundle.png?branch=master)](http://travis-ci.org/slot/HttpBundle  )

What does it?
------------- 

This Bundle is bringing you a simple, lightweight and versatile HTTP client for Symfony2. It uses sockets build into PHP, so you don't
need any eternal libraries like CURL etc.

Features include

   - SSL support
   - GET and POST requests
   - File downloads
   - Custom Headers
   - Custom Ports
   - GZip compression
   - Basic Authentication

Limitations
-----------

The client is pretty dumb. It supports standard HTTP Protocol, sends requests and return headers and body. It will not automatically interpret responses like json or xml but it will always make sure that Gzip-compressed responses are being inflated for you, so you can get the string and do what ever you want with it. 


Installation
-------------

Add Bundle to your deps file:



``[SlotHttpBundle]
    git=git://github.com/slot/HttpBundle.git
    target=/bundles/Slot/HttpBundle``

then run 

``bin/vendors install``

After successful installation add the bundle to autoload.php and AppKernel.php

**autoload.php**

``` php

$loader->registerNamespaces(array(
<?php
// app/autoload.php

$loader->registerNamespaces(array(
  // ...
  'Slot'          => __DIR__.'/../vendor/bundles',
  );
?>
```


``` php
<?php
// app/AppKernel.php

public function registerBundles()
{
    $bundles = array(
        // ...
        new FOS\UserBundle\FOSUserBundle(),
    );
}
```

Usage
-----

** GET Request **

``` php
<?php

   $client = get('http.client');

   $client->get('http://www.google.com');

   echo $client->getResponseBody();

?>
```