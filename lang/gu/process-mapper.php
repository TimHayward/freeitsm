<?php
/**
 * ગુજરાતી (gu) — Process Mapper module strings.
 * Falls back per-key to lang/en/process-mapper.php for anything missing.
 */
return [
    'title' => 'પ્રક્રિયા મેપર',

    'toolbar' => [
        'process'   => 'પ્રક્રિયા',
        'decision'  => 'નિર્ણય',
        'terminal'  => 'શરૂઆત/અંત',
        'document'  => 'દસ્તાવેજ',
        'connect'   => 'જોડો',
        'group'     => 'જૂથ',
        'lane'      => 'લેન',
        'export'    => 'નિકાસ',
        'save'      => 'સાચવો',
    ],

    'autosave' => [
        'label'   => 'ઑટો-સેવ',
        'saved'   => 'સાચવ્યું',
        'unsaved' => 'સાચવ્યું નથી',
        'unsaved_changes' => 'સાચવ્યા વગરના ફેરફારો',
        'saving'  => 'સાચવી રહ્યું છે…',
        'failed'  => 'સાચવવામાં નિષ્ફળ —',
        'retry'   => 'ફરી પ્રયાસ',
        'off'     => 'ઑટો-સેવ બંધ',
        'tooltip' => 'સંપાદન બંધ કર્યાની થોડી સેકન્ડ પછી આપમેળે સાચવે છે',
    ],

    'detail' => [
        'step_title'   => 'પગલાંની વિગતો',
        'group_title'  => 'જૂથની વિગતો',
        'lane_title'   => 'લેનની વિગતો',
        'label'        => 'લેબલ',
        'type'         => 'પ્રકાર',
        'colour'       => 'રંગ',
        'gradient'     => 'ગ્રેડિયન્ટ',
        'description'  => 'વર્ણન',
        'position'     => 'સ્થાન',
        'size'         => 'કદ',
        'height'       => 'ઊંચાઈ',
        'order'        => 'ક્રમ (ઉપરથી નીચે)',
        'connectors'   => 'કનેક્ટર્સ',
        'no_connectors'=> 'કોઈ કનેક્ટર્સ નથી',
    ],

    'export_modal' => [
        'title'  => 'નિકાસ — Mermaid ફ્લોચાર્ટ',
        'hint'   => 'Mermaid ને સપોર્ટ કરતા કોઈપણ Markdown એડિટરમાં આ માર્કઅપ પેસ્ટ કરો (GitHub, GitLab, Notion, Confluence, Obsidian…). લેન <code>subgraph</code> બ્લોક્સ બને છે; ઑટો-લેઆઉટ તમે હાથથી ગોઠવેલા સ્થાનોની જગ્યાએ આવે છે.',
        'copy'   => 'નકલ',
        'copied' => 'નકલ થઈ ✓',
        'close'  => 'બંધ',
    ],

    'toast' => [
        'no_process_open' => 'પહેલા પ્રક્રિયા ખોલો અથવા બનાવો',
        'saved'           => 'સાચવ્યું',
        'save_failed'     => 'સાચવવામાં નિષ્ફળ',
    ],
];
