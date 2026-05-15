<?php
/**
 * తెలుగు (te) — Process Mapper module strings.
 * Falls back per-key to lang/en/process-mapper.php for anything missing.
 */
return [
    'title' => 'ప్రక్రియ మాపర్',

    'toolbar' => [
        'process'   => 'ప్రక్రియ',
        'decision'  => 'నిర్ణయం',
        'terminal'  => 'ప్రారంభం/ముగింపు',
        'document'  => 'పత్రం',
        'connect'   => 'కనెక్ట్',
        'group'     => 'సమూహం',
        'lane'      => 'లేన్',
        'export'    => 'ఎగుమతి',
        'save'      => 'భద్రపరచు',
    ],

    'autosave' => [
        'label'   => 'ఆటో సేవ్',
        'saved'   => 'భద్రపరచబడింది',
        'unsaved' => 'భద్రపరచబడలేదు',
        'unsaved_changes' => 'భద్రపరచబడని మార్పులు',
        'saving'  => 'భద్రపరుస్తోంది…',
        'failed'  => 'సేవ్ విఫలమైంది —',
        'retry'   => 'మళ్ళీ ప్రయత్నించు',
        'off'     => 'ఆటో సేవ్ ఆఫ్',
        'tooltip' => 'మీరు ఎడిటింగ్ ఆపిన కొన్ని సెకన్ల తర్వాత ఆటోమేటిక్‌గా సేవ్ చేస్తుంది',
    ],

    'detail' => [
        'step_title'   => 'దశ వివరాలు',
        'group_title'  => 'సమూహ వివరాలు',
        'lane_title'   => 'లేన్ వివరాలు',
        'label'        => 'లేబుల్',
        'type'         => 'రకం',
        'colour'       => 'రంగు',
        'gradient'     => 'గ్రేడియంట్',
        'description'  => 'వివరణ',
        'position'     => 'స్థానం',
        'size'         => 'పరిమాణం',
        'height'       => 'ఎత్తు',
        'order'        => 'క్రమం (పై నుండి క్రిందికి)',
        'connectors'   => 'కనెక్టర్లు',
        'no_connectors'=> 'కనెక్టర్లు లేవు',
    ],

    'export_modal' => [
        'title'  => 'ఎగుమతి — Mermaid ఫ్లోచార్ట్',
        'hint'   => 'Mermaid మద్దతు ఉన్న ఏదైనా Markdown ఎడిటర్‌లో ఈ మార్క్‌అప్‌ను అతికించండి (GitHub, GitLab, Notion, Confluence, Obsidian…). లేన్‌లు <code>subgraph</code> బ్లాక్‌లుగా మారతాయి; ఆటో-లేఅవుట్ మీ చేతితో ఇచ్చిన స్థానాలను భర్తీ చేస్తుంది.',
        'copy'   => 'కాపీ',
        'copied' => 'కాపీ చేయబడింది ✓',
        'close'  => 'మూసివేయి',
    ],

    'toast' => [
        'no_process_open' => 'మొదట ఒక ప్రక్రియను తెరవండి లేదా సృష్టించండి',
        'saved'           => 'భద్రపరచబడింది',
        'save_failed'     => 'భద్రపరచడం విఫలమైంది',
    ],
];
