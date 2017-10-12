# Amazon Store Plugin for glFusion
This plugin provides a storefront for curated Amazon products, similar to
the "Astore" product that Amazon is retiring on Octoberi 27, 2017.

You must set up an associate account at Amazon and create your credentials before using this plugin.
See [https://affiliate-program.amazon.com/](https://affiliate-program.amazon.com/)

## Features
  * Add products through the admin interface to appear on the store homepage.
  * Pass `asin=<ASIN>` to have any product displayed as a "featured" product.
    * Requested products can optionally be added to the catalog.
  * Avoids Amazon request limits:
    * Items are retrieved from Amazon in bulk and cached.
    * A 1-second sleep() is induced if requests would be made too rapidly.
    * Only requests up to 10 items at a time.

## Usage
Amazon requires that affiliate links be placed only on sites that the associate controls.
For example, posting affiliate links in forums, Facebook, etc. is against the terms of service.

This plugin allows you to post a link to an Amazon product page on your site, even if you have
not added the item to your catalog.
  * `https://example.com/astore/index.php?detail=XXXXX` leads to a product information page with
a link to Amazon.
  * `https://example.com/astore/index.php?asin=XXXXX` leads to your catalog page with product XXXX
featured at the top of the page.
  * `https//example.com/astore/index.php` leads to your catalog page.

## Configuration
### AWS Access Key, Secret Key, Associate ID
Enter your Amazon credentials here. Visit the Affiliate home page (above) to create your credentials

### AWS Country
Select the domain associated with your country.

### AWS Cache Minuts
Enter the number of minutes to cache product information from Amazon.
Amazon recommends caching but indicates that it should be minimal in order for
the information to be up to date.

### Debug AWS?
Select &quot;Yes&quot; to log debug messages related to AWS transactions.

### Items Per Page
Enter the number of products to show on each page in the store.
Amazon has a query limit of 10 items at a time, so this is a good value to
use for the page limit. If more than 10 items are on a page and all must
be requested from Amazon, only 10 items will be shown on the first page load.

### Store Title
Enter a title for your store. This will be shown as a header on each page of
your store. It does not show on the product detail page nor if this value is
empty.

### Auto-Add Items to Catalog?
If this is &quot;Yes&quot; any items that are requested via the store URL
(`http://yoursite.com/astore/index.php?asin=XXXXXX`) will be added to the
product catalog. Set to &quot;No&quot; to ignore requested items.

### Max Featured/Block Description Lengeh
Enter the maximum number of characters to be shown in the product descriptions
in the featured or regular product blocks. Changes to these values mqy require changes to the CSS or templates to maintain the desired layout.

### Sort Order
Select the sorting method for the item list. Options are:
  * By date added, ascending or descending
  * Random ordering
  * No sorting (Database natural sorting)
