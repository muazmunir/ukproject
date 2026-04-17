<?php

return [
    // default UI label if nothing chosen yet
    'default_label' => 'English',

    // code => [Label, isRtl]
    'languages' => [
        'af' => ['Afrikaans', false],
        'ar' => ['العربية', true],
        'bn' => ['বাংলা', false],
        'de' => ['Deutsch', false],
        'en' => ['English', false],
        'es' => ['Español', false],
        'fa' => ['فارسی', true],
        'fr' => ['Français', false],
        'gu' => ['ગુજરાતી', false],
        'hi' => ['हिन्दी', false],
        'id' => ['Bahasa Indonesia', false],
        'it' => ['Italiano', false],
        'ja' => ['日本語', false],
        'ko' => ['한국어', false],
        'mr' => ['मराठी', false],
        'nl' => ['Nederlands', false],
        'pa' => ['ਪੰਜਾਬੀ', false],
        'pt' => ['Português', false],
        'ru' => ['Русский', false],
        'ta' => ['தமிழ்', false],
        'te' => ['తెలుగు', false],
        'th' => ['ไทย', false],
        'tr' => ['Türkçe', false],
        'ur' => ['اردو', true],
        'vi' => ['Tiếng Việt', false],
        'zh-CN' => ['简体中文', false],
        'zh-TW' => ['繁體中文', false],
    ],
    // languages that should flip page direction
    'rtl_codes' => ['ar','fa','ur','he'],
];
