<?php
if (!defined('ABSPATH')) exit;

add_action('wp_ajax_paste_image_upload', function () {
    check_ajax_referer('paste_image_upload_nonce', 'nonce');
    if (empty($_FILES['file'])) wp_send_json_error('Aucun fichier détecté');

    $file = $_FILES['file'];
    if ($file['error'] !== UPLOAD_ERR_OK) wp_send_json_error('Erreur lors du téléchargement');

    $allowed_mimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    if (!in_array($mime, $allowed_mimes)) wp_send_json_error('Type de fichier non autorisé : ' . esc_html($mime));

    $image_data = file_get_contents($file['tmp_name']);
    $base64 = 'data:' . $mime . ';base64,' . base64_encode($image_data);

    $openai_key = get_option('wsp_openai_api_key');
    // Initialisation vides
    $titre = $alt = $description = $legende = '';

    if ($openai_key && strlen($openai_key) > 10) {
        // Nouveau prompt :
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

        // ----------- Extraction format OpenAI format 1 (output -> content[0] -> text) -----------
        $ai_values = [];
        if (
            is_array($response)
            && isset($response['output'][0]['content'][0]['type'])
            && $response['output'][0]['content'][0]['type'] === 'output_text'
            && isset($response['output'][0]['content'][0]['text'])
        ) {
            $json = json_decode($response['output'][0]['content'][0]['text'], true);
            if (is_array($json)) {
                $ai_values = $json;
            }
        }

        // Attribuer chaque champ (protection vide)
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
    $tmp_name = $file['tmp_name'];
    $sideload = [
        'name' => $file['name'],
        'type' => $file['type'],
        'tmp_name' => $tmp_name,
        'error' => 0,
        'size'  => filesize($tmp_name)
    ];
    // Préremplir les champs avant l'insert
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

    wp_send_json_success([
        'attachment_id' => $attachment_id,
        'attachment_url' => $attachment_url,
        'titre' => $titre,
        'alt' => $alt,
        'description' => $description,
        'legende' => $legende
    ]);
});

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