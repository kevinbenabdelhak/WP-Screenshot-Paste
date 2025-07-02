<?php 


if (!defined('ABSPATH')) {
    exit;
}


// Injecte JS +CSS loader dans admin footer dans post.php, post-new.php, upload.php
add_action('admin_footer', 'paste_image_upload_js');
function paste_image_upload_js() {
    $screen = get_current_screen();
    // injecte le script seulement sur les écrans nécessaires..
    if (!in_array($screen->base, ['post', 'upload'])) return;
    ?>
    <style>
        #paste-image-loader {
            display: none;
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(255, 255, 255, 0.8);
            z-index: 999999;
            text-align: center;
            padding-top: 20vh;
        }
        #paste-image-loader .spinner {
            border: 4px solid #f3f3f3;
            border-top: 4px solid #0073aa;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            margin: auto;
            animation: spin 1s linear infinite;
        }
        @keyframes spin {
            0% { transform: rotate(0deg);}
            100% { transform: rotate(360deg);}
        }
    </style>
    <div id="paste-image-loader" aria-live="polite" aria-label="Chargement image collée">
        <div class="spinner"></div>
        <p><?php esc_html_e('Upload de l’image collée en cours...', 'the-paste'); ?></p>
    </div>
    <script>
    (function ($) {
        $(document).ready(function () {
            var $loader = $('#paste-image-loader');

            function showLoader() { $loader.show(); }
            function hideLoader() { $loader.hide(); }

            function uploadFile(file) {
                var data = new FormData();
                data.append('action', 'paste_image_upload');
                data.append('nonce', '<?php echo wp_create_nonce('paste_image_upload_nonce'); ?>');
                data.append('file', file);

                return $.ajax({
                    url: '<?php echo admin_url('admin-ajax.php'); ?>',
                    type: 'POST',
                    data: data,
                    processData: false,
                    contentType: false,
                });
            }





            // ---------- PATCH: TinyMCE (éditeur visuel) ----------
            function addTinyMCEPasteHandlerTo(editor) {
                if (editor.pasteImageHandlerAdded) return;
                editor.pasteImageHandlerAdded = true;

                editor.on('paste', function(e) {
                    var clipboardData = e.clipboardData || window.clipboardData;
                    if (!clipboardData || !clipboardData.items) return;

                    var items = clipboardData.items;
                    var files = [];
                    for (var i = 0; i < items.length; i++) {
                        var item = items[i];
                        if (item.kind === 'file' && item.type.indexOf('image') !== -1) {
                            var file = item.getAsFile();
                            if (file) files.push(file);
                        }
                    }
                    if (files.length === 0) return;
                    e.preventDefault();
                    showLoader();

                    var fileToUpload = files[0];
                    uploadFile(fileToUpload).done(function (response) {
                        if (response.success) {
                            var imageHtml = '<img src="' + response.data.attachment_url + '" style="max-width: 100%; height: auto;" />';
                            editor.execCommand('mceInsertContent', false, imageHtml);
                        } else {
                            alert('Erreur upload image : ' + response.data);
                        }
                        hideLoader();
                    }).fail(function () {
                        alert('Erreur lors de l\'upload de l\'image collée.');
                        hideLoader();
                    });
                });
            }

            function bindAllTinyMCE() {
                if (typeof tinymce === 'undefined' || !tinymce.editors) return;
                tinymce.editors.forEach(function(editor) {
                    if (editor && !editor.pasteImageHandlerAdded) {
                        addTinyMCEPasteHandlerTo(editor);
                    }
                });
            }

            setTimeout(bindAllTinyMCE, 1000);
            $(document).on('click', '.switch-tmce, .wp-switch-editor.switch-tmce', function(){
                setTimeout(bindAllTinyMCE, 400);
            });






            // ---------- PATCH: Editeur texte natif (code) ----------
            function handlePasteInEditorTextarea(event) {
                // Ne pas activer si visuel actif
                if (typeof tinymce !== 'undefined' && tinymce.activeEditor && !tinymce.activeEditor.isHidden()) return;

                var clipboardItems = (event.originalEvent ? event.originalEvent.clipboardData : event.clipboardData).items;
                if (!clipboardItems) return;

                var files = [];
                for (var i = 0; i < clipboardItems.length; i++) {
                    var item = clipboardItems[i];
                    if (item.kind === 'file' && item.type.indexOf('image') !== -1) {
                        var file = item.getAsFile();
                        if (file) {
                            files.push(file);
                        }
                    }
                }
                if (files.length === 0) return;

                event.preventDefault();
                showLoader();

                var fileToUpload = files[0];
                uploadFile(fileToUpload).done(function (response) {
                    if (response.success) {
                        var imageHtml = '<img src="' + response.data.attachment_url + '" style="max-width: 100%; height: auto;" />';
                        var textarea = event.target;
                        var start = textarea.selectionStart;
                        var end = textarea.selectionEnd;
                        var text = textarea.value;
                        textarea.value = text.substring(0, start) + imageHtml + text.substring(end);
                        textarea.selectionStart = textarea.selectionEnd = start + imageHtml.length;
                    } else {
                        alert('Erreur upload image : ' + response.data);
                    }
                    hideLoader();
                }).fail(function () {
                    alert('Erreur lors de l\'upload de l\'image collée.');
                    hideLoader();
                });
            }
            $('#content').on('paste', handlePasteInEditorTextarea);






            // ---------- PATCH: Media library (upload.php) ----------
            function refreshMediaModalImageOnPaste(imageUrlWithCacheBuster, attachmentId) {
                var tryCount = 0;
                var maxTries = 12; // 12 x 250ms = ~3 sec
                function updateModal() {
                    var $modal = $('.media-modal-content .attachment-details');
                    if ($modal.length) {
                        var $img = $modal.find('img[src]');
                        $img.each(function () {
                            var $this = $(this);
                            // On remplace si c'est bien notre media et que l'url sans query = bonne image
                            if (
                                ($this.attr('src').indexOf('/' + attachmentId) !== -1) ||
                                ($this.closest('.details-image').length) // fallback
                            ) {
                                $this.attr('src', imageUrlWithCacheBuster);
                            }
                        });
                    } else if (tryCount++ < maxTries) {
                        setTimeout(updateModal, 250);
                    }
                }
                setTimeout(updateModal, 200);
            }




            if (window.location.pathname.match(/\/upload\.php/)) {
                $(document).on('paste', function(event) {
                    var clipboardItems = (event.originalEvent ? event.originalEvent.clipboardData : event.clipboardData).items;
                    if (!clipboardItems) return;

                    var files = [];
                    for (var i = 0; i < clipboardItems.length; i++) {
                        var item = clipboardItems[i];
                        if (item.kind === 'file' && item.type.indexOf('image') !== -1) {
                            var file = item.getAsFile();
                            if (file) files.push(file);
                        }
                    }
                    if (files.length === 0) return;

                    event.preventDefault();
                    showLoader();

                    var fileToUpload = files[0];
                    uploadFile(fileToUpload).done(function (response) {
                        if (response.success) {
                            var attachment_id = response.data.attachment_id;
                            var attachment_url = response.data.attachment_url;

                            
                            // Rafraichit la liste pour rendre visible sans cache
                            window.location.reload();
            
                        } else {
                            alert('Erreur upload image : ' + response.data);
                        }
                        hideLoader();
                    }).fail(function () {
                        alert('Erreur lors de l\'upload de l\'image collée.');
                        hideLoader();
                    });
                });
            }
        });
    })(jQuery);
    </script>
    <?php
}