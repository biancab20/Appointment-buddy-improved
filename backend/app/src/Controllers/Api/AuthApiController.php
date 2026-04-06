<?php

namespace App\Controllers\Api;

use App\Services\Interfaces\IUserService;
use App\Services\UserService;
use App\Security\JwtService;

class AuthApiController extends ApiBaseController
{
    private IUserService $userService;
    private JwtService $jwtService;

    public function __construct()
    {
        $this->userService = new UserService();
        $this->jwtService = new JwtService();
    }

    public function login(): void
    {
        $payload = $this->readJsonBody();
        $email = (string)($payload['email'] ?? '');
        $password = (string)($payload['password'] ?? '');

        try {
            $user = $this->userService->login($email, $password);
            $accessToken = $this->jwtService->issueAccessToken($user);
            $refreshToken = $this->jwtService->issueRefreshToken((int)$user->id);

            $this->json([
                'token_type' => 'Bearer',
                'access_token' => $accessToken,
                'expires_in' => $this->jwtService->getAccessTokenTtlSeconds(),
                'refresh_token' => $refreshToken,
                'refresh_expires_in' => $this->jwtService->getRefreshTokenTtlSeconds(),
                'user' => [
                    'id' => (int)$user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                ],
            ]);
        } catch (\RuntimeException $e) {
            $this->json(['error' => 'Invalid email or password.'], 401);
        }
    }

    public function refresh(): void
    {
        $payload = $this->readJsonBody();
        $refreshToken = (string)($payload['refresh_token'] ?? '');

        if (trim($refreshToken) === '') {
            $this->json(['error' => 'Refresh token is required.'], 400);
            return;
        }

        try {
            $rotated = $this->jwtService->rotateRefreshToken($refreshToken);
            $user = $this->userService->findById((int)$rotated['user_id']);
            if (!$user) {
                $this->json(['error' => 'Invalid refresh token.'], 401);
                return;
            }

            $accessToken = $this->jwtService->issueAccessToken($user);

            $this->json([
                'token_type' => 'Bearer',
                'access_token' => $accessToken,
                'expires_in' => $this->jwtService->getAccessTokenTtlSeconds(),
                'refresh_token' => $rotated['refresh_token'],
                'refresh_expires_in' => $this->jwtService->getRefreshTokenTtlSeconds(),
                'user' => [
                    'id' => (int)$user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                ],
            ]);
        } catch (\RuntimeException $e) {
            $this->json(['error' => 'Invalid or expired refresh token.'], 401);
        }
    }

    public function logout(): void
    {
        $this->requireAuth();

        $payload = $this->readJsonBody();
        $refreshToken = trim((string)($payload['refresh_token'] ?? ''));

        if ($refreshToken !== '') {
            $this->jwtService->revokeRefreshToken($refreshToken, $this->authUserId());
        } else {
            $this->jwtService->revokeAllRefreshTokensForUser($this->authUserId());
        }

        $this->json([
            'message' => 'Logged out successfully.',
        ]);
    }

    public function currentUser(): void
    {
        $this->requireAuth();

        $this->json([
            'user' => [
                'id' => $this->authUserId(),
                'name' => (string)($this->authUser['name'] ?? ''),
                'email' => (string)($this->authUser['email'] ?? ''),
                'role' => $this->authUserRole(),
            ],
        ]);
    }
}