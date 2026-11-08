<?php
/**
 * Dual Store Service (Coconut Store & Juice Store)
 * N.A Fresh Fruits & Coconuts
 *
 * Provides store-mode detection, persistence (Session, Cookie, LocalStorage sync),
 * metadata resolution, and store-specific catalog separation.
 */

class StoreService {
    public const STORE_COCONUT = 'coconut';
    public const STORE_JUICE   = 'juice';

    public const COOKIE_NAME   = 'na_store_mode';
    public const SESSION_KEY   = 'store_mode';

    public const SETTING_JUICE_ENABLED    = 'juice_store_enabled';
    public const SETTING_SWITCH_ENABLED   = 'store_switch_button_enabled';
    public const SETTING_SWITCH_TO_JUICE  = 'switch_to_juice_text';
    public const SETTING_SWITCH_TO_COCONUT = 'switch_to_coconut_text';
    public const SETTING_JUICE_NAME       = 'juice_store_name';
    public const SETTING_JUICE_TAGLINE    = 'juice_store_tagline';

    /**
     * Resolve the current store mode for the browsing customer
     * Order of precedence:
     * 1. Query parameter (?store=juice / ?store=coconut)
     * 2. Active PHP Session ($_SESSION['store_mode'])
     * 3. Persistent Cookie ($_COOKIE['na_store_mode'])
     * 4. Default: 'coconut' (Strict requirement: Coconut Store always default for first-time visitors)
     */
    public static function getCurrentStore(): string {
        // Query param override
        if (!empty($_GET['store'])) {
            $param = strtolower(trim($_GET['store']));
            if (in_array($param, [self::STORE_COCONUT, self::STORE_JUICE], true)) {
                self::setStore($param);
                return $param;
            }
        }

        // Check active session
        if (!empty($_SESSION[self::SESSION_KEY])) {
            $sess = strtolower(trim($_SESSION[self::SESSION_KEY]));
            if (in_array($sess, [self::STORE_COCONUT, self::STORE_JUICE], true)) {
                return $sess;
            }
        }

        // Check persistent cookie
        if (!empty($_COOKIE[self::COOKIE_NAME])) {
            $cookie = strtolower(trim($_COOKIE[self::COOKIE_NAME]));
            if (in_array($cookie, [self::STORE_COCONUT, self::STORE_JUICE], true)) {
                $_SESSION[self::SESSION_KEY] = $cookie;
                return $cookie;
            }
        }

        // First-time visitors: Always Coconut Store
        return self::STORE_COCONUT;
    }

    /**
     * Persistently set the store mode
     */
    public static function setStore(string $store): bool {
        $clean = strtolower(trim($store));
        if (!in_array($clean, [self::STORE_COCONUT, self::STORE_JUICE], true)) {
            return false;
        }

        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }

        $_SESSION[self::SESSION_KEY] = $clean;
        $_SESSION[self::COOKIE_NAME] = $clean; // also mirror to na_store_mode session key

        // Set persistent cookie for 365 days
        if (!headers_sent()) {
            setcookie(
                self::COOKIE_NAME,
                $clean,
                [
                    'expires'  => time() + (365 * 86400),
                    'path'     => '/',
                    'samesite' => 'Lax',
                    'httponly' => false // allow JS reading for smooth client transitions
                ]
            );
        }

