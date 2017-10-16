# Amazon Store Plugin for glFusion
This plugin provides a storefront for curated Amazon products, similar to
the "Astore" product that Amazon is retiring on October 27, 2017.
Using the Product Advertising API you can display a curated set of products on your site.
Product IDs and search strings can be passed to the plugin to display other products that are not in your catalog.

Amazon requires that affiliate links be placed only on sites that the associate controls.
For example, posting affiliate links in forums, Facebook, etc. is against the terms of service.

You must set up an associate account at Amazon and create your credentials before using this plugin.
See [https://affiliate-program.amazon.com/](https://affiliate-program.amazon.com/)

## Features
  * Add products through the admin interface to appear on the store homepage.
  * Pass `asin=<ASIN>` to have any product displayed as a "featured" product.
    * Requested products can optionally be added to the catalog.
  * Supports searching Amazon and caches search results by query.
  * Avoids Amazon request limits:
    * Items are retrieved from Amazon in bulk and cached.
    * A 1-second sleep() is induced if requests would be made too rapidly.
    * Only requests up to 10 items at a time.
  * Items that are unavailable from Amazon or third-party sellers are not shown.


## Usage
This plugin allows you to post a link to an Amazon product page on your site,
even if you have not added the item to your catalog.

  * Link to Your Catalog Home Page
    * `https//example.com/astore/index.php` leads to your catalog page.

  * Link to a Product Detail Page
    * `https://example.com/astore/index.php?detail=XXXXX` shows a product
        information page with a link to the product page on Amazon.

  * Link to a Specific Product
    * `https://example.com/astore/index.php?asin=XXXXX` leads to your catalog page,
        with product XXXX featured at the top of the page.
        If so configured, this product is automatically added to the catalog.

  * Link to a Search Page
    * `https://example.com/astore/index.php?search=word1+word2+...`
        searches Amazon and displays a page of products.
        Search results are cached by query string but not added to the catalog.
        Only the first page of results is displayed, with a link to Amazon to view more results.

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
Search results are never added to the catalog.

### Max Featured/Block Description Lengeh
Enter the maximum number of characters to be shown in the product descriptions
in the featured or regular product blocks.
The Featured Item description can be fairly long as its space will expand on the page to accomodate it.
Increasing the Block description length may require changes to the CSS or templates to maintain the desired layout.

### Sort Order
Select the sorting method for the item list. Options are:
  * By date added, ascending or descending
  * Random ordering
  * No sorting (Database natural sorting)
