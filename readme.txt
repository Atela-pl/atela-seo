=== Atela SEO ===
Contributors: atelapl
Donate link: https://suppi.pl/atela
Tags: seo, sitemap, meta, redirects, breadcrumbs
Requires at least: 5.9
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.2.2
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

= 1.2.2 =
* Fix: saving a page in the Elementor editor could silently switch the "noindex" setting off, even when you had only edited something else on the page. noindex is now left untouched unless you change it yourself.
* Fix: an older copy of the SEO fields that Elementor keeps in the page settings could overwrite a newer value changed outside Elementor (for example noindex) the next time the page was updated. That copy is now cleared when the editor opens and after every save.
* Fix: turning noindex off, saving, and turning it back on within the same editing session was not saved, leaving the page indexable while the switch showed "on". It is now saved correctly.
* Updated Freemius SDK to the latest version.

= 1.2.1 =
* Fix: the plugin header's Description field was still in Polish, not English — it is the source string WordPress.org translation tools read, so it showed up as "needing translation" even in English. Now matches the readme.txt description.
* Fix: the "Upgrade" pricing tab was blank. It was built by Freemius from this plugin's own paid plans, but this product only has a Free plan — the paid Premium/Master plans live on the separate Atela SEO Premium add-on product. Disabled the direct pricing tab (`has_premium_version`, `has_paid_plans`) so upgrades go through the working "Add-Ons" tab instead.
* Fix: deactivating this plugin while Atela SEO Premium was still active crashed the site with a critical error — Premium's Freemius SDK assumes this plugin's parent module is already registered for the request, which never happens once this file stops loading. "Wyłącz" is now grayed out on the Plugins list (mirroring the equivalent guard already on Premium's "Włącz") and the deactivation request itself is blocked with a clear message, for both the single-plugin link and the bulk "Deactivate" action.
* Fix: a site that had previously been put into Freemius's anonymous ("Skip") mode by an earlier install attempt never showed the connect prompt again, even after a fresh reinstall — that state lives in the database, not in the plugin files. Added a notice with a "Połącz z Freemius" button on the plugin's own admin pages that explicitly resets it.
* Fix: the Elementor "Atela SEO Breadcrumbs" widget used Elementor's built-in `eicon-yoast` icon (a competitor's logo) as its widget icon. Replaced with a neutral navigation icon.

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
