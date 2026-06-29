# Aipex Podcast System deployment

The WordPress plugin lives inside this repository at:

```text
aipex-podcast-system-v2/
```

Downloading the whole GitHub repository ZIP is not a valid WordPress plugin ZIP because the plugin folder is nested inside the repository. The build workflow fixes this by creating a valid ZIP where `aipex-podcast-system-v2` is the plugin folder inside the archive.

## Build a valid plugin ZIP

Workflow:

```text
.github/workflows/build-plugin.yml
```

It runs on every push to `main`, every pull request to `main`, and manually from **Actions → Build Plugin ZIP → Run workflow**.

The workflow creates this artifact:

```text
aipex-podcast-system-v2.zip
```

That ZIP can be uploaded through **WordPress → Plugins → Add New → Upload Plugin**.

## Automatic deployment to WordPress

Workflow:

```text
.github/workflows/deploy-plugin-sftp.yml
```

It deploys the contents of:

```text
aipex-podcast-system-v2/
```

to your live WordPress plugin folder.

## Required GitHub repository secrets

Go to:

```text
GitHub repository → Settings → Secrets and variables → Actions → New repository secret
```

Add these secrets:

```text
WP_SFTP_HOST
WP_SFTP_PORT
WP_SFTP_USERNAME
WP_SFTP_PRIVATE_KEY
WP_PLUGIN_PATH
```

Recommended value for `WP_PLUGIN_PATH` on the Women's Radio Station site:

```text
/var/www/vhosts/womensradiostation.com/httpdocs/wp-content/plugins/aipex-podcast-system-v2/
```

If deploying to staging instead, use:

```text
/var/www/vhosts/womensradiostation.com/httpdocs/staging/wp-content/plugins/aipex-podcast-system-v2/
```

## Optional WP-CLI rewrite flush

If the server supports SSH and WP-CLI, add these optional secrets:

```text
WP_SSH_HOST
WP_SSH_PORT
WP_SSH_USERNAME
WP_SSH_PRIVATE_KEY
WP_PATH
```

For live site, `WP_PATH` is probably:

```text
/var/www/vhosts/womensradiostation.com/httpdocs
```

For staging:

```text
/var/www/vhosts/womensradiostation.com/httpdocs/staging
```

When those optional SSH secrets are present, the workflow attempts to run:

```bash
wp plugin activate aipex-podcast-system-v2 --allow-root
wp rewrite flush --allow-root
```

## Deployment behaviour

On every push to `main` that changes files inside `aipex-podcast-system-v2/`, GitHub Actions will:

1. Validate the plugin folder.
2. Copy the plugin source to a clean release folder.
3. Deploy it into the configured WordPress plugin path.
4. Optionally flush rewrite rules if SSH/WP-CLI secrets are configured.

## Important

Do not upload the whole repository ZIP to WordPress. Use either:

- the workflow artifact ZIP, or
- the automatic deployment workflow.