        return true;
    }

    /**
     * Is Juice Store feature active in Admin settings?
     */
    public static function isJuiceEnabled(): bool {
        $val1 = Setting::get(self::SETTING_JUICE_ENABLED, null);
        $val2 = Setting::get('dual_store_juice_enabled', null);
        if ($val1 !== null) return $val1 === '1';
        if ($val2 !== null) return $val2 === '1';
        return true;
    }

    /**
     * Is the store-switching button enabled in Admin settings?
     */
    public static function isSwitchButtonEnabled(): bool {
        if (!self::isJuiceEnabled()) return false;
        $val1 = Setting::get(self::SETTING_SWITCH_ENABLED, null);
        $val2 = Setting::get('dual_store_switch_button_enabled', null);
        if ($val1 !== null) return $val1 === '1';
        if ($val2 !== null) return $val2 === '1';
        return true;
    }

    /**
     * Get visual identity and branding metadata for a store
     */
    public static function getStoreMeta(?string $store = null): array {
        $current = $store ?: self::getCurrentStore();

        if ($current === self::STORE_JUICE) {
            $switchText = Setting::get(self::SETTING_SWITCH_TO_COCONUT, 'Looking for Coconut? 🥥');
            $juiceName  = Setting::get(self::SETTING_JUICE_NAME, 'N.A Fresh Fruit Juices & Shakes');
            return [
                'store'              => self::STORE_JUICE,
                'name'               => $juiceName,
                'store_name'         => $juiceName,
                'short_name'         => 'N.A Juice Bar',
                'tagline'            => Setting::get(self::SETTING_JUICE_TAGLINE, '100% Pure Cold-Pressed Juices • Fresh on Order • Jaora'),
                'badge_text'         => '100% Cold-Pressed & Chilled',
                'theme'              => 'juice',
                'primary_color'      => '#ea580c', // Vibrant orange
                'theme_color'        => '#ea580c',
                'primary_hover'      => '#c2410c',
                'accent_color'       => '#f97316',
                'bg_color'           => '#fffaf5',
                'switch_target'      => self::STORE_COCONUT,
                'switch_label'       => $switchText ?: 'Looking for Coconut? 🥥',
                'switch_button_text' => $switchText ?: 'Looking for Coconut? 🥥',
                'switch_icon'        => 'coconut',
                'switch_hint'        => 'Fresh Tender Coconut Water',
                'hero_title_1'       => 'Fresh',
                'hero_title_2'       => 'Cold-Pressed Juices',
                'hero_subtitle'      => '100% Pure Fruit. Squeezed Fresh On Order.',
                'hero_desc'          => 'Extracted fresh on order from sweet limes, Nagpur oranges, and royal pomegranates. Zero added water, zero chemicals. Pure citrus hydration and clean vitamins.',
                'hero_image'         => '/assets/images/juice/hero-juice.png',
                'hero_price'         => 50.00,
                'hero_price_unit'    => 'glass',
                'hero_badges'        => [
                    ['title' => '100% Pure Fruit', 'desc' => 'Zero Preservatives'],
                    ['title' => 'Cold Extracted', 'desc' => 'Nutrient Dense'],
                    ['title' => 'Fresh on Order', 'desc' => 'Never Pre-Bottled'],
                    ['title' => 'Quick Delivery', 'desc' => 'Jaora Doorstep']
                ]
            ];
        }

        // Default Coconut Store Meta
        $switchText  = Setting::get(self::SETTING_SWITCH_TO_JUICE, 'Looking for Juice? 🍹');
        $coconutName = Setting::get('store_name', 'N.A Fresh Fruits & Coconuts');
        return [
            'store'              => self::STORE_COCONUT,
            'name'               => $coconutName,
            'store_name'         => $coconutName,
            'short_name'         => 'N.A Coconuts',
            'tagline'            => Setting::get('store_tagline', 'Pure. Natural. Refreshing. • Jaora'),
            'badge_text'         => '100% Natural Coconut Water',
            'theme'              => 'coconut',
            'primary_color'      => '#235d3a', // Emerald green
            'theme_color'        => '#235d3a',
            'primary_hover'      => '#1b4a2e',
            'accent_color'       => '#2e7d32',
            'bg_color'           => '#f8faf8',
            'switch_target'      => self::STORE_JUICE,
            'switch_label'       => $switchText ?: 'Looking for Juice? 🍹',
            'switch_button_text' => $switchText ?: 'Looking for Juice? 🍹',
            'switch_icon'        => 'juice',
            'switch_hint'        => 'Cold-Pressed Juices & Shakes',
            'hero_title_1'       => 'Fresh',
            'hero_title_2'       => 'Coconut Water',
            'hero_subtitle'      => 'Pure. Natural. Refreshing.',
            'hero_desc'          => 'Direct from nature, our fresh coconut water keeps you hydrated, healthy and energized. No added sugar. No chemicals. Just pure goodness delivered in Jaora.',
            'hero_image'         => '/assets/images/hero-coconut.png',
            'hero_price'         => 40.00,
            'hero_price_unit'    => 'piece',
            'hero_badges'        => [
                ['title' => '100% Natural', 'desc' => 'From Tropical Palms'],
                ['title' => 'No Added Sugar', 'desc' => 'Zero Chemicals'],
                ['title' => 'Fresh & Hygienic', 'desc' => 'Cut on Delivery'],
                ['title' => 'Home Delivery', 'desc' => 'Fast in Jaora']
            ]
        ];
    }

    /**
     * Get store-filtered products
     */
    public static function getStoreProducts(?string $store = null, bool $activeOnly = true, ?int $categoryId = null, ?string $search = null): array {
        $currentStore = $store ?: self::getCurrentStore();
        return Product::getAll($activeOnly, $categoryId, $search, $currentStore);
    }

    /**
     * Get store-filtered categories
     */
    public static function getStoreCategories(?string $store = null, bool $activeOnly = true): array {
        $currentStore = $store ?: self::getCurrentStore();
        return Category::getAll($activeOnly, $currentStore);
    }
}
