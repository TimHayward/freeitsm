<?php
/**
 * API: Tickets — AI Reply Cleanup (streaming)
 *
 * POST { ticket_id: int, draft_text: string }
 * Streams Server-Sent Events as Claude rewrites the rough draft into a
 * properly formatted reply. The output is plain text with blank-line paragraph
 * breaks; the front-end is responsible for wrapping into <p> tags before
 * inserting into TinyMCE.
 *
 * Reuses the rfp_ai.php streaming helper but supplies its own settings
 * (key + model) from the tickets_reply_cleanup_* keys so this feature has
 * its own line on the Anthropic billing dashboard.
 */

session_start();
require_once '../../config.php';
require_once '../../includes/functions.php';
require_once '../../includes/encryption.php';
require_once '../../includes/rfp_ai.php';

// Disable buffering so SSE events flush immediately.
@ini_set('zlib.output_compression', '0');
@ini_set('output_buffering', '0');
@ini_set('implicit_flush', '1');
while (ob_get_level() > 0) ob_end_flush();
ob_implicit_flush(true);

header('Content-Type: text/event-stream');
header('Cache-Control: no-cache, no-transform');
header('X-Accel-Buffering: no');
header('Connection: keep-alive');

set_time_limit(0);

function sse_send(string $event, array $data): void
{
    echo "event: {$event}\n";
    echo 'data: ' . json_encode($data, JSON_UNESCAPED_SLASHES) . "\n\n";
    @flush();
}

if (!isset($_SESSION['analyst_id'])) {
    sse_send('error', ['message' => 'Not authenticated']);
    exit;
}

$input     = json_decode(file_get_contents('php://input'), true);
$ticketId  = (int)($input['ticket_id'] ?? 0);
$draftText = trim((string)($input['draft_text'] ?? ''));

if ($ticketId <= 0) {
    sse_send('error', ['message' => 'Ticket id required']);
    exit;
}
if ($draftText === '') {
    sse_send('error', ['message' => 'Draft text is empty — type something first']);
    exit;
}
if (mb_strlen($draftText) > 5000) {
    sse_send('error', ['message' => 'Draft is too long for cleanup (max 5000 characters)']);
    exit;
}

