=== WPIntegrations Form Connector for Zoho CRM ===
Contributors: wpintegrations
Tags: zoho crm, zoho, contact form 7, wpforms, gravity forms
Requires at least: 6.2
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Send Contact Form 7, Elementor Pro, WPForms, Fluent Forms and Gravity Forms submissions to Zoho CRM as leads or contacts.

== Description ==

**WPIntegrations Form Connector for Zoho CRM** turns every form submission into a Zoho CRM lead or contact — from whichever form plugin your site uses.

= Supported form plugins =

* Contact Form 7
* Elementor Pro Forms
* WPForms (Lite and paid editions)
* Fluent Forms
* Gravity Forms

One plugin covers them all: each integration picks a Zoho CRM record type, then a form plugin, then a form, then the Zoho fields it fills.

= Why this plugin =

* **Field mapping screen** — map your form's fields to the Zoho CRM fields you want filled. No renaming form fields to match Zoho.
* **Name parts** — a WPForms, Fluent Forms or Gravity Forms name field offers first and last name separately, so they land in the right Zoho fields.
* **Every Zoho data centre** — accounts in the US, EU, India, Australia, Japan, Canada and other Zoho regions connect the same way; the plugin talks to your account's own region.
* **Your own Zoho client** — you connect with a client you create in the Zoho API Console, so no third-party server ever holds your Zoho access.
* **Doesn't hold up your form** — on hosts running PHP-FPM or LiteSpeed (most of them), delivery happens after the visitor already has their confirmation, so a slow API doesn't slow your form down.
* **Full delivery log** — see every request, response and failure, worded from Zoho's own error. Nothing is silent.
* **Secure by design** — your client secret and tokens are stored encrypted, every admin endpoint requires administrator capability, and nothing is sent anywhere except Zoho.

= What this free version includes =

* Unlimited integrations, across all five form plugins
* Create Leads or Contacts in Zoho CRM
* Last Name, First Name, Email, Phone, Mobile, Company (leads), Lead Source and Description mapping
* Run your Zoho CRM workflow rules on new records, or skip them
* Values resolved at submission time: page URL, submission time, the logged-in user's name and email, site title and URL, or a fixed value
* A delivery log with the full request and response for every send

= Pro version =

