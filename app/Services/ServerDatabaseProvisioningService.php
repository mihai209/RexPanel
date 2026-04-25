<?php

namespace App\Services;

use App\Models\Database;
use App\Models\DatabaseHost;
use App\Models\Server;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PDO;
use PDOException;

class ServerDatabaseProvisioningService
{
    public function list(Server $server)
    {
        return $server->databases()->with('databaseHost.location')->orderBy('database')->get();
    }

    public function usage(Server $server): array
    {
        $used = $server->databases()->count();
        $limit = $server->database_limit;

        return [
            'used' => $used,
            'limit' => $limit,
            'remaining' => $limit === null ? null : max(0, $limit - $used),
            'is_unlimited' => $limit === null,
            'stored_only_backup_limit' => $server->backup_limit,
        ];
    }

    public function create(Server $server, array $data): Database
    {
        $this->assertDatabaseLimit($server);

        $host = $this->resolveEligibleHost($server, $data);
        $dialect = $this->dialect($host);
        $remote = trim((string) ($data['remote'] ?? '%')) ?: '%';
        $databaseName = $this->buildDatabaseName($server, (string) $data['database'], $host);
        $username = $this->buildUsername($server, (string) $data['database'], $host);
        $password = Str::password(24, true, true, false, false);

        $pdo = $this->connectToHost($host);

        try {
            if ($dialect === 'postgres') {
                $pdo->exec(sprintf('CREATE DATABASE %s', $this->quoteIdentifier($databaseName, $dialect)));
                $pdo->exec(sprintf(
                    'DO $$ BEGIN IF NOT EXISTS (SELECT 1 FROM pg_roles WHERE rolname = %s) THEN CREATE ROLE %s LOGIN PASSWORD %s; ELSE ALTER ROLE %s WITH LOGIN PASSWORD %s; END IF; END $$;',
                    $pdo->quote($username),
                    $this->quoteIdentifier($username, $dialect),
                    $pdo->quote($password),
                    $this->quoteIdentifier($username, $dialect),
                    $pdo->quote($password),
                ));
                $pdo->exec(sprintf(
                    'GRANT ALL PRIVILEGES ON DATABASE %s TO %s',
                    $this->quoteIdentifier($databaseName, $dialect),
                    $this->quoteIdentifier($username, $dialect),
                ));
            } else {
                $pdo->exec(sprintf(
                    'CREATE DATABASE IF NOT EXISTS %s CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci',
                    $this->quoteIdentifier($databaseName, $dialect)
                ));
                $pdo->exec(sprintf(
                    'CREATE USER IF NOT EXISTS %s@%s IDENTIFIED BY %s',
                    $pdo->quote($username),
                    $pdo->quote($remote),
                    $pdo->quote($password)
                ));
                $pdo->exec(sprintf(
                    'ALTER USER %s@%s IDENTIFIED BY %s',
                    $pdo->quote($username),
                    $pdo->quote($remote),
                    $pdo->quote($password)
                ));
                $pdo->exec(sprintf(
                    'GRANT ALL PRIVILEGES ON %s.* TO %s@%s',
                    $this->quoteIdentifier($databaseName, $dialect),
                    $pdo->quote($username),
                    $pdo->quote($remote)
                ));
                $pdo->exec('FLUSH PRIVILEGES');
            }
        } catch (PDOException $exception) {
            throw ValidationException::withMessages([
                'database' => $this->provisioningErrorMessage($exception, $host),
            ]);
        }

        return Database::query()->create([
            'server_id' => $server->id,
            'database_host_id' => $host->id,
            'database' => $databaseName,
            'username' => $username,
            'password' => $password,
            'remote_id' => $remote,
        ])->load('databaseHost.location');
    }

    public function resetPassword(Server $server, Database $database): string
    {
        $this->assertOwnedDatabase($server, $database);
        $host = $database->databaseHost;
        $dialect = $this->dialect($host);
        $password = Str::password(24, true, true, false, false);
        $pdo = $this->connectToHost($host);

        try {
            if ($dialect === 'postgres') {
                $pdo->exec(sprintf(
                    'ALTER ROLE %s WITH LOGIN PASSWORD %s',
                    $this->quoteIdentifier($database->username, $dialect),
                    $pdo->quote($password)
                ));
            } else {
                $pdo->exec(sprintf(
                    'ALTER USER %s@%s IDENTIFIED BY %s',
                    $pdo->quote($database->username),
                    $pdo->quote($database->remote_id ?: '%'),
                    $pdo->quote($password)
                ));
                $pdo->exec('FLUSH PRIVILEGES');
            }
        } catch (PDOException $exception) {
            throw ValidationException::withMessages([
                'database' => $this->provisioningErrorMessage($exception, $host),
            ]);
        }

        $database->update(['password' => $password]);

        return $password;
    }

