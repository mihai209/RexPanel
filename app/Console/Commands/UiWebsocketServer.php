<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\UserNotification;
use App\Services\UiWebsocketRedisService;
use Illuminate\Console\Command;
use Laravel\Sanctum\PersonalAccessToken;
use Workerman\Connection\TcpConnection;
use Workerman\Worker;

class UiWebsocketServer extends Command
{
    protected $signature = 'app:ui-websocket {action=start} {--daemon}';

    protected $description = 'Start the RA-panel UI websocket server using Workerman';

    protected static array $userConnections = [];

    public function handle(): void
    {
        $host = env('UI_WS_SERVER_HOST', '0.0.0.0');
        $port = (int) env('UI_WS_SERVER_PORT', 8082);

        $this->info("Starting UI WebSocket server on {$host}:{$port}...");

        global $argv;
        $argv[1] = $this->argument('action');
        if ($this->option('daemon')) {
            $argv[2] = '-d';
        }

        $worker = new Worker("websocket://{$host}:{$port}");
        $worker->count = 1;

        $worker->onWorkerStart = function (): void {
            \Workerman\Timer::add(0.1, function (): void {
                $message = app(UiWebsocketRedisService::class)->consumeQueuedEvent();

                if (! $message) {
                    return;
                }

                $userId = (int) ($message['user_id'] ?? 0);
                $payload = $message['payload'] ?? null;

                if ($userId < 1 || ! is_array($payload)) {
                    return;
                }

                foreach (self::$userConnections[$userId] ?? [] as $connection) {
                    if ($connection->readyState === TcpConnection::STATUS_ESTABLISHED) {
                        $connection->send(json_encode($payload, JSON_UNESCAPED_SLASHES));
                    }
                }
            });
        };

        $worker->onConnect = function (TcpConnection $connection): void {
            $connection->authenticated = false;
            $connection->userId = null;
        };

        $worker->onMessage = function (TcpConnection $connection, string $data): void {
            $message = json_decode($data, true);
            if (! is_array($message)) {
                return;
            }

            if (($message['type'] ?? null) === 'auth') {
                $this->handleAuth($connection, (string) ($message['token'] ?? ''));
                return;
            }

            if (! $connection->authenticated) {
                $connection->send(json_encode(['type' => 'auth_fail', 'error' => 'Not authenticated']));
                $connection->close();
                return;
            }

            if (($message['type'] ?? null) === 'ping') {
                $connection->send(json_encode(['type' => 'pong']));
            }
        };

        $worker->onClose = function (TcpConnection $connection): void {
            if (! $connection->userId) {
                return;
            }

            $userId = (int) $connection->userId;

            if (isset(self::$userConnections[$userId])) {
                self::$userConnections[$userId] = array_values(array_filter(
                    self::$userConnections[$userId],
                    fn (TcpConnection $client) => $client !== $connection
                ));

                if (count(self::$userConnections[$userId]) === 0) {
                    unset(self::$userConnections[$userId]);
                }
            }

            $this->syncSocketCount($userId);
        };

        Worker::runAll();
    }

    private function handleAuth(TcpConnection $connection, string $token): void
    {
        $accessToken = PersonalAccessToken::findToken($token);
        $user = $accessToken?->tokenable;

        if (! $user instanceof User) {
            $connection->send(json_encode(['type' => 'auth_fail', 'error' => 'Invalid token']));
            $connection->close();
            return;
        }

        $connection->authenticated = true;
        $connection->userId = $user->id;
        self::$userConnections[$user->id] ??= [];
        self::$userConnections[$user->id][] = $connection;

        $this->syncSocketCount($user->id);

        $connection->send(json_encode([
            'type' => 'auth_success',
            'userId' => $user->id,
            'unreadCount' => UserNotification::query()
                ->where('user_id', $user->id)
                ->where('is_read', false)
                ->count(),
        ], JSON_UNESCAPED_SLASHES));
    }

    private function syncSocketCount(int $userId): void
    {
        app(UiWebsocketRedisService::class)->syncSocketCount($userId, count(self::$userConnections[$userId] ?? []));
    }
}
