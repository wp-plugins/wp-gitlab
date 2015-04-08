=== WP Gitlab ===
Contributors: cfarence
Based on the: WP Gitlab
WP Gitlab Link: https://Gitlab.com/cfarence/wp-Gitlab
Donate link: http://www.charlessite90.com
Tags: gitlab, profile, repositories, commits, issues, widget, shortcode
Requires at least: 3.0.1
Tested up to: 4.1.1
Stable tag: 1.0
License: MIT License
License URI: http://opensource.org/licenses/MIT

Display users Gitlab public profile, repositories, commits, and issues.

== Notes ==
1. Your webserver must have the curl php module installed
2. Some themes break the profile widget. If you know how to fix this issue your help would be greatly appreciated.

== Description ==

WP Gitlab provides four sidebar widgets which can be configured to display public profile, repositories, commits, and issues from Gitlab in the sidebar. You can have as many widgets as you want configured to display different repositories.
This plugin is based on the plugin WP Github by seinoxygen.

Currently the plugin can list:

*   Profile
*   Repositories
*   Commits
*   Issues


### Using CSS

You can apply a customized style to the plugin simply uploading a file called `custom.css` in the plugin folder. It will allow you to upgrade the plugin without losing your custom style.

### Caching

The plugin caches all the data retrieved from Gitlab every 10 minutes to avoid exceeding the limit of api calls.

You can clear the cache from the plugin settings page located in the Wordpress settings menu.

### Support

If you have found a bug/issue or have a feature request please report here: https://wordpress.org/support/plugin/wp-gitlab

== Installation ==

1. Upload `wp-Gitlab` directory to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Add your gitlab url and api key to the settings
4. Add the widget through the 'Widgets' menu in WordPress or add the desired shortcode in your posts and pages.

== Frequently Asked Questions ==

= Which shortcodes are available? =

You can use the following codes to display profile, repositories, commits, and issues:

Embed profile:
`[Gitlab-profile username="cfarence"]`
List last 10 repositories:
`[Gitlab-repos username="cfarence" limit="10"]`
List last 10 commits from all repositories:
`[Gitlab-commits username="cfarence" limit="10"]`
List last 10 commits from a specific repository:
`[Gitlab-commits username="cfarence" repository="wp-Gitlab" limit="10"]`
List last 10 issues from all repositories:
`[Gitlab-issues username="cfarence" limit="10"]`
List last 10 issues from a specific repository:
`[Gitlab-issues username="cfarence" repository="wp-Gitlab" limit="10"]`


== Screenshots ==

1. Setting up the widget.
2. Repositories widget in action!
3. Repositories embedded in a page.
4. Profile shortcode.
5. Profile widget.

== Changelog ==

= 1.0 =
* First release