=== ForgeHub AI Alt Text ===
Contributors: wpforgehub
Tags: alt text, image seo, ai alt text, gemini, accessibility
Requires at least: 6.0
Tested up to: 7.1
Stable tag: 1.1.3
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Generate WCAG-compliant alt text and SEO titles for images with zero server bloat using your own Google Gemini, OpenAI, Claude, or DeepSeek API key.

== Description ==

**AI Alt Text & Image SEO Generator** is a lightweight, zero-bloat WordPress plugin designed to solve one crucial problem flawlessly: adding descriptive, WCAG-compliant Alt-Text and SEO titles to your media library using modern Vision AI.

Unlike traditional SaaS plugins that lock your data into expensive third-party subscriptions and slow down your server, our plugin operates on a pure **BYOK (Bring Your Own Key)** architecture. Your images communicate directly from your WordPress server to your chosen AI provider without intermediary proxy servers.

### 🌟 Key Features (Free Version)
* **6 Supported AI Providers:**
  * **Google Gemini:** Gemini 1.5 Flash & 2.0 Flash (Super fast, generous free tier).
  * **OpenAI:** GPT-4o & GPT-4o-mini vision models.
  * **Anthropic Claude:** Claude 3.5 Sonnet & Haiku for nuanced descriptions.
  * **DeepSeek:** Leading cost-effective model integration.
  * **OpenRouter:** Access 100+ open-source models with a single API key.
  * **Custom OpenAI-Compatible Endpoint:** Connect local Ollama, vLLM, Groq, or xAI.
* **1-Click Generation in Media Library:** Adds a dedicated "Generate with AI" button inside the standard WordPress attachment modal and edit media screens.
* **Multi-Language Support:** Generate alt-text in English, Turkish, German, Spanish, French, Italian, and more.
* **Brand & Niche Context:** Provide custom guidance (e.g., "e-commerce shoe store") to produce domain-specific keywords.
* **Zero Bloat & Zero External CDN:** Pure vanilla JavaScript and native WordPress APIs. Under 50 KB total footprint.

### 🚀 PRO Version Features
Looking to automate thousands of images across your site?
* **Bulk Background Scanner:** Queue and optimize all missing alt tags across your 10,000+ media library in batches via WP-Cron without timeouts.
* **Instant Upload Hook:** Automatically analyzes and writes alt text the exact second you upload any photo to WordPress.
* **WooCommerce Deep Context:** Injects product title, SKU, and categories into image SEO tags.

== Installation ==

1. Upload the `forgehub-ai-alt-text` folder to your `/wp-content/plugins/` directory, or install directly through the WordPress plugins screen.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Navigate to **Settings -> AI Alt-Text (BYOK)**.
4. Select your preferred AI provider (e.g., Google Gemini or OpenAI) and paste your API key.
5. Open any image in your **Media -> Library** and click **Generate with AI**!

== Frequently Asked Questions ==

= Do I need to pay for an external subscription to use this plugin? =
No! You bring your own API key (BYOK). Google Gemini offers a generous free tier, and OpenAI's GPT-4o-mini costs a fraction of a cent per image. You never pay middlemen fees.

= Is this compliant with accessibility standards (WCAG)? =
Yes. The default prompt instructs the AI vision models to produce concise, objective, descriptive alt text under 125 characters, strictly adhering to WCAG 2.1 accessibility guidelines.

= Does this plugin slow down my site frontend? =
Not at all. The plugin executes solely in the WordPress admin dashboard and media modal when you choose to generate alt text. Zero scripts or stylesheets are loaded on your public frontend.

== Changelog ==


= 1.1.3 =
* Rename: plugin is now "ForgeHub AI Alt Text" (ForgeHub naming standard).
* Fix: Plugins screen now shows a consistent "Settings" link.
= 1.1.2 =
* Rename: plugin slug and text domain are now forgehub-ai-alt-text (WordPress.org naming rules).
* Docs: README and readme.txt updated to the unified repository standard.

= 1.0.0 =
* Initial public release.
* Support for Google Gemini, OpenAI, Claude, DeepSeek, OpenRouter, and Custom Endpoints.
* Media library modal integration with live AJAX generation.
* Multi-language support and custom brand context prompt engineering.
