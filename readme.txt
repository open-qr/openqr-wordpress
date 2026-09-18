=== QR Code Generator: Dynamic QR Codes for Print ===
Contributors: openqr
Tags: qr code, qr code generator, dynamic qr, wifi qr code, print
Requires at least: 6.4
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.2.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Create and print QR codes for your pages, posts and products. Dynamic codes stay editable after printing: change the destination any time. Scans counted.

== Description ==

OpenQR is a QR code generator built for WordPress sites that use codes in the real world: on packaging, flyers, menus, window decals and business cards. Create a code for any page, post or product, download it for print, and change where it points whenever you like. Every printed copy follows: nothing needs reprinting.

**Requires a free OpenQR account and connects to openqr.uk over HTTPS.** Create a key at https://openqr.uk/api and paste it into Settings; the key stays on your server and is used only to talk to OpenQR.

* **Dynamic QR codes that stay editable after printing.** A printed code carries a short link you can re-point at any time: retarget a campaign, fix a typo, swap a landing page. The code on the paper never changes.
* **Your codes do not expire.** Disconnect or uninstall and your printed codes keep redirecting. A dynamic code does not quietly die the month you stop paying for a subscription tier.
* **Fixed-content codes too.** Nine types baked into the image: Wi-Fi cards, contact cards (vCard), WhatsApp, SMS, email, phone, URL, text and map location.
* **Built for print.** Download PNG up to 4096px or print-ready SVG, with the four-module quiet zone printers expect, plus contrast and density warnings before you commit to paper.
* **Create from anywhere.** One click on any page, post or product row (WooCommerce included), a Gutenberg block with fallback markup, an `[openqr]` shortcode, a create screen with helpful placeholders, and native **Elementor** (Free compatible) and **Avada / Fusion Builder** embeds: "continue on your phone" QR blocks with heading, instruction, button and download options, including a this-page source that resolves each product or listing in a reusable template.
* **Durable images.** QR images are generated once and stored in your uploads folder: page loads never wait on an external service, and a rendered page keeps working even if openqr.uk is unreachable.
* **Scan analytics.** Total and recent scans per code, counted when a printed code is scanned. Devices, referrers, regions and hour-by-hour detail live in your OpenQR dashboard.
* **Per-role permissions.** Choose exactly which roles can create and edit this site's codes; connecting the account and deleting codes stay with administrators.
* **Staging-safe.** A cloned site cannot repoint your live codes until you explicitly allow it.

Your codes belong to your OpenQR account, so they also keep working in the OpenQR dashboard, the REST API and everywhere else OpenQR works.

== Installation ==

1. Install and activate the plugin.
2. Create a free OpenQR account and an API key at https://openqr.uk/api (name it after your website).
3. Paste the key in Settings > OpenQR.
4. Click "Create QR" on any page, post or product, or use the OpenQR menu.

== Frequently Asked Questions ==

= Do the QR codes expire? =

No. Printed codes keep redirecting for as long as your OpenQR account exists, including on the free plan. Disconnecting the plugin or your account does not affect them: the images live in your uploads folder and the redirect lives in your account.

= Can I use it with WooCommerce products? =

Yes. Product rows get the same "Create QR" action as pages and posts, so you can put a scannable code on packaging, shelf labels or receipts and repoint it whenever the product page changes.

= Where is my API key stored? =

In your site's database, server-side only. It is never sent to the browser and never shown again after saving (only its last four characters). It is used solely to talk to openqr.uk over HTTPS.

= What happens if openqr.uk is unreachable? =

Your existing QR codes and images keep working: images are stored in your uploads folder and served directly by your web server. Only creating and editing codes needs the connection.

= Do printed codes keep working if I disconnect or uninstall? =

Disconnecting never affects your codes: they are yours, held in your OpenQR account, and the images live in your uploads folder. Uninstalling removes the plugin's data only, and published images keep working unless you tick "delete everything" before uninstalling.

= What are the free-plan limits? =

The free plan includes 1 active dynamic code and unlimited fixed-content codes, with scan analytics included. Your current limits and usage are shown on the dashboard; they come straight from your account, so they are always accurate.

= Can I rename a code's short link? =

Not from WordPress, deliberately: renaming a short link retires it, which would silently break every printed copy. Repointing the destination is different and always safe. That is the feature.

= Does this track my visitors? =

No. Scans are counted by OpenQR when a printed code is scanned, as with any OpenQR dynamic code. The plugin sends nothing to anyone except your own API calls to openqr.uk after you connect.

== Screenshots ==

1. Dashboard: usage, recent codes and what is shipping next.
2. Your QR codes: status, short links, inline editing and downloads.
3. Create screen: editable codes, plus nine fixed-content types with helpful placeholders.
4. Scan activity per code.

== Help shape OpenQR ==

The OpenQR dashboard shows what is shipping next, and the roadmap follows what users ask for: the "Request a feature" button opens a short form on openqr.uk. Sending a request sends your message to the OpenQR team, along with your plugin and WordPress versions so it is actionable; nothing is sent until you press Send on that form.

== Changelog ==

= 1.2.0 =
* New: native Elementor widget (Free compatible) and Avada / Fusion Builder element, sharing one embed renderer: three content sources (an existing OpenQR code, the current page or product with per-item resolution for reusable templates, or custom URL / fixed content), three presentations (QR only, QR with instruction, QR with button), QR colours, optional download link, and an editor-only "encodes" inspection so the wrong code is caught before publishing.
* Rendering never creates cloud codes or spends allowances; static embeds are rendered once and stored in your uploads folder like every other durable asset. Button clicks are ordinary links and are never counted as scans.

= 1.0.0 =
* First release: connect with an API key, create editable and fixed-content QR codes, page/post/product row actions, durable image assets, Gutenberg block and shortcode, scan activity, staging lock and per-role permissions.
