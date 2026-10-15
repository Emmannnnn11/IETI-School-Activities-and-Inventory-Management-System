# Deploying on Vercel (Laravel + PHP)

If the site **downloads a file named `download`** or `index.php`, Vercel was serving PHP as a static file. This repo includes `vercel.json` and `api/index.php` so the **vercel-php** runtime executes Laravel.

## Vercel project settings

1. **Settings → General**
   - **Framework Preset:** Other
   - **Root Directory:** `.` (repository root)
   - **Output Directory:** leave **empty** (do not set to `public` only)

2. **Environment variables** (Production + Preview): keep `APP_*` and `DB_*` as already configured. Also add:

   | Key | Value |
   | --- | --- |
   | `CACHE_DRIVER` | `array` |
   | `SESSION_DRIVER` | `cookie` |
   | `LOG_CHANNEL` | `stderr` |
   | `VIEW_COMPILED_PATH` | `/tmp` |

3. Redeploy from **Deployments → Redeploy**.

## Live URL

Use the domain shown under **Domains** on the deployment (e.g. `https://ieti-school-activities-and-inventor.vercel.app`).
