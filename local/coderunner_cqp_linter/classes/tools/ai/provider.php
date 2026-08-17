<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace local_coderunner_cqp_linter\tools\ai;

/**
 * Abstract AI chat provider.
 *
 * A provider turns a (system prompt, user content) pair into a single JSON
 * completion string. Each concrete provider knows one vendor's endpoint
 * layout, authentication scheme and payload shape; everything else (prompt
 * building, response parsing, CQP mapping) stays in {@see analyzer} and is
 * provider-independent.
 *
 * The active provider is chosen by the 'ai_provider' admin setting via
 * {@see provider::create()}, so sites can switch vendors (e.g. OpenAI to
 * Gemini or Azure AI Foundry) through configuration alone.
 *
 * @package    local_coderunner_cqp_linter
 * @copyright  2026 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class provider {

    /** @var string API base URL (no trailing slash); '' means provider default. */
    protected string $baseurl;

    /** @var string API key. */
    protected string $apikey;

    /** @var string Model name (for Azure: the deployment name). */
    protected string $model;

    /** @var int Request timeout in seconds. */
    protected int $timeout;

    /** @var float Sampling temperature. */
    protected float $temperature;

    /**
     * @param string $baseurl API base URL, or '' to use the provider default.
     * @param string $apikey API key.
     * @param string $model Model (or Azure deployment) name.
     * @param int $timeout Request timeout in seconds.
     * @param float $temperature Sampling temperature.
     */
    public function __construct(string $baseurl, string $apikey, string $model,
            int $timeout, float $temperature) {
        $this->baseurl     = rtrim($baseurl, '/') ?: rtrim($this->default_base_url(), '/');
        $this->apikey      = $apikey;
        $this->model       = $model;
        $this->timeout     = $timeout;
        $this->temperature = $temperature;
    }

    /**
     * Build the provider selected in the plugin's admin settings.
     *
     * Azure is the default; unknown/unset values of 'ai_provider' fall back to it.
     *
     * @return provider
     */
    public static function create(): provider {
        $cfg = fn($name, $default = '') => get_config('local_coderunner_cqp_linter', $name) ?: $default;

        $type        = (string)$cfg('ai_provider', 'azure');
        $baseurl     = (string)$cfg('ai_base_url', '');
        $apikey      = (string)$cfg('ai_api_key', '');
        $model       = (string)$cfg('ai_model', 'gpt-4o-mini');
        $timeout     = (int)$cfg('ai_timeout', 30);
        $temperature = (float)$cfg('ai_temperature', '0.2');

        switch ($type) {
            case 'gemini':
                return new provider\gemini($baseurl, $apikey, $model, $timeout, $temperature);
            case 'openai':
                return new provider\openai($baseurl, $apikey, $model, $timeout, $temperature);
            case 'azure':
            default:
                return new provider\azure($baseurl, $apikey, $model, $timeout, $temperature,
                    (string)$cfg('ai_azure_api_version', '2024-10-21'));
        }
    }

    /**
     * Request a completion and return the raw assistant content string.
     *
     * The system prompt instructs the model to answer with a single JSON
     * object; implementations should request JSON output where the vendor
     * API supports it.
     *
     * @param string $systemprompt System/instruction prompt.
     * @param string $usercontent User message content.
     * @return string|null Assistant content, or null on any failure.
     * @throws \moodle_exception If the provider is not configured correctly.
     */
    abstract public function complete(string $systemprompt, string $usercontent): ?string;

    /**
     * Base URL used when the 'ai_base_url' setting is blank.
     *
     * @return string '' if the provider has no sensible default (must be configured).
     */
    abstract protected function default_base_url(): string;

    /**
     * POST a JSON payload and return the decoded JSON response.
     *
     * @param string $url Full endpoint URL.
     * @param array $payload Request body, JSON-encoded before sending.
     * @param string[] $headers Extra headers as 'Name: value' strings.
     * @return array|null Decoded response, or null on transport/HTTP/JSON failure.
     */
    protected function post_json(string $url, array $payload, array $headers): ?array {
        global $CFG;
        require_once($CFG->libdir . '/filelib.php');

        $curl = new \curl();
        $curl->setHeader('Content-Type: application/json');
        foreach ($headers as $header) {
            $curl->setHeader($header);
        }

        $response = $curl->post($url, json_encode($payload), [
            'CURLOPT_TIMEOUT'        => $this->timeout,
            'CURLOPT_CONNECTTIMEOUT' => min(10, $this->timeout),
            'CURLOPT_RETURNTRANSFER' => true,
        ]);

        $info = $curl->get_info();
        $httpcode = (int)($info['http_code'] ?? 0);
        if ($curl->get_errno() || $httpcode < 200 || $httpcode >= 300) {
            debugging('CQP AI API HTTP ' . $httpcode . ': ' . substr((string)$response, 0, 500),
                DEBUG_DEVELOPER);
            return null;
        }

        $decoded = json_decode((string)$response, true);
        return is_array($decoded) ? $decoded : null;
    }
}
