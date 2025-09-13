\=== MAW Simple Forms ===
Contributors: mawebb, krikas-sec
Tags: forms, contact form, form builder, bootstrap, submissions, entries, email, gutenberg block, shortcode, gdpr, privacy
Requires at least: 6.0
Tested up to: 6.6
Requires PHP: 7.4
Stable tag: 0.1.2
License: GPLv2 or later
License URI: [https://www.gnu.org/licenses/gpl-2.0.html](https://www.gnu.org/licenses/gpl-2.0.html)

Simple, ad‑free WordPress form plugin. Build custom fields, receive email notifications, store entries, and manage status — with a Gutenberg block and clean Bootstrap‑friendly markup.

\== Description ==

**MAW Simple Forms** is a lightweight form plugin with no upsells or ads. Create any number of forms, define your own fields (Label, Name/slug, Type, Required), place the form using a **Gutenberg block** or **shortcode**, receive **email notifications**, and manage **submissions** in the dashboard (New / Completed / Trash).

**Highlights**

* No upsells, no ads
* Gutenberg block (**MAW Form**) – pick a form visually
* Shortcode fallback – `[maw_form id="123"]` or `[maw_form slug="contact"]`
* Stores entries in DB with statuses (New / Completed / Trash)
* Email notifications via `wp_mail()`
* Bootstrap‑friendly HTML out of the box
* i18n‑ready – English (default), Swedish (`sv_SE`) included

**Under the hood**

* Custom Post Type: `maw_form` (form definitions)
* Database table: `{prefix}maw_form_entries` (created on activation)
* Nonce/CSRF protection + honeypot anti‑spam
* All inputs are sanitized on save

\== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/` or install the ZIP via **Plugins → Add New → Upload Plugin**.
2. Activate **MAW Simple Forms**.
3. Go to **MAW Forms → Forms → Add New** to create your first form.
4. Insert it on a page with the **MAW Form** block or shortcode.

\== Usage ==

\=== Gutenberg Block ===

* Add the block **MAW Form** and select which form to embed from the settings panel (gear icon).

\=== Shortcodes ===

```
[maw_form id="123"]
[maw_form slug="contact"]
```

\=== Field Types ===

* Text, Email, Phone, Textarea (each supports **Required**)

\=== Entries Management ===

* **MAW Forms → Entries** – list, view, mark **Completed**, move to **Trash**, or delete permanently

\=== Email Notifications ===

* Each form has a **Recipient email** + **Success message** setting
* Subject: `New submission: {Form title}`
* Body: all submitted fields + IP

\== Styling (Bootstrap) ==

The frontend markup uses Bootstrap‑like classes: `form-label`, `form-control`, `mb-3`, `btn btn-primary`, `alert alert-success`.

If your theme doesn’t load Bootstrap, you can enqueue it **only when a form renders** by adding this to a small plugin or your theme:

```php
add_filter('maw_forms_enqueue_bootstrap', '__return_true');
```

\== Internationalization ==

* Default locale: English (strings in code)
* Included translation: **Swedish (`sv_SE`)** – `.po`, `.mo`, and block editor JSON

To add or update translations with WP‑CLI:

```bash
wp i18n make-pot . languages/maw-forms.pot --domain=maw-forms
msgmerge --update --backup=none languages/maw-forms-sv_SE.po languages/maw-forms.pot
wp i18n make-mo languages languages
wp i18n make-json languages --no-purge
```

\== Privacy ==

This plugin stores form submissions in your site’s database (`{prefix}maw_form_entries`). Consider updating your privacy policy to disclose what you collect and how long you retain it.

\== Frequently Asked Questions ==

\= The block doesn’t show up in the editor =

* Ensure your site uses the block editor and that the **`maw_form`** CPT is present (the plugin registers it with `show_in_rest => true`).
* Check the browser console for JavaScript errors from other plugins/themes.
* Flush caches and hard reload the editor.

\= The form looks unstyled =

* The plugin outputs Bootstrap‑friendly markup. If your theme doesn’t include Bootstrap, enable the optional filter:

```php
add_filter('maw_forms_enqueue_bootstrap', '__return_true');
```

\= Emails aren’t arriving =

* Verify the **Recipient email** is valid.
* Use an SMTP plugin (or your host’s recommended mail settings) to improve deliverability.
* Check the spam folder.

\= How do I translate the block UI? =

* Make sure JSON translation files exist in `languages/` (e.g., `maw-forms-sv_SE-*.json`). Rebuild with `wp i18n make-pot` + `make-json`.

\== Screenshots ==

1. Form editor with custom fields
2. Entries list with statuses (New / Completed / Trash)
3. MAW Form block in the editor
4. Frontend form with Bootstrap classes

\== Changelog ==

\= 0.1.2 =

* Add GPL‑2.0‑or‑later license and headers
* Polish admin UI (metabox field widths)
* Remove shortcode hint from settings box
* i18n: full English source + Swedish update

\= 0.1.1 =

* Gutenberg block (manual registration, no build step)
* i18n setup for PHP + JS (wp\_set\_script\_translations)

\= 0.1.0 =

* Initial release: forms CPT, fields, shortcode, email notifications, entries screen

\== Upgrade Notice ==

\= 0.1.2 =
This update finalizes licensing (GPL‑2.0‑or‑later), improves admin UI, and updates translations. No breaking changes.

\== Blocks ==

\= MAW Form =

* Block name: `mawebb/maw-form`
* Insert a MAW Simple Form and choose which one in the settings panel.
