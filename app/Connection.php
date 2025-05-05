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
class Connection
{
    public bool $pageStarted = false;

    /**
     * @param string $request -- URL requested, via $_SERVER['REQUEST_URI']
     * @param string $date -- Current date.
     * @param string $ip -- Requesting IP.
     * @param string $ipList -- List of possible originating IPs.
     * @param string $agent -- User-agent information.
     * @param string $user -- Best guess at the current user.
     * @param bool $isBot -- True if user looks like a known bot.
     * @param bool $isAdmin -- True if the user is recognized as a registered admin.
     * @param bool $isSuperAdmin -- True if the user is recognized as me.
     */
    public function __construct(
        public string $request = '',
        public string $date = '',
        public string $ip = '',
        public string $ipList = '',
        public string $agent = '',
        public string $user = '',
        public bool $isBot = false,
        public bool $isAdmin = false,
        public bool $isSuperAdmin = false
    ) {
        // $request.
        if (!empty($_SERVER['REQUEST_URI'])) {
            $this->request = $_SERVER['REQUEST_URI'];
        }

        // $date.
        $this->date = gmdate('c');

        // $ip (set by fn) and $ipList.
        $ipData = $this->getIpData();
        $this->ipList = implode(',', $ipData);

        // $agent.
        if (!empty($_SERVER['HTTP_USER_AGENT'])) {
            $this->agent .= 'agent:' . $_SERVER['HTTP_USER_AGENT'] . ', ';
        }
        if (!empty($_SERVER['HTTP_COOKIE'])) {
            $this->agent .= 'cookie:' . $_SERVER['HTTP_COOKIE'] . ', ';
        }
        // Grab regional client/proxy values like HTTP_ACCEPT_LANGUAGE, etc
        foreach ($_SERVER as $serverKey => $value) {
            if (preg_match('/country|language|region/', $serverKey)) {
                $this->agent .= "$serverKey:$value, ";
            }
        }
        $this->agent = preg_replace('/, $/', '', $this->agent); // trim trailing comma-and-space.
        if (empty($this->agent)) {
            $this->agent = '?';
        }

        // $user, $isAdmin and $isBot
        if (str_contains($this->agent, 'facebookexternalhit')) {
            $this->user .= 'bot:facebook,';
            $this->isBot = true;
        }
        if (str_contains($this->agent, 'Discordbot')) {
            $this->user .= 'bot:discord,';
            $this->isBot = true;
        }
        if (!empty($_SERVER['REMOTE_USER'])) {
            $this->user .= 'remote:' . $_SERVER['REMOTE_USER'] . ',';
        }
        if (!empty($_SERVER['PHP_AUTH_USER'])) {
            $this->user .= 'auth:' . $_SERVER['PHP_AUTH_USER'] . ',';
            if ('myself' === $_SERVER['PHP_AUTH_USER']) {
                $this->isAdmin = true;
                $this->isSuperAdmin = true;
                // This means these permissions can't be revoked 'til the session's killed.
                $_SESSION['isAdmin'] = true;
                $_SESSION['isSuperAdmin'] = true;
            }
            if ('auora' === $_SERVER['PHP_AUTH_USER']) {
                $this->isAdmin = true;
                // This means isAdmin can't be revoked 'til the session's killed.
                $_SESSION['isAdmin'] = true;
            }
        }
        if (array_key_exists('isAdmin', $_SESSION) && $_SESSION['isAdmin']) {
            $this->isAdmin = true;
        }
        if (array_key_exists('isSuperAdmin', $_SESSION) && $_SESSION['isSuperAdmin']) {
            $this->isSuperAdmin = true;
        }
        if (!empty($_SESSION['name'])) {
            $this->user .= 'sess:' . $_SESSION['name'] . ',';
        }
        $this->user = preg_replace('/\s*/', '', $this->user); // strip all whitespace.
        $this->user = preg_replace('/,$/', '', $this->user); // trim trailing comma.
        if (empty($this->user)) {
            $this->user = '?';
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

    /** Get a string describing the object. Mostly for debugging, not used by real code.
     * @return string Serialized object data.
     * @noinspection PhpUnused
     */
    public function __toString(): string
    {
        return var_export([
            'request' => $this->request,
            'date' => $this->date,
            'ip' => $this->ip,
            'ipList' => $this->ipList,
            'agent' => $this->agent,
            'user' => $this->user,
            'isBot' => $this->isBot,
            'isAdmin' => $this->isAdmin,
            'isSuperAdmin' => $this->isSuperAdmin,
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
