<?php

namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redis;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Ramsey\Uuid\Uuid;

class AuthService
{
    private const REFRESH_TOKEN_TTL = 604800; // 7 天（秒）
    private const REFRESH_KEY_PREFIX = 'esgchain:refresh_token:';

    public function login(string $email, string $password): ?array
    {
        $credentials = compact('email', 'password');

        if (!$accessToken = Auth::guard('api')->attempt($credentials)) {
            return null;
        }

        $user = Auth::guard('api')->user();

        // 帳號被停用時視同登入失敗，錯誤訊息與帳密錯誤一致，避免揭露帳號存在與否／是否被停用
        if (!$user->is_active) {
            Auth::guard('api')->logout();

            return null;
        }

        $refreshToken = $this->issueRefreshToken($user);

        return $this->respondWithToken($accessToken, $refreshToken, $user);
    }

    public function me(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->getRoleNames()->first(),
            'supplierId' => $user->supplier_id,
            'permissions' => $user->permissionStrings(),
        ];
    }

    public function refresh(string $refreshToken): ?array
    {
        // 驗證 refresh token JWT
        try {
            $payload = JWTAuth::setToken($refreshToken)->getPayload();
            $jti = $payload->get('jti');
            $userId = $payload->get('sub');
        } catch (\Throwable) {
            return null;
        }

        // 確認 Redis 中存在
        $redisKey = self::REFRESH_KEY_PREFIX . $jti;
        if (!Redis::exists($redisKey)) {
            return null;
        }

        // Rotation：刪除舊 jti
        Redis::del($redisKey);

        $user = User::find($userId);
        if (!$user) {
            return null;
        }

        // 發行新 access token
        $newAccessToken = Auth::guard('api')->login($user);
        $newRefreshToken = $this->issueRefreshToken($user);

        return $this->respondWithToken($newAccessToken, $newRefreshToken, $user);
    }

    public function logout(): void
    {
        // 從 JWT 取得 jti 並從 Redis 刪除（讓 refresh token 失效）
        try {
            $payload = JWTAuth::parseToken()->getPayload();
            $jti = $payload->get('jti');
            if ($jti) {
                Redis::del(self::REFRESH_KEY_PREFIX . $jti);
            }
        } catch (\Throwable) {
            // jti 找不到也沒關係
        }

        // 明確將目前這支 access token 加入 blacklist，確保登出後立即失效，
        // 不用等到自然過期（Auth::guard('api')->logout() 內部雖然也會嘗試 invalidate，
        // 但遇到例外會靜默吞掉繼續走完登出流程，不能保證真的有把 token 拉黑）
        try {
            JWTAuth::parseToken()->invalidate();
        } catch (\Throwable) {
            // token 已經失效或解析失敗，登出流程仍視為成功
        }

        Auth::guard('api')->logout();
    }

    private function issueRefreshToken(User $user): string
    {
        $jti = Uuid::uuid4()->toString();
        $payload = [
            'sub' => $user->id,
            'jti' => $jti,
            'iat' => time(),
            'exp' => time() + self::REFRESH_TOKEN_TTL,
        ];

        // 存入 Redis（jti → user_id）
        Redis::setex(
            self::REFRESH_KEY_PREFIX . $jti,
            self::REFRESH_TOKEN_TTL,
            $user->id
        );

        // 用私鑰簽發 RS256 refresh token
        return JWTAuth::customClaims($payload)->fromUser($user);
    }

    private function respondWithToken(string $accessToken, string $refreshToken, User $user): array
    {
        return [
            'access_token'  => $accessToken,
            'refresh_token' => $refreshToken,
            'token_type'    => 'bearer',
            'expires_in'    => config('jwt.ttl') * 60,
            'user' => [
                'id'         => $user->id,
                'name'       => $user->name,
                'email'      => $user->email,
                'role'       => $user->getRoleNames()->first(),
                'supplierId' => $user->supplier_id,
                'permissions' => $user->permissionStrings(),
            ],
        ];
    }
}
