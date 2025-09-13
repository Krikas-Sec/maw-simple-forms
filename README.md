# MAW Simple Forms

*Simple, ad‑free WordPress form plugin. Build custom fields, receive email notifications, store entries, and manage status — with a Gutenberg block and clean Bootstrap‑friendly markup.*

* **No upsells, no ads**
* **Gutenberg block** ("MAW Form")
* **Shortcode** fallback (`[maw_form id="123"]`)
* **DB entries** with statuses: New / Completed / Trash
* **Email notifications** via `wp_mail()`
* **Bootstrap‑friendly** HTML out of the box
* **i18n‑ready** (English default, Swedish `sv_SE` included)

---

## Table of Contents

* [Requirements](#requirements)
* [Installation](#installation)
* [Quick Start](#quick-start)
* [Usage](#usage)
* [Styling (Bootstrap)](#styling-bootstrap)
* [Security & Privacy](#security--privacy)
* [Internationalization (i18n)](#internationalization-i18n)
* [Troubleshooting](#troubleshooting)
* [Development](#development)
* [Release (GitHub Actions)](#release-github-actions)
* [Roadmap](#roadmap)
* [Contributing](#contributing)
* [License](#license)

---

## Requirements

* WordPress **6.0+**
* PHP **7.4+** (PHP 8.x recommended)

---

## Installation

### From GitHub ZIP

1. Download the latest release ZIP from the Releases page.
2. In WordPress: **Plugins → Add New → Upload Plugin**, choose the ZIP, click **Install Now**, then **Activate**.

### From source

1. Copy this folder to `wp-content/plugins/maw-simple-forms/`.
2. Activate **MAW Simple Forms** in **Plugins**.

---

## Quick Start

1. Go to **MAW Forms → Forms → Add New**.
2. Add fields (Label, Name/slug, Type, Required).
3. Set **Recipient email** and **Success message** in the sidebar.
4. Insert the form on a page:

   * **Block**: add the **MAW Form** block and pick your form.
   * **Shortcode**: `[maw_form id="123"]` or `[maw_form slug="contact"]`.
5. New submissions appear under **MAW Forms → Entries** where you can mark **Completed** or move to **Trash**.

---

## Usage

### Gutenberg Block

* Search for **“MAW Form”** in the block inserter.
* Use the settings panel (gear icon) to select which form to render.

### Shortcode

```text
[maw_form id="123"]
[maw_form slug="contact"]
```

### Field Types

* `Text`, `Email`, `Phone`, `Textarea`
  Each field supports **Required**.

### Entries Management

* Tabs: **New**, **Completed**, **Trash**
* Expand **Data** to view submitted values
* Permanent delete available on the Entries page

### Email Notifications

* Each form can send an email to the configured **Recipient email**.
* Subject: `New submission: {Form title}`
* Body: all submitted fields + IP

---

## Styling (Bootstrap)

The frontend markup uses Bootstrap‑like classes:

* `form-label`, `form-control`, `mb-3`, `btn btn-primary`, `alert alert-success`

If your theme doesn’t load Bootstrap, you can enqueue it via a filter:

```php
add_filter('maw_forms_enqueue_bootstrap', '__return_true');
```

> The plugin will conditionally enqueue Bootstrap 5 CSS from jsDelivr **only when a form renders**.

---

## Security & Privacy

* Nonce/CSRF protection on form submits
* Honeypot field to reduce bots
* Sanitization of all inputs
* Entries stored in DB (`wp_maw_form_entries`)
  Update your privacy policy accordingly (what you collect, how long you keep it).

---

## Internationalization (i18n)

* Default strings are **English**.
* **Swedish (`sv_SE`)** translations are included (`.po`, `.mo`, and block editor JSON).

Rebuild translations via WP‑CLI (plugin root):

```bash
# Generate/refresh POT (PHP + JS)
wp i18n make-pot . languages/maw-forms.pot --domain=maw-forms

# Merge POT into your Swedish PO (keeps existing translations)
msgmerge --update --backup=none languages/maw-forms-sv_SE.po languages/maw-forms.pot

# Build MO
wp i18n make-mo languages languages

# Build JS translation JSON for the block editor
wp i18n make-json languages --no-purge
```

WordPress will auto‑load `sv_SE` when **Settings → General → Site Language** is set to **Svenska**. For all other locales, English is used as fallback.

---

## Troubleshooting

**Block not showing in editor**

* Ensure CPT uses `show_in_rest => true` (it does in this plugin).
* Check console for errors; the block registers `wp_set_script_translations()` for i18n.
* Clear caches / hard reload the editor.

**JS translations not applied**

* Confirm JSON files exist in `languages/` (e.g., `maw-forms-sv_SE-<hash>.json`).
* Rebuild with `wp i18n make-pot` + `make-json` as shown above.

**Emails not received**

* Verify `Recipient email` is valid.
* Check your mail transport (SMTP plugin or host mail settings).
* Inspect spam folder.

---

## Development

### Codebase Highlights

* **Custom Post Type**: `maw_form` for form definitions
* **Shortcode**: `[maw_form]`
* **DB Table**: `wp_maw_form_entries` (created on activation)
* **Gutenberg block**: manual registration (no build step required)

### Build Translations

```bash
wp i18n make-pot . languages/maw-forms.pot --domain=maw-forms
msgmerge --update --backup=none languages/maw-forms-sv_SE.po languages/maw-forms.pot
wp i18n make-mo languages languages
wp i18n make-json languages --no-purge
```

### Coding Standards (optional)

If you use PHPCS with WordPress standard:

```bash
phpcs --standard=WordPress --extensions=php .
```

---

## Release (GitHub Actions)

A sample workflow can:

* generate translations (POT → PO/MO & JSON)
* package `maw-simple-forms.zip`
* attach the ZIP to a GitHub Release when you push a tag (e.g., `v0.1.2`)

See `.github/workflows/release.yml` in this repo (or add one based on your CI preferences).

---

## Roadmap

* CSV export for selected entries
* Turnstile / reCAPTCHA v3
* File uploads with MIME whitelist
* Thank‑you email to sender (auto‑reply)
* Gutenberg block options (button label, layout presets)
* Webhooks/REST (Slack/Discord/CRM)

---

## Contributing

Issues and PRs are welcome! Please keep changes focused and include testing notes.

---

## License

* **GPL‑2.0‑or‑later** (recommended for WordPress.org compatibility), or



