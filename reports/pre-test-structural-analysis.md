# WRPA 2.0 Structural Analysis — Pre-Test Baseline

_Phase IV Final Fixes verification run_

## Summary Table

| Module | Purpose | Init Trigger | Key Hooks | Dependencies | Notes |
| --- | --- | --- | --- | --- | --- |
| `WRPA_Core` | Bootstraps constants, loads modules, wires plugin lifecycle. | `plugins_loaded` (main plugin file). | `plugins_loaded`, `admin_init`, `template_redirect` actions. | Requires all WRPA module classes; invokes `WRPA_Email_Log` on activation. | Drives the Core → Access → Email → Cron → Verify chain directly.
| `WRPA_Access` | Manages content restriction meta, gating, membership lifecycle, and logging. | `WRPA_Core::init()` | Hooks `init`, `add_meta_boxes`, `save_post`, `template_redirect`, WooCommerce order/payment actions. | WordPress post meta APIs, WooCommerce, `WRPA_Email` (log proxy). | Emits `wrpa_access_first_granted` for cron welcome flow and exposes centralized logger.
| `WRPA_Email` | Provides templated email delivery, placeholder substitution, verification mailer, and logging bridge. | `WRPA_Core::init()` | Filters `wp_mail_content_type`, `wp_mail_from`, `wp_mail_from_name`; fires `wrpa_mail_*` and `wrpa_email_*` actions. | `WRPA_Email_Verify`, `WRPA_Email_Log`, `WRPA_Access`, WordPress mail APIs. | Handles placeholder defaults and fallback plain-text verification.
| `WRPA_Email_Verify` | Issues and validates verification tokens, updates verified flag, and redirects users. | `WRPA_Core::init()` | Hooks `init` for request handling; triggers `wrpa_email_verified`. | WordPress user/meta APIs, `WRPA_Core` URLs helper. | Defines verification constants and shares verify URLs with email placeholders.
| `WRPA_Email_Cron` | Schedules and executes recurring/batch email jobs. | `WRPA_Core::init()` | Hooks `init`, custom `wrpa_email_daily`, `user_register`, `wrpa_access_first_granted`, WooCommerce completed orders. | `WRPA_Email`, `WRPA_Email_Verify`, WordPress Cron/WP_DateTime, WooCommerce. | Schedules 03:10 runs, sequences campaigns, welcome, and verification jobs.
| `WRPA_Email_Admin` | Adds the Email Control Center UI in wp-admin. | `WRPA_Core::init()` | Hooks `admin_menu`, `admin_enqueue_scripts`. | `WRPA_Admin` navigation helpers, WordPress admin UI APIs. | Provides template listings, previews, and inline CodeMirror setup.
| `WRPA_Admin` | Registers the top-level WRPA dashboard and placeholder screens. | `WRPA_Core::init_hooks()` via `plugins_loaded`. | Hooks `admin_menu`. | WordPress admin menu APIs. | Uses full i18n coverage for placeholder content.
| `WRPA_Email_Log` | Creates and interacts with the email log table. | Activation via `WRPA_Core::activate()`; log writes gated by filter. | Fires `wrpa_email_log_ready`; respects `wrpa_enable_email_logging`. | WordPress DB APIs. | Logging integration remains opt-in pending Phase V enablement.

## Flagged Issue Retests

- **Internationalization (i18n):** Admin UI strings, email subjects, and campaign defaults are wrapped in translation helpers, confirming the earlier gaps are closed (e.g., `WRPA_Admin::register_menu()` and member placeholders, `WRPA_Email::subject_for()`, and `WRPA_Email_Cron::get_campaign_payload()`).
- **Email placeholders:** `WRPA_Email::replace_placeholders()` merges site defaults, core URL helpers, and runtime data before substitution, ensuring every `{token}` resolves cleanly, with verification URLs sourced from `WRPA_Email_Verify::get_verify_url()` when possible.
- **Verification constant:** `WRPA_Email_Verify` defines `META_FLAG` alongside token keys and `WRPA_Email::send_verification()` respects the flag to avoid duplicate messages.
- **Cron stub completion:** `WRPA_Email_Cron::init()` now schedules the `wrpa_email_daily` event, calculates the next 03:10 run, and dispatches tiered job batches, replacing the former stub.
- **Log integration:** `WRPA_Email::log()` routes through `WRPA_Access::log()` for consolidated persistence, while `WRPA_Access::log()` writes to uploads (with graceful fallbacks) and `WRPA_Email_Log::log()` persists database records when the `wrpa_enable_email_logging` filter allows it.

