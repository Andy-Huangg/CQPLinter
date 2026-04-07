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

namespace local_coderunner_pylint;

use cache;

/**
 * MUC-based cache for pylint results.
 *
 * Keys are sha256 hashes of (code + config), values are JSON-encoded pylint_result objects.
 *
 * @package    local_coderunner_pylint
 * @copyright  2026 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class result_cache {

    /**
     * Generate a cache key for a code string and configuration.
     *
     * @param string $code The student code.
     * @param array $config Configuration that affects lint output (disable, rcfile, etc.).
     * @return string Cache key (sha256 hash).
     */
    public static function make_key(string $code, array $config = []): string {
        // Sort config for consistent hashing.
        ksort($config);
        $configstr = json_encode($config);
        return hash('sha256', $code . '|' . $configstr);
    }

    /**
     * Get a cached lint result.
     *
     * @param string $code The student code.
     * @param array $config Lint configuration.
     * @return pylint_result|null Cached result or null on miss.
     */
    public static function get(string $code, array $config = []): ?pylint_result {
        $cache = cache::make('local_coderunner_pylint', 'lint_results');
        $key = self::make_key($code, $config);
        $data = $cache->get($key);

        if ($data === false) {
            return null;
        }

        return pylint_result::from_json($data);
    }

    /**
     * Store a lint result in the cache.
     *
     * @param string $code The student code.
     * @param array $config Lint configuration.
     * @param pylint_result $result The result to cache.
     */
    public static function set(string $code, array $config, pylint_result $result): void {
        $cache = cache::make('local_coderunner_pylint', 'lint_results');
        $key = self::make_key($code, $config);
        $cache->set($key, $result->to_json());
    }

    /**
     * Purge all cached lint results.
     */
    public static function purge(): void {
        $cache = cache::make('local_coderunner_pylint', 'lint_results');
        $cache->purge();
    }
}
