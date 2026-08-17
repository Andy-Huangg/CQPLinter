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
 * OpenAI-compatible chat completions provider.
 *
 * Works with api.openai.com and any service exposing the same API surface:
 * Azure AI Foundry's OpenAI-compatible route, OpenRouter, LiteLLM/other
 * proxies, local servers (Ollama, vLLM), etc. Point 'ai_base_url' at the
 * service and supply its key.
 *
 * @package    local_coderunner_cqp_linter
 * @copyright  2026 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class openai extends provider {

    protected function default_base_url(): string {
        return 'https://api.openai.com/v1';
    }

    public function complete(string $systemprompt, string $usercontent): ?string {
        $payload = [
            'model'           => $this->model,
            'temperature'     => $this->temperature,
            'response_format' => ['type' => 'json_object'],
            'messages'        => [
                ['role' => 'system', 'content' => $systemprompt],
                ['role' => 'user', 'content' => $usercontent],
            ],
        ];

        $decoded = $this->post_json(
            $this->baseurl . '/chat/completions',
            $payload,
            ['Authorization: Bearer ' . $this->apikey]
        );

        $content = $decoded['choices'][0]['message']['content'] ?? null;
        return is_string($content) ? $content : null;
    }
}
