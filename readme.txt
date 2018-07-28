=== Simple Slug Translate ===
Contributors: ko31
Donate link: https://go-sign.info
Tags: slugs, permalink, translate, translation
Requires at least: 4.3
Tested up to: 4.9.7
Stable tag: 2.0.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Simple Slug Translate can translate the post, page, category and taxonomy slugs to English automatically.

== Description ==

Simple Slug Translate can translate the post, page, category and taxonomy slugs to English automatically.

It can make the permalink looks more better, and also may be good for SEO.

The translation engine is powered by [Watson Language Translator](https://www.ibm.com/watson/services/language-translator/). It supports 9 languages.(English, Arabic, French, German, Italian, Japanese, Portuguese, Korean, and Spanish)

In order to use the service, you can apply for an [IBM Cloud Lite](https://www.ibm.com/cloud/lite-account) account and get your own API key of Watson Language Translator. For free plan, you can translate up to 1,000,000 characters per month.

== Related Links ==

* [Github](https://github.com/ko31/simple-slug-translate)
* [Documentation(English)](https://github.com/ko31/simple-slug-translate/wiki/Documentation)
* [Documentation(Japanese)](https://github.com/ko31/simple-slug-translate/wiki/%E3%83%89%E3%82%AD%E3%83%A5%E3%83%A1%E3%83%B3%E3%83%88)

== Installation ==

1. Upload the simple-slug-translate directory to the plugins directory.
1. Activate the plugin through the 'Plugins' menu in WordPress.
1. "Dashboard"->"Settings"->"Simple Slug Translate"
1. "API Settings": Input your own "API key".
1. "Translation Settings": Choose your "Source language".
1. When you will update the post, then the post slug will be automatically translated into English. The page, category and taxonomy as well.

Learn more:

* [Documentation(English)](https://github.com/ko31/simple-slug-translate/wiki/Documentation)
* [Documentation(Japanese)](https://github.com/ko31/simple-slug-translate/wiki/%E3%83%89%E3%82%AD%E3%83%A5%E3%83%A1%E3%83%B3%E3%83%88)

== Frequently asked questions ==

= What do I need to use this plugin? =

You need to apply for an [IBM Cloud Lite](https://www.ibm.com/cloud/lite-account) account and get your own API key of Watson Language Translator.

This plugin uses the API key to query the translation engine. The API key are not used except for query!

== Changelog ==

= 2.0.0 =
* Migrate to Language Translator API v3

= 1.2.2 =
* Fixed bug

= 1.2.1 =
* Update text

= 1.2.0 =
* Fixed bugs
* Add filter hook

= 1.1.0 =
* Add API settings check

= 1.0.1 =
* Add scheduled event

= 1.0.0 =
* Initial Release
