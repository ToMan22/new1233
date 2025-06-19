# 🎟️ Creator Giveaways & Live Q&A Module

Let creators host audience engagement sessions like random giveaways and AMA-style Q&A.

---

## 🎁 1. Giveaways Table

```php
Schema::create('giveaways', function (Blueprint $table) {
    $table->id();
    $table->foreignId('creator_id')->constrained('users')->onDelete('cascade');
    $table->string('title');
    $table->text('description')->nullable();
    $table->timestamp('starts_at')->nullable();
    $table->timestamp('ends_at')->nullable();
    $table->boolean('ended')->default(false);
    $table->timestamps();
});
```

---

## 👥 2. Giveaway Entry Table

```php
Schema::create('giveaway_entries', function (Blueprint $table) {
    $table->id();
    $table->foreignId('giveaway_id')->constrained()->onDelete('cascade');
    $table->foreignId('user_id')->constrained()->onDelete('cascade');
    $table->timestamp('entered_at')->useCurrent();
    $table->timestamps();

    $table->unique(['giveaway_id', 'user_id']);
});
```

---

## 🎙️ 3. Live Q&A Table

```php
Schema::create('qa_sessions', function (Blueprint $table) {
    $table->id();
    $table->foreignId('creator_id')->constrained('users')->onDelete('cascade');
    $table->string('topic');
    $table->timestamp('starts_at')->nullable();
    $table->boolean('is_live')->default(false);
    $table->timestamps();
});
```

```php
Schema::create('qa_questions', function (Blueprint $table) {
    $table->id();
    $table->foreignId('qa_session_id')->constrained()->onDelete('cascade');
    $table->foreignId('user_id')->constrained()->onDelete('cascade');
    $table->text('question');
    $table->boolean('answered')->default(false);
    $table->timestamps();
});
```

---

## 🧾 4. Giveaway Winner Logic

```php
$winner = GiveawayEntry::where('giveaway_id', $id)->inRandomOrder()->first();
$giveaway->update(['ended' => true]);
// notify winner
```

---

## 📥 5. Blade UI Snippets

```blade
<!-- Giveaway Entry Button -->
<form method="POST" action="{{ route('giveaway.enter', $giveaway) }}">
  @csrf
  <button class="btn btn-success">🎟 Enter Giveaway</button>
</form>

<!-- Q&A Question Submission -->
<form method="POST" action="{{ route('qa.question', $session) }}">
  @csrf
  <textarea name="question" required></textarea>
  <button class="btn btn-primary">Ask!</button>
</form>
```

---

## ✅ Optional Features

- Auto-close giveaways after end time
- Moderator control for Q&A sessions
- Real-time question voting
- Livewire or Pusher for real-time updates

Inspired by:
- [Laravel Q&A](https://github.com/eduardokum/laravel-push-notification)
- [Giveaway modules in Laravel Nova](https://github.com/laravel/nova)
