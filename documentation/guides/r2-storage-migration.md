# R2 Storage Migration

**Status**: Deferred (Docker volume sufficient for now)
**When to do this**: Before accepting real users at scale, or when migrating servers.

---

## Context

> [!info] Current State
> Files are stored on the local `public` disk inside the Docker container, persisted via the `storage_data` Docker volume. This works as long as you're on a single server and don't recreate volumes.

> [!warning] When this breaks
> - You migrate to a new server
> - You recreate Docker volumes (`docker compose down -v`)
> - You scale to multiple app containers (load balancing)

---

## Prerequisites

- [ ] Cloudflare account with R2 enabled
- [ ] R2 bucket created (name: `playersaloons` or your choice)
- [ ] R2 API token generated with **Object Read & Write** permission
- [ ] R2 public URL configured (either custom domain or R2.dev subdomain enabled)

---

## Step 1 — Get R2 Credentials

1. Go to [Cloudflare Dashboard](https://dash.cloudflare.com) → **R2**
2. Create bucket → name it (e.g. `playersaloons-prod`)
3. Go to **Manage R2 API Tokens** → Create token → **Object Read & Write**
4. Copy: `Access Key ID`, `Secret Access Key`
5. Your endpoint format: `https://<ACCOUNT_ID>.r2.cloudflarestorage.com`
6. For public URL: In the bucket → **Settings** → enable **R2.dev subdomain** OR connect your own domain

---

## Step 2 — Update .env in Coolify

Add these to your environment variables in Coolify:

```env
R2_ACCESS_KEY_ID=your_access_key
R2_SECRET_ACCESS_KEY=your_secret_key
R2_BUCKET=playersaloons-prod
R2_ENDPOINT=https://<ACCOUNT_ID>.r2.cloudflarestorage.com
R2_PUBLIC_URL=https://pub-xxx.r2.dev  # or your custom domain
```

---

## Step 3 — Install Adapter

```bash
composer require league/flysystem-aws-s3-v3 "^3.0"
```

Commit the updated `composer.json` and `composer.lock`.

---

## Step 4 — Code Changes (2 files)

### `app/Modules/Match/Actions/SubmitEvidenceAction.php`

```php
// Before
$path = $file->store("disputes/{$dispute->id}/evidence", 'public');

// After
$path = $file->store("disputes/{$dispute->id}/evidence", 'r2');
```

### `app/Modules/Match/Actions/SubmitMatchResultAction.php`

```php
// Before
$proofPath = $proofFile->store("matches/{$match->id}/submissions", 'public');

// After
$proofPath = $proofFile->store("matches/{$match->id}/submissions", 'r2');
```

---

## Step 5 — Update File URL Helpers (Blade Templates)

Find all instances of `/storage/{{ $path }}` or `'/storage/' . $path` that display evidence or submission proof, and replace with:

```blade
{{-- Before --}}
<img src="/storage/{{ $path }}">

{{-- After --}}
<img src="{{ Storage::disk('r2')->url($path) }}">
```

> [!note] Which files to check
> Search the views for `storage` to find all affected templates:
> `grep -r "'/storage/" resources/views`

---

## Step 6 — Migrate Existing Files (if any)

If you already have uploaded files in the Docker volume, copy them to R2 before switching:

```bash
# Enter the app container
docker compose -f docker-compose.prod.yml exec app bash

# List existing uploads
ls storage/app/public/disputes/
ls storage/app/public/matches/

# Use rclone or aws cli to sync to R2
# Or manually upload via Cloudflare dashboard for small amounts
```

---

## Step 7 — Deploy & Verify

```bash
# Redeploy to pick up composer change + code changes
# (Coolify → Redeploy)

# After deploy, verify disk config loaded correctly
docker compose -f docker-compose.prod.yml exec app \
  php artisan tinker --execute="dump(config('filesystems.disks.r2'));"

# Test: upload a dispute screenshot or match proof in the app
# Check R2 bucket in Cloudflare dashboard — file should appear
```

---

## Rollback

If something breaks, revert the 2 code changes (`'r2'` → `'public'`), remove the R2 env vars, and redeploy. Existing files in R2 stay there — no data loss.

---

## Related

- [[execution_checklist]] → File Storage Migration section
- `config/filesystems.php` → `r2` disk already configured
- `docker-compose.prod.yml` → `storage_data` volume (current solution)
