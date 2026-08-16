<?php

namespace Tests\Feature;

use App\Mail\WelcomeUserMail;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UserWelcomeMailTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsSuperadmin(): User
    {
        $admin = User::factory()->create(['role' => 'superadmin']);
        Sanctum::actingAs($admin);

        return $admin;
    }

    public function test_creating_user_generates_password_and_sends_welcome_mail(): void
    {
        Mail::fake();
        $this->actingAsSuperadmin();

        $response = $this->postJson('/api/v1/admin/users', [
            'name' => 'Maria Gomez',
            'email' => 'maria@example.com',
            'role' => 'store_owner',
        ]);

        $response->assertSuccessful();

        $user = User::where('email', 'maria@example.com')->first();

        $this->assertNotNull($user);
        $this->assertNull($user->store);
        $this->assertNotSame('password', $user->password);

        Mail::assertSent(WelcomeUserMail::class, function (WelcomeUserMail $mail) use ($user) {
            return $mail->email === 'maria@example.com'
                && $mail->storeName === null
                && $mail->loginUrl === config('app.frontend_url').'/login'
                && Hash::check($mail->password, $user->password);
        });
    }

    public function test_creating_user_with_store_includes_store_in_mail(): void
    {
        Mail::fake();
        $this->actingAsSuperadmin();

        $store = Store::create(['name' => 'Spa Demo']);

        $this->postJson('/api/v1/admin/users', [
            'name' => 'Carlos Ruiz',
            'email' => 'carlos@example.com',
            'role' => 'store_owner',
            'store_id' => $store->id,
        ])->assertSuccessful();

        Mail::assertSent(WelcomeUserMail::class, function (WelcomeUserMail $mail) {
            return $mail->storeName === 'Spa Demo';
        });
    }
}
