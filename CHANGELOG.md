# Changelog - Astore plugin for glFusion

## Version 0.3.0
Released 2023-03-17
- Refactor using DBAL.
- Require glFusion 2.0.0+, remove caching via DB table.

## Version 0.2.2
Released 2021-07-09
- Add sponsored and noopener tags to outbound links.
- Improve item retrieval and caching.
- Add a config item to close the store to the public.
- Add configurable disclaimer text to show as a tooltip and page footers.
- Add a search autotag.

## Version 0.2.1
Release 2021-06-17
- Add additional autotags with automatic disclaimers.

## Version 0.2.0
Release 2020-04-30
- Tell search engines not to index pages
- Restrict access to search function by group
- Add categories
- Update to Product API v5
- Increase default cache time to 3 hours
- Adjust cache time up or down by 25% to help avoid exceeding request limits
- Remove dependence on LGLib
- Add service function to get item information
- Support multiple ID types, e.g. "ISBN"
- Add admin menu to manually clear cache
- Add centerblock option to replace home page
- Implement glFusion 1.8.0 caching
- Add autotag support
- Use JSON internally
- Add config options to hide associate tag in links based on HTTP header and admin status
- Add Prime logo to detail page if applicable

## Version 0.1.1
Release 2017-10-16
- Show lowest price on detail page, not list
- Add missing `images` and `more_results` templates.
- Do not truncate title in detail view
- Fix when More Offer link is shown
- Fix typo
- Add tooltip help to export info

## Version 0.1.0
Release 2017-10-16
