<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

/** Store PHP sessions in MySQL so separate Vercel instances share session state. */
final class DatabaseSessionHandler implements SessionHandlerInterface
{
    /** @var array<string, string> */
    private array $locks = [];

    public function open(string $path, string $name): bool
    {
        return true;
    }

    public function close(): bool
    {
        foreach (array_keys($this->locks) as $sessionId) {
            $this->releaseLock($sessionId);
        }

        return true;
    }

    public function read(string $id): string|false
    {
        $pdo = db();
        $lockName = 'etbs_session_' . substr(hash('sha256', $id), 0, 43);
        $lock = $pdo->prepare('SELECT GET_LOCK(:lock_name, 5)');
        $lock->execute(['lock_name' => $lockName]);
        if ((int) $lock->fetchColumn() !== 1) {
            return false;
        }
        $this->locks[$id] = $lockName;

        $statement = $pdo->prepare(
            'SELECT session_data FROM app_sessions WHERE session_id = :session_id AND expires_at > :now'
        );
        $statement->execute(['session_id' => $id, 'now' => time()]);
        $data = $statement->fetchColumn();

        return $data === false ? '' : (string) $data;
    }

    public function write(string $id, string $data): bool
    {
        try {
            $expiresAt = time() + max(1, (int) ini_get('session.gc_maxlifetime'));
            $statement = db()->prepare(
                'INSERT INTO app_sessions (session_id, session_data, expires_at)
                 VALUES (:session_id, :session_data, :expires_at)
                 ON DUPLICATE KEY UPDATE
                    session_data = VALUES(session_data), expires_at = VALUES(expires_at)'
            );
            return $statement->execute([
                'session_id' => $id,
                'session_data' => $data,
                'expires_at' => $expiresAt,
            ]);
        } finally {
            $this->releaseLock($id);
        }
    }

    public function destroy(string $id): bool
    {
        try {
            $statement = db()->prepare('DELETE FROM app_sessions WHERE session_id = :session_id');
            return $statement->execute(['session_id' => $id]);
        } finally {
            $this->releaseLock($id);
        }
    }

    public function gc(int $max_lifetime): int|false
    {
        $statement = db()->prepare('DELETE FROM app_sessions WHERE expires_at <= :now');
        if (!$statement->execute(['now' => time()])) {
            return false;
        }

        return $statement->rowCount();
    }

    private function releaseLock(string $id): void
    {
        if (!isset($this->locks[$id])) {
            return;
        }

        $statement = db()->prepare('SELECT RELEASE_LOCK(:lock_name)');
        $statement->execute(['lock_name' => $this->locks[$id]]);
        unset($this->locks[$id]);
    }
}
