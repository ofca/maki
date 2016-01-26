<!--- @breadcrumb: index.md;Guides;guides/installation.md -->

# Installation

### Installation steps

* Download **ma**ki script from http://darkcinnamon.com/maki/download
* Put it in web accessible directory where you have your `md` files
* Navigate to this directory in your browser

### htaccess

**ma**ki use url rewriting for so called "nice urls". If `.htaccess` not exists **ma**ki will create it.

### Problem with base url

If **ma**ki has problem with base url detection (if you place maki in subdirectory) you can provide base url manually.

For example if you place **ma**ki in the `domain.com/sub/directory` your base path is `sub/directory`. Create `maki.json` configuration file and put there something like this:

~~~
{
    "url.base": "sub/directory"
}
~~~

*[md]: Markdown filess

