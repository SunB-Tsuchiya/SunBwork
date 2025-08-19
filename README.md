(Deployment notes)
------------------

Environment overrides
---------------------

Set `CORS_ALLOWED_ORIGINS` in your production `.env` to the specific frontend origin(s).

Example (single origin):

	CORS_ALLOWED_ORIGINS=https://app.example.com

Example (multiple origins):

	CORS_ALLOWED_ORIGINS=https://app.example.com,https://admin.example.com

Session cookie hardening
------------------------

In production, ensure the following environment variables are set:

	SESSION_SECURE_COOKIE=true
	SESSION_SAME_SITE=lax

If your frontend is hosted on a different top-level domain and you require cross-site cookies,
set `SESSION_SAME_SITE=none` and `SESSION_SECURE_COOKIE=true` (HTTPS required).

Switching session store to Redis (optional)
-----------------------------------------

1. Ensure Redis is available and configure `REDIS_HOST`/`REDIS_PASSWORD` in `.env`.
2. Set `SESSION_DRIVER=redis` in `.env`.
3. Optionally tune `config/cache.php` / `config/database.php` for your Redis setup.

After changes, clear caches and restart the app:

	php artisan config:clear
	php artisan route:clear
	php artisan cache:clear
	php artisan view:clear

