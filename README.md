# WPCalibrate Watermark Studio

[![WordPress Tested](https://img.shields.io/badge/WordPress-6.8%20to%207.1.2-blue.svg)](https://wordpress.org)
[![PHP](https://img.shields.io/badge/PHP-8.2%20|%208.3-8892BF.svg)](https://php.net)
[![License](https://img.shields.io/badge/License-GPL--2.0--or--later-green.svg)](LICENSE)
[![Latest Release](https://img.shields.io/badge/Release-v1.0.2-orange.svg)](https://github.com/zeeshanraza-official/wpcalibrate-watermark-studio/releases)

Professional image and typographic watermarking studio for WordPress Media Library and WooCommerce product images, engineered with safe original preservation, automatic upload processing, and background queue management.

---

## Table of Contents

- [About](#about)
  - [The Untouched Source Principle](#the-untouched-source-principle)
  - [Architecture Overview](#architecture-overview)
- [Features](#features)
  - [Image & Logo Watermarking](#image--logo-watermarking)
  - [Text & Typographic Watermarking](#text--typographic-watermarking)
  - [Combined Watermark Mode](#combined-watermark-mode)
  - [WooCommerce Integration](#woocommerce-integration)
  - [Bulk Processing & Background Queue](#bulk-processing--background-queue)
  - [System Health & Diagnostics](#system-health--diagnostics)
- [Documentation](#documentation)
  - [System Requirements](#system-requirements)
  - [Installation](#installation)
  - [Updating from WordPress Dashboard](#updating-from-wordpress-dashboard)
  - [Configuration Guide](#configuration-guide)
  - [Restoring Original Images](#restoring-original-images)
  - [Developer Hooks & Filters](#developer-hooks--filters)
- [Changelog](#changelog)
- [License](#license)
- [Author & Support](#author--support)

---

## About

**WPCalibrate Watermark Studio** was developed to solve a critical flaw present in many WordPress watermarking tools: destructive image overwriting. 

When watermarks are baked directly into master files, any rebranding, opacity adjustment, repositioning, or text correction permanently damages media assets. Reapplying watermarks on previously watermarked files causes unsightly double watermarks and progressive image degradation.

WPCalibrate Watermark Studio enforces strict **non-destructive architecture**:

### The Untouched Source Principle

1. **Pristine Master Backup**: Before applying any watermark for the first time, the original, full-resolution upload is stored in a protected storage directory (`wp-content/uploads/wpcalibrate-watermark-studio/originals/`). Direct web access is blocked via hardened server rules (`.htaccess` and `index.php` guards).
2. **Never Watermark Twice**: Reprocessing an image always starts from the clean, untouched source backup. Watermark stacking is impossible.
3. **Lossless Restoration**: With a single click in the Media Library or via bulk operations, any watermarked image can be cleanly reverted back to its pristine master file, re-generating all WordPress thumbnail sub-sizes automatically.

```
Upload Image
     │
     ├──► 1. Save untouched master to protected backup storage
     │
     └──► 2. Render watermark(s) to requested sub-sizes & master
              (Image logo, typography, or combined)
```

---

## Features

### Image & Logo Watermarking
- **Alpha Blending**: Supports transparent PNG, WebP, and JPEG watermark files with 0–100% smooth opacity blending.
- **Proportional Scaling**: Automatically scales the watermark relative to target image dimensions (1–100%).
- **Pixel Limit Constraints**: Set minimum and maximum watermark dimensions to prevent badges from rendering too small on mobile or overpowering banners.
- **9-Point Anchor Grid**: Position badges in any of 9 quadrants (Top-Left, Top-Center, Top-Right, Middle-Left, Center, Middle-Right, Bottom-Left, Bottom-Center, Bottom-Right).
- **Custom Pixel Offsets**: Fine-tune horizontal (X) and vertical (Y) padding.

### Text & Typographic Watermarking
- **Bundled Open Font**: Ships with Outfit (SIL Open Font License) for consistent, high-fidelity rendering across all hosting environments.
- **Dynamic Text Placeholders**: Supports dynamic tags such as `{site_name}`, `{date}`, `{year}`, `{filename}`, `{title}`, `{author}`, and `{alt}`.
- **Rich Typographic Controls**: Customize text string, font size, text color, and independent text opacity.
- **Readable Text Dropshadow**: Configurable drop shadow with customizable color, opacity, and X/Y offset to ensure high legibility against complex photo backgrounds.
- **Independent 9-Point Positioning**: Position text independently from logo watermarks.

### Combined Watermark Mode
- Apply both a logo graphic and a text copyright mark in the same pass.
- Position logo and text in different quadrants (e.g., logo at Top-Right, copyright text at Bottom-Left).
- Configurable compositing order ensures layered predictability.

### WooCommerce Integration
- **Targeted Store Protection**: Choose whether to watermark catalog images, featured product images, gallery attachments, or variations.
- **Exclusion Filters**: Exclude specific categories, product tags, or individual product IDs.
- **High-Performance Order Storage (HPOS)**: Declared 100% compatible with WooCommerce HPOS (`custom_order_tables`).

### Bulk Processing & Background Queue
- **Async Batching**: Process large libraries without browser timeouts or memory exhaustion.
- **Dual Engine**: Automatically utilizes Action Scheduler when available, with graceful fallback to WP-Cron.
- **Real-Time Progress UI**: Live percentage progress bar, batch counters, and status updates via secure WordPress REST API / AJAX endpoints.
- **Non-Destructive Re-apply**: Re-run bulk watermarking anytime settings change without cumulative degradation.

### System Health & Diagnostics
- **Engine Detection**: Real-time audit of GD Library, Imagick extensions, FreeType support, and memory limits.
- **Storage Permissions**: Validates read/write capabilities and security isolation on the original backups directory.
- **Queue Monitor**: Live status inspection of background cron jobs, Action Scheduler tasks, and lock tokens.
- **Audit Logging**: Comprehensive debug and activity logging with one-click log export and purge.

---

## Documentation

### System Requirements

| Component | Minimum Requirement | Recommended |
|---|---|---|
| **PHP** | 8.2 | 8.2 or 8.3 |
| **WordPress** | 6.8 | 6.8+ |
| **PHP Extensions** | GD or Imagick, FreeType, mbstring | Imagick + GD with FreeType |
| **WooCommerce** (Optional) | 8.0+ | Latest release (HPOS supported) |

---

### Installation

#### Method 1: WordPress Dashboard (ZIP Upload)
1. Download the latest release `wpcalibrate-watermark-studio-1.0.2.zip` from [Releases](https://github.com/zeeshanraza-official/wpcalibrate-watermark-studio/releases).
2. Log into your WordPress Admin panel.
3. Navigate to **Plugins > Add New > Upload Plugin**.
4. Choose the downloaded ZIP file and click **Install Now**.
5. Click **Activate Plugin**.

#### Method 2: Manual FTP / SFTP
1. Extract `wpcalibrate-watermark-studio-1.0.2.zip`.
2. Upload the `wpcalibrate-watermark-studio` folder to your server's `/wp-content/plugins/` directory.
3. In WordPress Admin, navigate to **Plugins** and click **Activate** under **WPCalibrate Watermark Studio**.

---

### Updating from WordPress Dashboard

To receive update notifications and perform single-click updates directly inside the WordPress dashboard:

1. Install and activate the free [Git Updater](https://git-updater.com) or [GitHub Updater](https://github.com/afragen/github-updater) plugin on your WordPress installation.
2. WPCalibrate Watermark Studio natively includes the required metadata headers:
   ```php
   * GitHub Plugin URI: zeeshanraza-official/wpcalibrate-watermark-studio
   * Primary Branch:    main
   ```
3. Whenever a new version is tagged and released on this GitHub repository, WordPress will display an update notification under **Dashboard > Updates** and **Plugins**, allowing you to update in one click without manual downloads.

---

### Configuration Guide

1. Navigate to **WPCalibrate > Watermark Studio** in your WordPress admin menu.
2. Select your desired **Watermark Mode**:
   - `Image / Logo`: Brand using an uploaded transparent PNG or WebP watermark file.
   - `Text Only`: Display a typographic copyright notice with custom colors and drop shadows.
   - `Combined`: Apply both image and text simultaneously.
3. Choose **Image Sizes**:
   - Select which registered thumbnail sizes should be watermarked (e.g., `large`, `medium_large`, `woocommerce_single`).
   - Leave `full` unchecked if you wish to keep master full-res files clean for download customers.
4. Configure **Automated Processing**:
   - Enable *Auto-watermark on upload* to automatically protect new uploads.
   - Specify post-type targeting or WooCommerce restrictions.
5. Save your settings. Use the built-in **Live Preview** canvas to test your settings on a sample canvas before saving.

---

### Restoring Original Images

#### Restoring a Single Image
1. Go to **Media > Library** in List View.
2. Hover over any watermarked image to reveal the **Restore Original** row action.
3. Alternatively, open the image in the Media Modal and click the **Restore Original Image** button in the sidebar.
4. The plugin replaces all watermarked files with the untouched backup and regenerates thumbnails.

#### Bulk Library Restoration
1. Navigate to **WPCalibrate > Watermark Studio > Bulk Processing**.
2. Select **Restore Originals** mode.
3. Click **Start Restoration**. The background queue safely restores your media assets in batches.

---

### Developer Hooks & Filters

Extend and customize watermarking behavior using WordPress actions and filters:

```php
// Bypass watermarking for specific uploads conditionally
add_filter( 'wpcalibrate_watermark_studio_should_watermark', function( bool $should_watermark, int $attachment_id ): bool {
    if ( get_post_meta( $attachment_id, '_skip_watermark', true ) ) {
        return false;
    }
    return $should_watermark;
}, 10, 2 );

// Modify watermark text dynamically at runtime
add_filter( 'wpcalibrate_watermark_studio_text_watermark_string', function( string $text, int $attachment_id ): string {
    $photographer = get_post_meta( $attachment_id, '_photographer_name', true );
    if ( $photographer ) {
        return "© " . date('Y') . " " . $photographer;
    }
    return $text;
}, 10, 2 );

// Adjust watermark opacity dynamically based on image context
add_filter( 'wpcalibrate_watermark_studio_watermark_opacity', function( int $opacity, int $attachment_id, string $size_name ): int {
    if ( 'thumbnail' === $size_name ) {
        return 40; // Lower opacity on small thumbnails
    }
    return $opacity;
}, 10, 3 );
```

---

## Changelog

### [1.0.2] - 2026-10-06
- **Added**: Full non-destructive restoration workflow to restore untouched original images without watermarks or metadata remnants.
- **Added**: Single-image restoration button in media modal and list table actions.
- **Added**: Bulk restoration background queue runner.
- **Added**: Intelephense diagnostics stubs and static analysis configurations.
- **Fixed**: Admin sidebar menu logo branding display and SVG icon rendering.
- **Fixed**: Combined watermark mode execution ensuring text and logo render cleanly without duplication or displacement.
- **Fixed**: Settings values persistence and validation across all configuration panels.
- **Fixed**: Proper directory separation during remote deployment to prevent duplicate versioned folders.
- **Updated**: GitHub integration headers (`GitHub Plugin URI`) for WordPress dashboard auto-updates.

### [1.0.1] - 2026-10-06
- **Added**: Automated upload filtering for WooCommerce product galleries and variable attachments.
- **Added**: Action Scheduler queue adapter with automatic fallback to WP-Cron.
- **Fixed**: Imagick resource cleanup and alpha mask channel preservation on transparent PNGs.
- **Fixed**: Text placeholder variable expansion for UTF-8 author and title tags.

### [1.0.0] - 2026-10-05
- **Initial Release**: Core non-destructive original storage architecture.
- **Feature**: GD and Imagick rendering engines with automatic capability detection.
- **Feature**: 9-point anchor grid and proportional scale constraints.
- **Feature**: Bundled Outfit typography with SIL Open Font License.
- **Feature**: System health diagnostics and log export tool.

---

## License

This software is released under the **GNU General Public License v2.0 or later** (GPL-2.0-or-later).  
See the [LICENSE](LICENSE) file for complete details.

---

## Author & Support

- **Author**: [WPCalibrate](https://wpcalibrate.com)
- **Repository**: [zeeshanraza-official/wpcalibrate-watermark-studio](https://github.com/zeeshanraza-official/wpcalibrate-watermark-studio)
- **Issues & Contributions**: Please report issues and submit pull requests through the GitHub Issues tracker.
