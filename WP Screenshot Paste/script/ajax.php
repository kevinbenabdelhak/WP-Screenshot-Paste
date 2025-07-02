<?php 


if (!defined('ABSPATH')) {
    exit;
}

add_action('wp_ajax_paste_image_upload', function () {
    check_ajax_referer('paste_image_upload_nonce', 'nonce');

    if (empty($_FILES['file'])) {
        wp_send_json_error('Aucun fichier détecté');
    }

    $file = $_FILES['file'];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        wp_send_json_error('Erreur lors du téléchargement');
    }

    $allowed_mimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!in_array($mime, $allowed_mimes)) {
        wp_send_json_error('Type de fichier non autorisé : ' . esc_html($mime));
    }

    require_once ABSPATH . 'wp-admin/includes/image.php';
    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/media.php';

    $attachment_id = media_handle_upload('file', 0);

    if (is_wp_error($attachment_id)) {
        wp_send_json_error($attachment_id->get_error_message());
    }

    $attachment_url = wp_get_attachment_url($attachment_id);
    $attachment_url = add_query_arg('t', time(), $attachment_url);

    wp_send_json_success([
        'attachment_id' => $attachment_id,
        'attachment_url' => $attachment_url,
    ]);
});