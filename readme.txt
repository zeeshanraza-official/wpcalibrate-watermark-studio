=== WPCalibrate Watermark Studio ===
Contributors: wpcalibrate
Tags: watermark, image watermark, text watermark, media library, woocommerce watermark
Requires at least: 6.8
Tested up to: 7.1.2
Requires PHP: 8.2
Stable tag: 1.0.2
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Professional image and text watermarking studio for WordPress Media Library and WooCommerce product images with safe original preservation.

== Description ==

**WPCalibrate Watermark Studio** provides an enterprise-grade watermarking system engineered for photographers, e-commerce store owners, agencies, and publishers.

### Core Architecture & Protection Principle

Most watermarking plugins destructively overwrite your master media uploads. If your branding changes or you ever wish to alter the watermark opacity, position, or text, your library is permanently compromised.

WPCalibrate Watermark Studio strictly enforces an **untouched original preservation model**:
* **Zero Destruction:** Before any destructive rendering takes place, pristine master images are copied to a secured, non-browsable directory protected against direct web traversal.
* **Never Watermark Twice:** Reprocessing or changing watermark parameters always regenerates derivatives from the untouched source, eliminating watermark stacking.
* **Instant Restoration:** Restore any single image or your entire media library back to pristine condition at any time.

### Key Features

* **Image & Logo Watermarking:** Upload transparent PNG or WebP logos with proportional scaling, 9-point grid alignment, custom pixel caps, and alpha blending.
* **Text Watermarking:** Clean typographic watermarking utilizing a bundled SIL Open Font License typeface (Outfit) ensuring reliable cross-server rendering.
* **Combined Mode:** Apply independent logo and text watermarks with custom offsets and predictable compositing order.
* **Target Specific Image Sizes:** Select which WordPress registered sub-sizes receive watermarks while keeping the master full-size image clean if desired.
* **Automated Processing:** Automatically watermark new media library uploads based on context rules (all images, specific post types, or WooCommerce products).
* **Bulk Processing Queue:** Asynchronous, batch-controlled bulk processor supporting Action Scheduler and WP-Cron with live progress tracking.
* **WooCommerce Integration:** Built-in targeting for product featured images, gallery images, and variation attachments with declared HPOS compatibility.
* **System Diagnostics:** In-depth environment audit displaying GD/Imagick capabilities, storage permissions, and background queue health.

*Notice:* Watermarking discourages casual unauthorized reuse and reinforces brand ownership, but cannot provide absolute copy protection against determined manual screen capture or cropping.

== Installation ==

1. Upload the `wpcalibrate-watermark-studio` folder to your `/wp-content/plugins/` directory, or install directly through the WordPress Plugins dashboard.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Navigate to **WPCalibrate > Watermark Studio** to configure your logo, text, and target sizes.
4. (Optional) Run the **Bulk Processing** tool to protect and watermark existing Media Library images.

== Frequently Asked Questions ==

= Does this plugin overwrite my original images permanently? =
No. The plugin preserves an untouched, full-fidelity backup of your original file before applying any watermark. You can restore your clean originals at any time.

= Does it require WooCommerce to work? =
No. WooCommerce is entirely optional. The plugin functions seamlessly on any standard WordPress installation. When WooCommerce is active, advanced product targeting is unlocked.

= Does this work with GD or Imagick? =
Both! The plugin features an image editor abstraction adapter that prioritizes Imagick if available, and falls back to PHP GD with true 32-bit alpha compositing.

= Can I choose which image sizes get watermarked? =
Yes. You can select specific WordPress registered image sizes (such as large, medium_large) and choose whether to include or exclude the full-size master image.

== Changelog ==

= 1.0.0 =
* Initial production release.
* Image, text, and combined watermarking modes.
* Nine-point position matrix with independent X/Y offsets.
* Protected original backup and restoration engine.
* Safe bulk processing queue with Action Scheduler and WP-Cron adapters.
* Bundled SIL Open Font License typeface for consistent server-side typography.
* Declared WooCommerce HPOS compatibility.
* Diagnostics and system health audit panel.

== Upgrade Notice ==

= 1.0.0 =
Initial release. No upgrade steps required.