try {
    $conn = connectToDatabase();

    // Load the per-feature AI settings (separate from RFP AI / Knowledge AI
    // so usage shows up as its own workspace on the Anthropic console).
    $stmt = $conn->prepare(
        "SELECT setting_key, setting_value FROM system_settings
          WHERE setting_key IN ('tickets_reply_cleanup_api_key',
                                'tickets_reply_cleanup_model',
                                'tickets_reply_cleanup_tone')"
    );
    $stmt->execute();

    $apiKey = '';
    $model  = '';
    $tone   = '';
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $val = $row['setting_value'];
        if ($row['setting_key'] === 'tickets_reply_cleanup_api_key') {
            $apiKey = $val !== '' ? decryptValue($val) : '';
        } elseif ($row['setting_key'] === 'tickets_reply_cleanup_model') {
            $model = $val ?? '';
        } elseif ($row['setting_key'] === 'tickets_reply_cleanup_tone') {
            $tone = $val ?? '';
        }
    }

    if ($apiKey === '') {
        sse_send('error', ['message' => 'Reply Cleanup AI not configured. Set up the key in Tickets → Settings → Reply Cleanup.']);
        exit;
    }
    if ($model === '') $model = 'claude-haiku-4-5-20251001';
    if ($tone  === '') $tone  = 'Friendly';

    // Resolve requester first name + ticket subject in one query.
    // users.preferred_name wins if the user has set one, else fall back to
    // users.display_name. Take the first whitespace-delimited token as the
    // greeting name ("Sarah Johnson" → "Sarah").
    $ticketStmt = $conn->prepare(
        "SELECT t.subject,
                COALESCE(NULLIF(TRIM(u.preferred_name), ''), u.display_name) AS name
           FROM tickets t
      LEFT JOIN users u ON u.id = t.user_id
          WHERE t.id = ?"
    );
    $ticketStmt->execute([$ticketId]);
    $ticketRow = $ticketStmt->fetch(PDO::FETCH_ASSOC) ?: ['subject' => '', 'name' => ''];
    $ticketSubject = trim((string)($ticketRow['subject'] ?? ''));
    $name = trim((string)($ticketRow['name'] ?? ''));
    $firstName = '';
    if ($name !== '') {
        $parts = preg_split('/\s+/', $name);
        $firstName = $parts[0] ?? '';
    }
    $greetingName = $firstName !== '' ? $firstName : 'there';

    // Fetch the first inbound email body — the "original problem reported".
    // This gives Claude enough context to (a) reference the issue in a short
    // verification ask when the draft is terse, and (b) phrase that ask
    // appropriately (e.g. "test" for issues, "confirm receipt" for hardware
    // requests). Capped to keep tokens predictable.
    $emailStmt = $conn->prepare(
        "SELECT body_content
           FROM emails
          WHERE ticket_id = ? AND direction = 'Inbound'
       ORDER BY received_datetime ASC, id ASC
          LIMIT 1"
    );
    $emailStmt->execute([$ticketId]);
    $rawBody = (string)($emailStmt->fetchColumn() ?: '');

    $originalProblem = '';
    if ($rawBody !== '') {
        $stripped = strip_tags($rawBody);
        $stripped = html_entity_decode($stripped, ENT_QUOTES, 'UTF-8');
        $stripped = preg_replace('/\s+/', ' ', $stripped) ?? '';
        $stripped = trim($stripped);
        if (mb_strlen($stripped) > 2000) {
            $stripped = mb_substr($stripped, 0, 2000) . '…';
        }
        $originalProblem = $stripped;
    }

    // Tone description — kept short so the cached system prompt stays small.
    $toneDescription = match ($tone) {
        'Formal'  => 'Polite, professional, formal British English. No contractions.',
        'Brief'   => 'Polite, concise, no padding. British English.',
        default   => 'Polite, friendly, professional British English.',
    };

    $system = <<<PROMPT
You clean up rough draft replies for IT support analysts. The user message will give you the ticket context (subject + original problem) AND the analyst's draft.

Your ONLY job is to:
- Add a "Dear {$greetingName}," greeting at the top
- Turn the analyst's shorthand and sentence fragments into proper full grammatical sentences
- Combine closely related points into a single short sentence where it reads more naturally
- Add paragraph breaks where natural
- Fix spelling and grammar
- Add "Kind regards," at the end (no name — the analyst signature is appended afterwards)
- Apply the requested tone

Tone: {$toneDescription}

# CONTEXT ENRICHMENT (only when the draft is VERY SHORT)

If — and ONLY if — the draft is fewer than ~15 words AND has no full sentence (e.g. just "fixed", "done", "work completed", "sorted", "delivered"), you may add ONE short sentence that:
1. Briefly references what was being addressed (using wording close to the original ticket subject)
2. Asks the user to verify in a way that fits the situation

Match the verification verb to the situation — pick the one that fits:
- Technical issues (errors, slowness, broken things, access problems): "please test it and let us know" / "please try again and let us know if it persists"
- Account / login / access requests: "please try logging in and confirm it works"
- Software install or config requests: "please launch it and confirm everything's working"
- Hardware / equipment delivery (mouse, keyboard, laptop, monitor): "please confirm it's set up correctly" / "please let us know if you have any issues setting it up" — NEVER use the words "test" or "re-test" for delivered hardware
- Information requests / questions answered: "please let us know if you need anything further" — NO verification ask needed

NEVER use the literal phrase "please re-test" verbatim — choose phrasing that fits the actual situation.

If the draft is LONGER than ~15 words OR contains complete sentences OR already mentions the issue OR already has its own next-steps/verification instructions ("call if not working", "let me know", "give it a try"), DO NOT add a context sentence — just clean up the grammar and stop.

# HARD CONSTRAINTS

