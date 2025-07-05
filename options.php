<?php

if (!defined('ABSPATH')) {
    exit;
}

add_action('admin_enqueue_scripts', function() {
    wp_enqueue_media();
});
add_action('admin_enqueue_scripts', function($hook) {
    if (strpos($hook, 'wsp-options') !== false) {
        wp_enqueue_media();
    }
});

add_action('admin_menu', function() {
    add_options_page('WP Screenshot Paste', 'WP Screenshot Paste', 'manage_options', 'wsp-options', 'wsp_options_page');
});
add_action('admin_init', function() {
    register_setting('wsp_options_group', 'wsp_openai_api_key');
    register_setting('wsp_options_group', 'wsp_screenshot_outer_margin', ['type'=>'integer','default'=>16]);
    register_setting('wsp_options_group', 'wsp_screenshot_bgtype',   ['type'=>'string','default'=>'color']);
    register_setting('wsp_options_group', 'wsp_screenshot_bgcolor1', ['type'=>'string','default'=>'#dde3ec']);
    register_setting('wsp_options_group', 'wsp_screenshot_bgcolor2', ['type'=>'string','default'=>'#aec6df']);
    register_setting('wsp_options_group', 'wsp_screenshot_bgangle',  ['type'=>'integer','default'=>135]);
    register_setting('wsp_options_group', 'wsp_screenshot_border_radius', ['type'=>'integer','default'=>12]);
    register_setting('wsp_options_group', 'wsp_screenshot_img_border_radius', ['type'=>'integer','default'=>8]);
    // Watermark fields - Remplacé ID par URL !
    register_setting('wsp_options_group', 'wsp_watermark_enable', ['type'=>'boolean', 'default'=>0]);
    register_setting('wsp_options_group', 'wsp_watermark_logo_url', ['type'=>'string', 'default'=>'']);
    register_setting('wsp_options_group', 'wsp_watermark_size', ['type'=>'integer', 'default'=>15]);
    register_setting('wsp_options_group', 'wsp_watermark_opacity', ['type'=>'float', 'default'=>0.5]);
    register_setting('wsp_options_group', 'wsp_watermark_position', ['type'=>'string', 'default'=>'bottom-right']);
});

function wsp_watermark_get_logo_url() {
    $url = get_option('wsp_watermark_logo_url', '');
    return $url;
}

