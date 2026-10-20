# Deploying on Vercel (Laravel + PHP)

If the site **downloads a file named `download`**, Vercel was serving PHP as a static file. This repo uses `vercel.json` + `api/index.php` + **vercel-php**.

## 1. Redeploy first (often enough)

After the latest commit with `vercel.json`, open the project → **Deployments** → **Redeploy** the latest **Ready** build.  
`vercel.json` already sets `"framework": null` (same as **Other**), so you may not need any dashboard change.

## 2. Where settings are in the Vercel UI

Vercel moved menus; there is not always a tab literally named **General**.

1. Go to [vercel.com/dashboard](https://vercel.com/dashboard).
2. Click your **project card** (not the team home).
3. Open **Settings** — top navigation bar on the project (**Overview · Deployments · … · Settings**), or a gear on the left.
4. In the left sidebar, open **Build and Deployment** (sometimes grouped under **Build & Development Settings**).

There you should see:

- **Framework Preset** — should be **Other** (overridden by `vercel.json` anyway).
- **Output Directory** — if it shows `public`, turn **Override** on and **clear the field** (empty).  
  Leaving `public` only is what causes PHP to download instead of run.

If you still cannot find **Output Directory**, skip it and redeploy; the repo config is the source of truth.

## 3. Environment variables

**Settings → Environment Variables** (same project sidebar).

Keep existing `APP_*` and `DB_*`. Add for **Production** and **Preview** if missing:

| Key | Value |
| --- | --- |
| `CACHE_DRIVER` | `array` |
| `SESSION_DRIVER` | `cookie` |
| `LOG_CHANNEL` | `stderr` |
| `VIEW_COMPILED_PATH` | `/tmp` |

Save, then **Redeploy**.

## 4. Check it worked

Open **Visit** on the deployment (e.g. `https://ieti-school-activities-and-inventor.vercel.app`).

- You should see the **login page**, not a download.
- On the project **Overview**, **Function Invocations** should increase when you refresh the site (not stay at 0).

## 5. Build failed (`vercel build` exited with 1)?

1. Open the failed deployment → **Building** → read the red error (often Composer or Node/PHP runtime).
2. **Settings → Build and Deployment → Node.js Version** → set **20.x** (avoid **24.x** with older PHP runtimes).
3. **Deployments → ⋯ → Redeploy** → turn **off** “Use existing Build Cache”.
4. Optional env var: `VERCEL_FORCE_NO_BUILD_CACHE` = `1` for Production + Preview.

Do **not** set **Install Command** or **Build Command** to `composer` in the dashboard — Composer is not on the Node build path (exit **127**). This repo uses **vercel-php**, which runs Composer during the PHP build. `vercel.json` sets `vercel-php@0.7.4`.
Confirm `APP_KEY` and `DB_*` exist for the environment being built.
