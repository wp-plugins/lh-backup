=== LH Backup ===
Contributors: shawfactor
Donate link: http://lhero.org/plugins/lh-backup/
Tags: backup,csv,mysql,email,ftp,file,export,wpdb
Requires at least: 3.0.
Tested up to: 4.2
Stable tag: trunk
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html


Have WordPress automatically create email backups of tables on your website and send them to an email address.

== Description ==

This plugin allows you to run sql select statements and csv files which are then emailed out to a configurable list of email addresses. This is very useful for backing up your wp database or alternatively generating daily reporting from your website.

Check out [our documentation][docs] for more information. 

[docs]: http://lhero.org/plugins/lh-backup/


Features:

* To be added


== Installation ==

1. Upload the entire `lh-backup` folder to the `/wp-content/plugins/` directory.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Create a new page (can be set too private) and remember its post id. All generated files will be attached to this page.
4. Navigate to Settings->LH Backup to set the email recipients and add the page id,recipient emails, and sql statements (omit select from the statement)


== Changelog ==

**1.0 July 13, 2015**  
Initial release.
