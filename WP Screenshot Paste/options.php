<?php 

if (!defined('ABSPATH')) {
    exit;
}


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
});




function wsp_options_page() {

    $outer_margin = intval(get_option('wsp_screenshot_outer_margin', 16));
    $bgtype = get_option('wsp_screenshot_bgtype', 'color');
    $bgcolor1 = get_option('wsp_screenshot_bgcolor1', '#dde3ec');
    $bgcolor2 = get_option('wsp_screenshot_bgcolor2', '#aec6df');
    $bgangle = intval(get_option('wsp_screenshot_bgangle', 135));
    $border_radius = intval(get_option('wsp_screenshot_border_radius', 12));


    $preview_width = 340;
    $preview_height = 200;
    $dummy_img_w = 340;
    $dummy_img_h = 200;

    ?>
    <div class="wrap">
        <h1>WP Screenshot Paste – OpenAI</h1>
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
                    <th scope="row">Arrondi des coins</th>
                    <td>
                        <input type="range" name="wsp_screenshot_border_radius" min="0" max="48" value="<?php echo esc_attr($border_radius); ?>" step="1" id="wsp_screenshot_border_radius" oninput="document.getElementById('radius_val').innerText=this.value"/>&nbsp;
                        <span id="radius_val"><?php echo esc_html($border_radius); ?></span> px
                    </td>
                </tr>
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



        // Aperçu dynamique COVER si marge=0, CONTAIN si > 0
        const previewW = 340, previewH = 200, imgW = 340, imgH = 200;
        const preview = document.getElementById('wsp-screenshot-preview-outer');
        const img = document.getElementById('wsp-screenshot-preview-img');
        function update_preview(){
            let margin = parseInt(document.getElementById('wsp_screenshot_outer_margin').value)||0;
            let radius = parseInt(document.getElementById('wsp_screenshot_border_radius').value)||0;
            let bgtype = document.getElementById('wsp_screenshot_bgtype').value;
            let bgcolor1 = document.getElementById('wsp_screenshot_bgcolor1').value;
            let bgcolor2 = document.getElementById('wsp_screenshot_bgcolor2').value;
            let bgangle = document.getElementById('wsp_screenshot_bgangle').value||0;

            let dispW, dispH, offsetX, offsetY;

            if (margin <= 0) {
                // COVER: remplit tout le cadre
                dispW = previewW;
                dispH = previewH;
                offsetX = 0;
                offsetY = 0;
            } else {
                // CONTAIN: maximalise dans cadre - marge*2
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
            img.style.borderRadius = Math.max(0, radius - margin)+"px";

            if(bgtype=='gradient'){
                preview.style.background = 'linear-gradient('+bgangle+'deg, '+bgcolor1+', '+bgcolor2+')';
            }else{
                preview.style.background = bgcolor1;
            }
            preview.style.borderRadius = radius+"px";
            preview.style.boxSizing = "border-box";
            preview.style.padding = '0';
        }

        [
            'wsp_screenshot_outer_margin',
            'wsp_screenshot_bgtype',
            'wsp_screenshot_bgcolor1',
            'wsp_screenshot_bgcolor2',
            'wsp_screenshot_bgangle',
            'wsp_screenshot_border_radius'
        ].forEach(id=>{
            let el = document.getElementById(id); if(!el)return;
            el.addEventListener('input', update_preview);
            el.addEventListener('change', update_preview);
        });
        window.onload = function(){ setBgUI(); update_preview(); };
    })();
    </script>
    <?php
}
