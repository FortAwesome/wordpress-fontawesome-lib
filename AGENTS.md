This is a library of functionality for using Font Awesome in WordPress.
It's not a plugin, but rather a collection of functions that can be used in themes or plugins.

## Development Environment

`wp-env` is the tool used for the development environment. It runs all code within Docker containers.

More information about `wp-env` can be found in the [WordPress Developer Handbook](https://developer.wordpress.org/block-editor/reference-guides/packages/packages-env).

`wp-env` is configured using the `.wp-env.json` file in the root of the repository.

`wp-env` should be available globally on the system and in the PATH.

If not, try to run it like this `npx wp-env`.

If not, prompt the user to install it globally using `npm install -g @wordpress/env`.

There are convenience scripts under `./bin` for common tasks like starting the environment or running tests.

### Running the development environment

```bash
bin/start
```

The output of the command will include the URL to access the WordPress site. However, since this is a library and not a plugin or theme, there won't be any visible changes on the site. The environment is primarily for running tests and developing the library.

### Running tests

```bash
bin/phpunit
```
