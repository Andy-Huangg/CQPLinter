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

namespace local_coderunner_cqp_linter\tools\ai\provider;

use local_coderunner_cqp_linter\tools\ai\provider;

/**
 * Azure OpenAI provider (deployment-based endpoints).
 *
 * Targets the classic Azure OpenAI resource layout used by Azure AI Foundry
 * deployments:
 *   {endpoint}/openai/deployments/{deployment}/chat/completions?api-version=...
 * with api-key header authentication. 'ai_base_url' is the resource endpoint
 * (e.g. https://myresource.openai.azure.com) and 'ai_model' is the
 * DEPLOYMENT name, not the underlying model name.
 *
 * Foundry resources that expose the OpenAI-compatible /models route can use
 * the 'openai' provider instead, pointing 'ai_base_url' at that route.
 *
 * @package    local_coderunner_cqp_linter
 * @copyright  2026 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class azure extends provider {

    /** @var string Azure OpenAI REST api-version query parameter. */
    private string $apiversion;

    /**
     * @param string $baseurl Azure resource endpoint (required; no default).
     * @param string $apikey Azure API key.
     * @param string $model Deployment name.
     * @param int $timeout Request timeout in seconds.
     * @param float $temperature Sampling temperature.
     * @param string $apiversion api-version query value, e.g. '2024-10-21'.
     */
    public function __construct(string $baseurl, string $apikey, string $model,
            int $timeout, float $temperature, string $apiversion) {
        parent::__construct($baseurl, $apikey, $model, $timeout, $temperature);
        $this->apiversion = trim($apiversion) ?: '2024-10-21';
    }

    protected function default_base_url(): string {
        // Every Azure resource has its own endpoint; there is no usable default.
        return '';
    }

    public function complete(string $systemprompt, string $usercontent): ?string {
        if ($this->baseurl === '') {
            throw new \moodle_exception('generalexceptionmessage', 'error', '',
                'Azure provider requires the API base URL setting (your resource endpoint).');
        }

        $payload = [
            'temperature'     => $this->temperature,
            'response_format' => ['type' => 'json_object'],
            'messages'        => [
                ['role' => 'system', 'content' => $systemprompt],
                ['role' => 'user', 'content' => $usercontent],
            ],
        ];

        $url = $this->baseurl . '/openai/deployments/' . rawurlencode($this->model)
             . '/chat/completions?api-version=' . rawurlencode($this->apiversion);

        $decoded = $this->post_json($url, $payload, ['api-key: ' . $this->apikey]);

        $content = $decoded['choices'][0]['message']['content'] ?? null;
        return is_string($content) ? $content : null;
    }
}
