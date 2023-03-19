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
  * Supports autotags. The syntax is generally `[astore:tag asin:ASIN caption text]`
    * `astore:text` - Display a text link for a single ASIN.
    * `astore:image` - Display an image link for a single ASIN.
    * `astore:iframe` - Display a product box for a single product in the page content.
    * `astore:search` - Display a text link to an amazon search page.
      Use `q:query_string` instead of an ASIN, and separate the query string words by plus (+) symbols.

## Usage
This plugin allows you to post a link to an Amazon product page on your site,
even if you have not added the item to your catalog.

  * Link to Your Catalog Home Page
    * `https//example.com/astore/index.php` leads to your catalog page.

  * Link to a Product Detail Page
    * `https://example.com/astore/detail.php?asin=XXXXX` shows a product
        information page with a link to the product page on Amazon.
        If you have URL Rewrite enabled in your configuration you can use
        friendly URLs like `detail.php/XXXXX`.

  * Link to a Specific Product
    * `https://example.com/astore/index.php?asin=XXXXX` leads to your catalog page,
        with product XXXX featured at the top of the page.
        If so configured, this product is automatically added to the catalog.

## Configuration
### Store Title
An optional string to be displayed at the top of the main store page.

### Store is Open?
Select "No" to easily close the store without disabling the plugin.
Prevents displaying the main store page, any product detail pages, and the
centerblock. Autotags are not affected, though the link to detail pages will
return 404.

### Use Product API?
This plugin is designed to user the Amazon Product API v5 to retreive product
information. If you prefer, you can manually enter the URLs to products from
Amazon for each item. In this case you do not need to obtain access and secret
keys, but some of the plugin's functionality will be limited.

If you select "No" here, you will need to add the Amazon "Text+Image" link to each product.

### AWS Access Key, Secret Key, Associate ID
Enter your Amazon credentials here. Visit the Affiliate home page (above) to create your credentialsa.

Access and Secret keys are only required if the Product API is used. The
Associate ID is always required.

### AWS Region
Select the country with which your associates tag is related.
This will determine which host and region are contacted by the API.

### AWS Cache Minutes
Enter the number of minutes to cache product information from Amazon.
Amazon recommends caching but indicates that it should be minimal in order for
the information to be up to date.

Only used if the Product API is enabled.

Enter zero to disable caching completely; otherwise the value must be between 10 and 240 minutes.

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
  * Random ordering (Will give odd results if more than one page of results.)
  * No sorting (Database natural sorting)

### Block Associate ID for Header
To avoid artificaly inflating the click count on Amazon's Associate Central you
can specify a header string to exclude from seeing the associate tag info in
product URLs. The URL will still link to the product page at Amazon but the
associate ID will not be included.

### Block Associate ID if Admin
Also, you can exclude logged-in administrators from having the associate ID
appear in product links if this is set to "Yes".

This only works if the Product API is used.

### Enable Centerblock
Enable the centerblock to have the Amazon store become your site's homepage.

### Group that can search
Since the search form submits request to Amazon, you may wish to restrict
access to that function to avoid exceeding the terms of service.

### Disclaimer
Enter a short disclaimer string to show as a tooltip for links to Amazon.
Amazon requires site visitors to be informed that they may be clicking on an affiliate link.
The default is `Paid Affiliate Link`.

### Full Disclaimer
Similar to Disclaimer, this is more descriptive text that is shown at the bottom
of catalog and product pages if not empty.