## Core → Access → Email → Cron → Verify Chain Integrity

1. The plugin bootstrap (`wisdom-rain-premium-access.php`) loads `WRPA_Core` and attaches `WRPA_Core::init()` to `plugins_loaded`.
2. `WRPA_Core::init()` defines constants, includes every module, calls `WRPA_Access::init()`, `WRPA_Email::init()`, `WRPA_Email_Verify::init()`, `WRPA_Email_Admin::init()`, and `WRPA_Email_Cron::init()`, then registers shared hooks.
3. `WRPA_Access::init()` wires restriction meta, gating, and WooCommerce grant listeners; its `wrpa_access_first_granted` action feeds the cron welcome flow.
4. `WRPA_Email::init()` normalizes outbound mail headers and exposes the templated `send_email()` API used by cron jobs, access events, and verification.
5. `WRPA_Email_Cron::init()` schedules the 03:10 daily dispatcher, reacts to registration/access events, and calls `WRPA_Email::send_email()` (plus optional `send_verification()`) to fulfill campaigns and transactional blasts.
6. `WRPA_Email_Verify::init()` watches for `?wrpa-verify=1` requests, validates tokens, sets the `META_FLAG`, and signals `wrpa_email_verified` consumers before redirecting via `WRPA_Core::urls()` data.

The direct method calls and hook hand-offs confirm the intended chain remains intact end-to-end.

## Dependency Map (Module → Collaborators)

- `WRPA_Core` → `WRPA_Access`, `WRPA_Email`, `WRPA_Email_Verify`, `WRPA_Email_Admin`, `WRPA_Email_Cron`, `WRPA_Admin`, `WRPA_Email_Log`.
- `WRPA_Access` → WordPress post/user meta APIs, WooCommerce order APIs, `WRPA_Email` (logging proxy), WordPress logging utilities.
- `WRPA_Email` → WordPress mail APIs, `WRPA_Email_Verify`, `WRPA_Access` (context + logging), `WRPA_Email_Log`, WordPress time/date helpers.
- `WRPA_Email_Verify` → WordPress user/meta APIs, `WRPA_Core::urls()` helper.
- `WRPA_Email_Cron` → WordPress Cron/timezone APIs, `WRPA_Email`, `WRPA_Email_Verify`, WooCommerce, WP_User helpers.
- `WRPA_Email_Admin` → WordPress admin menu/assets APIs, `WRPA_Admin` tab renderer.
- `WRPA_Admin` → WordPress admin UI APIs.
- `WRPA_Email_Log` → `$wpdb` / database schema APIs.

## Hook Map Highlights

- **Actions registered:**
  - `WRPA_Core` → `plugins_loaded`, `admin_init`, `template_redirect`.
  - `WRPA_Access` → `init`, `add_meta_boxes`, `save_post`, `template_redirect`, `woocommerce_order_status_completed`, `woocommerce_payment_complete`.
  - `WRPA_Email` → filters for `wp_mail_content_type`, `wp_mail_from`, `wp_mail_from_name`.
  - `WRPA_Email_Cron` → `init`, `wrpa_email_daily`, `user_register`, `wrpa_access_first_granted`, `woocommerce_order_status_completed`.
  - `WRPA_Email_Admin` → `admin_menu` (twice), `admin_enqueue_scripts`.
  - `WRPA_Admin` → `admin_menu`.
- **Actions emitted:**
  - `WRPA_Core` → `wrpa/admin_init`, `wrpa/frontend_init`, `wrpa/activate`, `wrpa/deactivate`.
  - `WRPA_Access` → `wrpa_access_first_granted`.
  - `WRPA_Email` → `wrpa_mail_sent`, `wrpa_mail_failed`, `wrpa_email_sent`, `wrpa_email_failed` plus verification log events.
  - `WRPA_Email_Cron` → `wrpa/email_daily`, `wrpa_email_job_executed`.
  - `WRPA_Email_Log` → `wrpa_email_log_ready`.

These mappings replace the previous Phase IV draft diagrams and will serve as the Pre-Test baseline reference.
