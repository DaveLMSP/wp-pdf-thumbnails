=== WP PDF Thumbnails ===
Contributors: dystrust
Tags: pdf thumbnail, pdf, thumbnail, image magick, imagick, plugin
Requires at least: 4.0
Tested up to: 4.5.3
Stable tag: 1.0.1
License: GPLv3

This plugin automatically creates a thumbnail from the first page of any uploaded PDF.

== Description ==
WP PDF Thumbnails hooks into and extends the WordPress Media Library.  Whenever a PDF is uploaded, a thumbnail is created from the first page of the PDF; the resulting file is named filename.pdf.jpg.  All built-in WordPress thumbnail functions should work as intended with these thumbnails.  They will also inherit the PDF's media categories and will show in place of the generic PDF icon in the Media Library and Media Browser.  In the case that the parent PDF is removed from the Media Library, the thumbnail will be deleted automatically.

== Installation ==
WP PDF Thumbnails requires ImageMagick with GhostScript support and the imagick PHP extension which is available through PECL.

1. Install ghostscript from your distribution's repository; it is installed by default on many distributions.

2. Install ImageMagick from your distribution's repository.

3. Install the [PECL imagick extension](https://pecl.php.net/package/imagick).

4. Copy the plugin folder to wp-content/plugins/.

== Frequently Asked Questions ==

= What will happen if ImageMagick is not available? =

If ImageMagick is not available, existing thumbnails will continue to work normally while creation of new thumbnails is disabled.  Thumbnail creation will resume when ImageMagick becomes available.

== Changelog ==

= 1.0.1 =
* Fixed incorrect guid on thumbnail attachments

= 1.0.0 =
* Initial public version of WP PDF Thumbnails uploaded to GitHub.  
* Fixed HTTP error on systems without ImageMagick.