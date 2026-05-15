<?php
/**
 * मराठी (mr) — Process Mapper module strings.
 * Falls back per-key to lang/en/process-mapper.php for anything missing.
 */
return [
    'title' => 'प्रक्रिया मॅपर',

    'toolbar' => [
        'process'   => 'प्रक्रिया',
        'decision'  => 'निर्णय',
        'terminal'  => 'सुरुवात/शेवट',
        'document'  => 'दस्तऐवज',
        'connect'   => 'जोडा',
        'group'     => 'गट',
        'lane'      => 'लेन',
        'export'    => 'निर्यात',
        'save'      => 'जतन करा',
    ],

    'autosave' => [
        'label'   => 'स्वयं-जतन',
        'saved'   => 'जतन केले',
        'unsaved' => 'जतन केलेले नाही',
        'unsaved_changes' => 'जतन न केलेले बदल',
        'saving'  => 'जतन करत आहे…',
        'failed'  => 'जतन अयशस्वी —',
        'retry'   => 'पुन्हा प्रयत्न',
        'off'     => 'स्वयं-जतन बंद',
        'tooltip' => 'संपादन थांबल्यानंतर काही सेकंदात आपोआप जतन होते',
    ],

    'detail' => [
        'step_title'   => 'पायरीचे तपशील',
        'group_title'  => 'गटाचे तपशील',
        'lane_title'   => 'लेनचे तपशील',
        'label'        => 'लेबल',
        'type'         => 'प्रकार',
        'colour'       => 'रंग',
        'gradient'     => 'ग्रेडियंट',
        'description'  => 'वर्णन',
        'position'     => 'स्थान',
        'size'         => 'आकार',
        'height'       => 'उंची',
        'order'        => 'क्रम (वरून खाली)',
        'connectors'   => 'कनेक्टर',
        'no_connectors'=> 'कनेक्टर नाहीत',
    ],

    'export_modal' => [
        'title'  => 'निर्यात — Mermaid फ्लोचार्ट',
        'hint'   => 'Mermaid समर्थन असलेल्या कोणत्याही Markdown संपादकात हे मार्कअप पेस्ट करा (GitHub, GitLab, Notion, Confluence, Obsidian…). लेन <code>subgraph</code> ब्लॉक बनतात; ऑटो-लेआउट तुम्ही हाताने ठेवलेल्या स्थानांची जागा घेतो.',
        'copy'   => 'कॉपी',
        'copied' => 'कॉपी झाले ✓',
        'close'  => 'बंद',
    ],

    'toast' => [
        'no_process_open' => 'प्रथम एक प्रक्रिया उघडा किंवा तयार करा',
        'saved'           => 'जतन केले',
        'save_failed'     => 'जतन अयशस्वी',
    ],
];