function wsp_options_page() {
    $outer_margin = intval(get_option('wsp_screenshot_outer_margin', 16));
    $bgtype = get_option('wsp_screenshot_bgtype', 'color');
    $bgcolor1 = get_option('wsp_screenshot_bgcolor1', '#dde3ec');
    $bgcolor2 = get_option('wsp_screenshot_bgcolor2', '#aec6df');
    $bgangle = intval(get_option('wsp_screenshot_bgangle', 135));
    $border_radius = intval(get_option('wsp_screenshot_border_radius', 12));
    $img_border_radius = intval(get_option('wsp_screenshot_img_border_radius', 8));

    $watermark_enable   = intval(get_option('wsp_watermark_enable', 0));
    $watermark_logo_url  = wsp_watermark_get_logo_url();
    $watermark_size     = intval(get_option('wsp_watermark_size', 15));
    $watermark_opacity  = floatval(get_option('wsp_watermark_opacity', 0.5));
    $watermark_position = get_option('wsp_watermark_position', 'bottom-right');

    $preview_width = 340;
    $preview_height = 200;
    $dummy_img_w = 340;
    $dummy_img_h = 200;
    ?>
    <div class="wrap">
        <h1>WP Screenshot Paste</h1>
        <form method="post" action="options.php" id="wsp-options-screen">
            <?php settings_fields('wsp_options_group'); do_settings_sections('wsp_options_group'); ?>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row">Clé API OpenAI</th>
                    <td>
                        <input type="text" name="wsp_openai_api_key" value="<?php echo esc_attr(get_option('wsp_openai_api_key')); ?>" style="width:400px;" autocomplete="off" />
                        <p class="description">La clé ne sera utilisée que côté serveur (jamais dans le navigateur).</p>
                    </td>
                </tr>
                <tr>
                    <th colspan=2 style="padding-top:16px;">
                        <h2>Personnalisation du style des captures collées</h2>
                        <p class="description">Visualisez le cadre qui sera appliqué sur vos screenshots collés.</p>
                    </th>
                </tr>
                <tr>
                    <th scope="row">Type de fond du cadre</th>
                    <td>
                        <select name="wsp_screenshot_bgtype" id="wsp_screenshot_bgtype">
                            <option value="color" <?php selected($bgtype,'color');?>>Couleur unie</option>
                            <option value="gradient" <?php selected($bgtype,'gradient');?>>Dégradé</option>
                        </select>
                    </td>
                </tr>
                <tr id="tr_bgcolor1">
                    <th scope="row">Couleur principale</th>
                    <td>
                        <input type="color" name="wsp_screenshot_bgcolor1" id="wsp_screenshot_bgcolor1" value="<?php echo esc_attr($bgcolor1); ?>" />
                    </td>
                </tr>
                <tr id="tr_bgcolor2" style="display:<?php echo ($bgtype=='gradient')?'table-row':'none';?>">
                    <th scope="row">Couleur secondaire</th>
                    <td>
                        <input type="color" name="wsp_screenshot_bgcolor2" id="wsp_screenshot_bgcolor2" value="<?php echo esc_attr($bgcolor2); ?>" />
                    </td>
                </tr>
                <tr id="tr_bgangle" style="display:<?php echo ($bgtype=='gradient')?'table-row':'none';?>">
                    <th scope="row">Angle (dégradé)</th>
                    <td>
                        <input type="number" min="0" max="360" step="1" name="wsp_screenshot_bgangle" id="wsp_screenshot_bgangle" style="width:70px;" value="<?php echo esc_attr($bgangle); ?>" />°
                    </td>
                </tr>
                <tr>
                    <th scope="row">Marge extérieure (en px)</th>
                    <td>
                        <input type="number" name="wsp_screenshot_outer_margin" min="0" max="64" value="<?php echo esc_attr($outer_margin); ?>" id="wsp_screenshot_outer_margin" /> px
                    </td>
                </tr>
                <tr>
                    <th scope="row">Arrondi des coins (cadre/fond)</th>
                    <td>
                        <input type="range" name="wsp_screenshot_border_radius" min="0" max="48" value="<?php echo esc_attr($border_radius); ?>" step="1" id="wsp_screenshot_border_radius" oninput="document.getElementById('radius_val').innerText=this.value"/>&nbsp;
                        <span id="radius_val"><?php echo esc_html($border_radius); ?></span> px
                    </td>
                </tr>
                <tr>
                    <th scope="row">Arrondi des coins de l'image</th>
                    <td>
                        <input type="range" name="wsp_screenshot_img_border_radius" min="0" max="48" value="<?php echo esc_attr($img_border_radius); ?>" step="1" id="wsp_screenshot_img_border_radius" oninput="document.getElementById('img_radius_val').innerText=this.value"/>&nbsp;
                        <span id="img_radius_val"><?php echo esc_html($img_border_radius); ?></span> px
                        <br>
                        <span class="description">Ceci concerne l&apos;image contenue (indépendamment du cadre/fond).</span>
                    </td>
                </tr>
                <tr>
                    <th colspan=2 style="padding-top:30px;">
                        <h2>Filigrane/Watermark (optionnel)</h2>
                    </th>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="wsp_watermark_enable">Importer un logo en filigrane</label>
                    </th>
                    <td>
                        <input type="checkbox" name="wsp_watermark_enable" id="wsp_watermark_enable" value="1" <?php checked($watermark_enable,1); ?>/>
                        <span class="description">Ajoute un logo ou filigrane sur chaque screenshot collé.</span>
                    </td>
                </tr>
                <tbody id="watermark-options-inner" style="<?php echo $watermark_enable?'':'display:none';?>">
                    <tr>
                        <th scope="row">URL du logo PNG à utiliser</th>
                        <td>
                            <input type="text"
                                   name="wsp_watermark_logo_url"
                                   id="wsp_watermark_logo_url"
                                   value="<?php echo esc_attr($watermark_logo_url);?>"
                                   style="width:340px"
                                   placeholder="https://.../votre-logo.png"
                                   />
                            <div id="wsp-watermark-logo-preview">
                                <?php if ($watermark_logo_url): ?>
                                    <img src="<?php echo esc_url($watermark_logo_url); ?>" style="max-width:90px;max-height:50px;vertical-align:middle;"/>
                                <?php endif; ?>
                            </div>
                            <br>
                            <span class="description">Indiquez ici l’URL complète d’un logo PNG transparent. (Exemple : depuis la médiathèque, faites un clic-droit "Copier l’adresse de l’image").</span>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Taille du logo</th>
                        <td>
                            <input type="range" name="wsp_watermark_size" id="wsp_watermark_size" min="5" max="50" step="1" value="<?php echo esc_attr($watermark_size); ?>" oninput="document.getElementById('wsp_watermark_size_val').innerText=this.value"/>
                            <span id="wsp_watermark_size_val"><?php echo esc_html($watermark_size); ?></span> %
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Opacité</th>
                        <td>
                            <input type="range" name="wsp_watermark_opacity" id="wsp_watermark_opacity" min="0.05" max="1" step="0.01" value="<?php echo esc_attr($watermark_opacity); ?>" oninput="document.getElementById('wsp_watermark_opacity_val').innerText=this.value"/>
                            <span id="wsp_watermark_opacity_val"><?php echo esc_html($watermark_opacity); ?></span>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Emplacement du logo</th>
                        <td>
                            <select name="wsp_watermark_position" id="wsp_watermark_position">
                                <option value="top-left" <?php selected($watermark_position,'top-left');?>>Haut gauche</option>
                                <option value="top-right" <?php selected($watermark_position,'top-right');?>>Haut droite</option>
                                <option value="bottom-left" <?php selected($watermark_position,'bottom-left');?>>Bas gauche</option>
                                <option value="bottom-right" <?php selected($watermark_position,'bottom-right');?>>Bas droite</option>
                            </select>
                        </td>
                    </tr>
                </tbody>
            </table>
            <div style="margin:24px 0 8px 0;">
                <strong>Aperçu instantané :</strong>
            </div>
            <div id="wsp-screenshot-preview-outer"
                 style="width:<?php echo $preview_width;?>px;height:<?php echo $preview_height;?>px;transition:.3s all;overflow:hidden;position:relative;">
                <img id="wsp-screenshot-preview-img"
                     src="https://dummyimage.com/<?php echo $dummy_img_w; ?>x<?php echo $dummy_img_h;?>/aaa/fff.png&text=Prévisualisation"
                     alt="apercu screenshot"
                     draggable="false"
                     style="width:<?php echo $dummy_img_w; ?>px;height:<?php echo $dummy_img_h;?>px;display:block;position:absolute;left:0;top:0;" />
                <img id="wsp-watermark-preview-img"
                     src="<?php echo esc_url($watermark_logo_url); ?>"
                     alt="apercu watermark"
                     draggable="false"
                     style="position:absolute;max-width:60px;max-height:60px;pointer-events:none;opacity:<?php echo esc_attr($watermark_opacity);?>;display:<?php echo $watermark_enable && $watermark_logo_url ? 'block':'none';?>;">
            </div>
            <?php submit_button(); ?>
        </form>
    </div>
    <script>
    (function(){
        function setBgUI(){
            var t=document.getElementById('wsp_screenshot_bgtype').value;
            document.getElementById('tr_bgcolor2').style.display = t==='gradient' ? 'table-row' : 'none';
            document.getElementById('tr_bgangle').style.display  = t==='gradient' ? 'table-row' : 'none';
        }
        document.getElementById('wsp_screenshot_bgtype').addEventListener('change', setBgUI);

        const previewW = 340, previewH = 200, imgW = 340, imgH = 200;
        const preview = document.getElementById('wsp-screenshot-preview-outer');
        const img = document.getElementById('wsp-screenshot-preview-img');
        const wm = document.getElementById('wsp-watermark-preview-img');
        let watermarkLogoUrl = wm.src;

        // MAJ dynamique du logo preview
        let logoInput = document.getElementById('wsp_watermark_logo_url');
        if(logoInput){
            logoInput.addEventListener('input', function(){
                var url = this.value.trim();
                document.getElementById('wsp-watermark-logo-preview').innerHTML = url ? '<img src="'+url+'" style="max-width:90px;max-height:50px;vertical-align:middle;"/>' : '';
                if (wm) wm.src = url, watermarkLogoUrl = url;
                update_preview();
            });
        }

        function update_preview(){
            let margin = parseInt(document.getElementById('wsp_screenshot_outer_margin').value)||0;
            let radius = parseInt(document.getElementById('wsp_screenshot_border_radius').value)||0;
            let img_radius = parseInt(document.getElementById('wsp_screenshot_img_border_radius').value)||0;
            let bgtype = document.getElementById('wsp_screenshot_bgtype').value;
            let bgcolor1 = document.getElementById('wsp_screenshot_bgcolor1').value;
            let bgcolor2 = document.getElementById('wsp_screenshot_bgcolor2').value;
            let bgangle = document.getElementById('wsp_screenshot_bgangle').value||0;

            let dispW, dispH, offsetX, offsetY;

            if (margin <= 0) {
                dispW = previewW;
                dispH = previewH;
                offsetX = 0;
                offsetY = 0;
            } else {
                let availW = Math.max(0, previewW - margin*2);
                let availH = Math.max(0, previewH - margin*2);
                let imgRatio = imgW / imgH;
                dispW = availW;
                dispH = Math.round(availW / imgRatio);
                if (dispH > availH) {
                    dispH = availH;
                    dispW = Math.round(availH * imgRatio);
                }
                offsetX = margin + Math.round((availW - dispW)/2);
                offsetY = margin + Math.round((availH - dispH)/2);
                if(dispW < 1 || dispH < 1) {dispW = 1; dispH = 1;}
            }

            img.style.width = dispW+'px';
            img.style.height = dispH+'px';
            img.style.left = offsetX+'px';
            img.style.top = offsetY+'px';
            img.style.objectFit = 'cover';
            img.style.borderRadius = img_radius+"px";

            let wmEnable = document.getElementById('wsp_watermark_enable').checked;
            let wmUrl = watermarkLogoUrl;
            let wmSizePct = parseInt(document.getElementById('wsp_watermark_size').value)||15;
            let wmOpacity = parseFloat(document.getElementById('wsp_watermark_opacity').value)||0.5;
            let wmPos = document.getElementById('wsp_watermark_position').value;

            if(bgtype=='gradient'){
                preview.style.background = 'linear-gradient('+bgangle+'deg, '+bgcolor1+', '+bgcolor2+')';
            }else{
                preview.style.background = bgcolor1;
            }
            preview.style.borderRadius = radius+"px";
            preview.style.boxSizing = "border-box";
            preview.style.padding = '0';

            if(wm && wmUrl && wmEnable){
                var size_px = Math.round(Math.min(dispW, dispH)*wmSizePct/100);
                wm.style.display = "block";
                wm.style.opacity = wmOpacity;
                wm.style.width = size_px+"px";
                wm.style.height = 'auto';
                wm.style.maxWidth = size_px+'px';
                wm.style.maxHeight = size_px+'px';
                var pad = 8;
                if(wmPos==='top-left'){
                    wm.style.left= (offsetX+pad)+'px'; wm.style.top= (offsetY+pad)+'px';
                    wm.style.right=wm.style.bottom='';
                }else if(wmPos==='top-right'){
                    wm.style.left=''; wm.style.right=(previewW-offsetX-dispW+pad)+'px';
                    wm.style.top= (offsetY+pad)+'px'; wm.style.bottom='';
                }else if(wmPos==='bottom-left'){
                    wm.style.left=(offsetX+pad)+'px'; wm.style.top=''; wm.style.bottom=(previewH-offsetY-dispH+pad)+'px'; wm.style.right='';
                }else{
                    wm.style.left=''; wm.style.right=(previewW-offsetX-dispW+pad)+'px';
                    wm.style.top=''; wm.style.bottom=(previewH-offsetY-dispH+pad)+'px';
                }
            }else{
                if(wm) wm.style.display='none';
            }
        }

        [
            'wsp_screenshot_outer_margin',
            'wsp_screenshot_bgtype',
            'wsp_screenshot_bgcolor1',
            'wsp_screenshot_bgcolor2',
            'wsp_screenshot_bgangle',
            'wsp_screenshot_border_radius',
            'wsp_screenshot_img_border_radius',
            'wsp_watermark_size',
            'wsp_watermark_opacity',
            'wsp_watermark_position'
        ].forEach(id=>{
            let el = document.getElementById(id); if(!el)return;
            el.addEventListener('input', update_preview);
            el.addEventListener('change', update_preview);
        });

        window.onload = function(){ setBgUI(); update_preview(); };

        document.getElementById('wsp_watermark_enable').addEventListener('change', function(){
            document.getElementById('watermark-options-inner').style.display = this.checked?'':'none';
            update_preview();
        });

    })();
    </script>
    <?php
}