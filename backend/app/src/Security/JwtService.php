<?php

namespace App\Security;

use App\Config\Database;
use App\Models\UserModel;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use PDO;

class JwtService
{
    private string $secret;
    private string $algorithm = 'HS256';
    private string $issuer;
    private string $audience;
    private int $accessTokenTtlSeconds;
    private int $refreshTokenTtlSeconds;

    public function __construct()
    {
        $this->secret = (string) (getenv('JWT_SECRET') ?: 'change-this-secret-in-production');
        $this->issuer = (string) (getenv('JWT_ISSUER') ?: 'appointment-buddy-improved');
        $this->audience = (string) (getenv('JWT_AUDIENCE') ?: 'appointment-buddy-improved-client');
        $this->accessTokenTtlSeconds = (int) (getenv('JWT_TTL_SECONDS') ?: 600);
        $this->refreshTokenTtlSeconds = (int) (getenv('REFRESH_TOKEN_TTL_SECONDS') ?: 1209600);
    }

    public function getAccessTokenTtlSeconds(): int
    {
        return $this->accessTokenTtlSeconds;
    }

    public function getRefreshTokenTtlSeconds(): int
    {
        return $this->refreshTokenTtlSeconds;
    }

    public function issueAccessToken(UserModel $user): string
    {
        $issuedAt = time();

        $payload = [
            'iss' => $this->issuer,
            'aud' => $this->audience,
            'iat' => $issuedAt,
            'nbf' => $issuedAt,
            'exp' => $issuedAt + $this->accessTokenTtlSeconds,
            'data' => [
                'id' => (int) $user->id,
                'email' => $user->email,
                'name' => $user->name,
                'role' => $user->role,
            ],
        ];

        return JWT::encode($payload, $this->secret, $this->algorithm);
    }

    /**
     * @return array<string, mixed>
     */
    public function decodeAccessToken(string $token): array
    {
        try {
            $decoded = JWT::decode($token, new Key($this->secret, $this->algorithm));
        } catch (\Throwable $e) {
            throw new \RuntimeException('Invalid token.');
        }

        $payload = json_decode(json_encode($decoded), true);
        if (!is_array($payload)) {
            throw new \RuntimeException('Invalid token payload.');
        }

        if (($payload['iss'] ?? null) !== $this->issuer || ($payload['aud'] ?? null) !== $this->audience) {
            throw new \RuntimeException('Invalid token issuer or audience.');
        }

        return $payload;
    }

    public function issueRefreshToken(int $userId): string
    {
        if ($userId <= 0) {
            throw new \RuntimeException('Invalid user id for refresh token.');
        }

        $token = bin2hex(random_bytes(64));
        $tokenHash = hash('sha256', $token);
        $expiresAt = (new \DateTimeImmutable('+' . $this->refreshTokenTtlSeconds . ' seconds'))->format('Y-m-d H:i:s');

        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("
            INSERT INTO refresh_tokens (user_id, token_hash, expires_at)
            VALUES (:user_id, :token_hash, :expires_at)
        ");
        $stmt->execute([
            ':user_id' => $userId,
            ':token_hash' => $tokenHash,
            ':expires_at' => $expiresAt,
        ]);

        return $token;
    }

    /**
     * Rotates a refresh token and returns the user id + replacement refresh token.
     *
     * @return array{user_id:int,refresh_token:string}
     */
    public function rotateRefreshToken(string $refreshToken): array
    {
        $refreshToken = trim($refreshToken);
        if ($refreshToken === '') {
            throw new \RuntimeException('Refresh token is required.');
        }

        $tokenHash = hash('sha256', $refreshToken);
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare("
            SELECT id, user_id, expires_at, revoked_at
            FROM refresh_tokens
            WHERE token_hash = :token_hash
            LIMIT 1
        ");
        $stmt->execute([':token_hash' => $tokenHash]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            throw new \RuntimeException('Invalid refresh token.');
        }

        if (!empty($row['revoked_at'])) {
            throw new \RuntimeException('Refresh token is no longer valid.');
        }

        $expiresAt = new \DateTimeImmutable((string) $row['expires_at']);
        $now = new \DateTimeImmutable('now');
        if ($expiresAt <= $now) {
            throw new \RuntimeException('Refresh token expired.');
        }

        $refreshId = (int) $row['id'];
        $userId = (int) $row['user_id'];

        $pdo->beginTransaction();
        try {
            $revoke = $pdo->prepare("
                UPDATE refresh_tokens
                SET revoked_at = NOW()
                WHERE id = :id AND revoked_at IS NULL
            ");
            $revoke->execute([':id' => $refreshId]);

            $newRefreshToken = $this->issueRefreshToken($userId);
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }

        return [
            'user_id' => $userId,
            'refresh_token' => $newRefreshToken,
        ];
    }

    public function revokeRefreshToken(string $refreshToken, ?int $expectedUserId = null): bool
    {
        $refreshToken = trim($refreshToken);
        if ($refreshToken === '') {
            return false;
        }

        $tokenHash = hash('sha256', $refreshToken);
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare("
            SELECT id, user_id, revoked_at
            FROM refresh_tokens
            WHERE token_hash = :token_hash
            LIMIT 1
        ");
        $stmt->execute([':token_hash' => $tokenHash]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return false;
        }

        if ($expectedUserId !== null && (int) $row['user_id'] !== $expectedUserId) {
            return false;
        }

        if (!empty($row['revoked_at'])) {
            return true;
        }

        $update = $pdo->prepare("
            UPDATE refresh_tokens
            SET revoked_at = NOW()
            WHERE id = :id AND revoked_at IS NULL
        ");
        $update->execute([':id' => (int) $row['id']]);

        return $update->rowCount() > 0;
    }

    public function revokeAllRefreshTokensForUser(int $userId): int
    {
        if ($userId <= 0) {
            return 0;
        }

        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("
            UPDATE refresh_tokens
            SET revoked_at = NOW()
            WHERE user_id = :user_id AND revoked_at IS NULL
        ");
        $stmt->execute([':user_id' => $userId]);

        return $stmt->rowCount();
    }
}