<?php

declare(strict_types=1);

namespace MidwestMemories;

use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Exception\GuzzleException;
use Spatie\Dropbox\TokenProvider;

/**
 * Auto-refresh Dropbox auth tokens.
 */
class TokenRefresher implements TokenProvider
{
    /**
     * Get the refresh token for the current Dropbox user.
     * @return string The refresh token.
     */
    public function getToken(): string
    {
        return $this->refreshToken();
    }

    /**
     * @return false|string
     */
    public function refreshToken(): string|false
    {
        $key = Conf::get(Key::DROPBOX_KEY);
        $secret = Conf::get(Key::DROPBOX_SECRET);
        $token = Conf::get(Key::DROPBOX_REFRESH_TOKEN);
        try {
            $client = new HttpClient();
            $request = $client->request(
                'POST',
                "https://$key:$secret@api.dropbox.com/oauth2/token",
                [
                    'form_params' => [
                        'grant_type' => 'refresh_token',
                        'refresh_token' => $token,
                    ],
                ]
            );
        } catch (GuzzleException $e) {
            Log::adminDebug("Failed to make dropbox API call to refresh token: '{$e->getCode()}: {$e->getMessage()}'.");
            return false;
        }

        if ($request->getStatusCode() !== 200) {
            return false;
        }

        try {
            $decodedRequest = json_decode($request->getBody()->getContents(), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            Log::adminDebug("Failed to decode json: '{$e->getCode()}: {$e->getMessage()}'.");
            return false;
        }
        try {
            $encodedResponse = trim(json_encode($decodedRequest['access_token'], JSON_THROW_ON_ERROR), '"');
        } catch (\JsonException $e) {
            Log::adminDebug("Failed to encode json: '{$e->getCode()}: {$e->getMessage()}'.");
            return false;
        }
        return $encodedResponse;
    }
}
