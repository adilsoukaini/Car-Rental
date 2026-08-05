# PWA Setup — Installable Home-Screen Experience

**Goal:** let a customer add this web app to their phone's home screen, where
it opens full-screen with a real icon and no browser address bar — the
"feels like a native app" experience, without an App Store, without a
separate codebase. This is a **later phase**, built once the core app already
works — a PWA wraps an existing working app, it isn't something to build
alongside the first features.

**Two honest platform differences, not a bug in your implementation:**

**1. Android/Chrome supports an automatic "Install" prompt. iOS/Safari does
not.** Apple deliberately doesn't implement the `beforeinstallprompt` browser
event — an iPhone user must manually tap Share → "Add to Home Screen." Your
UI needs to explicitly guide iOS users through this (a small instructional
banner/modal), since there's no one-tap button you can show them instead.

**2. Push notifications require the app to already be installed on iOS.**
Since iOS 16.4, push notifications work for a PWA — but *only* once it's
been added to the home screen. A visitor browsing in a normal Safari tab
cannot receive a push prompt at all. Plan any notification feature (booking
confirmations, pickup reminders) with a fallback (email, SMS) for visitors
who never install the app — don't make push the only notification channel.

---

## 1. What's actually needed

- `manifest.json` — app name, short name, icon set (multiple sizes — a
  generator tool is worth using rather than hand-crafting each size), theme
  color (should read from the active theme's `color.primary`, not be
  hardcoded — consistent with the rest of the token system), display mode
  `standalone`
- A service worker — handles offline asset caching. Start minimal (cache the
  app shell/static assets) rather than attempting full offline data sync,
  which is a much bigger, separate undertaking
- HTTPS is required for PWA installability — confirm your production
  deployment already has SSL (it should, per the earlier scalability/
  deployment planning)

Given the stack uses Vite (via Laravel + Inertia), `vite-plugin-pwa` handles
most of the manifest/service-worker boilerplate — use it rather than hand-
rolling this from scratch.

---

## 2. iOS-specific UX — build this explicitly, don't assume it's automatic

Detect iOS Safari specifically (there's no native install prompt to hook
into), and show a small, dismissible instructional banner: *"Installez
l'app: appuyez sur Partager puis 'Sur l'écran d'accueil'"* (or your
equivalent). This banner is real, necessary UI — not an edge case to skip.

For Android/Chrome, the native `beforeinstallprompt` event can be captured
and triggered from your own "Install" button, giving a cleaner one-tap
experience there.

---

## 3. Build order

1. Confirm HTTPS is live on the target environment (dev can use a self-signed
   cert or a tool like `ngrok`/`mkcert` for local testing, since PWA install
   won't work over plain HTTP except on `localhost`)
2. Add `vite-plugin-pwa`, generate the icon set, build `manifest.json` with
   theme color read dynamically from the active theme
3. Minimal service worker — cache the app shell and static assets only
4. Android install button (captures `beforeinstallprompt`)
5. iOS instructional banner (detected via user-agent, shown once, dismissible
   and not shown again after dismissal — store the dismissal in local
   storage or a cookie)
6. Verify, with real evidence:
   - Install on an actual Android device (or Chrome's device emulation as a
     first check, but confirm on a real device before considering this done)
   - Install on an actual iPhone via the Share → Add to Home Screen flow,
     confirm it opens full-screen with the correct icon and theme color
   - Confirm the app still works correctly when opened from the home screen
     icon (not just as a regular browser tab) — check that Inertia
     navigation, forms, and any auth session all behave correctly in
     standalone mode
   - If push notifications are in scope: confirm the notification permission
     prompt only appears for an installed (standalone-mode) session on iOS,
     and confirm the non-installed fallback (email) still works for anyone
     who hasn't installed
