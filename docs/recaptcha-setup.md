# Guest-comment reCAPTCHA setup

Guest comments on `/resources/{slug}` are protected by a hidden honeypot field and a 3-per-minute limit per IP.
Adding Google reCAPTCHA keys turns on a "I'm not a robot" check as well. Without keys the form still works.

## 1. Get the keys

1. Sign in at <https://www.google.com/recaptcha/admin/create> with a Google account the team owns.
2. **Label:** `MNCH Kenya guest comments`.
3. **reCAPTCHA type:** choose **Challenge (v2)** → **"I'm not a robot" checkbox**. (The form uses the v2 checkbox widget; v3 keys will not work.)
4. **Domains:** add `mnchkenyamentorship.org` (and any staging domain, e.g. `staging.mnchkenyamentorship.org`). Add `localhost` only for local testing.
5. Accept the terms and submit. Copy the **Site key** (public) and **Secret key** (private).

## 2. Add them to the server

In the production `.env`:

```dotenv
RECAPTCHA_SITE_KEY=your-site-key
RECAPTCHA_SECRET_KEY=your-secret-key
```

Then reload the config:

```bash
php artisan config:clear
php artisan config:cache   # only if you normally cache config in production
```

The keys are read in `config/services.php` under `recaptcha`. Never commit the secret key.

## 3. Check it works

1. Open a resource page while signed out, e.g. `/resources/<any-slug>`.
2. The guest comment form should show the reCAPTCHA checkbox above **Submit comment**.
3. Submit without ticking it: you should see "Please confirm you are not a robot."
4. Tick it and submit: you should see "Comment submitted and is pending approval!". The comment appears once approved in the admin panel.

## Troubleshooting

| Symptom | Likely cause |
|---|---|
| No checkbox shows | `RECAPTCHA_SITE_KEY` missing or config cached (`php artisan config:clear`) |
| "ERROR for site owner: Invalid domain" | Domain not listed in the reCAPTCHA admin console |
| "Invalid key type" | Keys were created as v3; create a v2 checkbox key |
| Always "confirm you are not a robot" | Wrong `RECAPTCHA_SECRET_KEY`, or the server cannot reach `www.google.com` (outbound HTTPS blocked) |

To turn reCAPTCHA off again, remove the two variables and clear the config cache.
