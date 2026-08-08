=== Atela SEO ===
Contributors: atelapl
Donate link: https://suppi.pl/atela
Tags: seo, sitemap, meta, redirects, breadcrumbs
Requires at least: 5.9
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.2.0
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
* **SEO Dashboard & Google Search Console:** Site-wide SEO score overview plus click/query/CTR stats per post, pulled from your Google Site Kit connection.
* **Core Web Vitals Analyzer:** On-demand mobile/desktop performance audit (LCP, CLS, FCP) via the free Google PageSpeed Insights API.

== Installation ==

1. Log in to your WordPress admin panel and navigate to **Plugins > Add New**.
2. Search for **Atela SEO**.
3. Click **Install Now**, and once installed successfully, click **Activate**.
4. A new configuration menu item, **Atela SEO**, will appear in your WordPress main menu.

== External services ==

This plugin connects to external APIs to ping search engines about sitemap updates.
* **Google:** Pings `https://www.google.com/ping?sitemap=...` to notify Google of new or updated sitemaps. [Google Terms of Service](https://policies.google.com/terms) | [Google Privacy Policy](https://policies.google.com/privacy)
* **Bing:** Pings `https://www.bing.com/ping?sitemap=...` to notify Bing. [Microsoft Terms of Use](https://www.microsoft.com/en-us/legal/terms-of-use) | [Microsoft Privacy Statement](https://privacy.microsoft.com/en-us/privacystatement)
* **Google Search Console data:** The SEO Dashboard reads Search Console statistics through your own active Google Site Kit connection; no credentials are handled by this plugin directly.
* **Google PageSpeed Insights:** The Core Web Vitals Analyzer sends your homepage URL to the public `https://www.googleapis.com/pagespeedonline/v5/runPagespeed` endpoint, using an API key you provide yourself. [Google Terms of Service](https://policies.google.com/terms) | [Google Privacy Policy](https://policies.google.com/privacy)

== Frequently Asked Questions ==

= Does the plugin require Elementor to work? =

No. Elementor is not required. The plugin fully supports the Classic Editor, the default block editor (Gutenberg), as well as the Elementor page builder.

== Changelog ==

= 1.2.0 =
* Feature: Integrated Freemius licensing, so the free plugin can now register add-ons (like Atela SEO Premium) and manage upgrades/trials directly from within WordPress.

= 1.1.3 =
* Fix: the Google Search Console dashboard (both the global report and the per-post meta box) got permanently stuck on "Fetching detailed statistics..." inside the Elementor editor. Root cause: the script's entire logic ran once on `DOMContentLoaded`, checking for GSC containers that don't exist in the DOM yet at that point in Elementor - they're only injected later, when the user expands that specific accordion section. The script now watches for the container to appear (via `MutationObserver`) on both code paths (already-loaded and DOMContentLoaded) instead of relying solely on the one-time page-load event. Confirmed live: the dashboard now renders real Search Console data automatically once the section is expanded.

= 1.1.2 =
* I18n: Added missing Polish translations for the SEO Dashboard and Core Web Vitals modules (36 strings) — Google Site Kit connection notices, PageSpeed Insights API key panel, mobile/desktop audit labels, and performance verdict messages.

= 1.1.1 =
* Fix: the Core Web Vitals and GSC error messages ("Error: ...", "An error occurred while fetching data.") were never actually translatable — no generated JS translation catalog exists for this plugin, so `wp.i18n` silently returned the English source regardless of site language (the GSC one also used the wrong text domain, which wouldn't have matched even with a catalog). Both now pass through pre-translated from PHP instead.

= 1.1.0 =
* Feature: Added SEO Dashboard with Google Search Console stats (via Google Site Kit).
* Feature: Added Core Web Vitals Analyzer (Google PageSpeed Insights).

= 1.0.1 =
* I18n: Complete localization support (English base with Polish translation included).
* Feature: Added beautiful XSL styling to XML Sitemap.
* Fix: Resolved WPCS issues and improved output escaping.

= 1.0.0 =
* Initial release.
