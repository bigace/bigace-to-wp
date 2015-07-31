bigace-to-wp
============

This script will help you migrate from [Bigace](http://www.bigace.de/) to [Wordpress](http://wordpress.org/).
It is a plugin that you drop into Wordpress that will read your Bigace database and migrate over your pages, news and comments.

## Supported Bigace Version
This package brings two plugins, one for importing content from Bigace 2.7.8 and one from Bigace 3.
You have to import every community manually. A wordpress multi-site installation is recommended if you migrate a multi-community installation.

## License
This script is a fork of the [s9y-to-wp importer](https://raw.github.com/ShakataGaNai/s9y-to-wp/), adapted to Bigace Web CMS.
There seems to be no license attached, maybe Public Domain or [CC0](http://creativecommons.org/publicdomain/zero/1.0/).

## Instructions
1. Install Wordpress as normal
2. Optional in the Wordpress DB
 * ``` TRUNCATE `wp_posts`;```
 * ``` TRUNCATE `wp_postmeta`; ```
 * ``` TRUNCATE `wp_term_relationships`;  ```
 * ``` TRUNCATE `wp_term_taxonomy`; ```
 * ``` TRUNCATE `wp_comments`; ```
 * ``` TRUNCATE `wp_commentmeta`; ```
 * ``` DELETE FROM `wp_terms` WHERE wp_terms.term_id != 1; ```
3. Copy the two files bigace.class.php and bigace2.php (or bigace3.php in case you were using Bigace v3) to /wordpress/wp-content/plugins/
4. Login to Wordpress Admin Interface
5. Goto Plugins > Installed plugins and activate "Bigace to Wordpress importer"
6. Goto Tools > Import
7. Click "Bigace"
8. Fill out the information, follow the proccess
9. That's it - now you can enjoy your new wordpress site

## Ideas for the future
* add content (table) import (as meta data for a post?)
* add configuration import ?

## Importing images and files
Images and files will ONLY be copied to the uploads directory, you need to manually add rewrite rules to your webserver config or .htaccess file.
The rewrite rules will be generated for you during import, but not added automatically to your config.

## Known limitations/issues
* generic entries table
* draft items will be skipped
* history items will be skipped
* workflow items are skipped
* configurations are not imported
* importer does not respect item language (as wordpress does not know post languages)
* user permissions will be resetted (everyone will be become an editor, except for the Super-Admin)
* item permissions will be resetted (as wordpress does not know item permissions)
* item state (like hidden) will be ignored, every page that needs to be private has to be adjusted afterwards
* user password is resetted (can be adjusted in the script)
* user have deactivated rich editing by default (see below)
* after the import, you'll need to edit one of the categories (just open and save it) to fix the hierarchy