You MUST NOT:
- Invent technical details, dates, ticket numbers, or facts not in the draft or ticket context
- Generalise or rename the problem ("Outlook crash" must not become "your email problem")
- Quote, summarise, or repeat details from the ticket body beyond the one-clause reference
- Add apologies, explanations, recommendations, or extra next steps the analyst didn't write
- Pad short drafts into multiple paragraphs (max one short context sentence + one short verification ask)
- Output any preamble like "Here is the cleaned-up email:"
- Add subject lines, signatures with names, footers, disclaimers, or contact details

# EXAMPLES

POSITIVE example A — draft is already substantial, just fix grammar (no enrichment):
Draft: "DNS issue resolved. Emails going out nicely. Any further problems let us know"
Correct output:
Dear Sarah,

The DNS issue has been resolved and emails are now sending normally. Please let us know if you experience any further problems.

Kind regards,

POSITIVE example B — VERY short draft on a technical-issue ticket (enrich):
Ticket subject: "Outlook keeps crashing on startup"
Draft: "fixed"
Correct output:
Dear Sarah,

The issue with Outlook crashing on startup has been resolved. Please test it and let us know if you experience any further problems.

Kind regards,

POSITIVE example C — VERY short draft on a hardware-request ticket (enrich, but NO "re-test"):
Ticket subject: "New mouse needed for desk B14"
Draft: "delivered"
Correct output:
Dear John,

Your new mouse has been delivered. Please let us know if you have any issues setting it up.

Kind regards,

POSITIVE example D — VERY short draft on an account-access ticket:
Ticket subject: "Cannot log into VPN"
Draft: "sorted"
Correct output:
Dear Mark,

Your VPN access has been restored. Please try logging in and confirm it works.

Kind regards,

NEGATIVE example — never pad / embellish / fabricate:
Ticket subject: "Outlook keeps crashing on startup"
Draft: "fixed"
WRONG output (do NOT do this):
Dear Sarah,

I'm pleased to inform you that I've successfully resolved the issue with Outlook crashing on startup. After investigating, I identified the root cause and applied the necessary fix. Outlook should now launch and run smoothly without any crashes. Please test it thoroughly and let me know if you encounter any further issues. I've also taken steps to prevent this from happening again.

Kind regards,

# OUTPUT FORMAT
Plain text only. Use a single blank line between paragraphs. No HTML, no markdown.
PROMPT;

    $userMessage = "TICKET CONTEXT:\n";
    $userMessage .= "Subject: " . ($ticketSubject !== '' ? $ticketSubject : '(none)') . "\n";
    $userMessage .= "Original problem reported by the user:\n";
    $userMessage .= ($originalProblem !== '' ? $originalProblem : '(no inbound email body on file)') . "\n\n";
    $userMessage .= "ANALYST'S DRAFT REPLY (clean this up):\n";
    $userMessage .= $draftText;

    $resp = rfpAiCallAnthropicStreaming(
        $conn,
        [
            'system'      => $system,
            'user'        => $userMessage,
            'max_tokens'  => 1024,
            'temperature' => 0.3,
        ],
        function (string $eventType, array $data) {
            if ($eventType === 'text') {
                sse_send('text', ['delta' => $data['delta'] ?? '']);
            } elseif ($eventType === 'usage') {
                sse_send('usage', $data);
            }
        },
        [
            'provider'   => 'anthropic',
            'api_key'    => $apiKey,
            'model'      => $model,
            'verify_ssl' => SSL_VERIFY_PEER,
        ]
    );

    sse_send('done', [
        'duration_ms' => $resp['duration_ms'] ?? null,
        'tokens_in'   => $resp['tokens_in']   ?? null,
        'tokens_out'  => $resp['tokens_out']  ?? null,
        'cache_read'  => $resp['cache_read']  ?? null,
        'cache_write' => $resp['cache_write'] ?? null,
    ]);

} catch (Throwable $e) {
    sse_send('error', ['message' => $e->getMessage()]);
}
