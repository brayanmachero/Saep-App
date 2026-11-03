<?php

namespace Tests\Feature;

use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Http\Request;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class ExpiredSessionResponseTest extends TestCase
{
    public function createApplication(): \Illuminate\Foundation\Application
    {
        $app = require __DIR__.'/../../bootstrap/app.php';
        $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
        return $app;
    }

    public function test_expired_html_post_redirects_with_visible_message_without_replaying_input(): void
    {
        Log::spy();
        $request = $this->request();
        $response = $this->app->make(ExceptionHandler::class)->render($request, new TokenMismatchException());

        $this->assertSame(303, $response->getStatusCode());
        $this->assertSame(route('login'), $response->headers->get('Location'));
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('no se guardaron', session('error'));
        $this->assertFalse(session()->has('_old_input'));
        Log::shouldHaveReceived('notice')->once()->with('session.csrf_expired', [
            'route' => null, 'method' => 'POST', 'json' => false,
        ]);
    }

    public function test_json_and_ajax_keep_419_with_machine_readable_recovery(): void
    {
        Log::spy();
        foreach ([['Accept', 'application/json'], ['X-Requested-With', 'XMLHttpRequest']] as [$header, $value]) {
            $request = $this->request();
            $request->headers->set($header, $value);
            $response = $this->app->make(ExceptionHandler::class)->render($request, new TokenMismatchException());
            $this->assertSame(419, $response->getStatusCode());
            $this->assertNull($response->headers->get('Location'));
            $data = json_decode($response->getContent(), true);
            $this->assertSame('SESSION_EXPIRED', $data['code']);
            $this->assertSame(route('login'), $data['login_url']);
        }
    }

    public function test_other_errors_are_not_redirected(): void
    {
        Log::spy();
        $request = $this->request();
        $request->headers->set('Accept', 'application/json');
        $response = $this->app->make(ExceptionHandler::class)->render($request, new HttpException(403));
        $this->assertSame(403, $response->getStatusCode());
        Log::shouldNotHaveReceived('notice');
    }

    public function test_login_displays_expiration_notice_and_a_new_csrf_form(): void
    {
        $this->withSession(['error' => 'El formulario venció.'])->get('/login')
            ->assertOk()
            ->assertSee('El formulario venció.')
            ->assertSee('role="alert"', false)
            ->assertSee('name="_token"', false);
    }

    private function request(): Request
    {
        $request = Request::create('/login', 'POST', ['password' => 'never-log-this', '_token' => 'stale']);
        $request->headers->set('Accept', 'text/html');
        $request->setLaravelSession(new Store('test', new ArraySessionHandler(120)));
        return $request;
    }
}
