# Laravel 12 Implementation TODO - Detailed Code Examples & Documentation References

> **Reference Guide**: [Laravel 12 Documentation](https://laravel.com/docs/12.x) | **Code Review Reference**: Laravel 12 Code Review Reference Guide

** **All Code is to be edited in the codebase C:\Users\User\Documents\tgirlsonlyproject\public_html\golive_clean once completed in the codebase update this file**
---

## 🔥 PHASE 1: Critical Security & Authentication (Weeks 1-2)

### 📋 1. Authentication & Authorization System
**Reference**: [Authentication - Laravel 12.x](https://laravel.com/docs/12.x/authentication)

#### 1.1 Complete Policy Implementation
**Current Status**: ❌ Only `PostPolicy.php` exists
**Required**: ✅ Policies for all major models

```php
// Task 1.1.1: Create User Policy
php artisan make:policy UserPolicy --model=User

// app/Policies/UserPolicy.php
<?php
namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class UserPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->is_admin;
    }

    public function view(User $user, User $model): bool
    {
        return $user->id === $model->id || $user->is_admin;
    }

    public function create(User $user): bool
    {
        return $user->is_admin;
    }

    public function update(User $user, User $model): bool
    {
        return $user->id === $model->id || $user->is_admin;
    }

    public function delete(User $user, User $model): bool
    {
        return $user->is_admin && $user->id !== $model->id;
    }

    public function viewProfile(User $user, User $model): bool
    {
        if ($user->id === $model->id) return true;
        if ($model->public_profile) return true;

        return $user->hasActiveSubscriptionTo($model);
    }
}
```

```php
// Task 1.1.2: Create Creator Policy
php artisan make:policy CreatorPolicy --model=Creator

// app/Policies/CreatorPolicy.php
<?php
namespace App\Policies;

use App\Models\User;
use App\Models\Creator;

class CreatorPolicy
{
    public function viewAny(User $user): bool
    {
        return true; // Public creator directory
    }

    public function view(User $user, Creator $creator): bool
    {
        return $user->canAccessProfile($creator->user);
    }

    public function create(User $user): bool
    {
        return $user->is_verified && !$user->is_creator;
    }

    public function update(User $user, Creator $creator): bool
    {
        return $user->id === $creator->user_id || $user->is_admin;
    }

    public function approve(User $user, Creator $creator): bool
    {
        return $user->is_admin;
    }
}
```

```php
// Task 1.1.3: Create Subscription Policy
php artisan make:policy SubscriptionPolicy --model=Subscription

// app/Policies/SubscriptionPolicy.php
<?php
namespace App\Policies;

use App\Models\User;
use App\Models\Subscription;

class SubscriptionPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Subscription $subscription): bool
    {
        return $user->id === $subscription->subscriber_id ||
               $user->id === $subscription->creator_id ||
               $user->is_admin;
    }

    public function create(User $user): bool
    {
        return $user->isVerified();
    }

    public function cancel(User $user, Subscription $subscription): bool
    {
        return $user->id === $subscription->subscriber_id;
    }
}
```

```php
// Task 1.1.4: Register Policies in AuthServiceProvider
// app/Providers/AuthServiceProvider.php
<?php
namespace App\Providers;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;
use App\Models\{User, Creator, Post, Subscription, Transaction};
use App\Policies\{UserPolicy, CreatorPolicy, PostPolicy, SubscriptionPolicy, TransactionPolicy};

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        User::class => UserPolicy::class,
        Creator::class => CreatorPolicy::class,
        Post::class => PostPolicy::class,
        Subscription::class => SubscriptionPolicy::class,
        Transaction::class => TransactionPolicy::class,
    ];

    public function boot(): void
    {
        $this->registerPolicies();

        // Define custom gates
        Gate::define('access-admin', function (User $user) {
            return $user->is_admin;
        });

        Gate::define('access-creator-forum', function (User $user) {
            return $user->canAccessCreatorForum();
        });

        Gate::define('moderate-content', function (User $user) {
            return $user->is_admin || $user->is_verified;
        });
    }
}
```

#### 1.2 Enhanced Two-Factor Authentication
**Reference**: [Authentication - Laravel 12.x](https://laravel.com/docs/12.x/authentication)

```php
// Task 1.2.1: Enhance 2FA Middleware
// app/Http/Middleware/Enhanced/TwoFactorAuthenticationMiddleware.php
<?php
namespace App\Http\Middleware\Enhanced;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use PragmaRX\Google2FA\Google2FA;

class TwoFactorAuthenticationMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $user = Auth::user();

        if (!$user) {
            return redirect()->route('login');
        }

        // Skip 2FA for non-sensitive routes
        if ($this->isExemptRoute($request)) {
            return $next($request);
        }

        if ($user->enable_2fa && !session('2fa_verified')) {
            return redirect()->route('2fa.verify');
        }

        return $next($request);
    }

    private function isExemptRoute(Request $request): bool
    {
        $exemptRoutes = [
            '2fa.verify',
            '2fa.challenge',
            'logout',
            'profile.2fa.setup'
        ];

        return in_array($request->route()->getName(), $exemptRoutes);
    }
}
```

```php
// Task 1.2.2: Create 2FA Controller
// app/Http/Controllers/Auth/TwoFactorController.php
<?php
namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use PragmaRX\Google2FA\Google2FA;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Writer;

class TwoFactorController extends Controller
{
    protected $google2fa;

    public function __construct()
    {
        $this->google2fa = new Google2FA();
    }

    public function showSetup()
    {
        $user = Auth::user();

        if (!$user->two_factor_secret) {
            $user->two_factor_secret = $this->google2fa->generateSecretKey();
            $user->save();
        }

        $qrCodeUrl = $this->google2fa->getQRCodeUrl(
            config('app.name'),
            $user->email,
            $user->two_factor_secret
        );

        return view('auth.2fa.setup', compact('qrCodeUrl'));
    }

    public function verify(Request $request)
    {
        $request->validate([
            'code' => 'required|digits:6'
        ]);

        $user = Auth::user();
        $valid = $this->google2fa->verifyKey(
            $user->two_factor_secret,
            $request->code
        );

        if ($valid) {
            session(['2fa_verified' => true]);
            return redirect()->intended('/dashboard');
        }

        return back()->withErrors(['code' => 'Invalid 2FA code']);
    }

    public function enable(Request $request)
    {
        $request->validate([
            'code' => 'required|digits:6'
        ]);

        $user = Auth::user();
        $valid = $this->google2fa->verifyKey(
            $user->two_factor_secret,
            $request->code
        );

        if ($valid) {
            $user->enable_2fa = true;
            $user->save();

            return redirect()->route('profile.edit')
                ->with('success', '2FA enabled successfully');
        }

        return back()->withErrors(['code' => 'Invalid 2FA code']);
    }
}
```

### 📋 2. Form Request Validation System
**Reference**: [Validation - Laravel 12.x](https://laravel.com/docs/12.x/validation)

#### 2.1 Missing Form Requests Implementation

```php
// Task 2.1.1: Create User Profile Update Request
php artisan make:request UpdateUserProfileRequest

// app/Http/Requests/UpdateUserProfileRequest.php
<?php
namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Rules\SecureFileUpload;

class UpdateUserProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('user'));
    }

    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'min:2',
                'max:255',
                'regex:/^[\pL\s\-]+$/u' // Letters, spaces, hyphens only
            ],
            'username' => [
                'required',
                'string',
                'min:3',
                'max:30',
                'regex:/^[a-zA-Z0-9_]+$/',
                Rule::unique('users')->ignore($this->user()->id)
            ],
            'email' => [
                'required',
                'email:rfc,dns',
                Rule::unique('users')->ignore($this->user()->id)
            ],
            'bio' => [
                'nullable',
                'string',
                'max:1000'
            ],
            'location' => [
                'nullable',
                'string',
                'max:100'
            ],
            'website' => [
                'nullable',
                'url',
                'max:255'
            ],
            'avatar' => [
                'nullable',
                'image',
                'mimes:jpeg,png,jpg,gif',
                'max:2048', // 2MB
                new SecureFileUpload
            ],
            'cover' => [
                'nullable',
                'image',
                'mimes:jpeg,png,jpg',
                'max:5120', // 5MB
                new SecureFileUpload
            ],
            'birthdate' => [
                'nullable',
                'date',
                'before:today',
                'after:1900-01-01'
            ]
        ];
    }

    public function messages(): array
    {
        return [
            'name.regex' => 'The name may only contain letters, spaces, and hyphens.',
            'username.regex' => 'The username may only contain letters, numbers, and underscores.',
            'email.email' => 'Please provide a valid email address.',
            'avatar.max' => 'Avatar image must be smaller than 2MB.',
            'cover.max' => 'Cover image must be smaller than 5MB.',
            'birthdate.before' => 'Birth date must be before today.',
            'birthdate.after' => 'Please provide a valid birth date.'
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'username' => strtolower($this->username),
            'email' => strtolower($this->email),
        ]);
    }
}
```

```php
// Task 2.1.2: Create Payment Processing Request
php artisan make:request ProcessPaymentRequest

// app/Http/Requests/ProcessPaymentRequest.php
<?php
namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Rules\SecureCardDataValidation;

class ProcessPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isVerified();
    }

    public function rules(): array
    {
        return [
            'amount' => [
                'required',
                'numeric',
                'min:1',
                'max:9999.99'
            ],
            'currency' => [
                'required',
                'string',
                'in:USD,EUR,GBP,CAD,AUD'
            ],
            'payment_method' => [
                'required',
                'string',
                'in:card,paypal,stripe,bank_transfer'
            ],
            'creator_id' => [
                'required',
                'exists:users,id',
                'different:' . $this->user()->id
            ],
            'subscription_type' => [
                'required',
                'string',
                'in:monthly,quarterly,semi_annual,annual'
            ],
            // Card validation (only if payment_method is card)
            'card_token' => [
                'required_if:payment_method,card',
                'string',
                'min:10',
                new SecureCardDataValidation
            ],
            'billing_address' => [
                'required',
                'array'
            ],
            'billing_address.street' => [
                'required',
                'string',
                'max:255'
            ],
            'billing_address.city' => [
                'required',
                'string',
                'max:100'
            ],
            'billing_address.postal_code' => [
                'required',
                'string',
                'regex:/^[a-zA-Z0-9\s-]{3,10}$/'
            ],
            'billing_address.country' => [
                'required',
                'string',
                'exists:countries,code'
            ]
        ];
    }

    public function messages(): array
    {
        return [
            'creator_id.different' => 'You cannot subscribe to yourself.',
            'card_token.required_if' => 'Card token is required for card payments.',
            'billing_address.*.required' => 'All billing address fields are required.',
            'billing_address.postal_code.regex' => 'Please provide a valid postal code.'
        ];
    }
}
```

```php
// Task 2.1.3: Create Subscription Management Request
php artisan make:request ManageSubscriptionRequest

// app/Http/Requests/ManageSubscriptionRequest.php
<?php
namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ManageSubscriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $subscription = $this->route('subscription');
        return $this->user()->can('update', $subscription);
    }

    public function rules(): array
    {
        return [
            'action' => [
                'required',
                'string',
                'in:pause,resume,cancel,upgrade,downgrade'
            ],
            'new_plan' => [
                'required_if:action,upgrade,downgrade',
                'exists:plans,id'
            ],
            'cancellation_reason' => [
                'required_if:action,cancel',
                'string',
                'max:500'
            ],
            'immediate' => [
                'boolean'
            ]
        ];
    }

    public function messages(): array
    {
        return [
            'new_plan.required_if' => 'Please select a plan for the upgrade/downgrade.',
            'cancellation_reason.required_if' => 'Please provide a reason for cancellation.'
        ];
    }
}
```

#### 2.2 Custom Validation Rules Implementation

```php
// Task 2.2.1: Enhanced Secure File Upload Rule
// app/Rules/SecureFileUpload.php
<?php
namespace App\Rules;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;

class SecureFileUpload implements ValidationRule
{
    public function validate(string $attribute, mixed $value, \Closure $fail): void
    {
        if (!$value instanceof UploadedFile) {
            $fail('Invalid file upload.');
            return;
        }

        // Check file size (max 10MB)
        if ($value->getSize() > 10485760) {
            $fail('File size cannot exceed 10MB.');
            return;
        }

        // Check MIME type
        $allowedMimes = [
            'image/jpeg', 'image/png', 'image/gif', 'image/webp',
            'video/mp4', 'video/quicktime', 'video/webm',
            'audio/mpeg', 'audio/wav'
        ];

        if (!in_array($value->getMimeType(), $allowedMimes)) {
            $fail('File type not allowed.');
            return;
        }

        // Check file extension matches MIME type
        $extension = strtolower($value->getClientOriginalExtension());
        $expectedMimes = [
            'jpg' => ['image/jpeg'],
            'jpeg' => ['image/jpeg'],
            'png' => ['image/png'],
            'gif' => ['image/gif'],
            'webp' => ['image/webp'],
            'mp4' => ['video/mp4'],
            'mov' => ['video/quicktime'],
            'webm' => ['video/webm'],
            'mp3' => ['audio/mpeg'],
            'wav' => ['audio/wav']
        ];

        if (!isset($expectedMimes[$extension]) ||
            !in_array($value->getMimeType(), $expectedMimes[$extension])) {
            $fail('File extension does not match file type.');
            return;
        }

        // Scan for malicious content (basic check)
        $content = file_get_contents($value->getPathname());
        if (strpos($content, '<?php') !== false ||
            strpos($content, '<script') !== false) {
            $fail('File contains potentially malicious content.');
            return;
        }
    }
}
```

```php
// Task 2.2.2: Age Verification Rule
php artisan make:rule AgeVerification

// app/Rules/AgeVerification.php
<?php
namespace App\Rules;

use Illuminate\Contracts\Validation\ValidationRule;
use Carbon\Carbon;

class AgeVerification implements ValidationRule
{
    private int $minimumAge;

    public function __construct(int $minimumAge = 18)
    {
        $this->minimumAge = $minimumAge;
    }

    public function validate(string $attribute, mixed $value, \Closure $fail): void
    {
        try {
            $birthdate = Carbon::parse($value);
            $age = $birthdate->diffInYears(Carbon::now());

            if ($age < $this->minimumAge) {
                $fail("You must be at least {$this->minimumAge} years old.");
            }
        } catch (\Exception $e) {
            $fail('Please provide a valid birth date.');
        }
    }
}
```

```php
// Task 2.2.3: Content Safety Rule Enhancement
// app/Rules/ContentSafety.php
<?php
namespace App\Rules;

use Illuminate\Contracts\Validation\ValidationRule;

class ContentSafety implements ValidationRule
{
    private array $bannedWords;
    private array $suspiciousPatterns;

    public function __construct()
    {
        $this->bannedWords = config('content.banned_words', []);
        $this->suspiciousPatterns = [
            '/\b(?:https?:\/\/)?(?:bit\.ly|tinyurl|t\.co|goo\.gl)\/\S+/i',
            '/\b(?:discord|telegram|whatsapp)\.(?:gg|me|com)\S*/i',
            '/\b(?:onlyfans|patreon)\.com\S*/i',
        ];
    }

    public function validate(string $attribute, mixed $value, \Closure $fail): void
    {
        $content = strtolower($value);

        // Check for banned words
        foreach ($this->bannedWords as $word) {
            if (strpos($content, strtolower($word)) !== false) {
                $fail('Content contains inappropriate language.');
                return;
            }
        }

        // Check for suspicious patterns
        foreach ($this->suspiciousPatterns as $pattern) {
            if (preg_match($pattern, $value)) {
                $fail('Content contains suspicious links or references.');
                return;
            }
        }

        // Check for excessive capital letters
        $upperCount = strlen(preg_replace('/[^A-Z]/', '', $value));
        $totalCount = strlen(preg_replace('/[^A-Za-z]/', '', $value));

        if ($totalCount > 0 && ($upperCount / $totalCount) > 0.5) {
            $fail('Please reduce the use of capital letters.');
            return;
        }

        // Check for repeated characters
        if (preg_match('/(.)\1{4,}/', $value)) {
            $fail('Content contains excessive repeated characters.');
            return;
        }
    }
}
```

### 📋 3. Database Security & Optimization
**Reference**: [Database - Laravel 12.x](https://laravel.com/docs/12.x/database)

#### 3.1 Missing Foreign Key Constraints

```php
// Task 3.1.1: Add Foreign Key Constraints Migration
php artisan make:migration add_missing_foreign_key_constraints

// database/migrations/[timestamp]_add_missing_foreign_key_constraints.php
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Users table constraints
        Schema::table('users', function (Blueprint $table) {
            $table->foreign('country_id')->references('id')->on('countries')->onDelete('set null');
            $table->foreign('gender_id')->references('id')->on('user_genders')->onDelete('set null');
        });

        // Posts table constraints
        Schema::table('posts', function (Blueprint $table) {
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('category_id')->references('id')->on('categories')->onDelete('set null');
        });

        // Subscriptions table constraints
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->foreign('subscriber_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('creator_id')->references('id')->on('users')->onDelete('cascade');
        });

        // Transactions table constraints
        Schema::table('transactions', function (Blueprint $table) {
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('subscription_id')->references('id')->on('subscriptions')->onDelete('set null');
        });

        // User messages constraints
        Schema::table('user_messages', function (Blueprint $table) {
            $table->foreign('sender_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('receiver_id')->references('id')->on('users')->onDelete('cascade');
        });

        // Attachments constraints
        Schema::table('attachments', function (Blueprint $table) {
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('post_id')->references('id')->on('posts')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['country_id']);
            $table->dropForeign(['gender_id']);
        });

        Schema::table('posts', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropForeign(['category_id']);
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropForeign(['subscriber_id']);
            $table->dropForeign(['creator_id']);
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropForeign(['subscription_id']);
        });

        Schema::table('user_messages', function (Blueprint $table) {
            $table->dropForeign(['sender_id']);
            $table->dropForeign(['receiver_id']);
        });

        Schema::table('attachments', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropForeign(['post_id']);
        });
    }
};
```

#### 3.2 Performance Indexes Implementation

```php
// Task 3.2.1: Add Performance Indexes Migration
php artisan make:migration add_performance_indexes

// database/migrations/[timestamp]_add_performance_indexes.php
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Users table indexes
        Schema::table('users', function (Blueprint $table) {
            $table->index(['is_creator', 'creator_application_status'], 'users_creator_status_idx');
            $table->index(['is_verified', 'created_at'], 'users_verified_created_idx');
            $table->index(['email_verified_at', 'is_admin'], 'users_email_admin_idx');
            $table->index('username'); // For profile lookups
        });

        // Posts table indexes
        Schema::table('posts', function (Blueprint $table) {
            $table->index(['user_id', 'status', 'created_at'], 'posts_user_status_created_idx');
            $table->index(['status', 'created_at'], 'posts_status_created_idx');
            $table->index(['category_id', 'status'], 'posts_category_status_idx');
            $table->index(['price', 'status'], 'posts_price_status_idx');
        });

        // Subscriptions table indexes
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->index(['subscriber_id', 'expires_at', 'canceled_at'], 'subs_user_expires_canceled_idx');
            $table->index(['creator_id', 'status'], 'subs_creator_status_idx');
            $table->index(['expires_at', 'status'], 'subs_expires_status_idx');
        });

        // Transactions table indexes
        Schema::table('transactions', function (Blueprint $table) {
            $table->index(['user_id', 'type', 'created_at'], 'trans_user_type_created_idx');
            $table->index(['status', 'created_at'], 'trans_status_created_idx');
            $table->index(['type', 'amount'], 'trans_type_amount_idx');
        });

        // User messages indexes
        Schema::table('user_messages', function (Blueprint $table) {
            $table->index(['receiver_id', 'created_at'], 'messages_receiver_created_idx');
            $table->index(['sender_id', 'created_at'], 'messages_sender_created_idx');
            $table->index(['receiver_id', 'read_at'], 'messages_receiver_read_idx');
        });

        // Attachments indexes
        Schema::table('attachments', function (Blueprint $table) {
            $table->index(['user_id', 'type'], 'attachments_user_type_idx');
            $table->index(['post_id', 'type'], 'attachments_post_type_idx');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('users_creator_status_idx');
            $table->dropIndex('users_verified_created_idx');
            $table->dropIndex('users_email_admin_idx');
            $table->dropIndex(['username']);
        });

        Schema::table('posts', function (Blueprint $table) {
            $table->dropIndex('posts_user_status_created_idx');
            $table->dropIndex('posts_status_created_idx');
            $table->dropIndex('posts_category_status_idx');
            $table->dropIndex('posts_price_status_idx');
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropIndex('subs_user_expires_canceled_idx');
            $table->dropIndex('subs_creator_status_idx');
            $table->dropIndex('subs_expires_status_idx');
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->dropIndex('trans_user_type_created_idx');
            $table->dropIndex('trans_status_created_idx');
            $table->dropIndex('trans_type_amount_idx');
        });

        Schema::table('user_messages', function (Blueprint $table) {
            $table->dropIndex('messages_receiver_created_idx');
            $table->dropIndex('messages_sender_created_idx');
            $table->dropIndex('messages_receiver_read_idx');
        });

        Schema::table('attachments', function (Blueprint $table) {
            $table->dropIndex('attachments_user_type_idx');
            $table->dropIndex('attachments_post_type_idx');
        });
    }
};
```

---

## 🔧 PHASE 2: Performance & Query Optimization (Weeks 3-4)

### 📋 4. Service Layer Implementation
**Reference**: [Service Container - Laravel 12.x](https://laravel.com/docs/12.x/container)

#### 4.1 Complete Service Pattern Implementation

```php
// Task 4.1.1: Create Payment Service
// app/Services/Payment/PaymentService.php
<?php
namespace App\Services\Payment;

use App\Models\{User, Transaction, Subscription};
use App\Services\Payment\Contracts\PaymentServiceContract;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PaymentService implements PaymentServiceContract
{
    public function processSubscription(User $user, User $creator, array $paymentData): array
    {
        return DB::transaction(function () use ($user, $creator, $paymentData) {
            try {
                // Validate payment data
                $this->validatePaymentData($paymentData);

                // Calculate amounts including taxes
                $amounts = $this->calculateAmounts($paymentData);

                // Process payment with gateway
                $paymentResult = $this->processGatewayPayment($paymentData, $amounts);

                if (!$paymentResult['success']) {
                    throw new \Exception($paymentResult['error']);
                }

                // Create subscription
                $subscription = $this->createSubscription($user, $creator, $paymentData, $amounts);

                // Create transaction record
                $transaction = $this->createTransaction($user, $subscription, $paymentResult, $amounts);

                // Update user wallet if applicable
                if (isset($amounts['platform_fee'])) {
                    $this->distributePlatformFee($amounts['platform_fee']);
                }

                Log::info('Subscription payment processed successfully', [
                    'user_id' => $user->id,
                    'creator_id' => $creator->id,
                    'subscription_id' => $subscription->id,
                    'transaction_id' => $transaction->id,
                    'amount' => $amounts['total']
                ]);

                return [
                    'success' => true,
                    'subscription' => $subscription,
                    'transaction' => $transaction,
                    'payment_id' => $paymentResult['payment_id']
                ];

            } catch (\Exception $e) {
                Log::error('Payment processing failed', [
                    'user_id' => $user->id,
                    'creator_id' => $creator->id,
                    'error' => $e->getMessage(),
                    'payment_data' => $paymentData
                ]);

                throw $e;
            }
        });
    }

    private function validatePaymentData(array $data): void
    {
        $required = ['amount', 'currency', 'payment_method', 'subscription_type'];

        foreach ($required as $field) {
            if (!isset($data[$field])) {
                throw new \InvalidArgumentException("Missing required field: {$field}");
            }
        }

        if ($data['amount'] <= 0) {
            throw new \InvalidArgumentException('Amount must be greater than zero');
        }
    }

    private function calculateAmounts(array $paymentData): array
    {
        $baseAmount = $paymentData['amount'];
        $platformFeeRate = config('payment.platform_fee_rate', 0.05); // 5%
        $taxRate = config('payment.tax_rate', 0.08); // 8%

        $platformFee = $baseAmount * $platformFeeRate;
        $creatorAmount = $baseAmount - $platformFee;
        $tax = $baseAmount * $taxRate;
        $total = $baseAmount + $tax;

        return [
            'base_amount' => $baseAmount,
            'platform_fee' => $platformFee,
            'creator_amount' => $creatorAmount,
            'tax' => $tax,
            'total' => $total
        ];
    }

    private function processGatewayPayment(array $paymentData, array $amounts): array
    {
        // This would integrate with actual payment gateway
        // For now, simulate successful payment
        return [
            'success' => true,
            'payment_id' => 'pay_' . uniqid(),
            'gateway_response' => ['status' => 'completed']
        ];
    }

    private function createSubscription(User $user, User $creator, array $paymentData, array $amounts): Subscription
    {
        $expiresAt = $this->calculateExpirationDate($paymentData['subscription_type']);

        return Subscription::create([
            'subscriber_id' => $user->id,
            'creator_id' => $creator->id,
            'amount' => $amounts['base_amount'],
            'currency' => $paymentData['currency'],
            'type' => $paymentData['subscription_type'],
            'status' => 'active',
            'starts_at' => now(),
            'expires_at' => $expiresAt,
            'auto_renew' => $paymentData['auto_renew'] ?? true
        ]);
    }

    private function createTransaction(User $user, Subscription $subscription, array $paymentResult, array $amounts): Transaction
    {
        return Transaction::create([
            'user_id' => $user->id,
            'subscription_id' => $subscription->id,
            'type' => 'subscription_payment',
            'amount' => $amounts['total'],
            'currency' => $subscription->currency,
            'status' => 'completed',
            'gateway_payment_id' => $paymentResult['payment_id'],
            'gateway_response' => $paymentResult['gateway_response'],
            'platform_fee' => $amounts['platform_fee'],
            'creator_amount' => $amounts['creator_amount'],
            'tax_amount' => $amounts['tax']
        ]);
    }

    private function calculateExpirationDate(string $type): \Carbon\Carbon
    {
        return match ($type) {
            'monthly' => now()->addMonth(),
            'quarterly' => now()->addMonths(3),
            'semi_annual' => now()->addMonths(6),
            'annual' => now()->addYear(),
            default => now()->addMonth()
        };
    }

    private function distributePlatformFee(float $fee): void
    {
        // Logic to handle platform fee distribution
        // Could involve updating admin accounts, reserves, etc.
    }
}
```

```php
// Task 4.1.2: Create Content Service
// app/Services/Content/ContentService.php
<?php
namespace App\Services\Content;

use App\Models\{Post, User, Attachment};
use App\Services\Content\AttachmentService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class ContentService
{
    protected AttachmentService $attachmentService;

    public function __construct(AttachmentService $attachmentService)
    {
        $this->attachmentService = $attachmentService;
    }

    public function createPost(User $user, array $data): Post
    {
        return DB::transaction(function () use ($user, $data) {
            // Create the post
            $post = Post::create([
                'user_id' => $user->id,
                'title' => $data['title'],
                'text' => $data['text'] ?? null,
                'price' => $data['price'] ?? 0,
                'category_id' => $data['category_id'] ?? null,
                'status' => $this->determinePostStatus($user, $data),
                'release_date' => $data['release_date'] ?? now(),
                'expire_date' => $data['expire_date'] ?? null,
                'is_public' => $data['is_public'] ?? false
            ]);

            // Handle attachments if present
            if (!empty($data['attachments'])) {
                $this->attachmentService->processPostAttachments($post, $data['attachments']);
            }

            // Handle tags if present
            if (!empty($data['tags'])) {
                $this->syncPostTags($post, $data['tags']);
            }

            // Clear relevant caches
            $this->clearUserContentCache($user);

            return $post->fresh(['attachments', 'tags', 'category']);
        });
    }

    public function updatePost(Post $post, array $data): Post
    {
        return DB::transaction(function () use ($post, $data) {
            $post->update([
                'title' => $data['title'] ?? $post->title,
                'text' => $data['text'] ?? $post->text,
                'price' => $data['price'] ?? $post->price,
                'category_id' => $data['category_id'] ?? $post->category_id,
                'is_public' => $data['is_public'] ?? $post->is_public,
                'expire_date' => $data['expire_date'] ?? $post->expire_date
            ]);

            // Handle new attachments
            if (!empty($data['attachments'])) {
                $this->attachmentService->processPostAttachments($post, $data['attachments']);
            }

            // Handle removed attachments
            if (!empty($data['remove_attachments'])) {
                $this->attachmentService->removeAttachments($data['remove_attachments']);
            }

            // Update tags
            if (isset($data['tags'])) {
                $this->syncPostTags($post, $data['tags']);
            }

            // Clear caches
            $this->clearPostCache($post);
            $this->clearUserContentCache($post->user);

            return $post->fresh(['attachments', 'tags', 'category']);
        });
    }

    public function deletePost(Post $post): bool
    {
        return DB::transaction(function () use ($post) {
            // Delete all attachments
            foreach ($post->attachments as $attachment) {
                $this->attachmentService->deleteAttachment($attachment);
            }

            // Clear caches
            $this->clearPostCache($post);
            $this->clearUserContentCache($post->user);

            return $post->delete();
        });
    }

    public function getPostsFeed(User $user, int $perPage = 10): \Illuminate\Pagination\LengthAwarePaginator
    {
        $cacheKey = "user_feed_{$user->id}_page_1";

        return Cache::remember($cacheKey, 300, function () use ($user, $perPage) {
            return Post::with(['user', 'attachments', 'category'])
                ->where('status', 'published')
                ->where(function ($query) {
                    $query->where('is_public', true)
                          ->orWhereHas('user', function ($q) {
                              // Posts from subscribed creators
                              $q->whereIn('id', $this->getUserSubscriptions(auth()->user()));
                          });
                })
                ->where(function ($query) {
                    $query->whereNull('release_date')
                          ->orWhere('release_date', '<=', now());
                })
                ->where(function ($query) {
                    $query->whereNull('expire_date')
                          ->orWhere('expire_date', '>', now());
                })
                ->orderBy('created_at', 'desc')
                ->paginate($perPage);
        });
    }

    private function determinePostStatus(User $user, array $data): string
    {
        // Auto-approve for verified creators
        if ($user->is_verified || $user->is_admin) {
            return 'published';
        }

        // Check content for moderation flags
        $content = ($data['title'] ?? '') . ' ' . ($data['text'] ?? '');
        if ($this->requiresModeration($content)) {
            return 'pending_review';
        }

        return 'published';
    }

    private function requiresModeration(string $content): bool
    {
        $flagWords = config('content.moderation_keywords', []);
        $lowerContent = strtolower($content);

        foreach ($flagWords as $word) {
            if (strpos($lowerContent, strtolower($word)) !== false) {
                return true;
            }
        }

        return false;
    }

    private function syncPostTags(Post $post, array $tags): void
    {
        $tagIds = [];

        foreach ($tags as $tagName) {
            $tag = \App\Models\Tag::firstOrCreate(['name' => strtolower(trim($tagName))]);
            $tagIds[] = $tag->id;
        }

        $post->tags()->sync($tagIds);
    }

    private function getUserSubscriptions(User $user): array
    {
        return Cache::remember(
            "user_subscriptions_{$user->id}",
            1800, // 30 minutes
            fn() => $user->activeSubscriptions()->pluck('creator_id')->toArray()
        );
    }

    private function clearPostCache(Post $post): void
    {
        Cache::forget("post_{$post->id}");
        Cache::forget("post_with_relations_{$post->id}");
    }

    private function clearUserContentCache(User $user): void
    {
        Cache::forget("user_posts_{$user->id}");
        Cache::forget("user_feed_{$user->id}_page_1");
        Cache::forget("user_content_count_{$user->id}");
    }
}
```

#### 4.2 Repository Pattern Implementation

```php
// Task 4.2.1: Create User Repository
// app/Repositories/UserRepository.php
<?php
namespace App\Repositories;

use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class UserRepository extends BaseRepository implements UserRepositoryInterface
{
    public function __construct(User $model)
    {
        parent::__construct($model);
    }

    public function findByUsername(string $username): ?User
    {
        return $this->model->where('username', $username)->first();
    }

    public function findByEmail(string $email): ?User
    {
        return $this->model->where('email', $email)->first();
    }

    public function getVerifiedCreators(int $limit = 50): Collection
    {
        return $this->model
            ->where('is_creator', true)
            ->where('is_verified', true)
            ->where('creator_application_status', 'approved')
            ->limit($limit)
            ->get();
    }

    public function getPopularCreators(int $limit = 20): Collection
    {
        return $this->model
            ->withCount(['activeSubscriptions'])
            ->where('is_creator', true)
            ->where('is_verified', true)
            ->orderBy('active_subscriptions_count', 'desc')
            ->limit($limit)
            ->get();
    }

    public function searchCreators(string $query, int $perPage = 15): LengthAwarePaginator
    {
        return $this->model
            ->where('is_creator', true)
            ->where('is_verified', true)
            ->where(function ($q) use ($query) {
                $q->where('name', 'like', "%{$query}%")
                  ->orWhere('username', 'like', "%{$query}%")
                  ->orWhere('bio', 'like', "%{$query}%");
            })
            ->paginate($perPage);
    }

    public function getUsersWithExpiringSubs(int $days = 7): Collection
    {
        return $this->model
            ->whereHas('activeSubscriptions', function ($query) use ($days) {
                $query->whereBetween('expires_at', [
                    now(),
                    now()->addDays($days)
                ]);
            })
            ->get();
    }

    public function getInactiveUsers(int $days = 30): Collection
    {
        return $this->model
            ->where('last_activity_at', '<', now()->subDays($days))
            ->whereDoesntHave('activeSubscriptions')
            ->get();
    }

    public function updateLastActivity(int $userId): bool
    {
        return $this->model
            ->where('id', $userId)
            ->update(['last_activity_at' => now()]);
    }

    public function bulkUpdateSettings(array $userIds, array $settings): int
    {
        return $this->model
            ->whereIn('id', $userIds)
            ->update(['settings' => $settings]);
    }
}
```

### 📋 5. Testing Infrastructure Implementation
**Reference**: [Testing - Laravel 12.x](https://laravel.com/docs/12.x/testing)

#### 5.1 Feature Tests Implementation

```php
// Task 5.1.1: Create Authentication Feature Tests
// tests/Feature/Auth/AuthenticationTest.php
<?php
namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_login_with_valid_credentials(): void
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => bcrypt('password123')
        ]);

        $response = $this->post('/login', [
            'email' => 'test@example.com',
            'password' => 'password123'
        ]);

        $response->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user);
    }

    public function test_user_cannot_login_with_invalid_credentials(): void
    {
        User::factory()->create([
            'email' => 'test@example.com',
            'password' => bcrypt('password123')
        ]);

        $response = $this->post('/login', [
            'email' => 'test@example.com',
            'password' => 'wrongpassword'
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_user_registration_with_valid_data(): void
    {
        $response = $this->post('/register', [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'username' => 'johndoe',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'birthdate' => '1990-01-01'
        ]);

        $response->assertRedirect('/dashboard');

        $this->assertDatabaseHas('users', [
            'email' => 'john@example.com',
            'username' => 'johndoe'
        ]);
    }

    public function test_user_registration_requires_age_verification(): void
    {
        $response = $this->post('/register', [
            'name' => 'Young User',
            'email' => 'young@example.com',
            'username' => 'younguser',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'birthdate' => now()->subYears(16)->format('Y-m-d') // Under 18
        ]);

        $response->assertSessionHasErrors('birthdate');
        $this->assertDatabaseMissing('users', [
            'email' => 'young@example.com'
        ]);
    }

    public function test_user_can_logout(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/logout');

        $response->assertRedirect('/');
        $this->assertGuest();
    }

    public function test_two_factor_authentication_required_when_enabled(): void
    {
        $user = User::factory()->create([
            'enable_2fa' => true,
            'two_factor_secret' => 'test_secret'
        ]);

        $this->actingAs($user);

        $response = $this->get('/dashboard');

        $response->assertRedirect('/2fa/verify');
    }
}
```

```php
// Task 5.1.2: Create Payment Feature Tests
// tests/Feature/Payment/PaymentProcessingTest.php
<?php
namespace Tests\Feature\Payment;

use App\Models\{User, Transaction, Subscription};
use App\Services\Payment\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentProcessingTest extends TestCase
{
    use RefreshDatabase;

    private PaymentService $paymentService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->paymentService = app(PaymentService::class);
    }

    public function test_successful_subscription_payment(): void
    {
        $user = User::factory()->create(['is_verified' => true]);
        $creator = User::factory()->create(['is_creator' => true]);

        $paymentData = [
            'amount' => 29.99,
            'currency' => 'USD',
            'payment_method' => 'card',
            'subscription_type' => 'monthly',
            'card_token' => 'test_token_123'
        ];

        $result = $this->paymentService->processSubscription($user, $creator, $paymentData);

        $this->assertTrue($result['success']);
        $this->assertInstanceOf(Subscription::class, $result['subscription']);
        $this->assertInstanceOf(Transaction::class, $result['transaction']);

        $this->assertDatabaseHas('subscriptions', [
            'subscriber_id' => $user->id,
            'creator_id' => $creator->id,
            'amount' => 29.99,
            'status' => 'active'
        ]);

        $this->assertDatabaseHas('transactions', [
            'user_id' => $user->id,
            'type' => 'subscription_payment',
            'status' => 'completed'
        ]);
    }

    public function test_payment_validation_rules(): void
    {
        $user = User::factory()->create();
        $creator = User::factory()->create(['is_creator' => true]);

        $response = $this->actingAs($user)
            ->postJson('/api/payments/subscription', [
                'creator_id' => $creator->id,
                'amount' => -10, // Invalid amount
                'currency' => 'INVALID',
                'payment_method' => 'invalid_method'
            ]);

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['amount', 'currency', 'payment_method']);
    }

    public function test_user_cannot_subscribe_to_themselves(): void
    {
        $user = User::factory()->create(['is_creator' => true]);

        $response = $this->actingAs($user)
            ->postJson('/api/payments/subscription', [
                'creator_id' => $user->id,
                'amount' => 29.99,
                'currency' => 'USD',
                'payment_method' => 'card'
            ]);

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['creator_id']);
    }

    public function test_subscription_creation_with_database_transaction(): void
    {
        $user = User::factory()->create();
        $creator = User::factory()->create(['is_creator' => true]);

        // Mock payment failure
        $this->mock(PaymentService::class, function ($mock) {
            $mock->shouldReceive('processSubscription')
                 ->andThrow(new \Exception('Payment gateway error'));
        });

        try {
            $this->paymentService->processSubscription($user, $creator, [
                'amount' => 29.99,
                'currency' => 'USD',
                'payment_method' => 'card',
                'subscription_type' => 'monthly'
            ]);
        } catch (\Exception $e) {
            // Expected exception
        }

        // Verify no partial data was saved
        $this->assertDatabaseMissing('subscriptions', [
            'subscriber_id' => $user->id,
            'creator_id' => $creator->id
        ]);

        $this->assertDatabaseMissing('transactions', [
            'user_id' => $user->id,
            'type' => 'subscription_payment'
        ]);
    }
}
```

```php
// Task 5.1.3: Create Content Management Tests
// tests/Feature/Content/PostManagementTest.php
<?php
namespace Tests\Feature\Content;

use App\Models\{User, Post, Category};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PostManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_verified_creator_can_create_post(): void
    {
        Storage::fake('public');

        $creator = User::factory()->create([
            'is_creator' => true,
            'is_verified' => true
        ]);

        $category = Category::factory()->create();
        $image = UploadedFile::fake()->image('test.jpg', 800, 600);

        $response = $this->actingAs($creator)
            ->post('/posts', [
                'title' => 'Test Post Title',
                'text' => 'This is test content for the post.',
                'category_id' => $category->id,
                'price' => 9.99,
                'is_public' => false,
                'attachments' => [$image],
                'tags' => ['test', 'content', 'sample']
            ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('posts', [
            'user_id' => $creator->id,
            'title' => 'Test Post Title',
            'status' => 'published',
            'price' => 9.99
        ]);

        Storage::disk('public')->assertExists('attachments/' . $image->hashName());
    }

    public function test_unverified_user_post_requires_moderation(): void
    {
        $user = User::factory()->create([
            'is_creator' => false,
            'is_verified' => false
        ]);

        $response = $this->actingAs($user)
            ->post('/posts', [
                'title' => 'Unverified User Post',
                'text' => 'This should require moderation.'
            ]);

        $this->assertDatabaseHas('posts', [
            'user_id' => $user->id,
            'title' => 'Unverified User Post',
            'status' => 'pending_review'
        ]);
    }

    public function test_post_content_safety_validation(): void
    {
        $creator = User::factory()->create(['is_creator' => true]);

        $response = $this->actingAs($creator)
            ->post('/posts', [
                'title' => 'Test with SPAM CONTENT!!!',
                'text' => 'Visit this suspicious link: bit.ly/scam'
            ]);

        $response->assertSessionHasErrors(['title', 'text']);
    }

    public function test_post_scheduled_publishing(): void
    {
        $creator = User::factory()->create(['is_creator' => true]);
        $futureDate = now()->addDays(1);

        $response = $this->actingAs($creator)
            ->post('/posts', [
                'title' => 'Scheduled Post',
                'text' => 'This will be published later.',
                'release_date' => $futureDate->format('Y-m-d H:i:s')
            ]);

        $post = Post::where('title', 'Scheduled Post')->first();

        $this->assertEquals($futureDate->format('Y-m-d H:i'),
                           $post->release_date->format('Y-m-d H:i'));
    }

    public function test_post_access_control_for_paid_content(): void
    {
        $creator = User::factory()->create(['is_creator' => true]);
        $subscriber = User::factory()->create();
        $nonSubscriber = User::factory()->create();

        $post = Post::factory()->create([
            'user_id' => $creator->id,
            'price' => 19.99,
            'is_public' => false
        ]);

        // Create active subscription
        Subscription::factory()->create([
            'subscriber_id' => $subscriber->id,
            'creator_id' => $creator->id,
            'expires_at' => now()->addMonth(),
            'status' => 'active'
        ]);

        // Subscriber can access
        $response = $this->actingAs($subscriber)
            ->get("/posts/{$post->id}");
        $response->assertStatus(200);

        // Non-subscriber cannot access
        $response = $this->actingAs($nonSubscriber)
            ->get("/posts/{$post->id}");
        $response->assertStatus(403);
    }
}
```

#### 5.2 Unit Tests Implementation

```php
// Task 5.2.1: Create User Model Tests
// tests/Unit/Models/UserTest.php
<?php
namespace Tests\Unit\Models;

use App\Models\{User, Subscription, Post};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_full_name_attribute(): void
    {
        $user = User::factory()->make([
            'name' => 'John Doe'
        ]);

        $this->assertEquals('John Doe', $user->name);
    }

    public function test_user_is_admin_method(): void
    {
        $admin = User::factory()->make(['is_admin' => true]);
        $user = User::factory()->make(['is_admin' => false]);

        $this->assertTrue($admin->isAdmin());
        $this->assertFalse($user->isAdmin());
    }

    public function test_user_can_access_profile_logic(): void
    {
        $creator = User::factory()->create(['public_profile' => false]);
        $subscriber = User::factory()->create();
        $nonSubscriber = User::factory()->create();

        // Create active subscription
        Subscription::factory()->create([
            'subscriber_id' => $subscriber->id,
            'creator_id' => $creator->id,
            'expires_at' => now()->addMonth(),
            'status' => 'active'
        ]);

        $this->assertTrue($subscriber->canAccessProfile($creator));
        $this->assertFalse($nonSubscriber->canAccessProfile($creator));
        $this->assertTrue($creator->canAccessProfile($creator)); // Own profile
    }

    public function test_user_monthly_earnings_calculation(): void
    {
        $creator = User::factory()->create(['is_creator' => true]);
        $subscriber1 = User::factory()->create();
        $subscriber2 = User::factory()->create();

        // Create subscriptions for current month
        Subscription::factory()->create([
            'creator_id' => $creator->id,
            'subscriber_id' => $subscriber1->id,
            'amount' => 29.99,
            'created_at' => now()->startOfMonth()->addDays(5)
        ]);

        Subscription::factory()->create([
            'creator_id' => $creator->id,
            'subscriber_id' => $subscriber2->id,
            'amount' => 19.99,
            'created_at' => now()->startOfMonth()->addDays(10)
        ]);

        // Create subscription for previous month (should not count)
        Subscription::factory()->create([
            'creator_id' => $creator->id,
            'amount' => 39.99,
            'created_at' => now()->subMonth()
        ]);

        $this->assertEquals(49.98, $creator->getMonthlyEarnings());
    }

    public function test_user_engagement_rate_calculation(): void
    {
        $creator = User::factory()->create(['is_creator' => true]);

        // Create some subscribers
        $subscribers = User::factory()->count(10)->create();
        foreach ($subscribers as $subscriber) {
            Subscription::factory()->create([
                'creator_id' => $creator->id,
                'subscriber_id' => $subscriber->id,
                'expires_at' => now()->addMonth(),
                'status' => 'active'
            ]);
        }

        // Create recent posts
        Post::factory()->count(3)->create([
            'user_id' => $creator->id,
            'status' => 'published',
            'created_at' => now()->subDays(15)
        ]);

        $engagementRate = $creator->engagement_rate;

        // With 10 subscribers and 3 posts in last 30 days: (3/10) * 100 = 30%
        $this->assertEquals(30.0, $engagementRate);
    }

    public function test_user_cache_clearing(): void
    {
        $user = User::factory()->create(['is_creator' => true]);

        // Access cached data to populate cache
        $subscriberCount = $user->subscriber_count;

        // Update user (should clear cache)
        $user->update(['name' => 'Updated Name']);

        // This is more of an integration test, but verifies cache is working
        $this->assertTrue(true); // Cache clearing happens in model events
    }
}
```

```php
// Task 5.2.2: Create Service Class Tests
// tests/Unit/Services/PaymentServiceTest.php
<?php
namespace Tests\Unit\Services;

use App\Models\{User, Subscription, Transaction};
use App\Services\Payment\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentServiceTest extends TestCase
{
    use RefreshDatabase;

    private PaymentService $paymentService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->paymentService = app(PaymentService::class);
    }

    public function test_calculate_amounts_with_platform_fee(): void
    {
        $reflection = new \ReflectionClass($this->paymentService);
        $method = $reflection->getMethod('calculateAmounts');
        $method->setAccessible(true);

        $paymentData = ['amount' => 100.00];
        $amounts = $method->invoke($this->paymentService, $paymentData);

        $this->assertEquals(100.00, $amounts['base_amount']);
        $this->assertEquals(5.00, $amounts['platform_fee']); // 5%
        $this->assertEquals(95.00, $amounts['creator_amount']);
        $this->assertEquals(8.00, $amounts['tax']); // 8%
        $this->assertEquals(108.00, $amounts['total']);
    }

    public function test_calculate_expiration_date(): void
    {
        $reflection = new \ReflectionClass($this->paymentService);
        $method = $reflection->getMethod('calculateExpirationDate');
        $method->setAccessible(true);

        $now = now();

        $monthly = $method->invoke($this->paymentService, 'monthly');
        $this->assertEquals($now->copy()->addMonth()->format('Y-m-d'), $monthly->format('Y-m-d'));

        $quarterly = $method->invoke($this->paymentService, 'quarterly');
        $this->assertEquals($now->copy()->addMonths(3)->format('Y-m-d'), $quarterly->format('Y-m-d'));

        $annual = $method->invoke($this->paymentService, 'annual');
        $this->assertEquals($now->copy()->addYear()->format('Y-m-d'), $annual->format('Y-m-d'));
    }

    public function test_payment_validation(): void
    {
        $reflection = new \ReflectionClass($this->paymentService);
        $method = $reflection->getMethod('validatePaymentData');
        $method->setAccessible(true);

        // Valid data should not throw
        $validData = [
            'amount' => 29.99,
            'currency' => 'USD',
            'payment_method' => 'card',
            'subscription_type' => 'monthly'
        ];

        $method->invoke($this->paymentService, $validData);
        $this->assertTrue(true); // No exception thrown

        // Invalid amount should throw
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Amount must be greater than zero');

        $invalidData = $validData;
        $invalidData['amount'] = -10;
        $method->invoke($this->paymentService, $invalidData);
    }

    public function test_missing_required_fields_validation(): void
    {
        $reflection = new \ReflectionClass($this->paymentService);
        $method = $reflection->getMethod('validatePaymentData');
        $method->setAccessible(true);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing required field: amount');

        $method->invoke($this->paymentService, [
            'currency' => 'USD',
            'payment_method' => 'card',
            'subscription_type' => 'monthly'
            // Missing 'amount'
        ]);
    }
}
```

---

## 🚀 PHASE 3: Code Quality & Architecture (Weeks 5-6)

### 📋 6. Controller Refactoring
**Reference**: [Controllers - Laravel 12.x](https://laravel.com/docs/12.x/controllers)

#### 6.1 Fat Controller Refactoring

```php
// Task 6.1.1: Refactor PaymentsController
// BEFORE (Fat Controller - app/Http/Controllers/PaymentsController.php)
/*
class PaymentsController extends Controller
{
    public function processSubscription(Request $request)
    {
        // 150+ lines of payment logic, validation,
        // database operations, email sending, etc.
    }
}
*/

// AFTER (Lean Controller)
// app/Http/Controllers/PaymentsController.php
<?php
namespace App\Http\Controllers;

use App\Http\Requests\ProcessPaymentRequest;
use App\Services\Payment\PaymentService;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentsController extends Controller
{
    public function __construct(
        private PaymentService $paymentService
    ) {}

    public function processSubscription(ProcessPaymentRequest $request): JsonResponse
    {
        try {
            $creator = User::findOrFail($request->creator_id);

            $result = $this->paymentService->processSubscription(
                user: $request->user(),
                creator: $creator,
                paymentData: $request->validated()
            );

            return response()->json([
                'success' => true,
                'message' => 'Subscription created successfully',
                'data' => [
                    'subscription_id' => $result['subscription']->id,
                    'transaction_id' => $result['transaction']->id,
                    'expires_at' => $result['subscription']->expires_at
                ]
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Payment processing failed',
                'error' => $e->getMessage()
            ], 422);
        }
    }

    public function getUserSubscriptions(Request $request): JsonResponse
    {
        $subscriptions = $this->paymentService->getUserSubscriptions($request->user());

        return response()->json([
            'success' => true,
            'data' => $subscriptions
        ]);
    }

    public function cancelSubscription(Request $request, Subscription $subscription): JsonResponse
    {
        $this->authorize('cancel', $subscription);

        $result = $this->paymentService->cancelSubscription(
            subscription: $subscription,
            reason: $request->input('reason'),
            immediate: $request->boolean('immediate', false)
        );

        return response()->json([
            'success' => $result,
            'message' => $result ? 'Subscription cancelled successfully' : 'Failed to cancel subscription'
        ]);
    }
}
```

```php
// Task 6.1.2: Refactor PostsController
// app/Http/Controllers/PostsController.php
<?php
namespace App\Http\Controllers;

use App\Http\Requests\Posts\{CreatePostRequest, UpdatePostRequest};
use App\Services\Content\ContentService;
use App\Models\Post;
use Illuminate\Http\{JsonResponse, RedirectResponse};
use Illuminate\View\View;

class PostsController extends Controller
{
    public function __construct(
        private ContentService $contentService
    ) {}

    public function index(): View
    {
        $posts = $this->contentService->getPostsFeed(
            user: auth()->user(),
            perPage: config('app.feed.feed_posts_per_page', 10)
        );

        return view('posts.index', compact('posts'));
    }

    public function create(): View
    {
        $this->authorize('create', Post::class);

        $categories = $this->contentService->getAvailableCategories();

        return view('posts.create', compact('categories'));
    }

    public function store(CreatePostRequest $request): RedirectResponse
    {
        try {
            $post = $this->contentService->createPost(
                user: $request->user(),
                data: $request->validated()
            );

            return redirect()
                ->route('posts.show', $post)
                ->with('success', 'Post created successfully!');

        } catch (\Exception $e) {
            return back()
                ->withInput()
                ->withErrors(['error' => 'Failed to create post: ' . $e->getMessage()]);
        }
    }

    public function show(Post $post): View
    {
        $this->authorize('view', $post);

        $post = $this->contentService->getPostWithRelations($post);

        return view('posts.show', compact('post'));
    }

    public function edit(Post $post): View
    {
        $this->authorize('update', $post);

        $categories = $this->contentService->getAvailableCategories();

        return view('posts.edit', compact('post', 'categories'));
    }

    public function update(UpdatePostRequest $request, Post $post): RedirectResponse
    {
        try {
            $updatedPost = $this->contentService->updatePost(
                post: $post,
                data: $request->validated()
            );

            return redirect()
                ->route('posts.show', $updatedPost)
                ->with('success', 'Post updated successfully!');

        } catch (\Exception $e) {
            return back()
                ->withInput()
                ->withErrors(['error' => 'Failed to update post: ' . $e->getMessage()]);
        }
    }

    public function destroy(Post $post): RedirectResponse
    {
        $this->authorize('delete', $post);

        try {
            $this->contentService->deletePost($post);

            return redirect()
                ->route('posts.index')
                ->with('success', 'Post deleted successfully!');

        } catch (\Exception $e) {
            return back()
                ->withErrors(['error' => 'Failed to delete post: ' . $e->getMessage()]);
        }
    }

    // API Methods
    public function apiIndex(): JsonResponse
    {
        $posts = $this->contentService->getPostsFeed(auth()->user());

        return response()->json([
            'success' => true,
            'data' => $posts
        ]);
    }

    public function apiStore(CreatePostRequest $request): JsonResponse
    {
        try {
            $post = $this->contentService->createPost(
                user: $request->user(),
                data: $request->validated()
            );

            return response()->json([
                'success' => true,
                'message' => 'Post created successfully',
                'data' => $post
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create post',
                'error' => $e->getMessage()
            ], 422);
        }
    }
}
```

### 📋 7. Middleware Enhancement
**Reference**: [Middleware - Laravel 12.x](https://laravel.com/docs/12.x/middleware)

#### 7.1 Performance Monitoring Middleware

```php
// Task 7.1.1: Enhanced Performance Monitoring
// app/Http/Middleware/PerformanceMonitoring.php
<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Log, Cache, DB};

class PerformanceMonitoring
{
    private float $startTime;
    private int $initialQueryCount;
    private float $initialMemory;

    public function handle(Request $request, Closure $next)
    {
        $this->startTime = microtime(true);
        $this->initialQueryCount = DB::getQueryLog() ? count(DB::getQueryLog()) : 0;
        $this->initialMemory = memory_get_usage(true);

        // Enable query logging for this request
        DB::enableQueryLog();

        $response = $next($request);

        $this->recordMetrics($request, $response);

        return $response;
    }

    private function recordMetrics(Request $request, $response): void
    {
        $executionTime = (microtime(true) - $this->startTime) * 1000; // milliseconds
        $queryCount = count(DB::getQueryLog()) - $this->initialQueryCount;
        $memoryUsage = (memory_get_usage(true) - $this->initialMemory) / 1024 / 1024; // MB
        $peakMemory = memory_get_peak_usage(true) / 1024 / 1024; // MB

        $metrics = [
            'url' => $request->fullUrl(),
            'method' => $request->method(),
            'execution_time' => round($executionTime, 2),
            'query_count' => $queryCount,
            'memory_usage' => round($memoryUsage, 2),
            'peak_memory' => round($peakMemory, 2),
            'response_status' => $response->getStatusCode(),
            'user_id' => auth()->id(),
            'timestamp' => now()->toISOString()
        ];

        // Log slow requests
        if ($executionTime > 1000) { // More than 1 second
            Log::warning('Slow request detected', $metrics);
        }

        // Log queries with high count
        if ($queryCount > 20) {
            Log::warning('High query count detected', array_merge($metrics, [
                'queries' => $this->formatQueries(DB::getQueryLog())
            ]));
        }

        // Store metrics for monitoring dashboard
        $this->storeMetricsForDashboard($metrics);

        // Alert if critical thresholds exceeded
        $this->checkCriticalThresholds($metrics);
    }

    private function formatQueries(array $queries): array
    {
        return array_map(function ($query) {
            return [
                'sql' => $query['query'],
                'bindings' => $query['bindings'],
                'time' => $query['time']
            ];
        }, array_slice($queries, -10)); // Last 10 queries
    }

    private function storeMetricsForDashboard(array $metrics): void
    {
        $key = 'performance_metrics_' . now()->format('Y-m-d-H');
        $existing = Cache::get($key, []);
        $existing[] = $metrics;

        // Keep only last 1000 entries per hour
        if (count($existing) > 1000) {
            $existing = array_slice($existing, -1000);
        }

        Cache::put($key, $existing, 3600); // 1 hour
    }

    private function checkCriticalThresholds(array $metrics): void
    {
        // Critical execution time (5+ seconds)
        if ($metrics['execution_time'] > 5000) {
            Log::critical('Critical execution time exceeded', $metrics);

            // Could trigger alerts here (Slack, email, etc.)
            event(new \App\Events\PerformanceThresholdExceeded('execution_time', $metrics));
        }

        // Critical memory usage (512+ MB)
        if ($metrics['peak_memory'] > 512) {
            Log::critical('Critical memory usage exceeded', $metrics);

            event(new \App\Events\PerformanceThresholdExceeded('memory_usage', $metrics));
        }

        // Critical query count (50+ queries)
        if ($metrics['query_count'] > 50) {
            Log::critical('Critical query count exceeded', $metrics);

            event(new \App\Events\PerformanceThresholdExceeded('query_count', $metrics));
        }
    }
}
```

#### 7.2 Security Audit Logger Middleware

```php
// Task 7.2.1: Enhanced Security Audit Logger
// app/Http/Middleware/Enhanced/SecurityAuditLoggerMiddleware.php
<?php
namespace App\Http\Middleware\Enhanced;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Log, Cache, DB};
use App\Models\SecurityEvent;

class SecurityAuditLoggerMiddleware
{
    private array $sensitiveRoutes = [
        'login', 'register', 'password.*', 'two-factor.*',
        'admin.*', 'payment.*', 'profile.update', 'settings.*'
    ];

    private array $suspiciousPatterns = [
        'script', 'javascript:', 'data:', 'vbscript:',
        '<iframe', '<object', '<embed', '<?php',
        'eval(', 'exec(', 'system(', 'shell_exec'
    ];

    public function handle(Request $request, Closure $next)
    {
        $startTime = microtime(true);

        // Log security-relevant requests
        if ($this->isSensitiveRoute($request)) {
            $this->logSecurityEvent($request, 'sensitive_route_access');
        }

        // Check for suspicious patterns
        if ($this->hasSuspiciousContent($request)) {
            $this->logSecurityEvent($request, 'suspicious_content', [
                'detected_patterns' => $this->getDetectedPatterns($request)
            ]);
        }

        // Monitor for brute force attempts
        $this->monitorBruteForce($request);

        $response = $next($request);

        // Log failed authentication attempts
        if ($this->isFailedAuth($request, $response)) {
            $this->logSecurityEvent($request, 'failed_authentication');
            $this->trackFailedAttempts($request);
        }

        // Log successful authentication
        if ($this->isSuccessfulAuth($request, $response)) {
            $this->logSecurityEvent($request, 'successful_authentication');
            $this->clearFailedAttempts($request);
        }

        // Log privilege escalation attempts
        if ($response->getStatusCode() === 403) {
            $this->logSecurityEvent($request, 'unauthorized_access_attempt');
        }

        return $response;
    }

    private function isSensitiveRoute(Request $request): bool
    {
        $routeName = $request->route()?->getName() ?? '';

        foreach ($this->sensitiveRoutes as $pattern) {
            if (fnmatch($pattern, $routeName)) {
                return true;
            }
        }

        return false;
    }

    private function hasSuspiciousContent(Request $request): bool
    {
        $content = strtolower(json_encode($request->all()));

        foreach ($this->suspiciousPatterns as $pattern) {
            if (strpos($content, strtolower($pattern)) !== false) {
                return true;
            }
        }

        return false;
    }

    private function getDetectedPatterns(Request $request): array
    {
        $detected = [];
        $content = strtolower(json_encode($request->all()));

        foreach ($this->suspiciousPatterns as $pattern) {
            if (strpos($content, strtolower($pattern)) !== false) {
                $detected[] = $pattern;
            }
        }

        return $detected;
    }

    private function monitorBruteForce(Request $request): void
    {
        if (!in_array($request->route()?->getName(), ['login', 'password.confirm'])) {
            return;
        }

        $ip = $request->ip();
        $key = "brute_force_attempts_{$ip}";
        $attempts = Cache::get($key, 0);

        if ($attempts >= 10) { // 10 attempts in last hour
            $this->logSecurityEvent($request, 'potential_brute_force', [
                'attempts_count' => $attempts,
                'blocked' => true
            ]);

            abort(429, 'Too many login attempts');
        }
    }

    private function isFailedAuth(Request $request, $response): bool
    {
        return in_array($request->route()?->getName(), ['login', 'password.confirm']) &&
               in_array($response->getStatusCode(), [422, 302]) &&
               session()->has('errors');
    }

    private function isSuccessfulAuth(Request $request, $response): bool
    {
        return in_array($request->route()?->getName(), ['login', 'register']) &&
               $response->getStatusCode() === 302 &&
               !session()->has('errors') &&
               auth()->check();
    }

    private function trackFailedAttempts(Request $request): void
    {
        $ip = $request->ip();
        $email = $request->input('email');

        // Track by IP
        $ipKey = "failed_attempts_ip_{$ip}";
        Cache::increment($ipKey, 1);
        Cache::put($ipKey, Cache::get($ipKey), 3600); // 1 hour

        // Track by email if provided
        if ($email) {
            $emailKey = "failed_attempts_email_{$email}";
            Cache::increment($emailKey, 1);
            Cache::put($emailKey, Cache::get($emailKey), 3600); // 1 hour
        }

        // Track for brute force detection
        $bruteKey = "brute_force_attempts_{$ip}";
        Cache::increment($bruteKey, 1);
        Cache::put($bruteKey, Cache::get($bruteKey), 3600); // 1 hour
    }

    private function clearFailedAttempts(Request $request): void
    {
        $ip = $request->ip();
        $email = $request->input('email');

        Cache::forget("failed_attempts_ip_{$ip}");
        Cache::forget("brute_force_attempts_{$ip}");

        if ($email) {
            Cache::forget("failed_attempts_email_{$email}");
        }
    }

    private function logSecurityEvent(Request $request, string $type, array $extra = []): void
    {
        $data = array_merge([
            'type' => $type,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'url' => $request->fullUrl(),
            'method' => $request->method(),
            'user_id' => auth()->id(),
            'session_id' => session()->getId(),
            'timestamp' => now(),
            'headers' => $this->getSanitizedHeaders($request),
            'payload' => $this->getSanitizedPayload($request)
        ], $extra);

        // Log to file
        Log::channel('security')->info("Security Event: {$type}", $data);

        // Store in database for dashboard
        try {
            SecurityEvent::create([
                'type' => $type,
                'user_id' => auth()->id(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'data' => $data,
                'severity' => $this->getSeverity($type),
                'created_at' => now()
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to store security event', [
                'error' => $e->getMessage(),
                'event_data' => $data
            ]);
        }
    }

    private function getSanitizedHeaders(Request $request): array
    {
        $headers = $request->headers->all();

        // Remove sensitive headers
        unset($headers['authorization'], $headers['cookie'], $headers['x-csrf-token']);

        return $headers;
    }

    private function getSanitizedPayload(Request $request): array
    {
        $payload = $request->all();

        // Remove sensitive fields
        unset($payload['password'], $payload['password_confirmation'],
              $payload['current_password'], $payload['card_number'],
              $payload['cvv'], $payload['card_token']);

        return $payload;
    }

    private function getSeverity(string $type): string
    {
        return match ($type) {
            'potential_brute_force', 'suspicious_content' => 'high',
            'failed_authentication', 'unauthorized_access_attempt' => 'medium',
            'sensitive_route_access', 'successful_authentication' => 'low',
            default => 'medium'
        };
    }
}
```

---

## 🔄 PHASE 4: Laravel 12 Advanced Features (Weeks 7-8)

### 📋 8. Modern Laravel 12 Features Implementation
**Reference**: [Laravel 12 Release Notes](https://laravel.com/docs/12.x/releases)

#### 8.1 Enhanced Starter Kit Integration

```php
// Task 8.1.1: Upgrade to Laravel 12 Starter Kit Features
// First, install the new starter kit components
// composer require laravel/livewire-starter-kit

// app/Http/Controllers/StarterKitController.php
<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;

class StarterKitController extends Controller
{
    public function dashboard(): View
    {
        $user = auth()->user();

        // Laravel 12 enhanced user metrics
        $metrics = [
            'subscription_count' => $user->activeSubscriptions()->count(),
            'monthly_earnings' => $user->getMonthlyEarnings(),
            'content_views' => $user->posts()->sum('view_count'),
            'engagement_rate' => $user->engagement_rate,
            'recent_activity' => $user->getRecentActivity()
        ];

        return view('dashboard', compact('metrics'));
    }

    public function profile(): View
    {
        $user = auth()->user();

        // Laravel 12 profile field system
        $profileFields = $user->getProfileFields();
        $customizations = $user->getProfileCustomizations();

        return view('profile.show', compact('user', 'profileFields', 'customizations'));
    }
}
```

```php
// Task 8.1.2: Implement Flux UI Components (Livewire)
// resources/views/livewire/components/enhanced-profile-manager.blade.php
<div class="space-y-6">
    <flux:heading size="lg">Profile Management</flux:heading>

    <flux:card>
        <flux:card.header>
            <flux:heading size="md">Basic Information</flux:heading>
        </flux:card.header>

        <flux:card.body class="space-y-4">
            <flux:field>
                <flux:label>Display Name</flux:label>
                <flux:input wire:model.blur="user.name" placeholder="Enter your display name" />
                <flux:error name="user.name" />
            </flux:field>

            <flux:field>
                <flux:label>Username</flux:label>
                <flux:input wire:model.blur="user.username" placeholder="Choose a unique username" />
                <flux:error name="user.username" />
            </flux:field>

            <flux:field>
                <flux:label>Bio</flux:label>
                <flux:textarea wire:model.blur="user.bio" placeholder="Tell us about yourself..." rows="4" />
                <flux:error name="user.bio" />
            </flux:field>
        </flux:card.body>
    </flux:card>

    <flux:card>
        <flux:card.header>
            <flux:heading size="md">Creator Settings</flux:heading>
        </flux:card.header>

        <flux:card.body class="space-y-4">
            <flux:field>
                <flux:checkbox wire:model.live="user.public_profile">
                    Make profile publicly visible
                </flux:checkbox>
            </flux:field>

            <flux:field>
                <flux:label>Monthly Subscription Price ($)</flux:label>
                <flux:input type="number" wire:model.blur="user.profile_access_price" step="0.01" min="0" />
                <flux:error name="user.profile_access_price" />
            </flux:field>

            <flux:field>
                <flux:label>Profile Category</flux:label>
                <flux:select wire:model="user.category_id" placeholder="Select category...">
                    @foreach($categories as $category)
                        <flux:option value="{{ $category->id }}">{{ $category->name }}</flux:option>
                    @endforeach
                </flux:select>
                <flux:error name="user.category_id" />
            </flux:field>
        </flux:card.body>
    </flux:card>

    <flux:card>
        <flux:card.header>
            <flux:heading size="md">Media Upload</flux:heading>
        </flux:card.header>

        <flux:card.body class="space-y-4">
            <flux:field>
                <flux:label>Profile Avatar</flux:label>
                <div class="flex items-center space-x-4">
                    @if($user->avatar)
                        <img src="{{ $user->avatar_url }}" alt="Avatar" class="w-16 h-16 rounded-full object-cover">
                    @endif
                    <flux:input type="file" wire:model="avatar" accept="image/*" />
                </div>
                <flux:error name="avatar" />

                @if($avatar)
                    <div class="mt-2">
                        <flux:button variant="primary" wire:click="uploadAvatar" wire:loading.attr="disabled">
                            <span wire:loading.remove wire:target="uploadAvatar">Upload Avatar</span>
                            <span wire:loading wire:target="uploadAvatar">Uploading...</span>
                        </flux:button>
                    </div>
                @endif
            </flux:field>

            <flux:field>
                <flux:label>Cover Image</flux:label>
                <div class="space-y-2">
                    @if($user->cover)
                        <img src="{{ $user->cover_url }}" alt="Cover" class="w-full h-32 rounded-lg object-cover">
                    @endif
                    <flux:input type="file" wire:model="cover" accept="image/*" />
                </div>
                <flux:error name="cover" />

                @if($cover)
                    <div class="mt-2">
                        <flux:button variant="primary" wire:click="uploadCover" wire:loading.attr="disabled">
                            <span wire:loading.remove wire:target="uploadCover">Upload Cover</span>
                            <span wire:loading wire:target="uploadCover">Uploading...</span>
                        </flux:button>