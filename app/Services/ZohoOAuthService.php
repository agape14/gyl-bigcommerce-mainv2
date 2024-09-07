<?php

namespace App\Services;

use App\Models\AccessToken;
use Carbon\Carbon;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;
use Illuminate\Support\Facades\Log;

class ZohoOAuthService
{
    protected $client;
    protected $clientId;
    protected $clientSecret;
    protected $redirectUri;
    protected $grantType;
    protected $refreshToken;
    protected $zohoBooksOrgId;

    /**
     * ZohoOAuthService constructor.
     */
    public function __construct()
    {
        $this->client = new Client();
        $this->clientId = env('CLIENT_ID');
        $this->clientSecret = env('CLIENT_SECRET');
        $this->redirectUri = env('REDIRECT_URI');
        $this->grantType = env('GRANT_TYPE');
        $this->refreshToken = env('REFRESH_TOKEN');
        $this->zohoBooksOrgId = env('ZOHO_BOOKS_ORG_ID');
    }

    /**
     * Get access token from Zoho OAuth.
     *
     * @return object
     */
    public function getAccessToken()
    {
        try {
            // Check for a valid access token first
            if ($this->hasValidAccessToken()) {
                return $this->getStoredToken(); // Return the stored token object
            }

            $headers = [
                'Accept' => 'application/json',
            ];

            $options = [
                'multipart' => [
                    ['name' => 'refresh_token', 'contents' => $this->refreshToken],
                    ['name' => 'client_id', 'contents' => $this->clientId],
                    ['name' => 'client_secret', 'contents' => $this->clientSecret],
                    ['name' => 'grant_type', 'contents' => $this->grantType],
                ],
            ];

            $request = new Request('POST', 'https://accounts.zoho.com/oauth/v2/token', $headers);


            $response = $this->client->sendAsync($request, $options)->wait();
            $body = (string) $response->getBody();
            $decodedResponse = json_decode($body);

            // Check if the access_token is present in the response
            if (isset($decodedResponse->access_token)) {
                // Store the new token and its expiry time
                $this->storeToken($decodedResponse);
                return $decodedResponse; // Return the whole response object
            } else {
                // Handle the absence of access_token
                throw new \Exception('Access token not found in the response');
            }
        } catch (\Throwable $th) {
            // Handle any exceptions
            Log::error('getAccessToken', [
                'message' => $th->getMessage(),
                'file' => $th->getFile(),
                'line' => $th->getLine()
            ]);
            return (object) ['error' => $th->getMessage()];
        }
    }

    /**
     * Check if the stored access token is still valid.
     *
     * @return bool
     */
    private function hasValidAccessToken()
    {
        $token = AccessToken::first();
        return $token && Carbon::now()->lessThan(Carbon::parse($token->expires_at));
    }

    /**
     * Get the stored access token.
     *
     * @return object
     */
    private function getStoredToken()
    {
        return AccessToken::first();
    }

    /**
     * Store the access token and its expiry time.
     *
     * @param object $tokenResponse
     * @return void
     */
    private function storeToken($tokenResponse)
    {
        AccessToken::truncate(); // Remove old tokens
        AccessToken::create([
            'access_token' => $tokenResponse->access_token,
            'expires_at' => Carbon::now()->addSeconds($tokenResponse->expires_in)
        ]);
    }
}
