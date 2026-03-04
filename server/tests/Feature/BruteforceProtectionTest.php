<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;
use Carbon\Carbon;

class BruteforceProtectionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Ensure we are using a clean state for the test user
        User::where('email', 'test@example.com')->delete();
        User::where('email', 'faculty@example.com')->delete();
        User::where('email', 'admin@example.com')->delete();
    }

    public function test_account_is_locked_after_5_failed_attempts()
    {
        $user = User::create([
            'email' => 'test@example.com',
            'password' => Hash::make('correct-password'),
            'first_name' => 'Test',
            'last_name' => 'User',
            'is_active' => true,
        ]);

        $loginData = [
            'email' => 'test@example.com',
            'password' => 'wrong-password',
            'skip_captcha' => true
        ];

        // 1-4 Attempts: Should return 401 with attempts_left
        for ($i = 1; $i <= 4; $i++) {
            $response = $this->postJson('/api/login', $loginData);
            $response->assertStatus(401);
            $response->assertJson(['attempts_left' => 5 - $i]);
        }

        // 5th Attempt: Should return 401 and trigger lockout
        $response = $this->postJson('/api/login', $loginData);
        $response->assertStatus(401);
        $response->assertJson(['attempts_left' => 0, 'is_locked' => true]);

        // Verify user is now locked in DB
        $user->refresh();
        $this->assertEquals(5, $user->login_attempts);
        $this->assertNotNull($user->lock_until);

        // Verify Activity Logs
        $this->assertDatabaseHas('user_activity_logs', [
            'actor_email' => 'test@example.com',
            'action' => 'account_lockout'
        ]);

        // 6th Attempt: Should return 423 Locked with "Wait until time is end" message (now with m/s)
        $response = $this->postJson('/api/login', $loginData);
        $response->assertStatus(423);
        $this->assertStringContainsString('Wait until time is end to login again', $response->json('message'));
        $this->assertStringContainsString('Try again in', $response->json('message'));
        $this->assertNotNull($response->json('lock_seconds_remaining'));
    }

    public function test_successful_login_resets_attempts()
    {
        $user = User::create([
            'email' => 'test@example.com',
            'password' => Hash::make('correct-password'),
            'first_name' => 'Test',
            'last_name' => 'User',
            'is_active' => true,
        ]);

        // Failed attempt
        $this->postJson('/api/login', [
            'email' => 'test@example.com',
            'password' => 'wrong-password',
            'skip_captcha' => true
        ]);

        $user->refresh();
        $this->assertEquals(1, $user->login_attempts);

        // Successful login
        $response = $this->postJson('/api/login', [
            'email' => 'test@example.com',
            'password' => 'correct-password',
            'skip_captcha' => true
        ]);

        $response->assertStatus(200);
        
        $user->refresh();
        $this->assertEquals(0, $user->login_attempts);
        $this->assertNull($user->lock_until);
    }

    public function test_lockout_expires_after_5_minutes()
    {
        $user = User::create([
            'email' => 'test@example.com',
            'password' => Hash::make('correct-password'),
            'first_name' => 'Test',
            'last_name' => 'User',
            'is_active' => true,
            'login_attempts' => 5,
            'lock_until' => now()->addMinutes(5)
        ]);

        // Immediate attempt: 423
        $response = $this->postJson('/api/login', [
            'email' => 'test@example.com',
            'password' => 'correct-password',
            'skip_captcha' => true
        ]);
        $response->assertStatus(423);

        // Travel 6 minutes into the future
        Carbon::setTestNow(now()->addMinutes(6));

        // Attempt after expiry: 200
        $response = $this->postJson('/api/login', [
            'email' => 'test@example.com',
            'password' => 'correct-password',
            'skip_captcha' => true
        ]);
        $response->assertStatus(200);

        Carbon::setTestNow(); // Reset time
    }

    public function test_lockout_only_affects_the_specific_account()
    {
        // 1. Create two users
        $faculty = User::create([
            'email' => 'faculty@example.com',
            'password' => Hash::make('faculty-password'),
            'first_name' => 'Faculty',
            'last_name' => 'User',
            'is_active' => true,
        ]);

        $admin = User::create([
            'email' => 'admin@example.com',
            'password' => Hash::make('admin-password'),
            'first_name' => 'Admin',
            'last_name' => 'User',
            'is_active' => true,
        ]);

        // 2. Lockout faculty
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/login', [
                'email' => 'faculty@example.com',
                'password' => 'wrong-password',
                'skip_captcha' => true
            ]);
        }

        // Verify faculty is locked
        $response = $this->postJson('/api/login', [
            'email' => 'faculty@example.com',
            'password' => 'wrong-password',
            'skip_captcha' => true
        ]);
        $response->assertStatus(423);

        // 3. Verify admin can still login correctly
        $response = $this->postJson('/api/login', [
            'email' => 'admin@example.com',
            'password' => 'admin-password',
            'skip_captcha' => true
        ]);
        
        $response->assertStatus(200);
        $response->assertJsonFragment(['email' => 'admin@example.com']);
    }
}
