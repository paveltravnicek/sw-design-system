<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
/**
 * View: settings screen.
 * Vars in scope: $s (settings), $schema, $presets.
 */
$notice = isset( $_GET['swds_notice'] ) ? sanitize_key( wp_unslash( $_GET['swds_notice'] ) ) : '';
$notice_map = array(
    'saved'        => array( 'ok',  'Nastavení uloženo a CSS přegenerováno.' ),
    'preset'       => array( 'ok',  'Preset aplikován. Doladit hodnoty můžete níže a uložit.' ),
    'reset'        => array( 'ok',  'Nastavení vráceno na výchozí hodnoty.' ),
    'imported'     => array( 'ok',  'Nastavení naimportováno.' ),
    'library'      => array( 'ok',  'Seznam sekcí byl aktualizován.' ),
    'usage'        => array( 'ok',  'Web byl projit znovu.' ),
    'library_local' => array( 'err', 'Nejnovější sekce se teď nepodařilo načíst. Zobrazují se vestavěné sekce — zkuste to prosím za chvíli znovu.' ),
    'import_error' => array( 'err', 'Import se nezdařil — vložený text není platný JSON.' ),
);
$export_json = wp_json_encode( $s, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );

// Build tab list from schema groups + fixed tabs.
$tabs = array();
foreach ( $schema as $gkey => $group ) {
    $tabs[ $gkey ] = array( 'title' => $group['title'], 'icon' => $group['icon'] );
}
$tabs['presets'] = array( 'title' => 'Presety',  'icon' => 'images-alt2' );
$tabs['help']    = array( 'title' => 'Nápověda', 'icon' => 'editor-help' );
$tabs['library'] = array( 'title' => 'Komponenty', 'icon' => 'block-default' );
$tabs['usage']   = array( 'title' => 'Použití', 'icon' => 'search' );
$tabs['convert'] = array( 'title' => 'Převod HTML', 'icon' => 'update' );
?>
<div class="wrap swds-app" id="swds-wrap">

    <div class="swds-hero">
        <div class="swds-hero__content">
            <span class="swds-hero-brand">Smart Websites</span>
            <h1>SW Design System</h1>
            <p>Konfigurace barev, atmosféry, tvarosloví, hloubky a pohybu na jednom místě — plugin z toho vygeneruje CSS soubor. Obsahuje presety, knihovnu komponent a nápovědu.</p>
        </div>
        <div class="swds-hero__meta">
            <div class="swds-version-card" aria-label="Verze pluginu">
                <strong><?php echo esc_html( SWDS_VERSION ); ?></strong>
                <span>Verze pluginu</span>
            </div>
        </div>
    </div>

    <div class="swds-body">

    <?php if ( $notice && isset( $notice_map[ $notice ] ) ) :
        list( $type, $msg ) = $notice_map[ $notice ]; ?>
        <div class="swds-notice swds-notice-<?php echo esc_attr( $type ); ?>">
            <span class="dashicons dashicons-<?php echo 'ok' === $type ? 'yes-alt' : 'warning'; ?>"></span>
            <span><?php echo esc_html( $msg ); ?></span>
        </div>
    <?php endif; ?>

    <ul class="swds-tabs">
        <?php $first = true; foreach ( $tabs as $tkey => $tab ) : ?>
            <li class="swds-tab<?php echo $first ? ' swds-tab-active' : ''; ?>" data-tab="<?php echo esc_attr( $tkey ); ?>">
                <span class="dashicons dashicons-<?php echo esc_attr( $tab['icon'] ); ?>"></span>
                <?php echo esc_html( $tab['title'] ); ?>
            </li>
        <?php $first = false; endforeach; ?>
    </ul>

    <form method="post" action="">
        <?php wp_nonce_field( 'swds_save', 'swds_nonce' ); ?>

        <?php /* ---------- Token group panels ---------- */
        $first = true;
        foreach ( $schema as $gkey => $group ) : ?>
            <div class="swds-panel<?php echo $first ? ' swds-panel-active' : ''; ?>" data-panel="<?php echo esc_attr( $gkey ); ?>">
                <p class="swds-intro"><?php echo esc_html( $group['intro'] ); ?></p>

                <div class="swds-fields">
                    <?php foreach ( $group['fields'] as $f ) :
                        $key = $f['key'];
                        $val = isset( $s[ $key ] ) ? $s[ $key ] : $f['default'];
                        $name = 'swds[' . $key . ']';
                        ?>
                        <div class="swds-field swds-field-<?php echo esc_attr( $f['type'] ); ?>">
                            <div class="swds-field-control">
                                <label for="swds-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $f['label'] ); ?></label>

                                <?php if ( 'color' === $f['type'] ) : ?>
                                    <input type="text" class="swds-color" id="swds-<?php echo esc_attr( $key ); ?>"
                                           name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( $val ); ?>">

                                <?php elseif ( 'range' === $f['type'] ) : ?>
                                    <span class="swds-range-wrap">
                                        <input type="range" id="swds-<?php echo esc_attr( $key ); ?>"
                                               name="<?php echo esc_attr( $name ); ?>"
                                               min="<?php echo esc_attr( $f['min'] ); ?>" max="<?php echo esc_attr( $f['max'] ); ?>"
                                               step="<?php echo esc_attr( $f['step'] ); ?>" value="<?php echo esc_attr( $val ); ?>"
                                               oninput="this.nextElementSibling.textContent=this.value+'<?php echo esc_js( $f['unit'] ); ?>'">
                                        <output class="swds-range-out"><?php echo esc_html( $val . $f['unit'] ); ?></output>
                                    </span>

                                <?php elseif ( 'select' === $f['type'] ) : ?>
                                    <select id="swds-<?php echo esc_attr( $key ); ?>" name="<?php echo esc_attr( $name ); ?>">
                                        <?php foreach ( $f['options'] as $ov => $ol ) : ?>
                                            <option value="<?php echo esc_attr( $ov ); ?>" <?php selected( $val, $ov ); ?>><?php echo esc_html( $ol ); ?></option>
                                        <?php endforeach; ?>
                                    </select>

                                <?php elseif ( 'toggle' === $f['type'] ) : ?>
                                    <label class="swds-switch">
                                        <input type="checkbox" id="swds-<?php echo esc_attr( $key ); ?>"
                                               name="<?php echo esc_attr( $name ); ?>" value="1" <?php checked( ! empty( $val ) ); ?>>
                                        <span class="swds-switch-track"></span>
                                    </label>
                                <?php endif; ?>
                            </div>
                            <p class="swds-help"><?php echo esc_html( $f['help'] ); ?></p>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php $first = false; endforeach; ?>

        <?php /* ---------- Presets panel ---------- */ ?>
        <div class="swds-panel" data-panel="presets">
            <p class="swds-intro">Presety jsou hotové sady nastavení. Aplikací jedním kliknutím dostanete soudržně jiný vzhled — ideální, když stejný design systém používáte na více webech a chcete, aby se lišily. Po aplikaci můžete hodnoty dál ladit v ostatních záložkách.</p>
            <div class="swds-presets">
                <?php
                $groups = SWDS_Presets::groups();
                foreach ( $groups as $gkey => $glabel ) :
                    // group title
                    ?>
                    <h3 class="swds-preset-group-title"><?php echo esc_html( $glabel ); ?></h3>
                    <?php
                    foreach ( $presets as $pkey => $preset ) :
                        if ( ( $preset['group'] ?? '' ) !== $gkey ) { continue; }
                        $t = array_merge( SWDS_Tokens::defaults(), $preset['tokens'] );
                        $active = ( isset( $s['_preset'] ) && $s['_preset'] === $pkey ); ?>
                        <div class="swds-preset-card<?php echo $active ? ' is-active' : ''; ?>">
                            <div class="swds-preset-swatch" style="background:linear-gradient(135deg,<?php echo esc_attr( $t['bg_1'] ); ?>,<?php echo esc_attr( $t['bg_2'] ); ?> 58%,<?php echo esc_attr( $t['bg_3'] ); ?>)">
                                <span class="swds-preset-dot" style="background:<?php echo esc_attr( $t['brand'] ); ?>"></span>
                                <span class="swds-preset-dot" style="background:<?php echo esc_attr( $t['brand_2'] ); ?>"></span>
                            </div>
                            <h3><?php echo esc_html( $preset['label'] ); ?><?php echo $active ? ' <em>(aktivní)</em>' : ''; ?></h3>
                            <p><?php echo esc_html( $preset['description'] ); ?></p>
                            <button type="submit" name="swds_action" value="apply_preset" class="button"
                                    onclick="this.form.querySelector('#swds-preset-input').value='<?php echo esc_attr( $pkey ); ?>'">
                                Použít preset
                            </button>
                        </div>
                    <?php endforeach;
                endforeach; ?>
            </div>
            <input type="hidden" name="preset" id="swds-preset-input" value="">

            <hr class="swds-hr">
            <h3 class="swds-subhead">Přenos nastavení mezi weby</h3>
            <p class="swds-help">Zkopírujte tento JSON a vložte ho na jiném webu pro identické nastavení. Nebo si ho uschovejte jako zálohu.</p>
            <textarea class="swds-export" readonly rows="6" onclick="this.select()"><?php echo esc_textarea( $export_json ); ?></textarea>
            <p class="swds-help" style="margin-top:14px;">Vložte JSON sem a klikněte na Importovat:</p>
            <textarea class="swds-import" name="import_json" rows="4" placeholder='{ "brand": "#0096E2", ... }'></textarea>
            <p><button type="submit" name="swds_action" value="import" class="button">Importovat nastavení</button></p>
        </div>

        <?php /* ---------- Help panel ---------- */ ?>
        <div class="swds-panel" data-panel="help">
            <p class="swds-intro">Jak design systém používat v LiveCanvas / HTML.</p>

            <div class="swds-help-block">
                <h3>1. Plátno (pozadí)</h3>
                <p>Aby společné modré pozadí prosvítalo skrz sekce, musí být obal s třídou <code>sw-canvas</code> — buď na <code>&lt;body&gt;</code> (přes téma / Customizer), nebo obalte sekce v Edit Main HTML do <code>&lt;div class="sw-canvas"&gt;…&lt;/div&gt;</code>. Bez něj jsou sekce průhledné. Výška se řídí obsahem — můžete tak obalit i jednu krátkou sekci, aniž by se roztáhla. Pokud chcete, aby pozadí pokrylo celý monitor i u krátké stránky, přidejte <code>sw-canvas--full</code> (tj. <code>class="sw-canvas sw-canvas--full"</code>) na obal celé stránky.</p>
            </div>
            <div class="swds-help-block">
                <h3>2. Světlý / tmavý režim sekce</h3>
                <p>Každá sekce může být světlá nebo tmavá nezávisle na pozadí stránky. Stačí přidat jednu třídu na sekci:</p>
                <p style="margin-top:.5rem;">
                  <code>sw-surface--auto</code> — sleduje web: ve světlém režimu světlá, v tmavém (dark mode) tmavá. <strong>Nejčastější volba.</strong><br>
                  <code>sw-surface--dark</code> — vždy tmavá (skleněné karty, světlý text). Typicky hero.<br>
                  <code>sw-surface--light</code> — vždy světlá, i když je web v tmavém režimu.
                </p>
                <p style="margin-top:.5rem;">Komponenty z knihovny už mají vhodnou třídu přednastavenou — hero a podobné jsou <code>--dark</code>, obsahové sekce <code>--auto</code>. Chování změníte prostou výměnou té jedné třídy.</p>
            </div>
            <div class="swds-help-block">
                <h3>3. Editovatelné texty</h3>
                <p>Nadpisy a texty mají atribut <code>editable="inline"</code> (prostý text + tučně/kurzíva). Hero nadpis s barevným přechodem má <code>editable="rich"</code>, protože obsahuje <code>&lt;span&gt;</code> — kdyby byl inline, LiveCanvas by span při editaci smazal a přechod by zmizel.</p>
            </div>
            <div class="swds-help-block">
                <h3>4. Obrázky</h3>
                <p>Obrázky jsou běžné <code>&lt;img&gt;</code> — v LiveCanvas je vyměníte kliknutím přes panel vlastností. Mají <code>loading="lazy"</code> kvůli výkonu.</p>
            </div>
            <div class="swds-help-block">
                <h3>5. Animace</h3>
                <p>Prvek s <code>data-sw-reveal</code> rozjede nájezd svých dětí (třída <code>sw-reveal-item</code>) při scrollu. Prvek s <code>data-sw-parallax</code> naklání mockup za myší. Vše respektuje systémové „omezit pohyb".</p>
            </div>
            <div class="swds-help-block">
                <h3>6. Třídy nadpisů</h3>
                <p><code>sw-h sw-h-display</code> (hero), <code>sw-h sw-h-large</code> (sekce), <code>sw-h sw-h-cta</code> (CTA). Velikost a styl řídí záložka Typografie — nezasahuje do globálního písma tématu.</p>
            </div>
        </div>

        <?php /* ---------- Library panel ---------- */
        $groups = SWDS_Library::grouped();
        $has_remote = false; $total = 0;
        foreach ( $groups as $g ) {
            foreach ( $g['items'] as $c ) { $total++; if ( 'remote' === $c['source'] ) { $has_remote = true; } }
        }
        ?>
        <div class="swds-panel" data-panel="library">
            <p class="swds-intro">Hotové sekce rozdělené do kategorií. U vybrané sekce klikněte na <strong>Kopírovat HTML</strong> a vložte ji v editoru stránky (LiveCanvas → přidat sekci → vložit kód). Vzhled — barvy, fonty i styl — se nastaví automaticky podle tohoto design systému. Klikem na název kategorie ji sbalíte nebo rozbalíte.</p>

            <p style="margin:-8px 0 18px;">
                <button type="submit" name="swds_action" value="refresh_library" class="button">↻ Načíst nejnovější sekce</button>
                <span class="swds-help" style="margin-left:10px;">Sekcí k dispozici: <?php echo (int) $total; ?></span>
            </p>

            <?php $i = 0; foreach ( $groups as $g ) : ?>
                <div class="swds-comp-cat" data-cat>
                    <button type="button" class="swds-comp-cat-head" data-cat-toggle>
                        <span class="swds-comp-cat-arrow dashicons dashicons-arrow-right-alt2"></span>
                        <span class="swds-comp-cat-label"><?php echo esc_html( $g['label'] ); ?></span>
                        <span class="swds-comp-cat-count"><?php echo count( $g['items'] ); ?></span>
                    </button>
                    <div class="swds-comp-cat-body">
                        <?php foreach ( $g['items'] as $c ) :
                            $id = 'swds-code-' . $i; $i++; ?>
                            <div class="swds-comp">
                                <div class="swds-comp-head">
                                    <h3><?php echo esc_html( $c['title'] ); ?></h3>
                                    <button type="button" class="button swds-copy" data-target="<?php echo esc_attr( $id ); ?>">Kopírovat HTML</button>
                                </div>
                                <textarea class="swds-code" id="<?php echo esc_attr( $id ); ?>" readonly rows="6"><?php echo esc_textarea( $c['code'] ); ?></textarea>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <?php /* ---------- Usage ----------
            Where each component actually sits on the site. Read-only: it
            runs one query and parses what it finds. Nothing here edits a
            page — converting stays manual, one section at a time, because
            a pasted copy may have been edited by hand and no rule can
            tell a deliberate change from drift. */
        $scan    = SWDS_Usage::scan();
        $flagged = 0;

        /* Grouped by page, not by component: fixing a site means working
           through it page by page — open one in LiveCanvas, sort out every
           section on it, move on — not chasing one component across eight
           pages and opening each of them again for the next component. */
        $pages = array();
        foreach ( $scan['rows'] as $r ) {
            $pid = $r['post_id'];
            if ( ! isset( $pages[ $pid ] ) ) {
                $pages[ $pid ] = array(
                    'title'  => $r['post_title'],
                    'type'   => $r['post_type'],
                    'status' => $r['status'],
                    'rows'   => array(),
                    'bad'    => 0,
                );
            }
            $pages[ $pid ]['rows'][] = $r;
            if ( $r['issues'] ) { $pages[ $pid ]['bad']++; $flagged++; }
        }

        // Sections in the order they appear on the page — that is the order
        // you meet them scrolling down it in the builder.
        foreach ( $pages as &$pg ) {
            usort( $pg['rows'], function ( $a, $b ) { return $a['index'] - $b['index']; } );
        }
        unset( $pg );

        /* Pages needing attention first, everything already current at the
           bottom. The front page is hoisted to the top only when it is one
           of the pages needing work — a clean home page sitting above the
           list would just push the actual work down. */
        $front = (int) get_option( 'page_on_front' );
        uasort( $pages, function ( $a, $b ) {
            if ( ( $a['bad'] > 0 ) !== ( $b['bad'] > 0 ) ) { return $a['bad'] > 0 ? -1 : 1; }
            return strcasecmp( $a['title'], $b['title'] );
        } );
        if ( $front && isset( $pages[ $front ] ) && $pages[ $front ]['bad'] ) {
            $pages = array( $front => $pages[ $front ] ) + $pages;
        }
        ?>
        <div class="swds-panel" data-panel="usage">
            <p class="swds-intro">Kde na webu které komponenty leží a jestli jsou v současné podobě. Tahle obrazovka <strong>jen čte</strong> — žádnou stránku nemění. U nálezu, který je něčím označený, si můžete jeho HTML poslat rovnou do převodníku, podívat se, co by se změnilo, a vložit ho zpátky sám.</p>

            <p style="margin:-8px 0 18px;">
                <button type="submit" name="swds_action" value="rescan_usage" class="button">↻ Projít web znovu</button>
                <span class="swds-help" style="margin-left:10px;">
                    Prohledáno stránek: <?php echo (int) $scan['scanned']; ?> ·
                    nalezeno sekcí: <?php echo count( $scan['rows'] ); ?> ·
                    k pozornosti: <strong><?php echo (int) $flagged; ?></strong> ·
                    naposledy <?php echo esc_html( date_i18n( 'j. n. H:i', $scan['time'] ) ); ?>
                </span>
            </p>

            <?php if ( $scan['capped'] ) : ?>
                <div class="swds-notice swds-notice-err">
                    <span class="dashicons dashicons-warning"></span>
                    <span>Výpis je omezený na <?php echo (int) SWDS_Usage::MAX_POSTS; ?> stránek, web je větší. Zobrazené nálezy platí, ale nejsou úplné.</span>
                </div>
            <?php endif; ?>

            <?php if ( ! $scan['rows'] ) : ?>
                <p class="swds-help">Na webu zatím není žádná sekce z tohohle design systému — nebo stránky používají jiné uložiště obsahu než <code>post_content</code>.</p>
            <?php else : ?>
                <p class="swds-usage-filter">
                    <label>
                        <input type="checkbox" id="swds-usage-only-bad">
                        Zobrazit jen stránky, které něco potřebují
                    </label>
                </p>

                <?php foreach ( $pages as $pid => $pg ) : ?>
                    <div class="swds-usage-group<?php echo $pg['bad'] ? '' : ' is-clean'; ?>">
                        <h3 class="swds-usage-head">
                            <a href="<?php echo esc_url( get_edit_post_link( $pid ) ); ?>"><?php echo esc_html( $pg['title'] ); ?></a>
                            <?php if ( $front === $pid ) : ?>
                                <span class="swds-usage-count">úvodní stránka</span>
                            <?php endif; ?>
                            <span class="swds-usage-count">
                                <?php echo count( $pg['rows'] ); ?> <?php
                                    echo 1 === count( $pg['rows'] ) ? 'sekce' : ( count( $pg['rows'] ) < 5 ? 'sekce' : 'sekcí' ); ?>
                                <?php echo 'publish' === $pg['status'] ? '' : '· ' . esc_html( $pg['status'] ); ?>
                            </span>
                            <?php if ( $pg['bad'] ) : ?>
                                <span class="swds-usage-bad"><?php echo (int) $pg['bad']; ?> k pozornosti</span>
                            <?php else : ?>
                                <span class="swds-usage-ok">vše aktuální</span>
                            <?php endif; ?>
                            <a class="swds-usage-view" href="<?php echo esc_url( get_permalink( $pid ) ); ?>" target="_blank" rel="noopener">zobrazit ↗</a>
                        </h3>
                        <table class="swds-usage-table">
                            <tbody>
                            <?php foreach ( $pg['rows'] as $n => $r ) : ?>
                                <tr class="<?php echo $r['issues'] ? 'is-flagged' : ''; ?>">
                                    <td class="swds-usage-pos"><?php echo (int) ( $n + 1 ); ?>.</td>
                                    <td class="swds-usage-page">
                                        <strong><?php echo esc_html( $r['title'] ); ?></strong>
                                        <?php if ( ! $r['exact'] ) : ?>
                                            <span class="swds-usage-meta">rozpoznáno z kódu, bez razítka</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="swds-usage-ver">
                                        <?php echo $r['exact'] ? '@' . (int) $r['version'] : '—'; ?>
                                    </td>
                                    <td class="swds-usage-issues">
                                        <?php if ( ! $r['issues'] ) : ?>
                                            <span class="swds-usage-ok">aktuální</span>
                                        <?php else : foreach ( $r['issues'] as $i ) : ?>
                                            <span class="swds-usage-flag"><?php echo esc_html( $i ); ?></span>
                                        <?php endforeach; endif; ?>
                                    </td>
                                    <td class="swds-usage-act">
                                        <button type="button" class="button button-small swds-usage-load"
                                                data-post="<?php echo (int) $pid; ?>"
                                                data-index="<?php echo (int) $r['index']; ?>">Do převodníku</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

            <?php if ( ! empty( $scan['unused'] ) ) : ?>
                <div class="swds-usage-group">
                    <h3 class="swds-usage-head">
                        Styly, které nic na webu nepoužívá
                        <span class="swds-usage-count"><?php echo count( $scan['unused'] ); ?></span>
                    </h3>
                    <p class="swds-help" style="max-width:860px;">
                        Třídy, které jsou v šabloně stylů, ale nepoužívá je žádná komponenta ani žádná z prohledaných stránek.
                        <strong>Je to podklad k projití očima, ne seznam ke smazání.</strong> Třída může patřit stránce,
                        která se do výpisu nevešla, rozepsanému konceptu, nebo jinému webu se stejným pluginem.
                    </p>
                    <p class="swds-usage-dead">
                        <?php foreach ( $scan['unused'] as $c ) : ?>
                            <code>.<?php echo esc_html( $c ); ?></code>
                        <?php endforeach; ?>
                    </p>
                </div>
            <?php endif; ?>
        </div>

        <?php /* ---------- Converter ----------
            Takes a section that was pasted into a page before the
            Bootstrap migration and brings it up to the current shape.
            Runs entirely in the browser: no round trip, no saving,
            nothing touched until you copy the result out yourself.

            The rules it applies are not written down here — they are
            read out of the bundled components by SWDS_Library::lc_hints(),
            so the converter keeps matching the library as it changes
            instead of drifting away from it. */
        $hints = SWDS_Library::lc_hints();
        // The component catalogue lets the converter stamp what it
        // recognises. Families shared by several components (the four
        // heroes) stay unstamped on purpose — see the note in admin.js.
        $hints['components'] = SWDS_Usage::catalogue();
        ?>
        <div class="swds-panel" data-panel="convert">
            <p class="swds-intro">Máte na stránce sekci vloženou ze starší verze knihovny? Vložte její HTML sem. Nástroj ho převede na současnou podobu — Bootstrap <code>row</code>/<code>col</code> místo starých mřížek, doplní <code>lc-block</code> a <code>editable</code> pro LiveCanvas a vyčistí zbytky, které do kódu zapsal starší runtime. <strong>Nic se nikam neukládá</strong>, výsledek si odnesete tlačítkem Kopírovat.</p>

            <div class="swds-conv">
                <div class="swds-conv-col">
                    <label class="swds-conv-label" for="swds-conv-in">Staré HTML</label>
                    <textarea id="swds-conv-in" class="swds-code swds-conv-area" rows="16" spellcheck="false" placeholder="Zkopírujte sem HTML sekce z LiveCanvasu…"></textarea>
                </div>
                <div class="swds-conv-col">
                    <label class="swds-conv-label" for="swds-conv-out">Převedené HTML</label>
                    <textarea id="swds-conv-out" class="swds-code swds-conv-area" rows="16" spellcheck="false" readonly></textarea>
                </div>
            </div>

            <p style="margin:14px 0 18px;">
                <button type="button" class="button button-primary" id="swds-conv-run">Převést</button>
                <button type="button" class="button swds-copy" data-target="swds-conv-out">Kopírovat HTML</button>
                <button type="button" class="button" id="swds-conv-clear">Vyčistit</button>
            </p>

            <div id="swds-conv-report" class="swds-conv-report" hidden></div>

            <script type="application/json" id="swds-lc-hints"><?php
                echo wp_json_encode( $hints );
            ?></script>
        </div>

        <?php /* ---------- Sticky action bar (hidden on help/library/presets via JS) ---------- */ ?>
        <div class="swds-actions">
            <button type="submit" name="swds_action" value="save" class="button button-primary button-hero">Uložit změny</button>
            <button type="submit" name="swds_action" value="reset" class="button"
                    onclick="return confirm('Vrátit všechna nastavení na výchozí hodnoty?');">Obnovit výchozí</button>
        </div>

    </form>
    </div><!-- /.swds-body -->
</div>
