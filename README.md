# afeefa/package-manager

CLI tool for two recurring tasks in Afeefa packages:

- **`setup`** — run install actions defined by packages (e.g. copy config templates into a project).
- **`release`** — bump the version, sync `composer.json` / `package.json`, commit, tag and push (optionally to split repos).

The binary is `vendor/bin/afeefa-package` and is always run from the **project root** (the directory that contains `vendor/` and `.afeefa/`).

## Installation

```bash
composer require --dev afeefa/package-manager
```

After installing, run the setup for the package manager itself once:

```bash
vendor/bin/afeefa-package setup afeefa/package-manager
```

This creates a `.afeefa/package/.installed` marker that `release` requires.

## Example project: from zero to release-ready

Starting with an empty directory, here is the full path to a project that `afeefa-package release` can publish.

### 1. Create a `composer.json` with a `name`

```json
{
    "name": "acme/my-package",
    "version": "0.1.0",
    "type": "library"
}
```

`name` is required for both `setup` and `release`. `version` is required for any package listed in `release/packages.php` (see step 4) — it can be set later interactively, but it is easier to add it now.

### 2. Install the package manager

```bash
composer require --dev afeefa/package-manager
```

This pulls in `vendor/afeefa/package-manager/` and the `vendor/bin/afeefa-package` binary.

### 3. Run setup for the package manager itself

```bash
vendor/bin/afeefa-package setup afeefa/package-manager
```

This creates `.afeefa/package/.installed`, which `release` checks for.

### 4. Add the release configuration

Create `.afeefa/package/release/version.txt`:

```text
0.1.0
```

Create `.afeefa/package/release/packages.php`:

```php
<?php
use Afeefa\Component\Package\Package\Package;

return [
    Package::composer()->path(getcwd()),
];
```

For an npm package use `Package::npm()` instead. To publish into a separate repo, append `->split('git@host:vendor/repo.git')`.

### 5. Commit and link the git remote

```bash
git init
git add .
git commit -m "initial commit"
git remote add origin <url>
git push -u origin main
```

`release` requires a clean working copy and an upstream branch — it will run `git push` and `git push origin <tag>` for you.

### 6. Release

```bash
vendor/bin/afeefa-package release
```

You will be prompted for the new version (Major / Minor / Patch / custom), shown a diff, and asked to confirm. After confirmation the tool bumps `version.txt` and the `version` field in every release package, commits, tags `v<version>` and pushes — including any split repos.

After this first release, only step 6 is needed for every subsequent release.

## How `setup` works

`setup` scans for packages that ship an install script and runs it.

A package is considered "setupable" if it contains:

```text
.afeefa/package/install/install.php
```

That file must `return` the fully-qualified class name of an `Install` action (a subclass of `Afeefa\Component\Package\Actions\Install`).

`setup` looks for these install scripts in two places:

1. **The current project itself** — `<cwd>/.afeefa/package/install/install.php`
2. **Each composer dependency** — `<cwd>/vendor/<vendor>/<package>/.afeefa/package/install/install.php`

You then pick one of the discovered packages, or `all`:

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

A minimal `install.php` returns the action class:

```php
<?php
return \Acme\MyPackage\Install::class;
```

The action subclasses `Afeefa\Component\Package\Actions\Install`, sets the `$configFolderName` (the subdir under `.afeefa/` to store the marker and any files), and overrides `install()`:

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

The base class handles the `--reset` flag, the `.installed` marker and basic CLI output. `createFiles()` copies templates from your package into `.afeefa/<configFolderName>/` of the project.

### Why does `setup` show "no packages to configure found"?

Either:

- you're not in a project root that has a `vendor/` directory, **or**
- none of your dependencies (and not the root package itself) ship an `.afeefa/package/install/install.php`.

## How `release` works

`release` bumps the version of one or more packages, commits, tags and pushes — optionally to *split* repos (one source repo, multiple published packages).

Required files under `.afeefa/package/release/`:

- **`version.txt`** — current project version, e.g. `1.4.2`.
- **`packages.php`** — returns an array of `Package` objects to be released:

  ```php
  <?php
  use Afeefa\Component\Package\Package\Package;

  return [
      Package::composer()->path(getcwd()),                                // root composer package
      Package::npm()->path(getcwd() . '/client'),                         // sibling npm package
      Package::composer()
          ->path(getcwd() . '/server')
          ->split('git@example.com:acme/server.git'),                     // split repo
  ];
  ```

Run:

```bash
vendor/bin/afeefa-package release
```

What it does:

1. Verifies that `setup afeefa/package-manager` has been run (marker exists).
2. Verifies `composer.json` / `package.json` are present and contain `name` (and `version` for release packages). Offers to add missing fields interactively.
3. Aborts if any working copy is dirty.
4. For split packages: ensures a clone exists under `.afeefa/package/release/split-packages/<vendor>/<name>`.
5. Asks for the next version (Major / Minor / Patch / custom).
6. Updates `version.txt` and the `"version"` field in every release package's `composer.json` / `package.json`.
7. Shows a `git diff` for each package and asks for confirmation.
8. For split packages: rsyncs the source into the split clone (excluding `.git`, `vendor`, `node_modules`).
9. Commits, pushes, tags `v<version>` and pushes the tag — for the root package and every split package.

## Recap: minimum required structure

```text
my-project/
├── composer.json                       # has "name"
├── vendor/
│   └── afeefa/package-manager/...
└── .afeefa/
    └── package/
        ├── .installed                  # created by `setup afeefa/package-manager`
        ├── install/
        │   └── install.php             # optional: only if your package itself is setupable
        └── release/
            ├── version.txt             # required for `release`
            └── packages.php            # required for `release`
```
