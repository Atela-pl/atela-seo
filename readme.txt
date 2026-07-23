=== Atela SEO ===
Contributors: atelapl
Donate link: https://atela.pl
Tags: seo, sitemap, meta, redirects, breadcrumbs
Requires at least: 5.9
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Professional SEO plugin with Elementor support, live previews, XML sitemaps, redirects, Schema.org, and automatic social media image letterboxing.

== Description ==

Atela SEO is a custom SEO plugin designed for full control over WordPress website optimization. It offers a dedicated tab directly within the Elementor editor, advanced search results live previews (Google, Facebook, Twitter/X) with a Mobile/Desktop toggle, as well as a powerful redirects manager, XML sitemaps, and Schema.org structural data.

### Features:

* **On-page SEO:** SEO Title, Meta Description, Canonical URL, Robots meta, Focus Keyword, Title separator.
* **Category and Tag SEO:** Dedicated fields for archives and taxonomies.
* **Redirect Manager (301, 302):** Instant traffic management and redirection.
* **XML Sitemaps:** Automatic sitemap generation and search engine pinging.
* **Schema.org (JSON-LD):** Automated structured data (WebSite, Organization, Article, WebPage).
* **Breadcrumbs:** Complete breadcrumb navigation system.
* **Live Previews:** Google SERP, Facebook Open Graph, Twitter/X Card (Mobile/Desktop views).
* **Automatic Image Letterboxing:** Automatically generates white borders for non-fitting social media images to avoid cropping.
* **Elementor Integration:** Dedicated "Atela SEO" tab directly in the page builder panel.
* **Gutenberg / Classic Editor Integration:** Meta box with live-updating previews.

== Installation ==

1. Download the plugin ZIP file.
2. Log in to your WordPress admin panel and navigate to **Plugins > Add New**.
3. Click on the **Upload Plugin** button and choose the downloaded ZIP file.
4. Click **Install Now**, and once installed successfully, click **Activate Plugin**.
5. A new configuration menu item, **Atela SEO**, will appear in your WordPress main menu.

== External services ==

This plugin connects to external APIs to ping search engines about sitemap updates.
* **Google:** Pings `https://www.google.com/ping?sitemap=...` to notify Google of new or updated sitemaps. [Google Terms of Service](https://policies.google.com/terms) | [Google Privacy Policy](https://policies.google.com/privacy)
* **Bing:** Pings `https://www.bing.com/ping?sitemap=...` to notify Bing. [Microsoft Terms of Use](https://www.microsoft.com/en-us/legal/terms-of-use) | [Microsoft Privacy Statement](https://privacy.microsoft.com/en-us/privacystatement)

== Frequently Asked Questions ==

= Does the plugin require Elementor to work? =

No. Elementor is not required. The plugin fully supports the Classic Editor, the default block editor (Gutenberg), as well as the Elementor page builder.

== Changelog ==

= 1.0.0 =
* Initial release.
