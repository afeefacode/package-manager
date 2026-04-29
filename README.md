# afeefa/package-manager

CLI tool for two recurring tasks in Afeefa packages:

- **`setup`** — run install actions defined by packages (e.g. copy config templates into a project).
- **`release`** — bump the version, sync `composer.json` / `package.json`, commit, tag and push (optionally to split repos).

The binary is `vendor/bin/afeefa-package` and is always run from the **project root** (the directory that contains `vendor/` and `.afeefa/`).

## Example project: from zero to release-ready

### 1. Create a `composer.json` with a `name`

```json
{
    "name": "acme/my-package",
    "version": "0.1.0",
    "type": "library"
}
```

`name` is required for both `setup` and `release`. `version` is required for any package listed in `release/packages.php` (step 4) — easier to set it now than later.

### 2. Install the package manager

```bash
composer require --dev afeefa/package-manager
```

### 3. Set up the package manager itself

```bash
vendor/bin/afeefa-package setup afeefa/package-manager
```

Creates `.afeefa/package/.installed`, which `release` checks for.

### 4. Add the release configuration

`.afeefa/package/release/version.txt`:

```text
0.1.0
```

`.afeefa/package/release/packages.php`:

```php
<?php
use Afeefa\Component\Package\Package\Package;

return [
    Package::composer()->path(getcwd()),
];
```

See [more `packages.php` examples](#more-packagesphp-examples) below for npm packages, combined composer+npm, and split repos.

### 5. Initialise git

```bash
git init
git add .
git commit -m "initial commit"
git remote add origin <url>
git push -u origin main
```

`release` requires a clean working copy and an upstream branch — it runs `git push` and `git push origin <tag>` for you.

### 6. Release

```bash
vendor/bin/afeefa-package release
```

You are prompted for the next version (Major / Minor / Patch / custom), shown a diff, and asked to confirm. After confirmation the tool bumps `version.txt` and the `version` field in every release package, commits, tags `v<version>` and pushes — including any split repos.

After the first release, only step 6 is needed each time.

## More `packages.php` examples

### Composer + npm in the same directory

A package that is published both to Packagist and npm from the same source tree:

```php
return [
    Package::composer()->path(getcwd()),
    Package::npm()->path(getcwd()),
];
```

### npm only

```php
return [
    Package::npm()->path(getcwd()),
];
```

### Split repos (mono-repo with separate published packages)

The source lives in one repo with sub-folders; each sub-folder is published to its own public repo. `release` keeps the split clones under `.afeefa/package/release/split-packages/<vendor>/<name>` and rsyncs the source into them on every release.

```php
use Afeefa\Component\Package\Package\Package;
use Symfony\Component\Filesystem\Path;

return [
    Package::composer()
        ->path(Path::join(getcwd(), 'server'))
        ->split('git@github.com:acme/my-package-server.git'),

    Package::npm()
        ->path(Path::join(getcwd(), 'client'))
        ->split('git@github.com:acme/my-package-client.git'),
];
```

### Aggregator over sibling directories

One project releases several sibling packages with a shared version:

```php
return [
    Package::composer()->path(Path::join(getcwd(), '..', 'lib-a')),
    Package::composer()->path(Path::join(getcwd(), '..', 'lib-b')),
    Package::composer()->path(Path::join(getcwd(), '..', 'lib-c')),
];
```

## How `setup` works

`setup` scans for packages that ship an install script and runs it. A package is "setupable" if it contains:

```text
.afeefa/package/install/install.php
```

That file must `return` the fully-qualified class name of an `Install` action (a subclass of `Afeefa\Component\Package\Actions\Install`).

`setup` looks in two places:

1. **The current project itself** — `<cwd>/.afeefa/package/install/install.php`
2. **Each composer dependency** — `<cwd>/vendor/<vendor>/<package>/.afeefa/package/install/install.php`

Pick a discovered package, or `all`:

```bash
vendor/bin/afeefa-package setup
vendor/bin/afeefa-package setup afeefa/package-manager
vendor/bin/afeefa-package setup all
```

Each install action writes a `.installed` marker into its config folder under `.afeefa/`. Re-running `setup` is a no-op once that marker exists; pass `--reset` to force a re-install:

```bash
vendor/bin/afeefa-package setup afeefa/package-manager --reset
```

### Writing an install action

`install.php` returns the action class:

```php
<?php
return \Acme\MyPackage\Install::class;
```

The action subclasses `Afeefa\Component\Package\Actions\Install`, sets `$configFolderName`, and overrides `install()`:

```php
class Install extends \Afeefa\Component\Package\Actions\Install
{
    protected $configFolderName = 'my-package';

    protected function install(): void
    {
        $this->createFiles([
            // File objects pointing at templates inside your package
        ]);
    }
}
```

The base class handles `--reset`, the `.installed` marker and CLI output. `createFiles()` copies templates from your package into `.afeefa/<configFolderName>/` of the project.

### "No packages to configure found"

Either the current directory has no `vendor/`, or none of the dependencies (and not the root package itself) ship an `.afeefa/package/install/install.php`.

## How `release` works

Run from the project root:

```bash
vendor/bin/afeefa-package release
```

The command:

1. Verifies that `setup afeefa/package-manager` has been run (`.installed` marker exists).
2. Verifies `composer.json` / `package.json` are present and contain `name` (and `version` for release packages). Offers to add missing fields interactively.
3. Aborts if any working copy is dirty.
4. For split packages: ensures a clone exists under `.afeefa/package/release/split-packages/<vendor>/<name>`.
5. Asks for the next version (Major / Minor / Patch / custom).
6. Updates `version.txt` and the `"version"` field in every release package's `composer.json` / `package.json`.
7. Shows a `git diff` for each package and asks for confirmation.
8. For split packages: rsyncs the source into the split clone (excluding `.git`, `vendor`, `node_modules`).
9. Commits, pushes, tags `v<version>` and pushes the tag — for the root package and every split package.