    public function delete(Server $server, Database $database): void
    {
        $this->assertOwnedDatabase($server, $database);
        $host = $database->databaseHost;
        $dialect = $this->dialect($host);
        $pdo = $this->connectToHost($host);

        try {
            if ($dialect === 'postgres') {
                $pdo->exec(sprintf('DROP DATABASE IF EXISTS %s', $this->quoteIdentifier($database->database, $dialect)));
                $pdo->exec(sprintf('DROP ROLE IF EXISTS %s', $this->quoteIdentifier($database->username, $dialect)));
            } else {
                $pdo->exec(sprintf('DROP DATABASE IF EXISTS %s', $this->quoteIdentifier($database->database, $dialect)));
                $pdo->exec(sprintf(
                    'DROP USER IF EXISTS %s@%s',
                    $pdo->quote($database->username),
                    $pdo->quote($database->remote_id ?: '%')
                ));
            }
        } catch (PDOException) {
            // Local state should still be cleaned up even if the remote host is already gone.
        }

        $database->delete();
    }

    protected function connectToHost(DatabaseHost $host): PDO
    {
        $dialect = $this->dialect($host);
        $port = (int) ($host->port ?: ($dialect === 'postgres' ? 5432 : 3306));

        $dsn = $dialect === 'postgres'
            ? sprintf('pgsql:host=%s;port=%d;dbname=%s', $host->host, $port, $host->database ?: 'postgres')
            : sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $host->host, $port, $host->database ?: 'mysql');

        return new PDO($dsn, $host->username, $host->password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    }

    private function assertDatabaseLimit(Server $server): void
    {
        if ($server->database_limit !== null && $server->databases()->count() >= $server->database_limit) {
            throw ValidationException::withMessages([
                'database' => 'Database limit reached for this server.',
            ]);
        }
    }

    private function resolveEligibleHost(Server $server, array $data): DatabaseHost
    {
        $server->loadMissing('node');

        $locationId = (int) ($server->node?->location_id ?? 0);
        $requestedHostId = isset($data['database_host_id']) ? (int) $data['database_host_id'] : 0;

        $eligibleHosts = DatabaseHost::query()
            ->where('location_id', $locationId)
            ->withCount('databases')
            ->orderBy('name')
            ->get();

        if ($eligibleHosts->isEmpty()) {
            throw ValidationException::withMessages([
                'database_host_id' => 'No database host is configured for this server location.',
            ]);
        }

        $availableHosts = $eligibleHosts->filter(function (DatabaseHost $host): bool {
            return (int) $host->max_databases === 0
                || (int) $host->databases_count < (int) $host->max_databases;
        })->values();

        if ($availableHosts->isEmpty()) {
            throw ValidationException::withMessages([
                'database_host_id' => 'All database hosts in this server location are exhausted or unavailable.',
            ]);
        }

        if ($requestedHostId > 0) {
            $selected = $availableHosts->firstWhere('id', $requestedHostId);

            if ($selected) {
                return $selected;
            }

            if ($eligibleHosts->contains('id', $requestedHostId)) {
                throw ValidationException::withMessages([
                    'database_host_id' => 'The selected database host is exhausted or unavailable for this location.',
                ]);
            }

            throw ValidationException::withMessages([
                'database_host_id' => 'The selected database host does not belong to this server location.',
            ]);
        }

        return $availableHosts->first();
    }

    private function assertOwnedDatabase(Server $server, Database $database): void
    {
        if ((int) $database->server_id !== (int) $server->id) {
            throw ValidationException::withMessages([
                'database' => 'Invalid database for this server.',
            ]);
        }
    }

    private function buildDatabaseName(Server $server, string $suffix, DatabaseHost $host): string
    {
        $maxLength = $this->dialect($host) === 'postgres' ? 63 : 64;
        $base = $this->sanitizeIdentifier(sprintf('s%d_%s', $server->id, $suffix), 'database');

        return substr($base, 0, $maxLength);
    }

    private function buildUsername(Server $server, string $suffix, DatabaseHost $host): string
    {
        $maxLength = $this->dialect($host) === 'postgres' ? 63 : 32;
        $base = $this->sanitizeIdentifier(sprintf('u%d_%s_%s', $server->id, $suffix, Str::lower(Str::random(6))), 'user');

        return substr($base, 0, $maxLength);
    }

    private function sanitizeIdentifier(string $value, string $fallback): string
    {
        $clean = preg_replace('/[^a-z0-9_]+/i', '_', strtolower(trim($value))) ?: '';
        $clean = trim(preg_replace('/_+/', '_', $clean), '_');

        return $clean !== '' ? $clean : $fallback;
    }

    private function dialect(DatabaseHost $host): string
    {
        return in_array(strtolower((string) $host->type), ['postgres', 'postgresql'], true) ? 'postgres' : 'mysql';
    }

    private function quoteIdentifier(string $value, string $dialect): string
    {
        return $dialect === 'postgres'
            ? '"' . str_replace('"', '""', $value) . '"'
            : '`' . str_replace('`', '``', $value) . '`';
    }

    private function provisioningErrorMessage(PDOException $exception, DatabaseHost $host): string
    {
        $code = strtoupper((string) $exception->getCode());
        $dialect = $this->dialect($host);

        if ($dialect === 'postgres' && $code === '42501') {
            return sprintf(
                'Database host user "%s" on %s lacks PostgreSQL CREATEDB/CREATEROLE privileges.',
                $host->username,
                $host->host
            );
        }

        if ($dialect !== 'postgres' && in_array($code, ['1044', '1045', '1227'], true)) {
            return sprintf(
                'Database host user "%s" on %s lacks MySQL/MariaDB privileges to create databases and users.',
                $host->username,
                $host->host
            );
        }

        return 'Failed to provision database: ' . $exception->getMessage();
    }
}
