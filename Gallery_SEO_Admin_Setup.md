# 📘 Gallery SEO Admin Setup Guide

This guide will help you integrate the **Gallery SEO Admin** module into your Laravel application.

---

## 📁 File Placement

Unzip the contents of the provided ZIP file and place them in your Laravel project:

| File | Destination |
|------|-------------|
| `AdminSeoReview.php` | `app/Http/Livewire/` |
| `admin-seo-review.blade.php` | `resources/views/livewire/` |

---

## 🧩 Route Registration

Add the following route to your `routes/web.php` file:

```php
use App\Http\Livewire\AdminSeoReview;

Route::middleware(['auth', 'admin'])->get('/admin/seo-logs', AdminSeoReview::class);
```

---

## 🗃️ Database Requirements

Ensure your **`logs`** table includes the following columns:

- `id`
- `message`
- `context (JSON)`
- `approved (boolean)`
- `published (boolean)`
- `created_at`

Your **`users`** table should include:

- `id`
- `is_trusted (boolean)`

---

## 🔔 Notification Webhook

Define the webhook endpoint in your `.env`:

```env
NOTIFICATION_ENDPOINT=https://your-domain.com/api/notify
```

In `config/services.php`, add:

```php
'notification' => [
    'endpoint' => env('NOTIFICATION_ENDPOINT'),
],
```

---

## ⚙️ Usage Features

- Approve individual or all suggestions.
- Toggle publish status manually.
- Auto-publish suggestions from trusted creators.
- Export logs to CSV.
- Webhook status indicator per log.

---

## ✅ Done!

Visit `/admin/seo-logs` as an admin user to access the moderation dashboard.

Let us know if you need Seeder scripts, role middleware, or Inertia/Vue support.