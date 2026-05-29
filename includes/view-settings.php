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
    'library'      => array( 'ok',  'Knihovna komponent načtena z GitHubu.' ),
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
                <p>Aby společné modré pozadí prosvítalo skrz sekce, musí být obal s třídou <code>sw-canvas</code> — buď na <code>&lt;body&gt;</code> (přes téma / Customizer), nebo obalte sekce v Edit Main HTML do <code>&lt;div class="sw-canvas"&gt;…&lt;/div&gt;</code>. Bez něj jsou sekce průhledné.</p>
            </div>
            <div class="swds-help-block">
                <h3>2. Editovatelné texty</h3>
                <p>Nadpisy a texty mají atribut <code>editable="inline"</code> (prostý text + tučně/kurzíva). Hero nadpis s barevným přechodem má <code>editable="rich"</code>, protože obsahuje <code>&lt;span&gt;</code> — kdyby byl inline, LiveCanvas by span při editaci smazal a přechod by zmizel.</p>
            </div>
            <div class="swds-help-block">
                <h3>3. Obrázky</h3>
                <p>Obrázky jsou běžné <code>&lt;img&gt;</code> — v LiveCanvas je vyměníte kliknutím přes panel vlastností. Mají <code>loading="lazy"</code> kvůli výkonu.</p>
            </div>
            <div class="swds-help-block">
                <h3>4. Animace</h3>
                <p>Prvek s <code>data-sw-reveal</code> rozjede nájezd svých dětí (třída <code>sw-reveal-item</code>) při scrollu. Prvek s <code>data-sw-parallax</code> naklání mockup za myší. Vše respektuje systémové „omezit pohyb".</p>
            </div>
            <div class="swds-help-block">
                <h3>5. Třídy nadpisů</h3>
                <p><code>sw-h sw-h-display</code> (hero), <code>sw-h sw-h-large</code> (sekce), <code>sw-h sw-h-cta</code> (CTA). Velikost a styl řídí záložka Typografie — nezasahuje do globálního písma tématu.</p>
            </div>
        </div>

        <?php /* ---------- Library panel ---------- */
        $library = SWDS_Library::components();
        $has_remote = false;
        foreach ( $library as $c ) { if ( 'remote' === $c['source'] ) { $has_remote = true; break; } }
        ?>
        <div class="swds-panel" data-panel="library">
            <p class="swds-intro">Hotové komponenty. Zkopírujte HTML a vložte do LiveCanvas (Add Section → prázdná → Edit Code), nebo do Edit Main HTML. Styl se načte automaticky z design systému. <strong>Nové komponenty se přidávají na GitHubu</strong> (soubor + záznam v <code>components.json</code>) — bez nutnosti aktualizovat plugin. Tlačítkem níže načtete aktuální seznam.</p>

            <p style="margin:-8px 0 18px;">
                <button type="submit" name="swds_action" value="refresh_library" class="button">↻ Načíst komponenty z GitHubu</button>
                <span class="swds-help" style="margin-left:10px;">Zdroj: <?php echo $has_remote ? 'GitHub (aktuální)' : 'lokální (vestavěné v pluginu)'; ?></span>
            </p>

            <?php $i = 0; foreach ( $library as $c ) :
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

        <?php /* ---------- Sticky action bar (hidden on help/library/presets via JS) ---------- */ ?>
        <div class="swds-actions">
            <button type="submit" name="swds_action" value="save" class="button button-primary button-hero">Uložit změny</button>
            <button type="submit" name="swds_action" value="reset" class="button"
                    onclick="return confirm('Vrátit všechna nastavení na výchozí hodnoty?');">Obnovit výchozí</button>
        </div>

    </form>
    </div><!-- /.swds-body -->
</div>
