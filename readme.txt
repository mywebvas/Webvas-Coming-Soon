=== Webvas Coming Soon Ultra-Tiny ===
Contributors: webvas
Tags: coming soon, maintenance mode, waitlist, lead capture, launch page, lightweight
Requires at least: 5.0
Tested up to: 6.9.4
Requires PHP: 8.0
Stable tag: 2.4.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

High-converting coming soon and maintenance mode with waitlist capture, private preview links, honest visitor counts, CSV export, and zero builder bloat.

== Description ==

Webvas Coming Soon Ultra-Tiny is built for clean launches, low-friction waitlist capture, and predictable behavior on shared hosting and modern server stacks.

Features:

* Lightweight coming soon page for visitors while approved admins keep full access.
* Coming Soon and Maintenance response modes with cache-aware headers.
* Secure waitlist submission flow with nonce validation, honeypot protection, duplicate blocking, and rate limiting.
* Lead attribution capture for landing URL, referrer host, and UTM parameters.
* Secure private preview link plus public page allowlist for controlled access.
* Streamed CSV export with optional date and source filters for low-memory environments.
* Honest unique-visitor counting by browser or device without heavy analytics scripts.
* Small built-in admin activity log plus one-click contact-list clearing after launch.
* Editable brand color, frontend heading, description, button text, reassurance microcopy, and optional social proof with safe defaults for plug-and-play use.
* Launch readiness signal based on waitlist size, visitor volume, and signup rate.
* Mobile-first frontend with minimal payload and graceful non-JavaScript fallback.

This plugin is intentionally small, explicit, and dependency-light so it stays maintainable and resilient.

