<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Reader Fonts
    |--------------------------------------------------------------------------
    |
    | Fonts available for each language. The keys are what the frontend sends
    | (e.g. ?font_ar=amiri). The PDF print template embeds a font from
    | resources/fonts when "file" exists there, otherwise it falls back to
    | the Google Fonts stylesheet named in "google".
    |
    */

    'fonts' => [
        'ar' => [
            'amiri' => ['family' => 'Amiri', 'file' => 'Amiri-Regular.ttf', 'google' => 'Amiri:wght@400;700'],
            'noto-naskh-arabic' => ['family' => 'Noto Naskh Arabic', 'file' => 'NotoNaskhArabic-Regular.ttf', 'google' => 'Noto+Naskh+Arabic:wght@400;700'],
            'kfgqpc-uthmanic' => ['family' => 'KFGQPC Uthmanic Script', 'file' => 'KFGQPCUthmanicScriptHAFS.otf', 'google' => null],
            'scheherazade-new' => ['family' => 'Scheherazade New', 'file' => 'ScheherazadeNew-Regular.ttf', 'google' => 'Scheherazade+New:wght@400;700'],
            'readex-pro' => ['family' => 'Readex Pro', 'file' => 'ReadexPro-Regular.ttf', 'google' => 'Readex+Pro:wght@400;700'],
        ],
        'bn' => [
            'noto-serif-bengali' => ['family' => 'Noto Serif Bengali', 'file' => 'NotoSerifBengali-Regular.ttf', 'google' => 'Noto+Serif+Bengali:wght@400;700'],
            'hind-siliguri' => ['family' => 'Hind Siliguri', 'file' => 'HindSiliguri-Regular.ttf', 'google' => 'Hind+Siliguri:wght@400;700'],
            'kalpurush' => ['family' => 'Kalpurush', 'file' => 'kalpurush.ttf', 'google' => null],
            'solaimanlipi' => ['family' => 'SolaimanLipi', 'file' => 'SolaimanLipi.ttf', 'google' => null],
        ],
    ],

    'default_fonts' => [
        'ar' => 'amiri',
        'bn' => 'noto-serif-bengali',
    ],

    /*
    |--------------------------------------------------------------------------
    | PDF Export
    |--------------------------------------------------------------------------
    */

    'pdf' => [
        'disk' => 'local',
        'paper_width' => env('PDF_PAPER_WIDTH', '5.83in'),
        'paper_height' => env('PDF_PAPER_HEIGHT', '8.27in'),
        'margin' => env('PDF_MARGIN', '0.6in'),
    ],

];
