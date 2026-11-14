<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureUserIsAdmin;
use App\Http\Middleware\EnsureUserIsScorer;
use App\Models\User;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class ScorerMiddlewareTest extends TestCase
{
    public function test_scorer_alias_is_registered_alongside_existing_admin_alias(): void
    {
        $aliases = $this->app['router']->getMiddleware();
        $this->assertSame(EnsureUserIsScorer::class, $aliases['scorer']);
        $this->assertSame(EnsureUserIsAdmin::class, $aliases['admin']);
    }

    public function test_resolved_scorer_reaches_the_next_handler(): void
    {
        $user = new User;
        $user->setRawAttributes(['id' => 123, 'is_scorer' => 1, 'is_admin' => 0, 'company_id' => null]);
        $request = Request::create('/scorer');
        $request->attributes->set('user', $user);
        $response = (new EnsureUserIsScorer)->handle($request, function ($passedRequest) use ($request, $user) {
            $this->assertSame($request, $passedRequest);
            $this->assertSame($user, $passedRequest->attributes->get('user'));

            return response('Allowed', 202);
        });
        $this->assertSame(202, $response->getStatusCode());
        $this->assertSame('Allowed', $response->getContent());
    }

    public static function nonScorerRequests(): array
    {
        return [
            'guest' => [null],
            'representative' => [['is_scorer' => 0, 'is_admin' => 0, 'company_id' => 1]],
            'pending account' => [['is_scorer' => 0, 'is_admin' => 0, 'company_id' => null]],
            'admin without scorer permission' => [['is_scorer' => 0, 'is_admin' => 1, 'company_id' => null]],
            'missing scorer flag' => [['is_admin' => 0, 'company_id' => null]],
        ];
    }

    #[DataProvider('nonScorerRequests')]
    public function test_non_scorers_are_forbidden_before_the_next_handler(?array $attributes): void
    {
        $request = Request::create('/scorer');
        if ($attributes !== null) {
            $user = new User;
            $user->setRawAttributes($attributes);
            $request->attributes->set('user', $user);
        }
        try {
            (new EnsureUserIsScorer)->handle($request, fn () => $this->fail('Unauthorized request reached the handler.'));
            $this->fail('Unauthorized request was accepted.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
    }

    public function test_cookie_alone_does_not_replace_the_authentication_middleware(): void
    {
        $request = Request::create('/scorer', cookies: ['company_token' => 'unresolved-token']);
        try {
            (new EnsureUserIsScorer)->handle($request, fn () => $this->fail('Unresolved request reached the handler.'));
            $this->fail('A cookie without a resolved user was accepted.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
    }
}
