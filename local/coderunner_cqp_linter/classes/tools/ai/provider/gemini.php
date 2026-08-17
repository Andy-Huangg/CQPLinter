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
 * Google Gemini provider (Generative Language API).
 *
 * Calls {base}/models/{model}:generateContent with x-goog-api-key
 * authentication and asks for a JSON response via
 * generationConfig.responseMimeType. 'ai_model' is a Gemini model name such
 * as 'gemini-2.0-flash'.
 *
 * @package    local_coderunner_cqp_linter
 * @copyright  2026 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class gemini extends provider {

    protected function default_base_url(): string {
        return 'https://generativelanguage.googleapis.com/v1beta';
    }

    public function complete(string $systemprompt, string $usercontent): ?string {
        $payload = [
            'system_instruction' => [
                'parts' => [['text' => $systemprompt]],
            ],
            'contents' => [
                ['role' => 'user', 'parts' => [['text' => $usercontent]]],
            ],
            'generationConfig' => [
                'temperature'      => $this->temperature,
                'responseMimeType' => 'application/json',
            ],
        ];

        $url = $this->baseurl . '/models/' . rawurlencode($this->model) . ':generateContent';

        $decoded = $this->post_json($url, $payload, ['x-goog-api-key: ' . $this->apikey]);

        $content = $decoded['candidates'][0]['content']['parts'][0]['text'] ?? null;
        return is_string($content) ? $content : null;
    }
}
