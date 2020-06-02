Leaflet Latitute Longitude
--------------------------

**Leaflet LatLon** module provides integration with [Leaflet Module](https://www.drupal.org/project/leaflet) for displaying maps  based on custom fields defined by drupal instead of geofield and provide basic functionality for showing position on map based marker provided.

This module provides a Leaflet LatLon views formatter and is partly based on leaflet views (submodule).

Installation and Use
--------------------

* Download the module using composer which will also download other dependency module.
  It is done simply running the following command from your project package root 
(where the main composer.json file is sited):  
__$ composer require 'drupal/leaflet_latlon'__  
(for dev: __$ composer require 'drupal/leaflet_latlon:1.x-dev'__)

* Enable the module to configure it.
* Make sure you have latitude and longitude fields defined.
* Create a new view and add the latitude and longitude fields.
* From format select Leaflet LatLon Map and in settings define the Latitude and Longitude fields

Authors/Credit
--------------
* [sarathkm](https://www.drupal.org/u/sarathkm)
