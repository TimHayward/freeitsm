<?php
/**
 * Shared CMDB AI helpers — load saved settings, call Anthropic, parse JSON.
 * Keeps the per-feature endpoints thin and consistent.
 */

require_once __DIR__ . '/../../includes/encryption.php';

const CMDB_AI_VALID_MODELS = [
    'claude-haiku-4-5-20251001',
    'claude-sonnet-4-6',
    'claude-opus-4-7',
];

const CMDB_AI_PROPERTY_TYPES = ['text', 'number', 'date', 'boolean', 'dropdown', 'object_ref'];

/**
 * Load CMDB AI settings from system_settings. Returns api_key, model,
 * custom_instructions. Throws if no key is set.
 */
function loadCmdbAiConfig(PDO $conn): array {
    $stmt = $conn->prepare(
        "SELECT setting_key, setting_value FROM system_settings
          WHERE setting_key IN ('cmdb_ai_api_key', 'cmdb_ai_model', 'cmdb_ai_custom_instructions')"
    );
    $stmt->execute();

    $cfg = ['api_key' => '', 'model' => 'claude-haiku-4-5-20251001', 'custom_instructions' => ''];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $key = $row['setting_key'];
        $val = $row['setting_value'];
        if ($key === 'cmdb_ai_api_key') {
            $val = decryptValue($val);
            $cfg['api_key'] = $val ?? '';
        } elseif ($key === 'cmdb_ai_model') {
            if ($val !== '') $cfg['model'] = $val;
        } elseif ($key === 'cmdb_ai_custom_instructions') {
            $cfg['custom_instructions'] = $val ?? '';
        }
    }

    if ($cfg['api_key'] === '') {
        throw new Exception('CMDB AI is not configured. Set your Anthropic key in CMDB → Settings → AI Integration.');
    }
    if (!in_array($cfg['model'], CMDB_AI_VALID_MODELS, true)) {
        $cfg['model'] = 'claude-haiku-4-5-20251001';
    }
    return $cfg;
}

/**
 * Send a one-shot (non-streaming) request to Anthropic and return the
 * decoded JSON response. Throws on network or HTTP error.
 */
function callAnthropic(array $cfg, string $systemPrompt, string $userMessage, int $maxTokens = 1500): array {
    $body = json_encode([
        'model'      => $cfg['model'],
        'max_tokens' => $maxTokens,
        'system'     => $systemPrompt,
        'messages'   => [['role' => 'user', 'content' => $userMessage]],
    ]);

    $ch = curl_init('https://api.anthropic.com/v1/messages');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $body,
        CURLOPT_HTTPHEADER     => [
            'x-api-key: ' . $cfg['api_key'],
            'anthropic-version: 2023-06-01',
            'content-type: application/json',
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => SSL_VERIFY_PEER,
        CURLOPT_TIMEOUT        => 60,
    ]);
    $resp = curl_exec($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);

    if ($resp === false) {
        throw new Exception('Network error talking to Anthropic: ' . $err);
    }
    $data = json_decode($resp, true);
    if ($http !== 200) {
        $msg = $data['error']['message'] ?? ('HTTP ' . $http);
        throw new Exception('Anthropic error: ' . $msg);
    }
    return $data;
}

/**
 * Pull the assistant's text out of the Anthropic response shape.
 */
function anthropicResponseText(array $resp): string {
    $blocks = $resp['content'] ?? [];
    $text = '';
    foreach ($blocks as $b) {
        if (($b['type'] ?? '') === 'text') {
            $text .= $b['text'] ?? '';
        }
    }
    return trim($text);
}

/**
 * Parse a JSON response from Claude robustly: strip markdown fences,
 * extract the first JSON object/array, and json_decode it.
 */
function parseClaudeJson(string $text): array {
    // Strip ```json ... ``` fences if present
    $text = preg_replace('/^```(?:json)?\s*|\s*```$/m', '', $text);
    $text = trim($text);

    // Find the first { or [ and use everything to the matching close.
    // Simple but robust enough for our short structured replies.
    $start = strcspn($text, '{[');
    if ($start === strlen($text)) {
        throw new Exception('AI did not return JSON');
    }
    $text = substr($text, $start);

    $decoded = json_decode($text, true);
    if (!is_array($decoded)) {
        throw new Exception('Could not parse AI JSON response');
    }
    return $decoded;
}
