=== QR codes by OpenQR ===
Contributors: openqr
Tags: qr code, qr codes, qr generator, dynamic qr, print
Requires at least: 6.4
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Create, manage and print QR codes for your pages, posts and products. Dynamic codes stay editable after printing, with scan analytics.

== Description ==

**Requires a free OpenQR account and connects to openqr.uk over HTTPS.** Create a key at https://openqr.uk/api and paste it into Settings; the key stays on your server and is used only to talk to OpenQR.

**Made for print.** Choose a page, post or product and click "Create QR": you get a code you can download and print, name so you recognise it later, and re-point at a new address whenever you like — without reprinting. Scans are counted in your dashboard.

* **Create QR from any page, post or product** — one click on the list row or the editor sidebar.
* **Editable after printing** — dynamic codes carry a short link; change the destination any time and every printed copy follows.
* **Fixed-content codes too** — Wi-Fi cards, contact cards, links, SMS, WhatsApp and more (nine types).
* **Durable images** — your QR images are generated once and stored in your uploads folder. Pages keep rendering even if openqr.uk is unreachable, and nothing runs on the public hot path.
* **Download for print** — PNG at up to 4096px and print-ready SVG. Four-module quiet zone by default, with contrast and density warnings before you print.
* **Block + shortcode** — `openqr/qr` Gutenberg block (with fallback markup that survives deactivation) and an `[openqr]` shortcode.
* **Scan activity** — total and recent scans per code; devices, referrers and places on Pro.
* **Sensible permissions** — editors can manage this site's codes only when you allow it; connecting the account and deleting codes stays with administrators.
* **Staging-safe** — a cloned site cannot repoint your live codes until you explicitly allow it.

Your QR codes belong to your OpenQR account, so they keep working in the dashboard, the REST API and everywhere else OpenQR works.

== Installation ==

1. Install and activate the plugin.
2. Create a free OpenQR account and an API key at https://openqr.uk/api (name it after your website).
3. Paste the key in Settings > OpenQR.
4. Click "Create QR" on any page, post or product — or use the OpenQR menu.

== Frequently Asked Questions ==

= Where is my API key stored? =

In your site's database, server-side only. It is never sent to the browser and never shown again after saving (only its last four characters). It is used solely to talk to openqr.uk over HTTPS.

= What happens if openqr.uk is unreachable? =

Your existing QR codes and images keep working: images are stored in your uploads folder and served directly by your web server. Only creating and editing codes needs the connection.

= Do printed codes keep working if I disconnect or uninstall? =

Disconnecting never affects your codes: they are yours, held in your OpenQR account, and the images live in your uploads folder. Uninstalling removes the plugin's data only — and published images keep working unless you tick "delete everything" before uninstalling.

= What are the free-plan limits? =

The free plan includes 1 active dynamic code and unlimited fixed-content codes, with scan analytics included. Your current limits and usage are shown on the dashboard; they come straight from your account, so they are always accurate.

= Can I rename a code's short link? =

Not from WordPress, deliberately: renaming a short link retires it, which would silently break every printed copy. Repointing the destination is different and always safe — that is the feature.

= Does this track my visitors? =

No. Scans are counted by OpenQR when a printed code is scanned, as with any OpenQR dynamic code. The plugin sends nothing to anyone except your own API calls to openqr.uk after you connect.

== Screenshots ==

1. Create a QR code for any page from the page list.
2. Your QR codes: status, short links and downloads.
3. Create screen: editable codes and fixed-content types.
4. Scan activity per code.

== Changelog ==

= 1.0.0 =
* First release: connect with an API key, create editable and fixed-content QR codes, page/post/product row actions, durable image assets, Gutenberg block and shortcode, scan activity, staging lock and per-role permissions.
