<?php

namespace Tests\Auth;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Illuminate\Http\Request;
use Laravel\Socialite\Two\GoogleProvider;
use Laravel\Socialite\Two\InvalidStateException;
use Tests\TestCase;

class GooglePreparationTest extends TestCase
{
    public function test_google_buttons_are_disabled_without_credentials(): void
    {
        config(['services.google.client_id' => null, 'services.google.client_secret' => null]);
        $this->get('/login')->assertOk()->assertSee('Tiếp tục với Google')
            ->assertSee('disabled aria-describedby="google-availability"', false);
        $this->get('/register')->assertOk()->assertSee('Đăng ký với Google')
            ->assertSee('disabled aria-describedby="google-availability"', false);
        $this->get('/auth/google/callback')->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_socialite_keeps_state_and_maps_google_subject_using_mock_http(): void
    {
        $request = Request::create('/auth/google/redirect');
        $request->setLaravelSession($this->app['session.store']);
        $provider = new GoogleProvider($request, 'test-client', 'test-secret', 'http://localhost/auth/google/callback');
        $redirect = $provider->redirect();
        parse_str(parse_url($redirect->getTargetUrl(), PHP_URL_QUERY), $parameters);
        $this->assertNotEmpty($parameters['state']);
        $this->assertSame($request->session()->get('state'), $parameters['state']);
        $this->assertStringContainsString('openid', $parameters['scope']);

        $request->query->set('state', $parameters['state']);
        $request->query->set('code', 'mock-code');
        $mock = new MockHandler([
            new Response(200, [], json_encode(['access_token' => 'mock-access-token', 'token_type' => 'Bearer'])),
            new Response(200, [], json_encode([
                'sub' => 'stable-google-subject',
                'email' => 'google@test.invalid',
                'email_verified' => true,
                'name' => 'Google Test',
            ])),
        ]);
        $provider->setHttpClient(new Client(['handler' => HandlerStack::create($mock)]));
        $user = $provider->user();
        $this->assertSame('stable-google-subject', $user->getId());
        $this->assertTrue($user->user['email_verified']);
        $this->assertNull($request->session()->get('state'));
        $this->assertGuest();

        // Callback thứ hai dùng provider mới như một HTTP request mới; state đã bị tiêu thụ.
        $replayed = new GoogleProvider($request, 'test-client', 'test-secret', 'http://localhost/auth/google/callback');
        $replayed->setHttpClient(new Client(['handler' => HandlerStack::create(new MockHandler([]))]));
        $this->expectException(InvalidStateException::class);
        $replayed->user();
    }

    public function test_socialite_rejects_invalid_state_before_any_google_request(): void
    {
        $request = Request::create('/auth/google/callback', 'GET', ['state' => 'invalid', 'code' => 'mock-code']);
        $request->setLaravelSession($this->app['session.store']);
        $request->session()->put('state', 'expected');
        $provider = new GoogleProvider($request, 'test-client', 'test-secret', 'http://localhost/auth/google/callback');
        $provider->setHttpClient(new Client(['handler' => HandlerStack::create(new MockHandler([]))]));
        $this->expectException(InvalidStateException::class);
        $provider->user();
    }
}
