# WPForgeHub AI Alt-Text & Image SEO Generator (BYOK)

<p align="center">
  <img src="assets/banner-772x250.png" alt="WPForgeHub AI Alt Text Banner" width="100%" style="border-radius: 12px;" />
</p>

[![WordPress 6.0+](https://img.shields.io/badge/WordPress-6.0%2B-blue.svg)](https://wordpress.org)
[![Tested up to 6.7](https://img.shields.io/badge/Tested%20up%20to-6.7-brightgreen.svg)](https://wordpress.org)
[![PHP 7.4-8.3](https://img.shields.io/badge/PHP-7.4%20--%208.3-green.svg)](https://php.net)
[![License: GPL v2](https://img.shields.io/badge/License-GPLv2-orange.svg)](license.txt)
[![Zero Bloat](https://img.shields.io/badge/Assets-%3C50KB-purple.svg)](https://wpforgehub.com)
[![BYOK Architecture](https://img.shields.io/badge/Architecture-BYOK-cyan.svg)](https://wpforgehub.com)

> ⚡ **Generate WCAG-compliant image Alt-Text and SEO titles directly from your WordPress Media Library using your own AI API keys.**

---

## 🌟 Why WPForgeHub AI Alt-Text?

Most AI plugins for WordPress charge recurring middleman markup fees and route your images through external proxy servers. **WPForgeHub AI Alt-Text** changes the game with a 100% private **BYOK (Bring Your Own Key)** architecture:

* **Zero Middleware:** Your server connects directly to your chosen AI provider using native WordPress APIs.
* **Virtually Free Operations:** Use Google Gemini's generous free tier or OpenAI's GPT-4o-mini for fractions of a penny per image.
* **Under 50 KB Footprint:** Zero external CDN dependencies, zero bloated JavaScript frameworks. Pure performance.

---

## 🤖 Supported AI Providers (6-in-1 Engine)

1. **Google Gemini:** `gemini-1.5-flash`, `gemini-2.0-flash` *(Fast & free quota)*
2. **OpenAI:** `gpt-4o-mini`, `gpt-4o` *(Industry standard vision)*
3. **Anthropic Claude:** `claude-3-5-haiku`, `claude-3-5-sonnet` *(Most natural copy)*
4. **DeepSeek:** `deepseek-chat`, `deepseek-reasoner` *(Low cost powerhouse)*
5. **OpenRouter:** Connect 100+ open vision models with a single API key
6. **Custom Endpoint:** Connect your local `Ollama`, `Groq`, or `vLLM` server

---

## 🚀 Free vs. PRO Feature Matrix

| Feature | FREE (Community) | PRO |
| :--- | :---: | :---: |
| **Media Library 1-Click Alt-Text** | ✅ | ✅ |
| **All 6 AI Providers (BYOK)** | ✅ | ✅ |
| **Multi-Language Output (EN, TR, DE, ES, FR, IT)** | ✅ | ✅ |
| **Brand & Niche Context Guidance** | ✅ | ✅ |
| **Auto-Update Attachment Title** | ✅ | ✅ |
| **Background Bulk Scanner (WP-Cron)** | ❌ | **✅ (10,000+ Images)** |
| **Instant Upload Automation (`add_attachment`)** | ❌ | **✅ Automatic** |
| **WooCommerce Context (Title, SKU, Category)** | ❌ | **✅ Deep SEO** |
| **Priority Developer Support** | ❌ | **✅ Included** |

---

## 💎 PRO Pricing & Licensing

| Plan Tier | Monthly | Annual (Best Value) | Lifetime | Ideal For |
| :--- | :--- | :--- | :--- | :--- |
| **Single Site (1 Site)** | $3.99 / mo | **$19 / yr** | $39 *(One-time)* | Individual blog or business site |
| **5 Sites** | $7.99 / mo | **$39 / yr** | $79 *(One-time)* | Freelancers & web developers |
| **Unlimited Sites** | $12.99 / mo | **$69 / yr** | $129 *(One-time)* | Agencies & multi-site networks |

👉 **[Get PRO on WPForgeHub](https://wpforgehub.com/#products)** — Instant license delivery, automatic updates, and 100% money-back guarantee.

---

## 📦 Installation

1. Clone or download this repository into your `/wp-content/plugins/wpfh-ai-alt-text/` folder:
   ```bash
   git clone https://github.com/WpForgeHub/wpfh-ai-alt-text.git
   ```
2. Activate the plugin through the **Plugins** menu in WordPress.
3. Go to **Settings -> AI Alt-Text (BYOK)**, choose your AI provider, and enter your API key.
4. Open **Media -> Library**, click any image, and hit **Generate with AI**!

---

## 🛡️ Security & Coding Standards

* 100% compliant with official **WordPress.org Plugin Guidelines**.
* Strict Nonce and `manage_options` / `upload_files` capability validation.
* Pure input sanitization (`sanitize_text_field`, `esc_url_raw`) and output escaping (`esc_html`, `esc_attr`).
* Licensed under **GPLv2 or later**.

---

## 🌐 WPForgeHub Ecosystem

Crafted with care by the **[WPForgeHub](https://wpforgehub.com)** team.
* Website: [https://wpforgehub.com](https://wpforgehub.com)
* GitHub: [@WpForgeHub](https://github.com/WpForgeHub)
* Support: `support@wpforgehub.com`
