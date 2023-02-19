# Theme

See the [Template Hierarchy](https://codex.wordpress.org/Template_Hierarchy) for details on the files in this folder.

`index.php` is the most generic template file. It is used to display a page when nothing more specific matches a query, e.g. it puts together the home page when no home.php file exists.

The important differences between the main templates are:

* `archive.php` - paginated, adds archive title/description
* `front-page.php` - home page of site, adds big logo image, mini tables sidebar
* `home.php` - paginated blog posts index, paginated
* `page.php` - adds breadcrumbs for hierarchical pages
* `search.php` - display search page, paginated
* `single.php` - template for all [single posts](https://developer.wordpress.org/themes/basics/template-hierarchy/#single-post), adds next/prev post links after the article
* `single-clubs.php` - display club, add updated date
* `single-history.php` - display a history page, add breadcrumbs and CSS if needed
