# shopping-cart-WordPress

Official [PlugnPay](https://www.plugnpay.com/) payment plugins for WordPress.

---

## What’s in this repo?

| Plugin | What it does |
|--------|----------------|
| **[BillPay Lite](plugnpay-billpay-lite/)** | Simple bill-pay form, payment links, hCaptcha, redirect to Smart Screens v2 |

More WordPress modules may be added here over time, similar to our [WooCommerce](https://github.com/plugnpay/shopping-cart-WooCommerce) repository.

---

## BillPay Lite — quick install

1. **Get the plugin** — download `plugnpay-billpay-lite.zip` from GitHub Releases, or zip the `plugnpay-billpay-lite` folder.
2. **Install in WordPress** — **Plugins → Add New → Upload Plugin**, or copy the folder to `wp-content/plugins/`.
3. **Activate** — turn on **PlugnPay BillPay Lite**.
4. **Configure** — open **PlugnPay BillPay Lite** in the admin menu (gateway account, hCaptcha, layout, receipt/callback).

**Full guide (setup, shortcode, payment links, troubleshooting):**  
👉 **[plugnpay-billpay-lite/README.md](plugnpay-billpay-lite/README.md)**

---

## Publishing a release

Zip the plugin folder for distribution:

```bash
cd plugnpay-billpay-lite && zip -r ../plugnpay-billpay-lite.zip .
```

Attach `plugnpay-billpay-lite.zip` to a GitHub release and copy notes from [CHANGELOG.md](plugnpay-billpay-lite/CHANGELOG.md).

---

## License & author

**GPL-2.0-or-later** — see [LICENSE](plugnpay-billpay-lite/LICENSE).

**PlugnPay Technologies Inc.** · [plugnpay.com](https://www.plugnpay.com/)
