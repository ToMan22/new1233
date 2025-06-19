# 🚨 Community Content Reporting & Moderation Insights

Enable members to report content and empower admins with analytics and moderation tools.

---

## 🚩 1. Report Table

```php
Schema::create('content_reports', function (Blueprint $table) {
    $table->id();
    $table->foreignId('reporter_id')->constrained('users')->onDelete('cascade');
    $table->foreignId('post_id')->constrained()->onDelete('cascade');
    $table->string('reason');
    $table->text('message')->nullable();
    $table->enum('status', ['pending', 'reviewed', 'actioned'])->default('pending');
    $table->timestamp('resolved_at')->nullable();
    $table->timestamps();
});
```

---

## 🧠 2. Reporting Form (Blade Partial)

```blade
<form method="POST" action="{{ route('report.post', $post) }}">
  @csrf
  <select name="reason">
    <option value="nudity">Nudity</option>
    <option value="harassment">Harassment</option>
    <option value="copyright">Copyright</option>
  </select>
  <textarea name="message" placeholder="Optional notes"></textarea>
  <button class="btn btn-warning">🚩 Report</button>
</form>
```

---

## 🔍 3. Admin Review Table

```blade
<table>
<tr><th>Post</th><th>Reporter</th><th>Reason</th><th>Status</th></tr>
@foreach($reports as $r)
<tr>
  <td><a href="{{ route('admin.posts.show', $r->post) }}">#{{ $r->post_id }}</a></td>
  <td>{{ $r->reporter->name }}</td>
  <td>{{ ucfirst($r->reason) }}</td>
  <td>{{ ucfirst($r->status) }}</td>
</tr>
@endforeach
</table>
```

---

## ✅ 4. Admin Action Buttons

```blade
<form method="POST" action="{{ route('admin.report.update', $report) }}">
  @csrf @method('PATCH')
  <button name="status" value="actioned" class="btn btn-danger">Take Down</button>
  <button name="status" value="reviewed" class="btn btn-secondary">Mark Reviewed</button>
</form>
```

---

## 📊 5. Moderation Insights

**Dashboard data:**

```php
$topOffenders = DB::table('content_reports')
  ->select('post_id', DB::raw('count(*) as count'))
  ->groupBy('post_id')
  ->orderByDesc('count')
  ->take(5)
  ->get();
```

Use with charting lib (e.g., ApexCharts, Chart.js).

---

## 🧾 Optional Enhancements

- Rate-limited reporting per user
- Email alert to moderators
- IP logging or abuse flags
- Tie to account warnings system

Inspired by:
- [Moderation patterns](https://github.com/laravelio/laravel.io)
- [Forum complaint tracking](https://flarum.org/)

