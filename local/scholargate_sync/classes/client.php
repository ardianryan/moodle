<?php
namespace local_scholargate_sync;

defined('MOODLE_INTERNAL') || die();

/**
 * ScholarGate Identity API Client.
 * Zero-dependency cURL client for ScholarGate Identity REST API.
 */
class client {
    /** @var string Base URL */
    private $baseurl;

    /** @var string API Key */
    private $apikey;

    /**
     * Constructor.
     *
     * @param string|null $baseurl
     * @param string|null $apikey
     */
    public function __construct(?string $baseurl = null, ?string $apikey = null) {
        global $CFG;

        $this->baseurl = rtrim($baseurl ?: get_config('local_scholargate_sync', 'api_url') ?: 'https://portal.sman1gedeg.sch.id', '/');
        $this->apikey  = $apikey ?: get_config('local_scholargate_sync', 'api_key') ?: '';

        if (empty($this->apikey)) {
            throw new \moodle_exception('error_no_api_key', 'local_scholargate_sync', '', null, 'ScholarGate API Key is not configured in settings.');
        }
    }

    /**
     * Execute an HTTP request.
     *
     * @param string $endpoint
     * @param array $queryparams
     * @return array
     * @throws \moodle_exception
     */
    private function request(string $endpoint, array $queryparams = []): array {
        global $CFG;

        $url = $this->baseurl . $endpoint;
        if (!empty($queryparams)) {
            $filtered = array_filter($queryparams, function($val) {
                return $val !== null && $val !== '';
            });
            if (!empty($filtered)) {
                $url .= '?' . http_build_query($filtered, '', '&');
            }
        }

        $origin = rtrim($CFG->wwwroot, '/');
        $headers = [
            'X-API-Key: ' . $this->apikey,
            'Referer: ' . $origin . '/',
            'Origin: ' . $origin,
            'Accept: application/json',
            'Content-Type: application/json',
        ];

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);

        $response = curl_exec($ch);
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlerror = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            throw new \moodle_exception('curlerror', 'local_scholargate_sync', '', $curlerror);
        }

        $decoded = json_decode($response, true);
        if ($decoded === null) {
            return [
                'success' => false,
                'status'  => $httpcode,
                'error'   => 'JSON decode failed',
                'raw'     => $response
            ];
        }

        $decoded['http_status'] = $httpcode;
        return $decoded;
    }

    /**
     * Test connection to ScholarGate Identity API.
     *
     * @return array
     */
    public function test_connection(): array {
        return $this->request('/api/v1/test');
    }

    /**
     * Get active academic year.
     *
     * @return array
     */
    public function get_active_academic_year(): array {
        return $this->request('/api/v1/academic-year/active');
    }

    /**
     * Lookup a single member by criteria (id, username, email, nip, nisn, etc.)
     *
     * @param array $criteria
     * @return array
     */
    public function lookup_member(array $criteria): array {
        return $this->request('/api/v1/members/lookup', $criteria);
    }

    /**
     * Get paginated members list.
     *
     * @param array $filters (role, kelas, page, per_page, search, etc.)
     * @return array
     */
    public function get_members(array $filters = []): array {
        return $this->request('/api/v1/members', $filters);
    }
}
