<?php
/**
 * ಕನ್ನಡ (kn) — Process Mapper module strings.
 * Falls back per-key to lang/en/process-mapper.php for anything missing.
 */
return [
    'title' => 'ಪ್ರಕ್ರಿಯೆ ಮ್ಯಾಪರ್',

    'toolbar' => [
        'process'   => 'ಪ್ರಕ್ರಿಯೆ',
        'decision'  => 'ನಿರ್ಧಾರ',
        'terminal'  => 'ಪ್ರಾರಂಭ/ಅಂತ್ಯ',
        'document'  => 'ದಸ್ತಾವೇಜು',
        'connect'   => 'ಸಂಪರ್ಕಿಸಿ',
        'group'     => 'ಗುಂಪು',
        'lane'      => 'ಲೇನ್',
        'export'    => 'ರಫ್ತು',
        'save'      => 'ಉಳಿಸಿ',
    ],

    'autosave' => [
        'label'   => 'ಸ್ವಯಂ-ಉಳಿತಾಯ',
        'saved'   => 'ಉಳಿಸಲಾಗಿದೆ',
        'unsaved' => 'ಉಳಿಸಿಲ್ಲ',
        'unsaved_changes' => 'ಉಳಿಸದ ಬದಲಾವಣೆಗಳು',
        'saving'  => 'ಉಳಿಸಲಾಗುತ್ತಿದೆ…',
        'failed'  => 'ಉಳಿಸಲು ವಿಫಲವಾಗಿದೆ —',
        'retry'   => 'ಮತ್ತೆ ಪ್ರಯತ್ನಿಸಿ',
        'off'     => 'ಸ್ವಯಂ-ಉಳಿತಾಯ ಆಫ್',
        'tooltip' => 'ಸಂಪಾದನೆ ನಿಲ್ಲಿಸಿದ ಕೆಲವು ಸೆಕೆಂಡುಗಳ ನಂತರ ಸ್ವಯಂಚಾಲಿತವಾಗಿ ಉಳಿಸುತ್ತದೆ',
    ],

    'detail' => [
        'step_title'   => 'ಹಂತದ ವಿವರಗಳು',
        'group_title'  => 'ಗುಂಪಿನ ವಿವರಗಳು',
        'lane_title'   => 'ಲೇನ್ ವಿವರಗಳು',
        'label'        => 'ಲೇಬಲ್',
        'type'         => 'ಪ್ರಕಾರ',
        'colour'       => 'ಬಣ್ಣ',
        'gradient'     => 'ಗ್ರೇಡಿಯಂಟ್',
        'description'  => 'ವಿವರಣೆ',
        'position'     => 'ಸ್ಥಾನ',
        'size'         => 'ಗಾತ್ರ',
        'height'       => 'ಎತ್ತರ',
        'order'        => 'ಕ್ರಮ (ಮೇಲಿನಿಂದ ಕೆಳಗೆ)',
        'connectors'   => 'ಸಂಪರ್ಕಗಳು',
        'no_connectors'=> 'ಯಾವುದೇ ಸಂಪರ್ಕಗಳಿಲ್ಲ',
    ],

    'export_modal' => [
        'title'  => 'ರಫ್ತು — Mermaid ಫ್ಲೋಚಾರ್ಟ್',
        'hint'   => 'Mermaid ಬೆಂಬಲಿಸುವ ಯಾವುದೇ Markdown ಸಂಪಾದಕದಲ್ಲಿ ಈ ಮಾರ್ಕಪ್ ಅನ್ನು ಅಂಟಿಸಿ (GitHub, GitLab, Notion, Confluence, Obsidian…). ಲೇನ್‌ಗಳು <code>subgraph</code> ಬ್ಲಾಕ್‌ಗಳಾಗುತ್ತವೆ; ಸ್ವಯಂಚಾಲಿತ ಲೇಔಟ್ ನಿಮ್ಮ ಕೈಯಿಂದ ಇರಿಸಲಾದ ಸ್ಥಾನಗಳನ್ನು ಬದಲಾಯಿಸುತ್ತದೆ.',
        'copy'   => 'ನಕಲಿಸಿ',
        'copied' => 'ನಕಲಿಸಲಾಗಿದೆ ✓',
        'close'  => 'ಮುಚ್ಚಿ',
    ],

    'toast' => [
        'no_process_open' => 'ಮೊದಲು ಒಂದು ಪ್ರಕ್ರಿಯೆಯನ್ನು ತೆರೆಯಿರಿ ಅಥವಾ ರಚಿಸಿ',
        'saved'           => 'ಉಳಿಸಲಾಗಿದೆ',
        'save_failed'     => 'ಉಳಿಸಲು ವಿಫಲವಾಗಿದೆ',
    ],
];
