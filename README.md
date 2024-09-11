Note that Pantheon's recent changes to [search-replace functionality](https://docs.pantheon.io/guides/multisite/search-replace/) for multisites should make this script unnecessary. If possible, use the platform functionality instead. You'll have a better time. This is intended as a backup if that method fails.

# Quicksilver Script - WPMU Database Clone

Pantheon Quicksilver script designed to be run when a database is cloned from live to a lower environment.

The script will update the `wp_blogs` and `wp_site` table to match the new environment's base url.

It will also update the respective `wp_options` table for each subsite. Note a full search and replace is not done - this can hang on large networks (200+). Those can be run on a per-site basis via terminus.


## Installation

1. Copy the code to your site repo at `/private/scripts`
2. Run `composer install` inside the script folder to install its dependencies.
3. Update your `pantheon.yml` file to include a snippet like below. This will enable the quicksilver hook.

    ```yml
    workflows:
        clone_database:
            after:
            - type: webphp
                description: Convert blog urls
                script: private/scripts/quicksilver-wpms-sr/multisite.php

    ```

4. Edit the `$domains` array in `multisite.php` to match your site's configuration.
