<?php
/**
 * বাংলা (bn) — Process Mapper module strings.
 * Falls back per-key to lang/en/process-mapper.php for anything missing.
 */
return [
    'title' => 'প্রসেস ম্যাপার',

    'toolbar' => [
        'process'   => 'প্রক্রিয়া',
        'decision'  => 'সিদ্ধান্ত',
        'terminal'  => 'শুরু/শেষ',
        'document'  => 'দলিল',
        'connect'   => 'সংযোগ',
        'group'     => 'গোষ্ঠী',
        'lane'      => 'লেন',
        'export'    => 'রপ্তানি',
        'save'      => 'সংরক্ষণ',
    ],

    'autosave' => [
        'label'   => 'স্বয়ংক্রিয় সংরক্ষণ',
        'saved'   => 'সংরক্ষিত',
        'unsaved' => 'অসংরক্ষিত',
        'unsaved_changes' => 'অসংরক্ষিত পরিবর্তন',
        'saving'  => 'সংরক্ষণ করা হচ্ছে…',
        'failed'  => 'সংরক্ষণ ব্যর্থ —',
        'retry'   => 'পুনরায় চেষ্টা',
        'off'     => 'স্বয়ংক্রিয় সংরক্ষণ বন্ধ',
        'tooltip' => 'সম্পাদনা থামার কয়েক সেকেন্ড পরে স্বয়ংক্রিয়ভাবে সংরক্ষণ করে',
    ],

    'detail' => [
        'step_title'   => 'ধাপের বিবরণ',
        'group_title'  => 'গোষ্ঠীর বিবরণ',
        'lane_title'   => 'লেনের বিবরণ',
        'label'        => 'লেবেল',
        'type'         => 'প্রকার',
        'colour'       => 'রঙ',
        'gradient'     => 'গ্রেডিয়েন্ট',
        'description'  => 'বর্ণনা',
        'position'     => 'অবস্থান',
        'size'         => 'আকার',
        'height'       => 'উচ্চতা',
        'order'        => 'ক্রম (উপর থেকে নিচে)',
        'connectors'   => 'সংযোগকারী',
        'no_connectors'=> 'কোনো সংযোগকারী নেই',
    ],

    'export_modal' => [
        'title'  => 'রপ্তানি — Mermaid ফ্লোচার্ট',
        'hint'   => 'Mermaid সমর্থনকারী যেকোনো Markdown সম্পাদকে এই মার্কআপ পেস্ট করুন (GitHub, GitLab, Notion, Confluence, Obsidian…)। লেনগুলি <code>subgraph</code> ব্লকে পরিণত হয়; অটো-লেআউট আপনার হাতে রাখা অবস্থানগুলির বদলে কাজ করে।',
        'copy'   => 'কপি',
        'copied' => 'কপি হয়েছে ✓',
        'close'  => 'বন্ধ',
    ],

    'toast' => [
        'no_process_open' => 'প্রথমে একটি প্রক্রিয়া খুলুন বা তৈরি করুন',
        'saved'           => 'সংরক্ষিত',
        'save_failed'     => 'সংরক্ষণ ব্যর্থ',
    ],
];
