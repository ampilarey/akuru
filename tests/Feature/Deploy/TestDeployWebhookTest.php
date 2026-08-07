<?php

declare(strict_types=1);

namespace Tests\Feature\Deploy;

use App\Support\Services\TestDeployTrigger;
use Tests\TestCase;

class TestDeployWebhookTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'deploy.test_webhook_secret' => 'test-deploy-secret-at-least-16',
            'deploy.test_allowed_hosts' => ['test.akuru.edu.mv'],
            'app.url' => 'https://test.akuru.edu.mv',
        ]);
    }

    public function test_missing_secret_returns_404(): void
    {
        config(['deploy.test_webhook_secret' => '']);

        $this->postJson('https://test.akuru.edu.mv/api/deploy/test-pull', [
            'sha' => str_repeat('a', 40),
        ])->assertNotFound();
    }

    public function test_wrong_secret_returns_401(): void
    {
        $this->withHeader('Authorization', 'Bearer wrong-secret-value-here')
            ->postJson('https://test.akuru.edu.mv/api/deploy/test-pull', [
                'sha' => str_repeat('a', 40),
            ])
            ->assertUnauthorized();
    }

    public function test_production_host_returns_404(): void
    {
        config(['app.url' => 'https://akuru.edu.mv']);

        $this->withHeader('Authorization', 'Bearer test-deploy-secret-at-least-16')
            ->postJson('https://akuru.edu.mv/api/deploy/test-pull', [
                'sha' => str_repeat('a', 40),
            ])
            ->assertNotFound();
    }

    public function test_valid_request_triggers_async_deploy(): void
    {
        $sha = str_repeat('b', 40);

        $this->mock(TestDeployTrigger::class, function ($mock) use ($sha) {
            $mock->shouldReceive('triggerAsync')
                ->once()
                ->with($sha)
                ->andReturn(['ok' => true, 'message' => 'Deploy started']);
        });

        $this->withHeader('Authorization', 'Bearer test-deploy-secret-at-least-16')
            ->postJson('https://test.akuru.edu.mv/api/deploy/test-pull', [
                'sha' => $sha,
            ])
            ->assertAccepted()
            ->assertJsonPath('message', 'Deploy started')
            ->assertJsonPath('sha', $sha);
    }
}
