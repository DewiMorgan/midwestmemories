<?php
namespace app;

use GuzzleHttp\Client as HttpClient;
use Spatie\Dropbox\TokenProvider;
use \Exception;

class TokenRefresher implements TokenProvider
{
    private $key = 'patzn6ehxdta0pr';
    private $secret = 'poz9yf6lbwyaz73';
    private $refreshToken = 'K-NmeccplOEAAAAAAAAAAUDMrD8Xh1eaJ_vGzpp13C99umpukGv9lpysJyB4ulOw';

    public function __construct() {
    }

    public function getToken(): string {
        return $this->refreshToken();
        //        return Cache::remember('access_token', 14000, function () {
        //            return $this->refreshToken();
        //        });
    }

    public function refreshToken() {
        try {
            $client = new HttpClient();
            $res = $client->request(
                'POST',
                "https://{$this->key}:{$this->secret}@api.dropbox.com/oauth2/token",
                [
                    'form_params' => [
                        'grant_type' => 'refresh_token',
                        'refresh_token' => $this->refreshToken,
                    ],
                ]
            );

            if ($res->getStatusCode() == 200) {
                $response = json_decode($res->getBody(), true);

                return trim(json_encode($response['access_token']), '"');
            } else {
                return false;
            }
        } catch (Exception $e) {
            // echo("{$e->getCode()}: {$e->getMessage()}");
            return false;
        }
    }
}
