Equiviq for Wordpress
=====================

Requirements: WordPress 6.4+, PHP 8.1+, and the PHP OpenSSL extension.

INSTALL
1. In WordPress, open Plugins > Add New > Upload Plugin. Select the
   equiviq-wordpress ZIP, install it, and click Activate.
2. Open the new Equiviq item in the WordPress admin sidebar, or use
   Plugins > Equiviq for Wordpress > Settings & setup.

UPDATES
Version 0.2.8 and later checks the public Equiviq WordPress plugin releases
on every WordPress update check. Versions 0.2.2 through 0.2.7 cached release
results for six hours; if an update does not appear, upload the latest ZIP once
with Plugins > Add New > Upload Plugin and choose Replace current with uploaded.
Future versions appear under Plugins and Updates. Site administrators may enable
WordPress's normal auto-update control for this plugin. Only the installable
equiviq-wordpress-<version>.zip release asset is used.

CONNECT TO EQUIVIQ
1. In Equiviq, open Settings > Plugins > Wordpress CMS Integration > Settings.
   The Equiviq tenant plugin must be installed and enabled. Copy the full
   API endpoint URL displayed in its API endpoint card.
2. In its API keys card, create a key with a label identifying this WordPress
   site. Copy the key immediately; Equiviq displays it only once.
3. In WordPress > Equiviq > Connection settings, paste the endpoint URL and
   API key. Save settings, then click Test connection. A successful test
   reports that public guides are available.

PUBLISH GUIDES
1. Add an Equiviq Guides block to a WordPress page and publish the page.
   Classic editor users may insert [equiviq_guides] instead.
2. The block sidebar contains include/exclude filters for folders,
   categories, tags, and companies, callout styling, and article detail
   visibility settings.
3. If several pages use the block, return to WordPress > Equiviq and select
   the primary guide page. Article links use ?eqw_guide= on that page, so
   they work with both plain and custom WordPress permalink structures.
4. For a reusable shortcode with its own filters, create a Knowledge display
   entry under Equiviq > Knowledge. Paste the generated shortcode on a page.

LEAD GENERATION
1. Keep the Equiviq connection configured with the same Knowledge API key.
   Set a WordPress privacy policy page or a CTA-specific privacy URL.
2. Under Equiviq > Lead Generation, add a CTA. Choose the displayed fields,
   required fields, label overrides, Acquisition Source/Campaign, and visitor messages.
   Email is always required; Organization is required when displayed.
   The form places given name and surname on one row, and country code beside
   phone. Field labels appear as input placeholders.
3. Paste its [equiviq_lead id="..."] shortcode into a WordPress page.
   The site hosts the form. The plugin checks the tenant work-email exclusion
   list, then sends the lead to Equiviq server-to-server with the saved key.
4. Failed deliveries are stored in a local queue with the original submission
   ID. WordPress retries soon after submission and hourly. The Lead Generation
   admin page shows pending and review-needed submissions and has Retry now.
   WP-Cron requires site traffic or a server cron trigger to run on schedule.
5. Newsletter, ticket, and task actions are reserved for a later release.

DEVELOPMENT
The distributed assets/leads.js bundles phone-number metadata for all
supported countries. From the Equiviq workspace, run
node wordpress-package/build-leads.mjs after changing src/leads.js, then
rebuild the ZIP. The site does not load a third-party script at runtime.

TROUBLESHOOTING
- No Equiviq menu: confirm the plugin is activated and your WordPress account
  has the manage_options capability (normally an Administrator).
- Connection fails: confirm the Equiviq tenant plugin is enabled; copy the
  whole HTTPS endpoint, without a query string; create a new key if the old
  key was lost or revoked; then save and test again.
- The key field remains blank after saving by design. Its placeholder says
  whether a key is saved. Leave it blank to retain that key.
- If EQW_API_URL or EQW_API_KEY constants are set in wp-config.php, they
  override the corresponding values saved in the WordPress admin.
- Only active, published guides with Public Access are served. Internal Only
  and Authenticated Access articles are never returned by the public API.

The API key stays on the WordPress server. Article data is fetched on demand
and cached briefly; no Equiviq article database is synchronized to WordPress.
