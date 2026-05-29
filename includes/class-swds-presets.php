<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * SWDS_Presets
 *
 * 20 named token sets grouped by mood, for one-click site differentiation.
 * Each preset only specifies keys that differ from defaults.
 */
class SWDS_Presets {

    /** Group labels (for the admin UI). */
    public static function groups() {
        return array(
            'signature' => 'Základní',
            'luxury'    => 'Luxus a prémiovost',
            'playful'   => 'Hravé a netradiční',
            'nature'    => 'Přírodní tóny',
            'calm'      => 'Uklidňující tóny',
        );
    }

    public static function all() {
        return array(

            /* ---------- ZÁKLADNÍ ---------- */
            'ocean' => array(
                'group' => 'signature', 'label' => 'Ocean',
                'description' => 'Výchozí modrá. Svěží, technologická, důvěryhodná.',
                'tokens' => array(
                    'brand' => '#0096E2', 'brand_2' => '#5cc8ff',
                    'bg_1' => '#058BD8', 'bg_2' => '#043F81', 'bg_3' => '#020817',
                    'bg_angle' => 135, 'spot_intensity' => 'normal', 'grid_overlay' => 'on',
                    'radius' => 'soft', 'card_style' => 'glass', 'shadow' => 'soft', 'glow' => 'normal',
                ),
            ),
            'ink' => array(
                'group' => 'signature', 'label' => 'Ink',
                'description' => 'Tmavá grafitová s modrým akcentem. Seriózní, střízlivá.',
                'tokens' => array(
                    'brand' => '#3B82F6', 'brand_2' => '#93C5FD',
                    'bg_1' => '#1E293B', 'bg_2' => '#0F172A', 'bg_3' => '#020617',
                    'bg_angle' => 160, 'spot_intensity' => 'subtle', 'grid_overlay' => 'faint',
                    'radius' => 'subtle', 'card_style' => 'outline', 'shadow' => 'flat', 'glow' => 'subtle',
                ),
            ),
            'slate' => array(
                'group' => 'signature', 'label' => 'Slate',
                'description' => 'Neutrální břidlicová. Univerzální, čistá, nenápadná.',
                'tokens' => array(
                    'brand' => '#64748B', 'brand_2' => '#CBD5E1',
                    'bg_1' => '#334155', 'bg_2' => '#1E293B', 'bg_3' => '#0B1120',
                    'bg_angle' => 135, 'spot_intensity' => 'subtle', 'grid_overlay' => 'faint',
                    'radius' => 'soft', 'card_style' => 'glass', 'shadow' => 'soft', 'glow' => 'off',
                ),
            ),
            'crimson' => array(
                'group' => 'signature', 'label' => 'Crimson',
                'description' => 'Sytá červená. Sebevědomá, dynamická, výrazná.',
                'tokens' => array(
                    'brand' => '#EF4444', 'brand_2' => '#FCA5A5',
                    'bg_1' => '#7F1D1D', 'bg_2' => '#450A0A', 'bg_3' => '#1C0606',
                    'bg_angle' => 130, 'spot_intensity' => 'normal', 'grid_overlay' => 'on',
                    'radius' => 'soft', 'card_style' => 'glass', 'shadow' => 'dramatic', 'glow' => 'normal',
                ),
            ),

            /* ---------- LUXUS A PRÉMIOVOST ---------- */
            'obsidian-gold' => array(
                'group' => 'luxury', 'label' => 'Obsidian Gold',
                'description' => 'Černá se zlatým akcentem. Maximální luxus a exkluzivita.',
                'tokens' => array(
                    'brand' => '#D4AF37', 'brand_2' => '#F4E4A6',
                    'bg_1' => '#1A1A1A', 'bg_2' => '#0D0D0D', 'bg_3' => '#000000',
                    'bg_angle' => 135, 'spot_intensity' => 'subtle', 'grid_overlay' => 'off',
                    'radius' => 'subtle', 'card_style' => 'outline', 'shadow' => 'dramatic', 'glow' => 'subtle',
                    'heading_tracking' => 'wide',
                ),
            ),
            'royal-purple' => array(
                'group' => 'luxury', 'label' => 'Royal Purple',
                'description' => 'Hluboká fialová se zlatem. Královská, honosná, prémiová.',
                'tokens' => array(
                    'brand' => '#C9A227', 'brand_2' => '#E8D48B',
                    'bg_1' => '#3B0764', 'bg_2' => '#1E1B4B', 'bg_3' => '#0A0118',
                    'bg_angle' => 145, 'spot_intensity' => 'normal', 'grid_overlay' => 'off',
                    'radius' => 'soft', 'card_style' => 'glass', 'shadow' => 'dramatic', 'glow' => 'subtle',
                    'heading_tracking' => 'normal',
                ),
            ),
            'champagne' => array(
                'group' => 'luxury', 'label' => 'Champagne',
                'description' => 'Teplá béžová a šampaňská. Elegantní, jemná, butikový luxus.',
                'tokens' => array(
                    'brand' => '#B8860B', 'brand_2' => '#E6C200',
                    'bg_1' => '#4A3F2A', 'bg_2' => '#2E2719', 'bg_3' => '#14110A',
                    'bg_angle' => 120, 'spot_intensity' => 'subtle', 'grid_overlay' => 'off',
                    'radius' => 'round', 'card_style' => 'glass', 'shadow' => 'soft', 'glow' => 'off',
                    'heading_tracking' => 'wide',
                ),
            ),
            'emerald-lux' => array(
                'group' => 'luxury', 'label' => 'Emerald Lux',
                'description' => 'Smaragdová zeleň se zlatem. Bohatá, sofistikovaná.',
                'tokens' => array(
                    'brand' => '#CBA135', 'brand_2' => '#F0DA8C',
                    'bg_1' => '#064E3B', 'bg_2' => '#022C22', 'bg_3' => '#01140F',
                    'bg_angle' => 150, 'spot_intensity' => 'subtle', 'grid_overlay' => 'off',
                    'radius' => 'subtle', 'card_style' => 'outline', 'shadow' => 'dramatic', 'glow' => 'subtle',
                ),
            ),
            'platinum' => array(
                'group' => 'luxury', 'label' => 'Platinum',
                'description' => 'Stříbřitě šedá, vysoký kontrast. Moderní prémiová střídmost.',
                'tokens' => array(
                    'brand' => '#A8B2C0', 'brand_2' => '#E2E8F0',
                    'bg_1' => '#2B2F36', 'bg_2' => '#16181C', 'bg_3' => '#050607',
                    'bg_angle' => 135, 'spot_intensity' => 'off', 'grid_overlay' => 'faint',
                    'radius' => 'sharp', 'card_style' => 'outline', 'shadow' => 'flat', 'glow' => 'off',
                    'heading_tracking' => 'wide',
                ),
            ),

            /* ---------- HRAVÉ A NETRADIČNÍ ---------- */
            'bubblegum' => array(
                'group' => 'playful', 'label' => 'Bubblegum',
                'description' => 'Růžová a tyrkysová. Veselá, mladá, energická.',
                'tokens' => array(
                    'brand' => '#FF4FA3', 'brand_2' => '#4FD1FF',
                    'bg_1' => '#7C2D6B', 'bg_2' => '#3B1E5E', 'bg_3' => '#160B2E',
                    'bg_angle' => 110, 'spot_intensity' => 'strong', 'grid_overlay' => 'on',
                    'radius' => 'round', 'card_style' => 'glass', 'shadow' => 'dramatic', 'glow' => 'neon',
                ),
            ),
            'citrus' => array(
                'group' => 'playful', 'label' => 'Citrus',
                'description' => 'Limetka a oranžová. Svěží, optimistická, šťavnatá.',
                'tokens' => array(
                    'brand' => '#FF8A00', 'brand_2' => '#C6F432',
                    'bg_1' => '#166534', 'bg_2' => '#3F6212', 'bg_3' => '#14210A',
                    'bg_angle' => 100, 'spot_intensity' => 'strong', 'grid_overlay' => 'on',
                    'radius' => 'round', 'card_style' => 'glass', 'shadow' => 'soft', 'glow' => 'normal',
                ),
            ),
            'electric' => array(
                'group' => 'playful', 'label' => 'Electric',
                'description' => 'Neonová modrofialová. Futuristická, výrazná, kyberpunk.',
                'tokens' => array(
                    'brand' => '#7C3AED', 'brand_2' => '#22D3EE',
                    'bg_1' => '#1E1B4B', 'bg_2' => '#0F0A2E', 'bg_3' => '#05030F',
                    'bg_angle' => 125, 'spot_intensity' => 'strong', 'grid_overlay' => 'on',
                    'radius' => 'soft', 'card_style' => 'glass', 'shadow' => 'dramatic', 'glow' => 'neon',
                ),
            ),
            'mango-tango' => array(
                'group' => 'playful', 'label' => 'Mango Tango',
                'description' => 'Mango a korálová. Vřelá, přátelská, hravá.',
                'tokens' => array(
                    'brand' => '#FF6B4A', 'brand_2' => '#FFB07C',
                    'bg_1' => '#C2410C', 'bg_2' => '#7A1F4B', 'bg_3' => '#0F0613',
                    'bg_angle' => 120, 'spot_intensity' => 'strong', 'grid_overlay' => 'faint',
                    'radius' => 'round', 'card_style' => 'glass', 'shadow' => 'dramatic', 'glow' => 'normal',
                ),
            ),
            'candy-pop' => array(
                'group' => 'playful', 'label' => 'Candy Pop',
                'description' => 'Fialová a žlutá. Odvážná, nečekaná, zábavná.',
                'tokens' => array(
                    'brand' => '#FACC15', 'brand_2' => '#E879F9',
                    'bg_1' => '#6B21A8', 'bg_2' => '#4C1D95', 'bg_3' => '#1E0A3C',
                    'bg_angle' => 115, 'spot_intensity' => 'strong', 'grid_overlay' => 'on',
                    'radius' => 'round', 'card_style' => 'glass', 'shadow' => 'soft', 'glow' => 'neon',
                ),
            ),

            /* ---------- PŘÍRODNÍ TÓNY ---------- */
            'forest' => array(
                'group' => 'nature', 'label' => 'Forest',
                'description' => 'Hluboká lesní zeleň. Klidná, přírodní, vyvážená.',
                'tokens' => array(
                    'brand' => '#10B981', 'brand_2' => '#6EE7B7',
                    'bg_1' => '#065F46', 'bg_2' => '#064E3B', 'bg_3' => '#021410',
                    'bg_angle' => 135, 'spot_intensity' => 'normal', 'grid_overlay' => 'off',
                    'radius' => 'soft', 'card_style' => 'solid', 'shadow' => 'soft', 'glow' => 'subtle',
                ),
            ),
            'terracotta' => array(
                'group' => 'nature', 'label' => 'Terracotta',
                'description' => 'Hliněná a okrová. Zemitá, teplá, řemeslná.',
                'tokens' => array(
                    'brand' => '#E07A5F', 'brand_2' => '#F2CC8F',
                    'bg_1' => '#6B3A2A', 'bg_2' => '#46241A', 'bg_3' => '#1C0E0A',
                    'bg_angle' => 125, 'spot_intensity' => 'subtle', 'grid_overlay' => 'off',
                    'radius' => 'soft', 'card_style' => 'solid', 'shadow' => 'soft', 'glow' => 'off',
                ),
            ),
            'moss' => array(
                'group' => 'nature', 'label' => 'Moss',
                'description' => 'Mechová a olivová. Tlumená, organická, usazená.',
                'tokens' => array(
                    'brand' => '#84A98C', 'brand_2' => '#CAD2C5',
                    'bg_1' => '#354F3B', 'bg_2' => '#223226', 'bg_3' => '#0E160F',
                    'bg_angle' => 140, 'spot_intensity' => 'subtle', 'grid_overlay' => 'off',
                    'radius' => 'soft', 'card_style' => 'glass', 'shadow' => 'soft', 'glow' => 'off',
                ),
            ),
            'desert' => array(
                'group' => 'nature', 'label' => 'Desert',
                'description' => 'Písková a měděná. Teplá, pouštní, klidně sebevědomá.',
                'tokens' => array(
                    'brand' => '#C77D4A', 'brand_2' => '#E6B98A',
                    'bg_1' => '#5C4326', 'bg_2' => '#3A2A18', 'bg_3' => '#16100A',
                    'bg_angle' => 120, 'spot_intensity' => 'normal', 'grid_overlay' => 'faint',
                    'radius' => 'round', 'card_style' => 'glass', 'shadow' => 'soft', 'glow' => 'subtle',
                ),
            ),

            /* ---------- UKLIDŇUJÍCÍ TÓNY ---------- */
            'lavender' => array(
                'group' => 'calm', 'label' => 'Lavender',
                'description' => 'Levandulová a šeříková. Jemná, snová, konejšivá.',
                'tokens' => array(
                    'brand' => '#A78BFA', 'brand_2' => '#DDD6FE',
                    'bg_1' => '#4C3F6B', 'bg_2' => '#2E2647', 'bg_3' => '#140F24',
                    'bg_angle' => 140, 'spot_intensity' => 'subtle', 'grid_overlay' => 'faint',
                    'radius' => 'round', 'card_style' => 'glass', 'shadow' => 'soft', 'glow' => 'off',
                ),
            ),
            'seafoam' => array(
                'group' => 'calm', 'label' => 'Seafoam',
                'description' => 'Mořská zeleň a mátová. Svěží, lehká, uvolňující.',
                'tokens' => array(
                    'brand' => '#5EEAD4', 'brand_2' => '#A7F3D0',
                    'bg_1' => '#0F5C5B', 'bg_2' => '#0A3B3A', 'bg_3' => '#041817',
                    'bg_angle' => 150, 'spot_intensity' => 'subtle', 'grid_overlay' => 'off',
                    'radius' => 'round', 'card_style' => 'glass', 'shadow' => 'soft', 'glow' => 'subtle',
                ),
            ),
            'twilight' => array(
                'group' => 'calm', 'label' => 'Twilight',
                'description' => 'Soumračná modrofialová. Tichá, hloubavá, klidná.',
                'tokens' => array(
                    'brand' => '#818CF8', 'brand_2' => '#C7D2FE',
                    'bg_1' => '#312E5E', 'bg_2' => '#1E1B3A', 'bg_3' => '#0C0A1C',
                    'bg_angle' => 160, 'spot_intensity' => 'subtle', 'grid_overlay' => 'faint',
                    'radius' => 'soft', 'card_style' => 'glass', 'shadow' => 'soft', 'glow' => 'subtle',
                ),
            ),
            'fog' => array(
                'group' => 'calm', 'label' => 'Fog',
                'description' => 'Mlhavá modrošedá. Minimalistická, vzdušná, tichá.',
                'tokens' => array(
                    'brand' => '#94A3B8', 'brand_2' => '#E2E8F0',
                    'bg_1' => '#3A4654', 'bg_2' => '#252D38', 'bg_3' => '#11161D',
                    'bg_angle' => 135, 'spot_intensity' => 'off', 'grid_overlay' => 'faint',
                    'radius' => 'soft', 'card_style' => 'glass', 'shadow' => 'flat', 'glow' => 'off',
                ),
            ),
        );
    }

    public static function get( $key ) {
        $all = self::all();
        return isset( $all[ $key ] ) ? $all[ $key ] : null;
    }

    /** Defaults with a preset's tokens applied on top. */
    public static function apply( $key ) {
        $preset = self::get( $key );
        if ( ! $preset ) {
            return SWDS_Tokens::defaults();
        }
        $merged            = array_merge( SWDS_Tokens::defaults(), $preset['tokens'] );
        $merged['_preset'] = $key;
        return $merged;
    }
}
