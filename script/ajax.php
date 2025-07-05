<?php
if (!defined('ABSPATH')) exit;

add_action('wp_ajax_paste_image_upload', function () {
    check_ajax_referer('paste_image_upload_nonce', 'nonce');
    if (empty($_FILES['file'])) wp_send_json_error('Aucun fichier détecté');

    $file = $_FILES['file'];
    if ($file['error'] !== UPLOAD_ERR_OK) wp_send_json_error('Erreur lors du téléchargement');

    $allowed_mimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    if (!in_array($mime, $allowed_mimes)) wp_send_json_error('Type de fichier non autorisé : ' . esc_html($mime));

    $new_tmp = $file['tmp_name'];
    $has_design = false;

 
    $outer_margin = intval(get_option('wsp_screenshot_outer_margin', 16));
    $border_radius = intval(get_option('wsp_screenshot_border_radius', 12));
    $img_border_radius = intval(get_option('wsp_screenshot_img_border_radius', 8));
    $bgtype   = get_option('wsp_screenshot_bgtype','color');
    $bgcolor1 = get_option('wsp_screenshot_bgcolor1','#dde3ec');
    $bgcolor2 = get_option('wsp_screenshot_bgcolor2','#aec6df');
    $bgangle  = intval(get_option('wsp_screenshot_bgangle',135));
  

    // Ajouter cadre si marge/radius OU SI arrondi image
    if($outer_margin>0 || $border_radius>0 || $img_border_radius>0){
  
        switch($mime){
            case 'image/png':  $src = imagecreatefrompng($file['tmp_name']); break;
            case 'image/jpeg': $src = imagecreatefromjpeg($file['tmp_name']); break;
            case 'image/gif':  $src = imagecreatefromgif($file['tmp_name']); break;
            case 'image/webp': $src = function_exists('imagecreatefromwebp') ? imagecreatefromwebp($file['tmp_name']) : null; break;
            default: $src = null;
        }
        if($src){
            $ow = imagesx($src);
            $oh = imagesy($src);
            $margin = max(0, $outer_margin);

            $dst_w = $ow + 2*$margin;
            $dst_h = $oh + 2*$margin;

            $radius = min($border_radius, min($dst_w, $dst_h)/2);

            $dst = imagecreatetruecolor($dst_w, $dst_h);
            imagesavealpha($dst, true);
            imagealphablending($dst, false);

            // FOND transparent
            $trans = imagecolorallocatealpha($dst, 0,0,0,127);
            imagefill($dst, 0,0, $trans);

            // --- FOND extérieur ---
            if($bgtype === 'gradient' && function_exists('imagecreatetruecolor')){
                $c1 = sscanf($bgcolor1, "#%02x%02x%02x");
                $c2 = sscanf($bgcolor2, "#%02x%02x%02x");
                if($bgangle==90 || $bgangle==270){
                    // gauche-droite
                    for($x=0;$x<$dst_w;$x++){
                        $alpha = $dst_w>1 ? $x/($dst_w-1) : 0;
                        $r = $c1[0]+($c2[0]-$c1[0])*$alpha;
                        $g = $c1[1]+($c2[1]-$c1[1])*$alpha;
                        $b = $c1[2]+($c2[2]-$c1[2])*$alpha;
                        $col = imagecolorallocate($dst, $r,$g,$b);
                        imageline($dst,$x,0,$x,$dst_h-1,$col);
                    }
                } elseif($bgangle==0 || $bgangle==180) {
                    // haut-bas
                    for($y=0;$y<$dst_h;$y++){
                        $alpha = $dst_h>1 ? $y/($dst_h-1) : 0;
                        $r = $c1[0]+($c2[0]-$c1[0])*$alpha;
                        $g = $c1[1]+($c2[1]-$c1[1])*$alpha;
                        $b = $c1[2]+($c2[2]-$c1[2])*$alpha;
                        $col = imagecolorallocate($dst, $r,$g,$b);
                        imageline($dst,0,$y,$dst_w-1,$y,$col);
                    }
                } else {
                    // diagonale 45/135
                    for($y=0;$y<$dst_h;$y++){
                        for($x=0;$x<$dst_w;$x++){
                            $alphax = $dst_w>1 ? $x/($dst_w-1) : 0;
                            $alphay = $dst_h>1 ? $y/($dst_h-1) : 0;
                            if(abs($bgangle-135) <= abs($bgangle-45)){
                                $alpha = ($alphax + $alphay)/2;
                            }else{
                                $alpha = (1-$alphax + $alphay)/2;
                            }
                            $r = $c1[0]+($c2[0]-$c1[0])*$alpha;
                            $g = $c1[1]+($c2[1]-$c1[1])*$alpha;
                            $b = $c1[2]+($c2[2]-$c1[2])*$alpha;
                            imagesetpixel($dst, $x, $y, imagecolorallocate($dst, $r,$g,$b));
                        }
                    }
                }
                // Si radius>0, "masquer" hors arrondi
                if($radius>0){
                    $mask = imagecreatetruecolor($dst_w, $dst_h);
                    imagesavealpha($mask,true);
                    $tt = imagecolorallocatealpha($mask,0,0,0,127);
                    imagefill($mask,0,0,$tt);
                    imagefilledroundedrect($mask, 0,0, $dst_w-1,$dst_h-1, $radius, imagecolorallocate($mask,255,255,255));
                    for($y=0;$y<$dst_h;$y++){
                        for($x=0;$x<$dst_w;$x++){
                            $px = imagecolorat($mask,$x,$y);
                            if((($px>>24)&0x7F)==127) imagesetpixel($dst,$x,$y,$trans);
                        }
                    }
                    imagedestroy($mask);
                }
            } else {
                // Couleur unie simple !
                if (preg_match('!^#([A-Fa-f0-9]{3,6})$!', trim($bgcolor1))) {
                    sscanf(trim($bgcolor1), "#%02x%02x%02x", $rr, $gg, $bb);
                    $fond_col = imagecolorallocate($dst, $rr, $gg, $bb);
                } else {
                    $fond_col = imagecolorallocate($dst, 220, 226, 236);
                }
                if($radius>0) {
                    imagefilledroundedrect($dst, 0,0, $dst_w-1,$dst_h-1, $radius, $fond_col);
                } else {
                    imagefilledrectangle($dst, 0,0, $dst_w-1,$dst_h-1, $fond_col);
                }
            }

            // --- COLLAGE IMAGE source (arrondi SI BESOIN) ---
if($img_border_radius>0){
    $img_radius_value = min($img_border_radius, min($ow,$oh)/2);

    // Masque arrondi sur dimension de l'image source
    $mask = imagecreatetruecolor($ow, $oh);
    imagesavealpha($mask,true);
    $tt = imagecolorallocatealpha($mask,0,0,0,127);
    imagefill($mask,0,0,$tt);
    imagefilledroundedrect($mask, 0,0, $ow-1,$oh-1, $img_radius_value, imagecolorallocate($mask,255,255,255));

    // Collage EN PLACE uniquement sur les zones arrondies
    for($y=0;$y<$oh;$y++){
        for($x=0;$x<$ow;$x++){
            $mx = imagecolorat($mask,$x,$y);
            // Si pixel masque NON transparent -> coller le pixel
            if((($mx>>24)&0x7F)!=127){
                imagesetpixel($dst, $x+$margin, $y+$margin, imagecolorat($src,$x,$y));
            }
        }
    }
    imagedestroy($mask);
} else {
   
    imagecopy($dst, $src, $margin, $margin, 0, 0, $ow, $oh);
}
            // Réenregistrement dans un fichier temporaire (force PNG si arrondi image!)
            $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
            $rnd = wp_generate_password(10,false,false);
            if($img_border_radius>0) $ext = 'png';
            $out_fn = sys_get_temp_dir().'/wspaste_'.time().'_'.$rnd.'.'.$ext;
            switch(true){
                case ($img_border_radius>0): imagepng($dst, $out_fn); break;
                case ($mime=='image/png'): imagepng($dst, $out_fn); break;
                case ($mime=='image/jpeg'): imagejpeg($dst, $out_fn, 96); break;
                case ($mime=='image/gif'): imagegif($dst, $out_fn); break;
                case ($mime=='image/webp' && function_exists('imagewebp')): imagewebp($dst, $out_fn); break;
            }
            imagedestroy($src); imagedestroy($dst);
            $new_tmp = $out_fn;
            $has_design = true;
        }
    }

    // Lire l'image finale en base64 pour OpenAI
    $image_data = file_get_contents($new_tmp);
    $base64 = 'data:' . $mime . ';base64,' . base64_encode($image_data);

    $openai_key = get_option('wsp_openai_api_key');
    $titre = $alt = $description = $legende = '';

    if ($openai_key && strlen($openai_key) > 10) {
        $prompt = 'Renvoie-moi un objet JSON avec les clés suivantes pour cette image : '
            . '"titre" (titre pertinent et court), "alt" (texte alternatif pour l\'accessibilité), '
            . '"description" (description longue détaillée), "legende" (légende synthétique). '
            . 'Formate uniquement la réponse en json sans texte hors json.';

        $payload = [
           "model" => "gpt-4.1",
           "input" => [
               [
                   "role" => "user",
                   "content" => [
                       [
                           "type" => "input_text",
                           "text" => $prompt
                       ],[
                           "type" => "input_image",
                           "image_url" => $base64
                       ]
                   ]
               ]
           ],
           "text" => [
               "format" => [
                   "type" => "json_object"
               ]
           ],
           "reasoning" => (object)[],
           "tools" => [],
           "temperature" => 1,
           "max_output_tokens" => 2048,
           "top_p" => 1,
           "store" => true
        ];

        $response = wsp_call_openai($openai_key, $payload);

        $ai_values = [];
        if (
            is_array($response)
            && isset($response['output'][0]['content'][0]['type'])
            && $response['output'][0]['content'][0]['type'] === 'output_text'
            && isset($response['output'][0]['content'][0]['text'])
        ) {
            $json = json_decode($response['output'][0]['content'][0]['text'], true);
            if (is_array($json)) $ai_values = $json;
        }
        $titre = isset($ai_values['titre']) ? $ai_values['titre'] : '';
        $alt = isset($ai_values['alt']) ? $ai_values['alt'] : '';
        $description = isset($ai_values['description']) ? $ai_values['description'] : '';
        $legende = isset($ai_values['legende']) ? $ai_values['legende'] : '';

        if (!$alt && !$titre && !$description && !$legende) {
            wp_send_json_error("Impossible d'extraire les champs JSON depuis OpenAI. Réponse brute : " . json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        }
    }

    require_once ABSPATH.'wp-admin/includes/file.php';
    require_once ABSPATH.'wp-admin/includes/media.php';
    require_once ABSPATH.'wp-admin/includes/image.php';

    $tmp_name = $new_tmp;
    $sideload = [
        'name' => $file['name'],
        'type' => $file['type'],
        'tmp_name' => $tmp_name,
        'error' => 0,
        'size'  => filesize($tmp_name)
    ];
    $post_data = array(
        'post_title'   => $titre ? sanitize_text_field($titre) : '',
        'post_excerpt' => $legende ? sanitize_text_field($legende) : '',
        'post_content' => $description ? sanitize_textarea_field($description) : '',
    );
    $attachment_id = media_handle_sideload($sideload, 0, null, $post_data);
    if(is_wp_error($attachment_id)) wp_send_json_error($attachment_id->get_error_message());

    if($alt) update_post_meta($attachment_id, '_wp_attachment_image_alt', sanitize_text_field($alt));
    if($legende) wp_update_post(['ID'=>$attachment_id, 'post_excerpt'=>sanitize_text_field($legende)]);
    if($description) wp_update_post(['ID'=>$attachment_id, 'post_content'=>sanitize_textarea_field($description)]);
    if($titre) wp_update_post(['ID'=>$attachment_id, 'post_title'=>sanitize_text_field($titre)]);

    $attachment_url = wp_get_attachment_url($attachment_id);
    $attachment_url = add_query_arg('t', time(), $attachment_url);

    if($has_design && is_file($new_tmp)) unlink($new_tmp);

    wp_send_json_success([
        'attachment_id' => $attachment_id,
        'attachment_url' => $attachment_url,
        'titre' => $titre,
        'alt' => $alt,
        'description' => $description,
        'legende' => $legende
    ]);
});



if (!function_exists('imagefilledroundedrect')) {
    function imagefilledroundedrect(&$im, $x1,$y1,$x2,$y2, $radius, $col) {
        // Corners
        imagefilledellipse($im, $x1+$radius, $y1+$radius, $radius*2, $radius*2, $col);
        imagefilledellipse($im, $x2-$radius, $y1+$radius, $radius*2, $radius*2, $col);
        imagefilledellipse($im, $x1+$radius, $y2-$radius, $radius*2, $radius*2, $col);
        imagefilledellipse($im, $x2-$radius, $y2-$radius, $radius*2, $radius*2, $col);
        // Edges
        imagefilledrectangle($im, $x1+$radius, $y1, $x2-$radius, $y2, $col);
        imagefilledrectangle($im, $x1, $y1+$radius, $x2, $y2-$radius, $col);
    }
}

function wsp_call_openai($api_key, $payload) {
    $url = "https://api.openai.com/v1/responses";
    $args = [
        'headers' => [
            'Content-Type'  => 'application/json',
            'Authorization' => 'Bearer '.$api_key,
        ],
        'body' => json_encode($payload),
        'timeout' => 120,
    ];
    $res = wp_remote_post($url, $args);
    if(is_wp_error($res)) return ['error'=>['message' => $res->get_error_message()]];
    $json = json_decode(wp_remote_retrieve_body($res), true);
    return $json;
}