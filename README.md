# Equiviq for WordPress

This repository contains only the installable WordPress site plugin. The Equiviq server-side plugin is maintained separately.

## Install

Download the `equiviq-wordpress-<version>.zip` asset from the [latest release](https://github.com/imseanbrown/wordpress-equiviq/releases/latest). In WordPress, use **Plugins → Add New → Upload Plugin**. Configure the Equiviq API URL and key under **Equiviq → Connection settings**. See [the plugin guide](equiviq-wordpress/README.txt) for setup details.

## Updates

Version 0.2.2 introduces GitHub release detection through WordPress's `Update URI` mechanism. Install this version once. Later releases appear in WordPress's standard **Plugins** and **Updates** screens. Site administrators can enable the normal **Enable auto-updates** control for this plugin; this repository does not force automatic installation.

The updater accepts only a release asset named `equiviq-wordpress-<version>.zip` from this repository. Source archives generated automatically by GitHub are not installable plugin ZIPs.

To release a new version, update both version strings in `equiviq-wordpress/equiviq-wordpress.php`, push the change, then tag that commit `v<version>` and push the tag. The release workflow validates the version and attaches the installable ZIP.
