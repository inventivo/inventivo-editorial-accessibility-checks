=== inventivo Editorial Accessibility Checks ===
Contributors: inventivode
Tags: accessibility, a11y, wcag, content, editor
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Finds five common editorial accessibility issues in posts and pages. Lists findings in your admin. Reports only, changes nothing.

== Description ==

inventivo Editorial Accessibility Checks reads the saved content of published posts and pages and lists findings under Tools > Accessibility Checks.

The five checks look for:

* Images without an alt attribute or with whitespace-only alternative text. An empty alt attribute is allowed for decorative images.
* Empty or unclear link text.
* Skipped heading levels within the content. The first heading is not flagged.
* Form fields without a label.
* Tables without a header cell. Tables marked as presentation or none are excluded.

The checks are based on the WCAG 2.2 success criteria. The plugin reports what it finds. It does not change your content and it does not change your website.

Automated checks can only detect a part of the accessibility issues on a page. Testing by a person, including with a screen reader and by keyboard, remains necessary.

Results are stored in your own database. Nothing is sent to us or to any third party.

= What it does not do =

No overlay. No widget on your site. No automatic fixes. This plugin only reads and reports.

No external interface. No data collection. No contrast checks, rendered-page analysis, or checks of content added by themes, shortcodes or scripts.

A background task checks 20 posts per batch, every five minutes when WordPress scheduled tasks run. Saving an eligible post checks it again. Scan now processes the next batch immediately, not the entire site at once. Background tasks depend on site visits or a configured system scheduler.

The list supports title search, content-type and rule filters, and sorting by finding count or last checked date. Open Show findings to see excerpts. Up to 50 excerpts are stored per post; all findings are counted.

If no findings were detected, the result reads:
No issues found by these 5 checks. This does not mean the page is accessible.

Before the first scan, the page reads:
No pages have been checked yet. The first scan runs automatically, or you can start it now.

= Legal note =

This plugin does not determine, certify or guarantee conformance with WCAG, the European Accessibility Act, the German BFSG, the ADA or any other standard or law. Whether your website meets legal requirements is a question only you and your advisers can answer.

Automated checks can only detect a part of the accessibility issues on a page. Testing by a person, including with a screen reader and by keyboard, remains necessary.

== Installation ==

1. Upload the inventivo-editorial-accessibility-checks folder to /wp-content/plugins/.
2. Activate inventivo Editorial Accessibility Checks in Plugins.
3. Open Tools > Accessibility Checks. The PHP DOM extension must be available.
4. Wait for the scheduled batch or use Scan now.

== Frequently Asked Questions ==

= Does this plugin make my website accessible? =

No. It finds five common issues in editorial content and lists them. You must resolve them yourself. Many aspects of accessibility are outside these checks and require testing by a person.

= Does it change my content? =

No. It only stores the findings and scan timestamps as post metadata in your own database.

= Who can use the results page? =

Users with the edit_others_posts capability, normally editors and administrators. Editor links additionally respect the permission to edit each post.

= Does it read custom content types? =

By default it reads published posts and pages. Developers can extend the public content types through editorial_accessibility_checks_post_types. The other extension filters are editorial_accessibility_checks_rules, editorial_accessibility_checks_unclear_link_texts, editorial_accessibility_checks_findings and editorial_accessibility_checks_batch_size.

= What happens when I remove the plugin? =

Deactivation removes the scheduled task and keeps results. Deletion removes the scheduled task, all result metadata and the batch-position options.

== Changelog ==

= 1.0.0 =
* Initial release with five editorial checks, scheduled batches and a results list.