A separate Pro plugin, sold on [wpintegrations.net](https://wpintegrations.net/plugins/connect-wordpress-forms-to-zoho-crm/), adds:

* Any Zoho CRM module, custom modules included — Deals, Accounts, Cases, Tasks, Products and more
* Every Zoho CRM field, including your custom fields, following the layout you choose
* Updating the existing record instead of creating a duplicate, matched by email or any field marked unique in Zoho CRM
* An Account and a Deal created with the contact, in the pipeline and stage you choose
* Record owner, assignment rules and tags
* Approval processes and blueprints, Notes, and the Google Ads click ID (GCLID)
* Files uploaded through the form, placed in File Upload and Image Upload fields or attached to the record
* A delivery queue that retries failed sends automatically, and Resend for failed deliveries in the log
* Conditions — send only when the answers match, e.g. only when the consent box is ticked

= Source code and third-party libraries =

The complete, human-readable source of this plugin is public at [github.com/wpintegrations/wpintegrations-connector-for-zoho-crm](https://github.com/wpintegrations/wpintegrations-connector-for-zoho-crm).

The admin screens are a React app. The compiled bundle in `build/assets/` is generated with Vite from the source in `resources/js/`, which ships with the plugin together with `package.json` and `vite.config.mjs`. To rebuild it, install Node.js 18 or later and pnpm, then run `pnpm install` and `pnpm run build` in the plugin folder.

The bundle includes these open-source libraries:

* React and React DOM 18.3.1 (MIT) — [github.com/facebook/react](https://github.com/facebook/react)
* Ant Design 5.29.3 (MIT) — [github.com/ant-design/ant-design](https://github.com/ant-design/ant-design)
* Ant Design Icons 5.6.1 (MIT) — [github.com/ant-design/ant-design-icons](https://github.com/ant-design/ant-design-icons)

This plugin is built by WPIntegrations and is not affiliated with, endorsed by, or sponsored by Zoho Corporation, Contact Form 7, Elementor, WPForms, Fluent Forms or Gravity Forms. All names are the trademarks of their respective owners, used here only to describe what the plugin connects.

= Requirements =

* At least one of the supported form plugins (Elementor forms need Elementor Pro)
* A Zoho CRM account (any edition, including Free)
* A Server-based Application client created in the Zoho API Console (https://api-console.zoho.com/)

== External services ==

This plugin connects to **Zoho CRM** and **Zoho Accounts** (operated by Zoho Corporation, https://www.zoho.com/). Nothing is sent until you connect your Zoho account on the Settings tab, and form data is sent only when a form that has an integration is submitted.

* **Connecting:** your browser is sent to Zoho Accounts (`https://accounts.zoho.com/oauth/v2/auth`) to allow access. The plugin then sends your client ID, client secret and the one-time authorization code to your account's Zoho Accounts server (for example `https://accounts.zoho.com/oauth/v2/token` or `https://accounts.zoho.eu/oauth/v2/token`) to receive access tokens, and refreshes them there while connected. The permissions asked for are creating leads, creating contacts and reading your organization's name.
* **What is sent:** the form fields you explicitly map for the record, and whether Zoho should run its workflow rules for it. If you map them, it also sends the optional values the mapping screen offers: the page URL, the submission time, the logged-in user's name and email, the site title and URL, and any fixed text you enter.
* **What is read:** your Zoho CRM organization's name, to confirm the connection.
* **Where:** your account's Zoho CRM API domain (for example `https://www.zohoapis.com` or `https://www.zohoapis.eu`), at `/crm/v8/Leads`, `/crm/v8/Contacts` and `/crm/v8/org`, authenticated with your own access token.

No data is sent to WPIntegrations or any other third party. Zoho terms of service: https://www.zoho.com/terms.html — privacy policy: https://www.zoho.com/privacy.html

== Installation ==

1. Install and activate the plugin.
2. In the Zoho API Console (https://api-console.zoho.com/), add a **Server-based Application** client. Use your site's address as the homepage URL, and copy the **Authorized redirect URI** from the plugin's **Settings** tab. If your Zoho account is outside the US data centre, turn on **Multi-DC** in the client's Settings tab.
3. Paste the client's Client ID and Client Secret on the plugin's **Settings** tab, save them, and click **Connect to Zoho CRM**. Allow access in Zoho, then use **Test connection**.
4. Open the **Integrations** tab and create an integration: choose Lead or Contact, pick the form plugin and the form, map the fields, and save.

The full walkthrough, Zoho API Console and Multi-DC included, is in the [getting-started guide](https://wpintegrations.net/docs/wordpress-forms-zoho-crm-getting-started/).

== Frequently Asked Questions ==

= Which fields do I have to map? =

**Last Name** — Zoho CRM requires it on every lead and contact. Everything else is optional. A submission with an empty last name is not sent, and the log says why. If your Zoho layout makes another field mandatory (for example Company on leads), map that too; the log names any field Zoho asks for.

= My form has a single "Name" field. What do I map? =

Map it to Last Name. Zoho needs a last name, and a full name in that field still identifies the person.

= My Zoho account is in Europe (or India, Australia, Japan, Canada). Does it work? =

Yes. Turn on **Multi-DC** for your client in the Zoho API Console (Settings tab of the client), then connect. Zoho tells the plugin which region your account is in, and every request goes there.

= Connecting says Zoho did not accept the Client ID or Client Secret =

Check that you copied both from the same client, that the client type is Server-based Application, and — for accounts outside the US — that Multi-DC is on for the client.

= What happens when the same person submits twice? =

The free version creates a new record each time, as Zoho's own web forms do. The Pro version can update the existing record instead, matched by email or by any field marked unique in Zoho CRM.

= Does it work with the free version of Elementor? =

The Forms widget is an Elementor Pro feature, so Elementor Pro is required for Elementor forms. The other form plugins work in their free editions, except Gravity Forms, which is paid only.

= What happens if Zoho is temporarily unavailable? =

The submission is not delivered, and the failure is recorded in the Logs tab with the full response.

= Where is the source code of the admin screens? =

In `resources/js/` inside the plugin, and publicly at [github.com/wpintegrations/wpintegrations-connector-for-zoho-crm](https://github.com/wpintegrations/wpintegrations-connector-for-zoho-crm). The compiled files in `build/` are generated from it with Vite: run `pnpm install` and then `pnpm run build` in the plugin folder. See "Source code and third-party libraries" above for the bundled libraries.

= Where do I see errors? =

The **Logs** tab shows every delivery with the full request and response.

== Changelog ==

= 1.0.1 =
* The Pro card on the Settings page now links to the plugin's own page on wpintegrations.net.

= 1.0.0 =
* Initial release: Contact Form 7, Elementor Pro, WPForms, Fluent Forms and Gravity Forms submissions to Zoho CRM leads and contacts, with field mapping, every Zoho data centre, workflow rules on or off, a delivery log and encrypted credential storage.
