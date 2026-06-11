# PlugnPay BillPay Lite — WordPress

Let customers pay bills on your WordPress site without building a full checkout. You add a simple payment form (or a link in an email), customers confirm with hCaptcha, and they finish paying on PlugnPay’s secure hosted page.

**Card numbers never go through your WordPress site** — payment details are entered on PlugnPay Smart Screens v2.

---

## What you need before you start

- WordPress **5.8+** and PHP **7.4+**
- A [PlugnPay](https://www.plugnpay.com/) account with **Smart Screens v2** and an **authorization hash key** configured
- **hCaptcha** site key and secret key ([hcaptcha.com](https://www.hcaptcha.com/))
- **HTTPS** on your site (recommended; the plugin can require it)

---

## Install the plugin

1. Download **`plugnpay-billpay-lite.zip`** from GitHub Releases, or zip the `plugnpay-billpay-lite` folder from this repo.
2. In WordPress, go to **Plugins → Add New → Upload Plugin** and upload the ZIP.
3. Click **Activate**.

**Manual install:** copy the `plugnpay-billpay-lite` folder to `wp-content/plugins/` and activate it under **Plugins**.

---

## Set it up (5 minutes)

Open **PlugnPay BillPay Lite** in your WordPress admin sidebar.

### 1. Gateway settings

Enter the credentials from your PlugnPay account:

- **Gateway Account** — your PlugnPay username  
- **Authorization Hash Key** — must match your PlugnPay account  
- **Authorization Hash Fields** — must match your PlugnPay account  
- **Currency** — e.g. `USD`  
- **Card Types Allowed** — check the types your PlugnPay account accepts (Visa, Mastercard, Amex, Discover, Diners, JCB, EasyLink, Bermuda, IslandCard, Butterfield, KeyCard, MilStar, Solo, Switch). Icons show where artwork exists; others appear as text labels.

### 2. hCaptcha

Paste your **Site Key** and **Secret Key** from hCaptcha.

### 3. Page layout

- Set **min/max amount** and field labels  
- Turn **Invoice Number** / **Customer ID** fields on or off as needed  

### 4. After payment

Choose what happens when payment succeeds:

- **Receipt** — PlugnPay shows a receipt (fill in your company details), or  
- **Callback** — send the customer back to a URL on your site  

### 5. Contact info

Add an email and phone number — these appear if something goes wrong (bad captcha, invalid amount, etc.).

When you’re done, **Save** at the bottom of the settings page. The settings screen shows your live payment URLs — copy those if you need direct links.

---

## Add a payment form to a page

Edit any page or post and add this shortcode:

```
[pnp_payment_form]
```

Publish the page. Customers enter amount (and invoice/customer ID if enabled), pass hCaptcha, then pay on PlugnPay.

---

## Send a payment link (email, SMS, portal)

You can link straight to payment with the amount and reference numbers already filled in.

**Simple link format** (easiest to read and share):

```
https://YOUR-SITE.com/wp-admin/admin-ajax.php?action=pnp_payment&amt=25.00&id1=INV-123&id2=CUST-456
```

| Parameter | Meaning |
|-----------|---------|
| `amt` | Amount (e.g. `25.00`) |
| `id1` | First identifier (e.g. invoice number) |
| `id2` | Second identifier (e.g. customer ID) — omit if you don’t use it |

**Example** — customer owes $25 on invoice INV-123:

```
https://yoursite.com/wp-admin/admin-ajax.php?action=pnp_payment&amt=25.00&id1=INV-123
```

If WordPress is installed in a subfolder, include that path — replace **`subfolder`** in the URL below with your actual folder name:

```
https://yoursite.com/subfolder/wp-admin/admin-ajax.php?action=pnp_payment&amt=25.00&id1=INV-123
```

> **Tip:** Your exact URLs are shown on the **PlugnPay BillPay Lite** settings page — use those instead of guessing.

**Optional pretty links** — if you prefer `/pnp-pay/?amt=25.00&id1=INV-123`, set the slug under Page Layout and visit **Settings → Permalinks → Save** once. The shortcode form works without this step.

---

## How payment works

1. Customer fills in the form or opens your payment link.  
2. They review their details and complete **hCaptcha**.  
3. They’re sent to **PlugnPay Smart Screens v2** to enter card details.  
4. PlugnPay shows a **receipt** or sends them to your **callback URL**, depending on your settings.

---

## Something not working?

### “Page not found” (404)

- Use the **admin-ajax URL** from your plugin settings — it always works.  
- If you use pretty links (`/pnp-pay/`), go to **Settings → Permalinks** and click **Save** (no changes needed).

### “Missing required information”

- Check the amount is within your min/max settings.  
- Include any fields you marked as required (invoice #, customer ID, etc.).

### “Temporarily unavailable”

- Captcha failed, too many tries, or HTTPS required but site is on HTTP.  
- Wait a few minutes and try again, or check your hCaptcha keys.

### Form looks old after updating the plugin

- Clear your **page cache** (LiteSpeed, Cloudflare, etc.) so the new form HTML loads.

### Card icons missing or showing text labels

In **Card Types Allowed**, pick from the supported PlugnPay values: Visa, Mastercard, Amex, Discover, Diners, JCB, EasyLink, Bermuda, IslandCard, Butterfield, KeyCard, MilStar, Solo, Switch. Major brands show icons; regional types (e.g. EasyLink, Bermuda) show a text label instead.

---

## Security (short version)

This plugin is built for **hosted payments**: WordPress never stores card numbers. It only collects amount and reference fields, verifies hCaptcha, and securely hands off to PlugnPay. Keep your site on HTTPS and protect your PlugnPay hash key like any other secret.

---

## For developers

This repo is part of PlugnPay’s **`shopping-cart-WordPress`** GitHub project. Technical details (file layout, constants, hooks) live in the [repository README](../README.md).

**Version:** 2.0.1 · **License:** [GPL-2.0-or-later](LICENSE) · **Author:** [PlugnPay Technologies Inc.](https://www.plugnpay.com/)

See [CHANGELOG.md](CHANGELOG.md) for release history.
