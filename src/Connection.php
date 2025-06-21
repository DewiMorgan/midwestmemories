<?php

declare(strict_types=1);

namespace MidwestMemories;

/**
 * Manage HTTP request connection data.
 * ToDo:
 *  isBot to use BotSign table.
 *  Do something with the ipLookup table.
 *  Timestamps with timezone-aware display.
 *  Make admin levels more DB-configurable.
 *  Ability to register accounts (with authorization).
 *  Ability to change passwords.
 */
class Connection extends Singleton
{
    /** @var string $request URL requested, via $_SERVER['REQUEST_URI'] */
    public string $request = '';
    /** @var string $date Current date. */
    public string $date = '';
    /** @var string $ip Requesting IP. */
    public string $ip = '';
    /** @var string $ipList List of possible originating IPs. */
    public string $ipList = '';
    /** @var string $agent User-agent information. */
    public string $agent = '';
    /** @var string $usernameGuesses String containing best guesses at the current user's name. */
    public string $usernameGuesses = '';
    /** @var bool $isBot True if user looks like a known bot. */
    public bool $isBot = false;

    /**
     * Protected singleton constructor.
     */
    protected function __construct()
    {
        parent::__construct();

        // $request.
        if (!empty($_SERVER['REQUEST_URI'])) {
            $this->request = $_SERVER['REQUEST_URI'];
        }

        // $date.
        $this->date = gmdate('c');

        // $ip (set by the fn) and $ipList.
        $ipData = $this->getIpData();
        $this->ipList = implode(',', $ipData);

        // $agent.
        if (!empty($_SERVER['HTTP_USER_AGENT'])) {
            $this->agent .= 'agent:' . $_SERVER['HTTP_USER_AGENT'] . ', ';
        }
        if (!empty($_SERVER['HTTP_COOKIE'])) {
            $this->agent .= 'cookie:' . $_SERVER['HTTP_COOKIE'] . ', ';
        }
        // Grab regional client/proxy values like HTTP_ACCEPT_LANGUAGE, etc.
        foreach ($_SERVER as $serverKey => $value) {
            if (preg_match('/country|language|region/', $serverKey)) {
                $this->agent .= "$serverKey:$value, ";
            }
        }
        $this->agent = preg_replace('/, $/', '', $this->agent); // trim trailing comma-and-space.
        if (empty($this->agent)) {
            $this->agent = '?';
        }

        // Bot detection
        if (str_contains($this->agent, 'facebookexternalhit')) {
            $this->usernameGuesses .= 'bot:facebook,';
            $this->isBot = true;
        }
        if (str_contains($this->agent, 'Discordbot')) {
            $this->usernameGuesses .= 'bot:discord,';
            $this->isBot = true;
        }

        // Get user instance from the session.
        $user = User::getInstance();

        // Handle username guesses from various sources.
        if (!empty($_SERVER['REMOTE_USER'])) {
            $this->usernameGuesses .= 'remote:' . $_SERVER['REMOTE_USER'] . ',';
        }
        if (!empty($_SERVER['PHP_AUTH_USER'])) {
            $this->usernameGuesses .= 'auth:' . $_SERVER['PHP_AUTH_USER'] . ',';
        }
        if (!empty($user->username) && $user->isLoggedIn) {
            $this->usernameGuesses .= 'user:' . $user->username . ',';
        }
        $this->usernameGuesses = preg_replace('/\s*/', '', $this->usernameGuesses); // strip all whitespace.
        $this->usernameGuesses = preg_replace('/,$/', '', $this->usernameGuesses); // trim trailing comma.
        if (empty($this->usernameGuesses)) {
            $this->usernameGuesses = '?';
        }
    }

    /**
     * Extract all possible IP addresses, given many possible proxy headers.
     * The first one is taken as the "most valid" one, so they should probably be ordered from best to worst.
     * @return string[]
     */
    private function getIpData(): array
    {
        $ipArray = [];
        foreach (
            [
                'HTTP_CLIENT_IP',
                'HTTP_CF_CONNECTING_IP',
                'PROXY_REMOTE_ADDR', // Proxy aliases.
                'HTTP_X_REAL_IP',
                'HTTP_X_CLUSTER_CLIENT_IP', // _X_s.
                'HTTP_FORWARDED_FOR',
                'HTTP_FORWARDED',
                'HTTP_X_FORWARDED_FOR',
                'HTTP_X_FORWARDED',
                'HTTP_PROXY_CONNECTION',
                'HTTP_VIA',
                'HTTP_X_COMING_FROM',
                'HTTP_COMING_FROM',
                'REMOTE_ADDR', // Should be last: the only one guaranteed legitimate.
            ] as $key
        ) {
            if (!empty($_SERVER[$key])) {
                $list = explode(',', $_SERVER[$key]);
                foreach ($list as $ip) {
                    $ip = trim($ip);
                    $ip = $this->normalizeIp($ip);
                    if ($this->validatePublicIp($ip)) {
                        $this->ip = $ip;
                    }
                    if ($this->validateIp($ip)) {
                        $ipArray[$ip] = 1;
                    }
                }
            }
        }
        return array_keys($ipArray);
    }

    /**
     * Ensures an IP address is both a valid IP address and does not fall within
     * a private network range.
     * @param string $ip The ip number to check.
     * @return bool True if the IP is a valid public IP.
     */
    private function validatePublicIp(string $ip): bool
    {
        return (false !== filter_var(
                $ip,
                FILTER_VALIDATE_IP,
                FILTER_FLAG_IPV4 |
                FILTER_FLAG_IPV6 |
                FILTER_FLAG_NO_PRIV_RANGE |
                FILTER_FLAG_NO_RES_RANGE
            )
        );
    }

    /** Just check an IP address truly is one.
     * @param string $ip The ip number to check.
     * @return bool True if the IP is a valid IP.
     */
    private function validateIp(string $ip): bool
    {
        return (false !== filter_var(
                $ip,
                FILTER_VALIDATE_IP,
                FILTER_FLAG_IPV4 | FILTER_FLAG_IPV6
            )
        );
    }

    /**
     * Get a string describing the object. Mostly for debugging, not used by real code.
     * @return string Serialized object data.
     * @noinspection PhpUnused
     */
    public function __toString(): string
    {
        $user = User::getInstance();

        return var_export([
            'request' => $this->request,
            'date' => $this->date,
            'ip' => $this->ip,
            'ipList' => $this->ipList,
            'agent' => $this->agent,
            'usernameGuesses' => $this->usernameGuesses,
            'isBot' => $this->isBot,
            'user' => (string)$user
        ], true);
    }

    /**
     * Function to normalize IPv4 and IPv6 addresses with port
     * @param string $ip
     * @return string The normalized IP.
     */
    private function normalizeIp(string $ip): string
    {
        if (str_contains($ip, ':') && substr_count($ip, '.') === 3 && !str_contains($ip, '[')) {
            // IPv4 with port (e.g., 123.123.123.123:80)
            $ips = explode(':', $ip);
            $ip = $ips[0];
        } else {
            // IPv6 with port (e.g., [::1]:80)
            $ips = explode(']', $ip);
            $ip = ltrim($ips[0], '[');
        }
        return $ip;
    }
}
