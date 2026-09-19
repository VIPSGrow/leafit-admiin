# PWA Setup Guide

This project has a full PWA (Progressive Web App) setup. Below is a summary of all PWA-related files and their purposes.

## PWA Files

| File | Location | Purpose |
|------|----------|---------|
| `manifest.json` | `public/manifest.json` | PWA manifest with app metadata, icons, and display config |
| `sw.js` | `public/sw.js` | Service Worker with cache-first (static) and network-first (API) strategies |
| `offline.html` | `public/offline.html` | Offline fallback page shown when no network & no cache |
| `favicon.png` | `public/images/icons/favicon.png` | App icon (sourced from backend) |

## Icon Folders

| Folder | Purpose |
|--------|---------|
| `public/images/icons/windows/` | Windows PWA tiles (StoreLogo, Square44x44Logo, Wide310x150Logo, etc.) |
| `public/images/icons/android/` | Android launcher icons |
| `public/images/icons/ios/` | iOS touch icons |
| `public/images/icons/` (root) | Fallback generic icons + favicon |

## App Info
- **Name**: LeafIT Panel Rakho
- **Short Name**: LeafIT
- **Owner**: Activepro Technology Private Limited
- **Theme Color**: #05a6e8

## Template Integration

### Frontend (main template)
- **File**: `app/Views/frontend/retro/template.php`
- Contains full PWA setup: manifest link, service worker registration, theme-color, apple-touch-icon, mask-icon, msapplication tags
- Service worker registration includes update detection with user prompt

### Backend Templates
- `app/Views/backend/user/template.php` - Admin panel PWA links
- `app/Views/backend/partner/template.php` - Partner panel PWA links

## Caching Strategy

| Type | Strategy | Cache |
|------|----------|-------|
| Static assets (JS, CSS, images) | Cache-first, then network | `leafit-static-v1` |
| API calls (`/api/`) | Network-first, then cache | `leafit-api-v1` |
| Offline page | Pre-cached on install | `leafit-cache-v1` |

## PWA Meta Tags (Frontend Template)

- `theme-color` (dynamic from settings)
- `description`, `keywords`, `author`
- `manifest` link with CORS
- `apple-touch-icon` (multiple sizes)
- `mask-icon` (Safari pinned icon)
- `apple-mobile-web-app-capable`, `apple-mobile-web-app-status-bar-style`, `apple-mobile-web-app-title`
- `application-name`
- `msapplication-TileColor`, `msapplication-TileImage`, `msapplication-config`

## Important Notes

1. PWA requires HTTPS in production (localhost is exempt for development)
2. Service worker scope is determined by its location - `/public/sw.js` has scope `/public/`
3. Cache version is in `CACHE_NAME` constant in `sw.js` - change it to force cache refresh
4. The offline page auto-reloads when connection is restored
5. API caching uses network-first strategy to ensure fresh data when online
