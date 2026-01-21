# How to Release a New Version

Follow these steps to release a new version of the Form Settings plugin.

## Step 1: Update Version Numbers

1. **Update `form-settings.php`**:
   - Change `Version: 1.0.0` to your new version (e.g., `1.1.0`)
   - Update `FORM_SETTINGS_VERSION` constant

2. **Update `CHANGELOG.md`**:
   - Add new version section with changes
   - Move items from `[Unreleased]` to the new version

## Step 2: Commit Changes

```bash
cd /Users/luisrodriguez/Local\ Sites/veritas-injury-lawyers/app/public/wp-content/plugins/form-settings

git add .
git commit -m "Release version 1.1.0"
git push origin master
```

## Step 3: Create Git Tag

```bash
# Create annotated tag
git tag -a v1.1.0 -m "Version 1.1.0 - Description of changes"

# Push tag to GitHub
git push origin v1.1.0
```

## Step 4: Create GitHub Release

1. Go to: https://github.com/Luis14718/form-settings-plugin/releases
2. Click "Create a new release"
3. Select the tag you just created (v1.1.0)
4. Title: "Version 1.1.0"
5. Description: Copy from CHANGELOG.md
6. Click "Publish release"

GitHub will automatically create a ZIP file of your plugin.

## Step 5: Test Update

1. Go to another WordPress site with the plugin installed
2. Navigate to **Plugins** page
3. You should see "Update Available" notification
4. Click "Update Now"
5. Plugin will automatically download and install from GitHub

## Version Numbering Guide

- **Patch** (1.0.0 → 1.0.1): Bug fixes only
- **Minor** (1.0.0 → 1.1.0): New features, backward compatible
- **Major** (1.0.0 → 2.0.0): Breaking changes

## Quick Release Command

```bash
# For patch release (1.0.0 → 1.0.1)
./release.sh patch

# For minor release (1.0.0 → 1.1.0)
./release.sh minor

# For major release (1.0.0 → 2.0.0)
./release.sh major
```
