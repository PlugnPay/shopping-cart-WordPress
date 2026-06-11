# Changelog

All notable changes to the PlugnPay BillPay Lite WordPress plugin are documented here.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [2.0.1] - 2026-06-11

### Changed

- Renamed plugin to **PlugnPay BillPay Lite** to match the BillPay Lite product name
- Install folder and main bootstrap file: `plugnpay-billpay-lite/` / `plugnpay-billpay-lite.php`
- Text domain: `plugnpay-billpay-lite`
- Repository structured for publication as `shopping-cart-WordPress` on GitHub

## [2.0.0] - 2026-06-11

Major release merging prior fork work into a unified, production-ready plugin with improved routing, security, and admin experience.

### Added

- **Admin-ajax payment endpoint** — primary entry point at `wp-admin/admin-ajax.php?action=pnp_payment`; form POST and internal handoffs always use this URL so payments work on subdirectory installs without permalink changes
- **Dedicated security layer** (`includes/security.php`) — input normalization, alias mapping (`amt` / `id1` / `id2`), amount and account-code validation, blocked card-field detection, CSRF nonces, IP-based rate limiting (10 failures / 15 minutes), and short-lived server-side payment sessions (15-minute TTL)
- **Legacy route support** — pretty slug (`/pnp-pay/`), `index.php?pnp_pay=1`, and path-based fallback for direct GET links when rewrite rules are available
- **Subdirectory install handling** — request path normalization and rewrite rule patterns for home/site URL path prefixes
- **Deferred rewrite flush** — activation and slug changes schedule a flush; admin notice when pretty URLs may 404 until permalinks are saved
- **Card brand icon filtering** — icons on the form and captcha page match **Card Types Allowed** admin setting; unknown types are omitted
- **PlugnPay-branded UI** — logo header bar, structured layout, and card brand row on payment and captcha pages
- **Admin settings overhaul** — collapsible sections (Page Layout, Gateway, hCaptcha, Contacts, Success Response with Callback/Receipt subsections); live endpoint URL reference and example direct link on settings page
- **hCaptcha integration** — widget on captcha confirmation step with server-side verification via hCaptcha siteverify API
- **Smart Screens v2 handoff** — authorized POST to `https://pay1.plugnpay.com/pay/` with MD5 transaction hash and whitelisted hidden fields
- **Receipt and callback modes** — configurable on-screen receipt fields or callback URL with hidden POST / POST / GET transition types
- **Require HTTPS** setting — reject payment endpoint traffic over plain HTTP when enabled (default: Yes)
- **Secret preservation** — authorization hash key and hCaptcha secret retained when password fields are left blank on settings save
- **Amount range normalization** — min/max swapped automatically when min exceeds max on save

### Changed

- **Form action URL** — shortcode and captcha forms POST to admin-ajax instead of rewrite-dependent paths
- **Payment flow** — two-step flow: validate input → captcha confirmation → SSv2 redirect (session stored server-side, not in hidden amount/id fields)
- **Plugin version** — bumped to 2.0.0 to reflect architectural changes from merged forks
- **Rejected/error pages** — unified branded message layout with configurable contact email and phone

### Fixed

- **404 on payment slug** — `pre_handle_404`, `parse_request` injection, and path fallback prevent WordPress from treating valid payment URLs as 404s
- **Permalink dependency** — form submissions no longer require flushed rewrite rules
- **Subdirectory path detection** — direct GET links resolve correctly when WordPress lives in a subdirectory (e.g. `/woocommerce/`)
- **Callback URL validation** — saving Callback response type without a success URL shows a settings error instead of silently accepting invalid state
- **XSS hardening** — sanitization and escaping across payment input and rendered output
- **Session tampering** — captcha step reloads payment data from transient token rather than trusting client-supplied amount/identifier fields
- **Audit findings** — addressed security review items around input whitelist, nonce verification on POST, nocache headers on endpoint responses, and blocked PAN/CVV field names

### Security

- No cardholder data collected or stored on WordPress (SAQ A scope)
- Rate limiting, CSRF nonces, HTTPS enforcement, and blocked sensitive field names
- SSv2 redirect limited to explicit field whitelist

---

## Prior history

Versions before 2.0.0 existed across separate fork repositories. This release consolidates that work into the official `plugnpay-billpay-lite` plugin structure documented in this repository.

[2.0.1]: #201---2026-06-11
[2.0.0]: #200---2026-06-11
