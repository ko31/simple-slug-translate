# Simple Slug Translate

[![](https://img.shields.io/wordpress/plugin/v/simple-slug-translate.svg)](https://wordpress.org/plugins/simple-slug-translate/)
[![](https://ps.w.org/simple-slug-translate/assets/banner-1544x500.png)](https://wordpress.org/plugins/simple-slug-translate/)

Simple Slug Translate can translate the post, page, category and taxonomy slugs to English automatically.

## Description

Simple Slug Translate can translate the post, page, category and taxonomy slugs to English automatically.

It can make the permalink looks more better, and also may be good for SEO.

The translation engine is powered by [Watson Language Translator](https://www.ibm.com/watson/services/language-translator/). Thanks to that support the following languages:

* Arabic
* Czech
* Danish
* Dutch
* German
* Greek
* English
* Spanish
* Finnish
* French
* Hebrew
* Hindi
* Hungarian
* Italian
* Japanese
* Korean
* Norwegian Bokmal
* Polish
* Portuguese
* Russian
* Swedish
* Turkish
* Simplified Chinese
* Traditional Chinese

In order to use the service, you can apply for an [IBM Cloud Lite](https://www.ibm.com/cloud/lite-account) account and get your own API key of Watson Language Translator. For free plan, you can translate up to 1,000,000 characters per month.

## Installation

1. Upload the simple-slug-translate directory to the plugins directory.
1. Activate the plugin through the 'Plugins' menu in WordPress.
1. "Dashboard"->"Settings"->"Simple Slug Translate"
1. "API Settings": Input your own "API key".
1. "Translation Settings": Choose your "Source language".
1. "Permission Settings": Choose enable post types.
1. When you will update the post, then the post slug will be automatically translated into English. The page, category and taxonomy as well.

Learn more:

* [Documentation(English)](https://github.com/ko31/simple-slug-translate/wiki/Documentation)
* [Documentation(Japanese)](https://github.com/ko31/simple-slug-translate/wiki/%E3%83%89%E3%82%AD%E3%83%A5%E3%83%A1%E3%83%B3%E3%83%88)

## Changelog

### 2.6.1

* Fixed bug

### 2.6.0

* Fixed bug
* Add overwrite settings

### 2.5.0

* Add supported languages

### 2.4.0

* Add supported languages

### 2.3.1

* Fixed bug

### 2.3.0

* Support service endpoints by location

### 2.2.0

* Add filter hook

### 2.1.0

* Support Gutenberg 
* Add post type settings
* Fixed some bugs

### 2.0.0

* Migrate to Language Translator API v3

### 1.2.2

* Fixed bug

### 1.2.1

* Update text

### 1.2.0

* Fixed bugs
* Add filter hook

### 1.1.0

* Add API settings check

### 1.0.1

* Add scheduled event

### 1.0.0

* Initial Release

