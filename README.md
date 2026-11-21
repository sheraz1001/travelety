# Travelety

Travel search site for travellers in the UAE and the Gulf: flights, hotels, destination guides, a daily blog and price alerts. Laravel 13 + Filament 4 admin.

## What's inside

| Area | URL | Source |
|---|---|---|
| Home with search | `/` | `resources/views/home.blade.php` |
| Flight route pages (thousands) | `/flights/dubai-to-istanbul` | `FlightRoute` model, fares from the data API |
| From / to hubs | `/flights-from/dubai`, `/flights-to/istanbul` | |
| Hotel guides | `/hotels/istanbul` | `Destination` model |
| Destination guides (flight + hotel combined) | `/travel/istanbul` | `Destination` model |
| Blog | `/blog`, `/blog/{slug}`, `/blog/feed` | `Post` model |
| Outbound redirects (search, partners) | `/go/flights`, `/go/hotels`, `/go/{partner}` | `GoController` — logs clicks |
| Sitemaps / robots | `/sitemap.xml`, `/robots.txt` | |
| Admin | `/admin` | Filament |

All booking links point to `/go/...` on this domain, which redirects to the branded search domain (`search_domain` in Site settings) or the partner link. No third-party brand names or scripts are in page source.

## Automation (cron)

Add one cron job on the server: `* * * * * cd /path/to/app && php artisan schedule:run >> /dev/null 2>&1`

| Command | When | What |
|---|---|---|
| `fares:update` | every 4 h | Refreshes fares for 150 routes; routes with fares go live automatically |
| `routes:write` | 03:30 | AI writes unique text for live routes without text |
| `destinations:write` | 04:00 | AI writes 3 destination guides + hotel pages |
| `blog:daily` | 06:00 | Writes the next topic from the queue, runs quality checks, publishes (if auto-publish is on and QC passes) or holds for review |
| `alerts:send` | 09:00 | Emails subscribers when a watched route gets cheaper |

## Setup

1. `composer install --no-dev --optimize-autoloader`
2. Copy `.env.example` to `.env`, fill DB, `FARES_API_TOKEN`, `AI_API_KEY`, mail; run `php artisan key:generate`
3. `php artisan migrate --force && php artisan db:seed --force && php artisan storage:link`
4. Without SSH: set `SETUP_KEY` in `.env`, open `/_setup/install?key=YOUR_KEY`, then remove the key.