Built by Webvas. Developer: Michael Madojutimi.

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/`, or install it through the WordPress plugins screen.
2. Activate the plugin through the `Plugins` screen in WordPress.
3. Go to `Settings > Webvas Coming Soon`.
4. Turn the coming soon page on and choose either `Coming Soon` or `Maintenance` mode.
5. Export waitlist entries from the same settings page when needed.

== Frequently Asked Questions ==

= Who can manage the plugin and still see the normal website? =

By default, only users who can activate plugins can open the settings page, export the list, turn the page on or off, and keep normal site access while visitors see the coming soon page.

= Does it work without JavaScript? =

Yes. The waitlist form uses an enhanced async flow when available, with a normal WordPress form-post fallback.

= Will duplicate emails be stored? =

No. Duplicate emails are blocked at the database level.

= How are unique visitors counted? =

The plugin counts one unique visitor per browser or device using a first-party cookie stored by your own site. It is meant to be an honest launch metric, not a full analytics suite.

= Can I clear the saved contact list later? =

Yes. Site owners can empty the saved contact list from the settings page after launch. The action is logged and asks for confirmation first.

= Can I change the default frontend text? =

Yes. The plugin keeps sensible default copy, but advanced users can change the heading, description, button text, short reassurance text, brand color, and optional social proof text from the settings page without editing code. The description also supports a small safe set of inline HTML tags.

= Does the plugin help me decide when to launch? =

Yes. The admin area includes a lightweight launch-readiness signal that looks at visitor volume, waitlist size, and signup rate to show whether you are still early, building momentum, or ready to launch.

= What happens on uninstall? =

The plugin removes its settings, custom frontend copy, admin audit log, temporary rate-limit data, waitlist table, and unique-visitor table on uninstall.

== Screenshots ==

1. Visitor-facing coming soon page with waitlist form.
2. Admin settings page with maintenance toggle and CSV export.
3. Dashboard widget with quick status and waitlist entry count.

== Changelog ==

= 2.4.2 =

* Removed a UTF-8 byte-order mark from the main plugin file so header output stays fully safe on real WordPress installs.
* Published the same release snapshot as a clean Git-ready and WordPress-safe hotfix tag.

= 2.4.1 =

* Made the launch-readiness math more transparent in the admin area by showing the exact signup-rate formula and a plain-language calculation summary.
* Added live-preview guidance for social-proof text, including placeholder support for {count}, {visitors}, and {rate}.
* Kept the social-proof flow clearer for both novice and advanced users without adding heavier UI.

= 2.4.0 =

* Added a launch-readiness signal that turns visitor count, waitlist size, and signup rate into a simple launch-stage indicator.
* Added opt-in social proof with automatic demand-based copy and a manual override option.
* Added safe brand-color customization and timestamped CSV filenames with filter context for cleaner export handoff.

= 2.3.12 =

* Added a validated brand-color setting so site owners can personalize the launch page without editing code.
* Added timestamped CSV filenames with optional filter context for cleaner exports and launch-team handoff.
* Kept the color system single-input and automatically generated the stronger accent shade to avoid UI bloat.

= 2.3.11 =

* Removed unnecessary link-target attributes from the frontend description HTML allowlist so launch-page rich text stays tighter and safer.
* Kept the final release package aligned after the admin-compatibility hardening work.
* Preserved the lightweight popup/new-tab guide flow and the conversion-copy improvements.

= 2.3.10 =

* Replaced the embedded admin guide viewer with a safer popup-window flow on large screens and a new-tab fallback elsewhere for broader server and admin-plugin compatibility.
* Kept the guide link lightweight and same-origin without iframe embedding or admin-wide overlay behavior.
* Preserved the conversion-copy improvements and 155-character plain-text SEO meta description cap.

= 2.3.9 =

* Reworked the in-admin Launch Guide into a safer in-page panel flow to reduce the chance of conflicts with modern plugin admin apps.
* Changed the default form button to a stronger conversion-focused label and added editable reassurance microcopy under the button.
* Tightened SEO meta description output so it stays within a 155-character plain-text limit.

= 2.3.8 =

* Made the in-admin Launch Guide panel responsive and in-page so it stays clean on mobile and does not cover the wider WordPress admin screen.
* Added customizable frontend button text for site owners who want the call-to-action to match their launch copy.
* Allowed a small, safe inline-HTML subset in the frontend description field while keeping SEO metadata plain-text and sanitized.

= 2.3.7 =

* Added a lightweight in-admin Launch Guide link that opens a clean, closable handbook panel with a graceful new-tab fallback.
* Added a static plugin guide document with setup, workflow, scaling notes, and launch best practices.
* Kept the docs experience same-origin, dependency-light, and self-contained for predictable admin performance.

= 2.3.6 =

* Added a proper frontend meta description and a stronger title built from the editable launch heading plus site name.
* Removed redundant HTML cache-control meta tags while keeping the authoritative HTTP no-cache and noindex headers.
* Cleared a leftover unused visit-count option so the release package stays lean and internally consistent.

= 2.3.5 =

* Added a lightweight admin help footer with support, feature request, and developer links.
* Simplified the plugin-row links in WordPress admin so each one has a clear, non-duplicate purpose.
* Kept branding intentionally subtle and text-only so the admin area stays clean and trustworthy.

= 2.3.4 =

* Added polished plugin metadata for WordPress admin, including plugin page and developer links.
* Refined the plugin description and discovery tags for a clearer first public release presentation.
* Tightened first-release packaging details without changing the lightweight runtime architecture.

= 2.3.3 =

* Improved visitor de-duplication by merging same-device visits more conservatively when browser cookies change or disappear.
* Added a separate danger-zone action to clear saved visitor counts.
* Added admin controls for editing the frontend heading and description while keeping strong plug-and-play defaults.
* Signed the frontend tracking context so hidden attribution fields are no longer blindly trusted if someone tampers with them.

= 2.3.2 =

* Fixed early WordPress bootstrap compatibility by avoiding hard dependency on pluggable salt functions before they are loaded.
* Hardened visitor counting so the same device does not keep inflating totals when cookies are slow or unavailable.
* Added optional CSV export filters for date range and source.
* Added a lightweight admin activity log and a confirmed one-click clear action for saved contacts.

= 2.3.1 =

* Fixed private preview access so the current request can still open the real site even if a redirect or cookie write is blocked.
* Prevented public allowlisted paths from bypassing the coming soon page when the requested page does not exist.
* Replaced inflated session-style counts with unique visitor counting per browser or device.
* Simplified admin wording for non-technical site owners and tightened access to trusted plugin managers.

= 2.3.0 =

* Added Coming Soon and Maintenance response modes.
* Added landing URL, referrer host, and UTM attribution capture to waitlist exports.
* Added secure bypass URL support and allowlist path controls.
* Added visitor counts to the admin dashboard and settings page.

= 2.2.2 =

* Hardened response headers for safer public release behavior.
* Narrowed cache-disabling signals to true frontend coming-soon request contexts.
* Preserved the current page URL for non-JavaScript form fallback redirects.
* Added uninstall cleanup, release packaging files, and CI syntax checks.

= 2.2.1 =

* Fixed mobile overflow and submit-state UI toggling.
* Removed unnecessary visitor-facing microcopy after form submission.
* Improved no-cache behavior and export availability handling.

== Upgrade Notice ==

= 2.4.2 =

Recommended as the clean first public tag. Removes a UTF-8 BOM from the main plugin file so header behavior stays predictable.

= 2.4.0 =

Recommended for public launch. Adds decision support and optional social proof without introducing analytics bloat or new storage layers.

= 2.3.12 =

Recommended for Git push. Adds safe visual customization and better export naming without increasing operational complexity.

= 2.3.11 =

Recommended for final Git push and public release. Tightens the last small HTML surface without changing the lightweight architecture.

= 2.3.10 =

Recommended before final ship. Removes the riskiest admin-compatibility surface while keeping the built-in guide accessible.

= 2.3.9 =

Recommended before shipping. Improves admin compatibility, conversion copy, and final metadata control without adding bloat.

= 2.3.8 =

Recommended before Git push. Improves admin guide usability and launch-copy flexibility without adding heavy UI or unsafe markup handling.

= 2.3.7 =

Recommended for first public release. Adds a clean in-admin Launch Guide without adding heavy dependencies or admin clutter.

= 2.3.6 =

Recommended for first public release. Polishes frontend metadata and trims old release residue without changing the lightweight architecture.

= 2.3.5 =

Recommended for public launch. Adds support and product-signature polish without affecting runtime performance.

= 2.3.4 =

Recommended for first public release. Improves presentation and plugin-list clarity without adding runtime bloat.

= 2.3.3 =

Recommended for launch-focused sites that want steadier visitor numbers, editable frontend copy, and better attribution integrity without heavy dependencies.

= 2.3.2 =

Recommended for launch-focused sites that want safer preview access, steadier visitor numbers, and cleaner launch operations without plugin bloat.

= 2.3.1 =

Recommended for launch-focused sites that want more honest visitor numbers, safer preview access, and clearer admin controls.

= 2.3.0 =

Recommended for launch-focused sites that need lean pre-launch lead capture plus operational control without builder bloat.

= 2.2.2 =

Recommended for public release use. Improves release safety, fallback behavior, and packaging quality.
