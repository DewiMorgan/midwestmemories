<?php
declare(strict_types=1);

namespace app;

use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Exception\GuzzleException;
use Spatie\Dropbox\TokenProvider;

/**
 * Auto-refresh Dropbox auth tokens.
 */
class TokenRefresher implements TokenProvider
{
    private const INI_FILE = 'DropboxAuth.ini';
    private string $key;
    private string $secret;
    private string $refreshToken;

    public function __construct() {
        // Parse the INI file.
        if (!$authArray = Db::readIniInParents(self::INI_FILE)) {
            Db::adminDebug('Dropbox Auth information could not be found.');
            die();
        }
        if (empty($authArray['key']) || empty($authArray['secret']) || empty($authArray['refresh_token'])) {
            Db::adminDebug('Dropbox Auth information was not set in INI file.');
            die();
        }
        $this->key = $authArray['key'];
        $this->secret = $authArray['secret'];
        $this->refreshToken = $authArray['refresh_token'];
    }

    /**
     * Get the refresh token for the current Dropbox user.
     * @return string The refresh token.
     */
    public function getToken(): string {
        return $this->refreshToken();
        //        return Cache::remember('access_token', 14000, function () {
        //            return $this->refreshToken();
        //        });
    }

    /**
     * @return false|string
     */
    public function refreshToken(): string|false {
        try {
            $client = new HttpClient();
            $res = $client->request(
                'POST',
                "https://$this->key:$this->secret@api.dropbox.com/oauth2/token",
                [
                    'form_params' => [
                        'grant_type' => 'refresh_token',
                        'refresh_token' => $this->refreshToken,
                    ],
                ]
            );
        } catch (GuzzleException $e) {
            Db::adminDebug("Failed to make dropbox API call to refresh token: '{$e->getCode()}: {$e->getMessage()}'.");
            return false;
        }

        if ($res->getStatusCode() != 200) {
            return false;
        }

        $response = json_decode($res->getBody()->getContents(), true);
        return trim(json_encode($response['access_token']), '"');
    }
}
