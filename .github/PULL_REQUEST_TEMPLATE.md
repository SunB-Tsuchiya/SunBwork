Summary
-------
Briefly describe the problem and what this PR changes.

This PR ensures SPA-facing API endpoints use session-based authentication by routing them through the `web` middleware so `StartSession` runs and `auth:sanctum` can validate first-party cookies.

What I changed
--------------
- Moved SPA-facing routes in `routes/api.php` to use `['web','auth:sanctum']`.
- Removed temporary debug routes used during investigation.
- Added explanatory comments to `routes/api.php`.

Why this change
---------------
- The default `api` middleware group is stateless and does not start the session.
- First-party SPA requests rely on session cookies + XSRF token; without `web` middleware these requests returned 401.

How I tested
-----------
- Cleared config/route/view caches.
- From inside the `laravel` container, called `/api/user` with the browser's encrypted `laravel_session` cookie and `Origin: http://localhost:5174` header; confirmed 200 and user JSON returned.

Verification steps for reviewer
-------------------------------
1. Pull branch and run the app.
2. Log in via the SPA (or copy the `laravel_session` cookie from an authenticated session).
3. Call `/api/user` from the SPA and verify it returns the authenticated user (status 200).
4. Confirm non-SPA API usage remains unchanged.

Checklist
---------
- [ ] Code follows project conventions.
- [ ] I verified auth behavior locally with a browser or curl.
- [ ] I removed any temporary debug routes.
- [ ] I updated docs/comments where relevant.

Notes
-----
If you prefer to keep API routes stateless, an alternative is to ensure `EnsureFrontendRequestsAreStateful` is applied correctly and `SANCTUM_STATEFUL_DOMAINS` covers the SPA origin(s). This PR chooses the explicit `web` middleware approach for clarity and minimal changes.
