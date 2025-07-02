<?php 

if (!defined('ABSPATH')) {
    exit;
}


add_action('admin_menu', function() {
    add_options_page('WP Screenshot Paste - OpenAI', 'WP Screenshot Paste', 'manage_options', 'wsp-options', 'wsp_options_page');
});
add_action('admin_init', function() {
    register_setting('wsp_options_group', 'wsp_openai_api_key');
});

function wsp_options_page() {
?>
<div class="wrap">
    <h1>WP Screenshot Paste – OpenAI</h1>
    <form method="post" action="options.php">
        <?php settings_fields('wsp_options_group'); do_settings_sections('wsp_options_group'); ?>
        <table class="form-table">
            <tr valign="top">
                <th scope="row">Clé API OpenAI</th>
                <td>
                    <input type="text" name="wsp_openai_api_key" value="<?php echo esc_attr(get_option('wsp_openai_api_key')); ?>" style="width:400px;" autocomplete="off" />
                    <p class="description">La clé ne sera utilisée que côté serveur (jamais dans le navigateur).</p>
                </td>
            </tr>
        </table>
        <?php submit_button(); ?>
    </form>
</div>
<?php
}