<?php
// ToDo:
// isBot to use BotSign table.
// Do something with the ipLookup table.
// Timestamps with timezone-aware display.

class Connection {
    public $request = '';
    public $date = '';
    public $ip = '';
    public $ipList = '';
    public $agent = '';
    public $user = '';
    public $isBot = false;
    public $target = '';

    public function __construct() {
        // $request and $alias.
        if (!empty($_SERVER['REQUEST_URI'])) {
            $this->request = $_SERVER['REQUEST_URI'];
        }
        
        // $date.
        $this->date = gmdate('c');

        // $ip (set by fn) and $ipList.        
        $ips = $this->getIpData();
        $this->ipList = implode(',', $ips);

        // $agent.    
        if (!empty($_SERVER['HTTP_USER_AGENT'])) {
            $this->agent .= 'agent:' . $_SERVER['HTTP_USER_AGENT'] . ', ';
        }
        if (!empty($_SERVER['HTTP_COOKIE'])) {
            $this->agent .= 'cookie:' . $_SERVER['HTTP_COOKIE'] . ', ';
        }
        // Grab regional client/proxy vals like HTTP_ACCEPT_LANGUAGE, HTTP_CF_IPCOUNTRY, etc
        foreach ($_SERVER as $key => $value) {
            if (preg_match('/country|language|region/', $key)) {
                $this->agent .= "$key:$value, ";
            }
        }
        $this->agent = preg_replace('/, $/', '', $this->agent); // trim trailing comma-and-space.
        if (empty($this->agent)) {
            $this->agent = '?';
        }
    
        // $user, $isAdmin and $isBot
        if (preg_match('/facebookexternalhit/', $this->agent)) {
            $this->user .= 'bot:facebook,';
            $this->isBot = true;
        }
        if (preg_match('/Discordbot/', $this->agent)) {
            $this->user .= 'bot:discord,';
            $this->isBot = true;
        }
        if (!empty($_SERVER['REMOTE_USER'])) {
            $this->user .= 'remote:' . $_SERVER['REMOTE_USER'] . ',';
        }
        if (!empty($_SERVER['PHP_AUTH_USER'])) {
            $this->user .= 'auth:' . $_SERVER['PHP_AUTH_USER'] . ',';
            $this->isAdmin = ('myself' === $_SERVER['PHP_AUTH_USER']);
            // This means isAdmin can't be revoked 'til the session's killed.
            $_SESSION['isAdmin'] = true;
        }
        if (array_key_exists('isAdmin', $_SESSION) && $_SESSION['isAdmin']) {
            $this->isAdmin = true;
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

    /** Get the alias information from the DB, if non-deleted.
     * @return array[] of [$aliasId, $target], or an empty array.
    */
    private function getTargetDataByAlias($aliasName) {
        global $db;
        return $db->sqlGetRow('SELECT `id`, `target` FROM snoopy_links WHERE deleted=false and alias=?', 's', $aliasName);
    }
   
    /**
    * Extract all possible IP addresses, given many possible proxy headers.
    * The first one is taken as the "most valid" one, so they should probably be ordered from best to worst.
    */
    private function getIpData() {
        $ipArray = [];
        foreach ([
            'HTTP_CLIENT_IP', 'HTTP_CF_CONNECTING_IP', 'PROXY_REMOTE_ADDR', // Proxy aliases.
            'HTTP_X_REAL_IP', 'HTTP_X_CLUSTER_CLIENT_IP', // _X_s.
            'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED',
            'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED',
            'HTTP_PROXY_CONNECTION', 'HTTP_VIA',
            'HTTP_X_COMING_FROM', 'HTTP_COMING_FROM',
            'REMOTE_ADDR', // Should be last: the only non-spoofable one.
        ] as $key) {
            if (!empty($_SERVER[$key])) {
                $list = explode(',', $_SERVER[$key]);
                foreach ($list as $ip) {
                    $ip = trim($ip);
                    $ip = $this->normalize_ip($ip);
                    if ($this->validate_public_ip($ip)) {
                        $this->ip = $ip;
                    }
                    if ($this->validate_ip($ip)) {
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
    * @param string $ip The ip unmber to check.
    * @return bool True if the IP is a valid public IP.
    */
    private function validate_public_ip($ip) {
        return filter_var(
            $ip,
            FILTER_VALIDATE_IP, 
            FILTER_FLAG_IPV4 | 
            FILTER_FLAG_IPV6 |
            FILTER_FLAG_NO_PRIV_RANGE | 
            FILTER_FLAG_NO_RES_RANGE
        );
    }
    
    /** Just check that an IP address really is one.
    * @param string $ip The ip unmber to check.
    * @return bool True if the IP is a valid IP.
    */
    private function validate_ip($ip) {
        return filter_var(
            $ip,
            FILTER_VALIDATE_IP, 
            FILTER_FLAG_IPV4 | 
            FILTER_FLAG_IPV6
        );
    }

    /** Get a string describing the object.
    * @return string Serialized object data,
    */
    public function __toString() {
        return var_export([
            'request' => $this->request,
            'date' => $this->date,
            'ip' => $this->ip,
            'ipList' => $this->ipList,
            'agent' => $this->agent,
            'user' => $this->user,
            'isBot' => $this->isBot,
            'isAdmin' => $this->isAdmin,
        ], true);
    }
    
    // Function to normalize IPv4 and IPv6 addresses with port
    function normalize_ip($ip){
        if (strpos($ip, ':') !== false && substr_count($ip, '.') == 3 && strpos($ip, '[') === false){
            // IPv4 with port (e.g., 123.123.123.123:80)
            $ip = explode(':', $ip);
            $ip = $ip[0];
        } else {
            // IPv6 with port (e.g., [::1]:80)
            $ip = explode(']', $ip);
            $ip = ltrim($ip[0], '[');
        }
        return $ip;
    }
}
