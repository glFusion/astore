# Amazon Store Plugin for glFusion
This plugin provides a storefront for curated Amazon products, similar to
the "Astore" product that Amazon is retiring.

You must set up an associate account at Amazon and create your credentials before using this plugin.
See [https://affiliate-program.amazon.com/](https://affiliate-program.amazon.com/)

## Features
  * Add products through the admin interface to appear on the store homepage.
  * Pass "asin=<ASIN>" to have any product displayed as a "featured" product.
  * Avoid Amazon request limits:
    * Items are retrieved from Amazon in bulk and cached.
    * A 1-second sleep() is induced if requests would be made too rapidly.
  * Items can be automatically added to the catalog if individually requested

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

### Store Title
Enter a title for your store. This will be shown as a header on each page of
your store. It does not show on the product detail page nor if this value is
empty.

### Add Featured Item to Catalog?
If this is &quot;Yes&quot; any items that are requested via the store URL
(`http://yoursite.com/astore/index.php?asin=XXXXXX`) will be added to the
product catalog. Set to &quot;No&quot; to ignore requested items.

### Max Featured/Block Description
Enter the maximum number of characters to be shown in the product descriptions
in the featured or regular product blocks.

