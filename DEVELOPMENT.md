# Development Environment

## wp-env must be globally installed first

```
npm -g i @wordpress/env
```

## Start the development environment

```bash
wp-env start
```

convenience script:

```bash
bin/start
```

## Tail the debug.log

```bash
wp-env run wordpress -- tail -f wp-content/debug.log
```

with the timestamps and starting with the last few lines:

```bash
wp-env run wordpress -- tail -n 50 -f wp-content/debug.log
```

## Configure wp-env

Edit `.wp-env.json` to add additional plugins, themes, or mu-plugins. See the [wp-env documentation](https://developer.wordpress.org/block-editor/reference-guides/packages/packages-env/#configuration) for more details.

## Reload docker configs and restart containers (without fully destroying)

```bash
wp-env start --update
```
