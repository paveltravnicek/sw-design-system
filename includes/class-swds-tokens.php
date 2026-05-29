<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * SWDS_Tokens
 *
 * The single source of truth for the design system's configurable tokens.
 * Each "group" maps to a settings tab; each "field" renders an admin control
 * AND knows how to turn its stored value into CSS custom properties.
 *
 * Philosophy: ~10 high-impact controls with thorough help text, NOT 60 raw
 * values. Several fields are "personality" switches (e.g. shadow depth) that
 * expand internally into multiple CSS variables.
 */
class SWDS_Tokens {

    /**
     * Full schema. Returns an array of groups -> fields.
     *
     * Field shape:
     *   key      string  unique option key
     *   label    string  short admin label
     *   type     string  color | range | select | toggle | gradient | color_pair
     *   default  mixed   default value
     *   help     string  thorough explanation shown under the control
     *   ...type-specific keys (min/max/step/unit, options[], etc.)
     */
    public static function schema() {
        return array(

            /* ===================== BARVY ===================== */
            'colors' => array(
                'title' => 'Barvy',
                'icon'  => 'admin-customizer',
                'intro' => 'Základní paleta celého design systému. Tyto barvy se propíšou do všech komponent — pozadí, karet, tlačítek i textu. Pokud měníte barvy pro nový web, začněte tady.',
                'fields' => array(
                    array(
                        'key' => 'brand', 'label' => 'Hlavní (brand) barva', 'type' => 'color', 'default' => '#0096E2',
                        'help' => 'Nejdůležitější barva webu. Používá se na tlačítka, odkazy, zvýraznění a jako základ záře a gradientů. Tohle je ta barva, kterou si lidé s webem spojí.',
                    ),
                    array(
                        'key' => 'brand_2', 'label' => 'Doplňková (světlá) barva', 'type' => 'color', 'default' => '#5cc8ff',
                        'help' => 'Světlejší varianta hlavní barvy. Tvoří přechody na tlačítkách a barevný gradient v nadpisech. Volte ji jako světlejší/jasnější odstín hlavní barvy, ať spolu ladí.',
                    ),
                    array(
                        'key' => 'bg_1', 'label' => 'Pozadí — horní', 'type' => 'color', 'default' => '#058BD8',
                        'help' => 'První (nejsvětlejší) stop pozadí. Velký gradient na pozadí stránky začíná tady nahoře vlevo a plynule přechází do tmavších tónů. Společné „plátno" pod všemi sekcemi.',
                    ),
                    array(
                        'key' => 'bg_2', 'label' => 'Pozadí — střední', 'type' => 'color', 'default' => '#043F81',
                        'help' => 'Prostřední stop pozadí. Ovlivňuje celkový tón webu — sytější odstín = výraznější, tmavší = klidnější dojem.',
                    ),
                    array(
                        'key' => 'bg_3', 'label' => 'Pozadí — spodní (nejtmavší)', 'type' => 'color', 'default' => '#020817',
                        'help' => 'Poslední (nejtmavší) stop pozadí dole vpravo. Dává hloubku. Většinou hodně tmavá barva, ať gradient pěkně „zapadne".',
                    ),
                    array(
                        'key' => 'text', 'label' => 'Barva textu', 'type' => 'color', 'default' => '#ffffff',
                        'help' => 'Hlavní barva textu na pozadí design systému. Na tmavém pozadí bílá, na světlém tmavá. Musí mít dostatečný kontrast vůči pozadí kvůli čitelnosti.',
                    ),
                    array(
                        'key' => 'text_muted', 'label' => 'Barva tlumeného textu', 'type' => 'color', 'default' => 'rgba(255,255,255,0.72)',
                        'help' => 'Pro vedlejší texty (podnadpisy, popisky karet). Měla by být méně výrazná než hlavní text, ale pořád dobře čitelná. Klidně poloprůhledná varianta barvy textu.',
                    ),
                ),
            ),

            /* ===================== POZADÍ / ATMOSFÉRA ===================== */
            'atmosphere' => array(
                'title' => 'Atmosféra',
                'icon'  => 'art',
                'intro' => 'Tohle je nejsilnější páka pro odlišení webů. Stejné komponenty s jinou atmosférou vypadají úplně jinak. Ovládá úhel gradientu, barevná „světla" a jemnou mřížku na pozadí.',
                'fields' => array(
                    array(
                        'key' => 'bg_angle', 'label' => 'Úhel gradientu pozadí', 'type' => 'range', 'default' => 135, 'min' => 0, 'max' => 360, 'step' => 5, 'unit' => '°',
                        'help' => 'Směr, kterým se pozadí přelévá ze světlé do tmavé. 135° = z levého horního do pravého dolního rohu (výchozí). 0° = zdola nahoru, 90° = zleva doprava. Změna úhlu znatelně mění dojem, aniž byste sahali na barvy.',
                    ),
                    array(
                        'key' => 'spot_intensity', 'label' => 'Intenzita barevných světel', 'type' => 'select', 'default' => 'normal',
                        'options' => array(
                            'off'    => 'Vypnuto (čistý gradient)',
                            'subtle' => 'Decentní',
                            'normal' => 'Normální',
                            'strong' => 'Výrazná',
                        ),
                        'help' => 'Na pozadí jsou dvě měkká barevná „světla" (záře) v rozích, která dodávají hloubku a živost. Decentní = sotva znát, Výrazná = nápadné barevné skvrny. Vypnuto nechá jen čistý gradient.',
                    ),
                    array(
                        'key' => 'grid_overlay', 'label' => 'Mřížka na pozadí', 'type' => 'select', 'default' => 'on',
                        'options' => array(
                            'off'   => 'Vypnuto',
                            'faint' => 'Sotva viditelná',
                            'on'    => 'Zapnuto',
                        ),
                        'help' => 'Jemná technická mřížka přes pozadí (jako milimetrový papír), zesvětlená do ztracena ke krajům. Dává webu „digitální" nádech. Pokud chcete čistší/jemnější vzhled, dejte sotva viditelnou nebo vypněte.',
                    ),
                ),
            ),

            /* ===================== TVAROSLOVÍ ===================== */
            'shape' => array(
                'title' => 'Tvarosloví',
                'icon'  => 'screenoptions',
                'intro' => 'Zaoblení rohů a tvar prvků. Jeden z nejrychlejších způsobů, jak změnit charakter webu: ostré hrany působí technicky a striktně, silně zaoblené hravě a měkce.',
                'fields' => array(
                    array(
                        'key' => 'radius', 'label' => 'Zaoblení rohů', 'type' => 'select', 'default' => 'soft',
                        'options' => array(
                            'sharp'  => 'Ostré (bez zaoblení)',
                            'subtle' => 'Mírné',
                            'soft'   => 'Měkké (výchozí)',
                            'round'  => 'Hodně kulaté',
                        ),
                        'help' => 'Zaoblení rohů u karet, boxů a obrázků. Ostré = moderní/striktní dojem, Měkké = přátelské (výchozí), Hodně kulaté = hravé. Mění všechny prvky najednou, takže web zůstane konzistentní.',
                    ),
                    array(
                        'key' => 'button_shape', 'label' => 'Tvar tlačítek', 'type' => 'select', 'default' => 'pill',
                        'options' => array(
                            'pill'    => 'Pilulka (plně zaoblené)',
                            'rounded' => 'Zaoblené rohy',
                            'square'  => 'Ostré rohy',
                        ),
                        'help' => 'Tlačítka mají často jiné zaoblení než karty. Pilulka = plně kulaté konce (výchozí, moderní), Zaoblené = jemné rohy, Ostré = hranatá tlačítka. Nezávislé na obecném zaoblení rohů výše.',
                    ),
                    array(
                        'key' => 'card_style', 'label' => 'Styl karet', 'type' => 'select', 'default' => 'glass',
                        'options' => array(
                            'glass'    => 'Sklo (průhledné + rozostření)',
                            'solid'    => 'Plné pozadí',
                            'outline'  => 'Jen obrys',
                        ),
                        'help' => 'Jak vypadají karty s obsahem. Sklo = poloprůhledné s rozostřením pozadí (moderní „glassmorphism", výchozí), Plné = neprůhledné pozadí (čitelnější, méně efektní), Jen obrys = průhledné s rámečkem (nejlehčí). Sklo nemusí fungovat ve velmi starých prohlížečích — tam se chová jako plné.',
                    ),
                ),
            ),

            /* ===================== HLOUBKA (STÍNY + ZÁŘE) ===================== */
            'depth' => array(
                'title' => 'Hloubka',
                'icon'  => 'layout',
                'intro' => 'Stíny a záře určují, jak moc prvky „vystupují" ze stránky. Ovlivňuje to, jestli web působí plochý a střízlivý, nebo plastický a efektní.',
                'fields' => array(
                    array(
                        'key' => 'shadow', 'label' => 'Síla stínů', 'type' => 'select', 'default' => 'soft',
                        'options' => array(
                            'flat'     => 'Ploché (bez stínů)',
                            'soft'     => 'Jemné (výchozí)',
                            'dramatic' => 'Výrazné',
                        ),
                        'help' => 'Jak hluboké stíny vrhají karty, tlačítka a mockupy. Ploché = moderní „flat" vzhled bez stínů, Jemné = decentní hloubka (výchozí), Výrazné = silné stíny, prvky výrazně „levitují". Mění všechny stíny najednou.',
                    ),
                    array(
                        'key' => 'glow', 'label' => 'Intenzita záře', 'type' => 'select', 'default' => 'normal',
                        'options' => array(
                            'off'    => 'Vypnuto',
                            'subtle' => 'Decentní',
                            'normal' => 'Normální',
                            'neon'   => 'Neon (výrazná)',
                        ),
                        'help' => 'Barevná záře v brand barvě kolem tlačítek a mockupů. Dodává „technologický" lesk. Vypnuto = bez záře (střízlivé), Neon = výrazné svítící okraje. Tahle drobnost dost mění osobnost webu.',
                    ),
                ),
            ),

            /* ===================== TYPOGRAFIE (jen škála DS) ===================== */
            'type' => array(
                'title' => 'Typografie',
                'icon'  => 'editor-textcolor',
                'intro' => 'POZOR: tohle NEnastavuje globální písmo webu (to řeší WordPress / téma / LiveCanvas). Ovládá pouze velikost a styl nadpisů design systému — tedy prvků se třídami sw-h-display, sw-h-large a sw-h-cta. Tím se vyhneme konfliktu s nastavením tématu.',
                'fields' => array(
                    array(
                        'key' => 'heading_scale', 'label' => 'Velikost nadpisů', 'type' => 'select', 'default' => 'normal',
                        'options' => array(
                            'compact' => 'Menší',
                            'normal'  => 'Normální (výchozí)',
                            'large'   => 'Větší',
                            'huge'    => 'Obří',
                        ),
                        'help' => 'Celková velikost velkých nadpisů design systému. Nadpisy jsou plynule responzivní (zmenší se na mobilu samy) — tohle nastavuje jejich rozsah. Obří = poutavé hero sekce, Menší = střízlivější. Netýká se běžných textů ani nadpisů mimo design systém.',
                    ),
                    array(
                        'key' => 'heading_tracking', 'label' => 'Mezery mezi znaky', 'type' => 'select', 'default' => 'tight',
                        'options' => array(
                            'tight'  => 'Těsné (výchozí)',
                            'normal' => 'Normální',
                            'wide'   => 'Rozvolněné',
                        ),
                        'help' => 'Mezery mezi písmeny ve velkých nadpisech. Těsné = písmena u sebe (moderní, výchozí), Rozvolněné = vzdušnější. Silný stylový signál — těsné mezery působí sebevědomě a současně.',
                    ),
                    array(
                        'key' => 'heading_gradient', 'label' => 'Barevný přechod v nadpisu', 'type' => 'toggle', 'default' => true,
                        'help' => 'Zapíná dvoubarevný přechod ve zvýrazněné části hero nadpisu (část obalená značkou <span>). Při vypnutí bude celý nadpis v barvě textu. Přechod jde z bílé do doplňkové barvy.',
                    ),
                ),
            ),

            /* ===================== POHYB / ANIMACE ===================== */
            'motion' => array(
                'title' => 'Pohyb',
                'icon'  => 'controls-play',
                'intro' => 'Animace při scrollování a interakci. Vždy respektují systémové nastavení „omezit pohyb" (uživatelé citliví na animace je nedostanou, ať tu nastavíte cokoli).',
                'fields' => array(
                    array(
                        'key' => 'motion_enabled', 'label' => 'Animace', 'type' => 'toggle', 'default' => true,
                        'help' => 'Hlavní vypínač všech animací design systému (nájezd prvků při scrollu, parallax mockupu za myší). Při vypnutí se vše zobrazí rovnou, bez pohybu — užitečné pro maximální střízlivost nebo výkon.',
                    ),
                    array(
                        'key' => 'reveal_style', 'label' => 'Styl nájezdu prvků', 'type' => 'select', 'default' => 'normal',
                        'options' => array(
                            'subtle' => 'Decentní (krátký, blízko)',
                            'normal' => 'Normální (výchozí)',
                            'bold'   => 'Výrazný (delší, zdálky)',
                        ),
                        'help' => 'Jak prvky „naběhnou", když na ně doscrollujete. Decentní = jemné a rychlé, Výrazný = prvky přiletí zdálky a pomaleji. Karty navíc naskakují postupně za sebou. Funguje jen když jsou animace zapnuté.',
                    ),
                    array(
                        'key' => 'hero_tilt', 'label' => '3D náklon hero mockupu', 'type' => 'toggle', 'default' => true,
                        'help' => 'Naklonění prohlížeče a telefonu v hero sekci do prostoru (3D efekt) plus jejich jemné natáčení za myší na desktopu. Při vypnutí budou mockupy rovně, čelně — čistší, méně efektní. Na mobilu jsou rovně vždy.',
                    ),
                ),
            ),

            /* ===================== LAYOUT ===================== */
            'layout' => array(
                'title' => 'Layout',
                'icon'  => 'align-center',
                'intro' => 'Svislé mezery mezi sekcemi. Maximální šířku obsahu záměrně neřešíme — tu má na starosti Bootstrap kontejner v LiveCanvas (.container / .container-fluid), kde si breakpointy nastavíte přímo.',
                'fields' => array(
                    array(
                        'key' => 'section_spacing', 'label' => 'Mezery mezi sekcemi', 'type' => 'select', 'default' => 'normal',
                        'options' => array(
                            'compact' => 'Sevřené',
                            'normal'  => 'Normální (výchozí)',
                            'airy'    => 'Vzdušné',
                        ),
                        'help' => 'Svislé odsazení nahoře a dole u každé sekce. Sevřené = sekce blíž u sebe (kompaktní web), Vzdušné = hodně prostoru kolem (prémiový, klidný dojem). Mění rytmus celé stránky.',
                    ),
                ),
            ),

            /* ===================== DARK MODE ===================== */
            'dark' => array(
                'title' => 'Dark mode',
                'icon'  => 'visibility',
                'intro' => 'Barvy pro tmavý režim (aktivní, když má stránka atribut data-bs-theme="dark"). Výchozí design je už tmavě modrý, takže tohle využijete hlavně pokud váš web přepíná mezi světlým a tmavým režimem. Nech vypnuté, pokud režimy nepřepínáš.',
                'fields' => array(
                    array(
                        'key' => 'dark_enabled', 'label' => 'Vlastní barvy pro dark mode', 'type' => 'toggle', 'default' => false,
                        'help' => 'Když je zapnuto, v tmavém režimu se použijí barvy nastavené níže místo výchozích. Když vypnuto, plugin do dark modu nezasahuje a nechá platit hlavní barvy. Zapínejte jen pokud web skutečně přepíná světlý/tmavý režim.',
                    ),
                    array(
                        'key' => 'dark_text', 'label' => 'Text (dark mode)', 'type' => 'color', 'default' => '#f7eade',
                        'help' => 'Barva hlavního textu v tmavém režimu. Aktivní jen při zapnutém vlastním dark modu výše.',
                    ),
                    array(
                        'key' => 'dark_bg', 'label' => 'Pozadí (dark mode)', 'type' => 'color', 'default' => '#524438',
                        'help' => 'Základní barva pozadí v tmavém režimu. Aktivní jen při zapnutém vlastním dark modu výše.',
                    ),
                ),
            ),
        );
    }

    /**
     * Flat list of defaults (key => default value) across all groups.
     */
    public static function defaults() {
        $out = array( '_preset' => 'ocean' );
        foreach ( self::schema() as $group ) {
            foreach ( $group['fields'] as $f ) {
                $out[ $f['key'] ] = $f['default'];
            }
        }
        return $out;
    }

    /**
     * Look up a single field definition by key (or null).
     */
    public static function field( $key ) {
        foreach ( self::schema() as $group ) {
            foreach ( $group['fields'] as $f ) {
                if ( $f['key'] === $key ) {
                    return $f;
                }
            }
        }
        return null;
    }

    /**
     * Merge stored settings over defaults so missing keys are safe.
     */
    public static function get() {
        $stored = get_option( SWDS_OPTION, array() );
        if ( ! is_array( $stored ) ) {
            $stored = array();
        }
        return array_merge( self::defaults(), $stored );
    }
}
