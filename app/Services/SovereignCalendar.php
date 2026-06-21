<?php

namespace App\Services;

use Carbon\Carbon;

/**
 * 🗓️ SovereignCalendar — The Right Interface at the Right Moment
 *
 * Determines which holiday/event is active on a given date.
 * Returns a string key used as `data-holiday` attribute in blade templates.
 *
 * Priority: earlier entries win if dates overlap.
 * Each event has: key, name, title_ru, description, month, day_from, day_to, and aesthetics.
 */
class SovereignCalendar
{
    /**
     * Unified event registry.
     * Events are checked in order — first match wins.
     */
    public static array $events = [
        // ─── Q1: January ──────────────────────────────────────────────────────
        [
            'key'         => 'new-year',
            'name'        => 'Sovereign Winter Festival',
            'title_ru'    => 'Зимние праздники Meanly',
            'description' => 'Deep emerald pine branches, festive crimson, and falling snow.',
            'month'       => 1,
            'day_from'    => 1,
            'day_to'      => 10,
            'aesthetics'  => [
                'brand_primary'   => '#059669', // Deep Emerald
                'brand_secondary' => '#dc2626',
                'accent_color'    => '#e2e8f0',
                'particle_type'   => 'snowflake-sparkle',
                'glow_effect'     => 'radial-gradient(circle, rgba(5,150,105,0.12) 0%, rgba(0,0,0,0) 70%)',
                'sound_theme'     => 'winter-chimes',
            ],
        ],

        // ─── Q1: February ─────────────────────────────────────────────────────
        [
            'key'         => 'valentine',
            'name'        => 'St. Valentine\'s Day',
            'title_ru'    => 'День святого Валентина',
            'description' => 'Deep crimson hearts and soft romantic gradients.',
            'month'       => 2,
            'day_from'    => 12,
            'day_to'      => 16,
            'aesthetics'  => [
                'brand_primary'   => '#f43f5e',
                'brand_secondary' => '#fbcfe8',
                'accent_color'    => '#e11d48',
                'particle_type'   => 'crimson-heart',
                'glow_effect'     => 'radial-gradient(circle, rgba(244,63,94,0.14) 0%, rgba(0,0,0,0) 70%)',
                'sound_theme'     => 'romantic-soft',
            ],
        ],
        [
            'key'         => 'defender-day',
            'name'        => 'Defender of the Fatherland Day',
            'title_ru'    => 'День защитника Отечества 🪖',
            'description' => 'Strict slate-grey colors and solid honorable styling.',
            'month'       => 2,
            'day_from'    => 23,
            'day_to'      => 23,
            'aesthetics'  => [
                'brand_primary'   => '#64748b',
                'brand_secondary' => '#475569',
                'accent_color'    => '#f1f5f9',
                'particle_type'   => 'slate-star',
                'glow_effect'     => 'radial-gradient(circle, rgba(100,116,139,0.1) 0%, rgba(0,0,0,0) 70%)',
                'sound_theme'     => 'military-march',
            ],
        ],

        // ─── Q1: March ────────────────────────────────────────────────────────
        [
            'key'         => 'womens-day',
            'name'        => 'International Women\'s Day',
            'title_ru'    => 'Международный женский день',
            'description' => 'Golden mimosa blossoms and spring renewal colors.',
            'month'       => 3,
            'day_from'    => 6,
            'day_to'      => 10,
            'aesthetics'  => [
                'brand_primary'   => '#eab308',
                'brand_secondary' => '#ec4899',
                'accent_color'    => '#fdf2f8',
                'particle_type'   => 'mimosa-blossom',
                'glow_effect'     => 'radial-gradient(circle, rgba(234,179,8,0.1) 0%, rgba(0,0,0,0) 70%)',
                'sound_theme'     => 'spring-cheerful',
            ],
        ],

        // ─── Q2: April ────────────────────────────────────────────────────────
        [
            'key'         => 'cosmonautics-day',
            'name'        => 'Cosmonautics Day',
            'title_ru'    => 'День космонавтики 🚀',
            'description' => 'Mystic deep indigo stars and galactic exploratory glows.',
            'month'       => 4,
            'day_from'    => 12,
            'day_to'      => 12,
            'aesthetics'  => [
                'brand_primary'   => '#6366f1',
                'brand_secondary' => '#4f46e5',
                'accent_color'    => '#e0e7ff',
                'particle_type'   => 'cosmic-star',
                'glow_effect'     => 'radial-gradient(circle, rgba(99,102,241,0.12) 0%, rgba(0,0,0,0) 70%)',
                'sound_theme'     => 'space-ambient',
            ],
        ],
        [
            'key'         => 'doctor-day',
            'name'        => 'Medical Heroism Day',
            'title_ru'    => 'День медицинского работника',
            'description' => 'Digital cyan grids and glowing cross particles.',
            'month'       => 4,
            'day_from'    => 21,
            'day_to'      => 21,
            'aesthetics'  => [
                'brand_primary'   => '#06b6d4',
                'brand_secondary' => '#0d9488',
                'accent_color'    => '#ffffff',
                'particle_type'   => 'medical-grid',
                'glow_effect'     => 'radial-gradient(circle, rgba(6,182,212,0.1) 0%, rgba(0,0,0,0) 70%)',
                'sound_theme'     => 'clinical-calm',
            ],
        ],

        // ─── Q2: May ──────────────────────────────────────────────────────────
        [
            'key'         => 'may-day',
            'name'        => 'Spring and Labor Day',
            'title_ru'    => 'День Труда 🌹',
            'description' => 'Solid workers red and spring floral vibes.',
            'month'       => 5,
            'day_from'    => 1,
            'day_to'      => 1,
            'aesthetics'  => [
                'brand_primary'   => '#ef4444',
                'brand_secondary' => '#dc2626',
                'accent_color'    => '#fef2f2',
                'particle_type'   => 'red-rose',
                'glow_effect'     => 'radial-gradient(circle, rgba(239,68,68,0.12) 0%, rgba(0,0,0,0) 70%)',
                'sound_theme'     => 'spring-cheerful',
            ],
        ],
        [
            'key'         => 'victory-day',
            'name'        => 'Victory Day',
            'title_ru'    => 'День Победы 🎖️',
            'description' => 'Deep memorial red and glowing honor stars.',
            'month'       => 5,
            'day_from'    => 9,
            'day_to'      => 9,
            'aesthetics'  => [
                'brand_primary'   => '#dc2626',
                'brand_secondary' => '#991b1b',
                'accent_color'    => '#fef2f2',
                'particle_type'   => 'victory-star',
                'glow_effect'     => 'radial-gradient(circle, rgba(220,38,38,0.14) 0%, rgba(0,0,0,0) 70%)',
                'sound_theme'     => 'classical-serene',
            ],
        ],
        [
            'key'         => 'orchid-day',
            'name'        => 'Orchid Bloom Festival',
            'title_ru'    => 'Фестиваль цветущих орхидей',
            'description' => 'Aesthetic spring blossom with floating magenta orchid petals.',
            'month'       => 5,
            'day_from'    => 12,
            'day_to'      => 12,
            'aesthetics'  => [
                'brand_primary'   => '#d946ef',
                'brand_secondary' => '#c084fc',
                'accent_color'    => '#fbcfe8',
                'particle_type'   => 'orchid-petal',
                'glow_effect'     => 'radial-gradient(circle, rgba(217,70,239,0.12) 0%, rgba(0,0,0,0) 70%)',
                'sound_theme'     => 'ambient-zen',
            ],
        ],
        [
            'key'         => 'sons-birthday',
            'name'        => 'Sovereign Heir Day',
            'title_ru'    => 'День рождения наследника',
            'description' => 'Celebrating with Albiceleste heavenly-blue aesthetics and solar glows.',
            'month'       => 5,
            'day_from'    => 19,
            'day_to'      => 19,
            'aesthetics'  => [
                'brand_primary'   => '#74acdf',
                'brand_secondary' => '#ffffff',
                'accent_color'    => '#ffb900',
                'particle_type'   => 'argentine-sun',
                'glow_effect'     => 'radial-gradient(circle, rgba(116,172,223,0.15) 0%, rgba(0,0,0,0) 70%)',
                'sound_theme'     => 'classical-serene',
            ],
        ],

        // ─── Q2: June ─────────────────────────────────────────────────────────
        [
            'key'         => 'russia-day',
            'name'        => 'Russia Day',
            'title_ru'    => 'День России 🇷🇺',
            'description' => 'Patriotic blue and white-blue-red tricolor accents.',
            'month'       => 6,
            'day_from'    => 12,
            'day_to'      => 12,
            'aesthetics'  => [
                'brand_primary'   => '#3b82f6',
                'brand_secondary' => '#1d4ed8',
                'accent_color'    => '#ffffff',
                'particle_type'   => 'tricolor-sparkle',
                'glow_effect'     => 'radial-gradient(circle, rgba(59,130,246,0.12) 0%, rgba(0,0,0,0) 70%)',
                'sound_theme'     => 'classical-serene',
            ],
        ],

        // ─── Q3: August ───────────────────────────────────────────────────────
        [
            'key'         => 'babel-library',
            'name'        => 'Library of Babel Solstice',
            'title_ru'    => 'День Вавилонской Библиотеки',
            'description' => 'Amber book pages and drifting hexadecimal characters.',
            'month'       => 8,
            'day_from'    => 24,
            'day_to'      => 24,
            'aesthetics'  => [
                'brand_primary'   => '#d97706',
                'brand_secondary' => '#292524',
                'accent_color'    => '#854d0e',
                'particle_type'   => 'parchment-symbol',
                'glow_effect'     => 'radial-gradient(circle, rgba(217,119,6,0.12) 0%, rgba(0,0,0,0) 70%)',
                'sound_theme'     => 'monastery-echoes',
            ],
        ],

        // ─── Q2: June ─────────────────────────────────────────────────────────
        [
            'key'         => 'little-prince',
            'name'         => 'Beloved\'s Birthday',
            'title_ru'    => 'День рождения любимой 🌹',
            'description' => 'Drifting golden stars and cosmic rose petals for my beloved.',
            'month'       => 6,
            'day_from'    => 15,
            'day_to'      => 16,
            'aesthetics'  => [
                'brand_primary'   => '#e11d48',
                'brand_secondary' => '#fbbf24',
                'accent_color'    => '#09090b',
                'particle_type'   => 'cosmic-star',
                'glow_effect'     => 'radial-gradient(circle, rgba(225,29,72,0.12) 0%, rgba(0,0,0,0) 70%)',
                'sound_theme'     => 'musicbox-lullaby',
            ],
        ],
        [
            'key'         => 'halloween',
            'name'        => 'Sovereign Halloween',
            'title_ru'    => 'Суверенный Хэллоуин',
            'description' => 'Spooky gothic neobrutalism with dark harvest pumpkin embers.',
            'month'       => 10,
            'day_from'    => 25,
            'day_to'      => 31, // (overlaps with Q4 November but keeps strictly in October)
            'aesthetics'  => [
                'brand_primary'   => '#f97316',
                'brand_secondary' => '#7c3aed',
                'accent_color'    => '#000000',
                'particle_type'   => 'pumpkin-ember',
                'glow_effect'     => 'radial-gradient(circle, rgba(249,115,22,0.08) 0%, rgba(0,0,0,0) 70%)',
                'sound_theme'     => 'gothic-dark',
            ],
        ],

        // ─── Q4: November ─────────────────────────────────────────────────────
        [
            'key'         => 'national-unity',
            'name'        => 'National Unity Day',
            'title_ru'    => 'День народного единства 🤝',
            'description' => 'Warm orange and golden amber unity tones.',
            'month'       => 11,
            'day_from'    => 4,
            'day_to'      => 4,
            'aesthetics'  => [
                'brand_primary'   => '#f97316',
                'brand_secondary' => '#ea580c',
                'accent_color'    => '#fff7ed',
                'particle_type'   => 'orange-sparkle',
                'glow_effect'     => 'radial-gradient(circle, rgba(249,115,22,0.1) 0%, rgba(0,0,0,0) 70%)',
                'sound_theme'     => 'ambient-zen',
            ],
        ],
        [
            'key'         => 'black-friday',
            'name'        => 'Midnight Cyber-Sale',
            'title_ru'    => 'Кибер-распродажа Черная Пятница',
            'description' => 'High-contrast cybernetic lime and neon grid codes.',
            'month'       => 11,
            'day_from'    => 20,
            'day_to'      => 30,
            'aesthetics'  => [
                'brand_primary'   => '#22c55e',
                'brand_secondary' => '#a855f7',
                'accent_color'    => '#000000',
                'particle_type'   => 'cyber-rain',
                'glow_effect'     => 'radial-gradient(circle, rgba(34,197,94,0.08) 0%, rgba(0,0,0,0) 70%)',
                'sound_theme'     => 'synthwave-glitch',
            ],
        ],
        // ─── Q4: December ─────────────────────────────────────────────────────
        [
            'key'         => 'constitution-day',
            'name'        => 'Constitution Day',
            'title_ru'    => 'День Конституции 📜',
            'description' => 'Sovereign legal violet and gold seals.',
            'month'       => 12,
            'day_from'    => 12,
            'day_to'      => 12,
            'aesthetics'  => [
                'brand_primary'   => '#8b5cf6',
                'brand_secondary' => '#7c3aed',
                'accent_color'    => '#f5f3ff',
                'particle_type'   => 'gold-seal',
                'glow_effect'     => 'radial-gradient(circle, rgba(139,92,246,0.12) 0%, rgba(0,0,0,0) 70%)',
                'sound_theme'     => 'classical-serene',
            ],
        ],
        [
            'key'         => 'new-year-eve',
            'name'        => 'New Year\'s Eve',
            'title_ru'    => 'Канун Нового года 🎇',
            'description' => 'Light violet evening glow and magical anticipation sparks.',
            'month'       => 12,
            'day_from'    => 25,
            'day_to'      => 30,
            'aesthetics'  => [
                'brand_primary'   => '#a78bfa',
                'brand_secondary' => '#8b5cf6',
                'accent_color'    => '#f5f3ff',
                'particle_type'   => 'twilight-star',
                'glow_effect'     => 'radial-gradient(circle, rgba(167,139,250,0.12) 0%, rgba(0,0,0,0) 70%)',
                'sound_theme'     => 'winter-chimes',
            ],
        ],
        [
            'key'         => 'new-year',
            'name'        => 'Sovereign Winter Festival',
            'title_ru'    => 'Зимние праздники Meanly',
            'description' => 'Deep emerald pine branches, festive crimson, and falling snow.',
            'month'       => 12,
            'day_from'    => 31,
            'day_to'      => 31,
            'aesthetics'  => [
                'brand_primary'   => '#059669',
                'brand_secondary' => '#dc2626',
                'accent_color'    => '#e2e8f0',
                'particle_type'   => 'snowflake-sparkle',
                'glow_effect'     => 'radial-gradient(circle, rgba(5,150,105,0.12) 0%, rgba(0,0,0,0) 70%)',
                'sound_theme'     => 'winter-chimes',
            ],
        ],
    ];

    /**
     * Resolve the active holiday key for a given date.
     * Returns null if no event is active.
     */
    public static function resolve(?Carbon $date = null): ?string
    {
        $date ??= Carbon::now();

        $month = (int) $date->format('n');
        $day   = (int) $date->format('j');

        foreach (self::$events as $event) {
            if ($event['month'] === $month
                && $day >= $event['day_from']
                && $day <= $event['day_to']
            ) {
                return $event['key'];
            }
        }

        return null;
    }

    /**
     * Get the color associated with a given holiday key.
     */
    public static function colorFor(string $key): ?string
    {
        foreach (self::$events as $event) {
            if ($event['key'] === $key) {
                return $event['aesthetics']['brand_primary'] ?? null;
            }
        }

        return null;
    }

    /**
     * Get a full event descriptor by key.
     */
    public static function event(string $key): ?array
    {
        foreach (self::$events as $event) {
            if ($event['key'] === $key) {
                return $event;
            }
        }

        return null;
    }

    /**
     * Get all events as a map: key => event.
     */
    public static function all(): array
    {
        $map = [];
        foreach (self::$events as $event) {
            $map[$event['key']] = $event;
        }
        return $map;
    }
}
