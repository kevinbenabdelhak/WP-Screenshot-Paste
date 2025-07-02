<?php
if (!defined('ABSPATH')) exit;

add_action('admin_footer', 'paste_image_upload_js');
function paste_image_upload_js() {
    $screen = get_current_screen();
    if (!in_array($screen->base, ['post','upload'])) return;
    ?>
    <style>
        #paste-image-loader {
            display: none;
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(255,255,255,0.8);
            z-index: 999999;
            text-align: center;
            padding-top: 20vh;
        }
        #paste-image-loader .spinner {
            border:4px solid #f3f3f3;
            border-top:4px solid #0073aa; border-radius:50%;
            width:40px;height:40px;margin:auto;
            animation:spin 1s linear infinite;
        }
        @keyframes spin{0%{transform:rotate(0deg);}100%{transform:rotate(360deg);}}
    </style>
    <div id="paste-image-loader" aria-live="polite" aria-label="Chargement image collée">
        <div class="spinner"></div>
        <p id="paste-image-loader-message"><?php esc_html_e("Upload de l’image collée en cours...",'the-paste'); ?></p>
    </div>
    <script>
    (function($){
        $(document).ready(function(){
            var $loader = $('#paste-image-loader'), $loaderMsg = $('#paste-image-loader-message');

            function showLoader(msg) { $loader.show(); $loaderMsg.text(msg || "Upload de l’image collée en cours…"); }
            function hideLoader() { $loader.hide(); }

            function uploadFile(file) {
                // DÉCOUPÉ EN 2 pour mises à jour du loader
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
                    xhr: function() {
                        var xhr = $.ajaxSettings.xhr();
                        if (xhr.upload) {
                          xhr.upload.addEventListener('progress', function(){
                            showLoader('Upload de l’image collée en cours...');
                          }, false);
                        }
                        return xhr;
                    },
                    beforeSend: function() {
                        showLoader('Upload de l’image collée en cours...');
                    }
                });
            }

            // Editeur visuel TinyMCE
            function addTinyMCEPasteHandlerTo(editor) {
                if (editor.pasteImageHandlerAdded) return;
                editor.pasteImageHandlerAdded = true;
                editor.on('paste', function(e){
                    var clipboardData = e.clipboardData||window.clipboardData;
                    if (!clipboardData || !clipboardData.items) return;
                    var items = clipboardData.items, files = [];
                    for (var i=0;i<items.length;i++) {
                        var item=items[i]; if (item.kind==='file' && item.type.indexOf('image')!==-1) files.push(item.getAsFile());
                    }
                    if (files.length===0) return;
                    e.preventDefault();
                    showLoader('Upload de l’image collée en cours…');
                    var fileToUpload = files[0];

                    // loader "alt IA" dès fin d’upload
                    var uploadPromise = uploadFile(fileToUpload);
                    setTimeout(function(){ showLoader('Génération du texte alternatif de l\'image par IA…'); }, 900);

                    uploadPromise.done(function(response){
                        hideLoader();
                        if (response.success) {
                            var altHtml = response.data.alt ? ' alt="' + $('<div>').text(response.data.alt).html() + '" ' : '';
                            var imageHtml = '<img src="' + response.data.attachment_url + '"'+altHtml+'style="max-width:100%;height:auto;" />';
                            editor.execCommand('mceInsertContent', false, imageHtml);
                        } else {
                            alert('Erreur upload/génération IA : '+response.data);
                        }
                    }).fail(function(){
                        hideLoader();
                        alert('Erreur lors de l\'upload de l\'image collée ou IA.');
                    });
                });
            }

            function bindAllTinyMCE() {
                if(typeof tinymce==='undefined'||!tinymce.editors)return;
                tinymce.editors.forEach(function(editor){
                    if(editor && !editor.pasteImageHandlerAdded){
                        addTinyMCEPasteHandlerTo(editor);
                    }
                });
            }
            setTimeout(bindAllTinyMCE, 1000);
            $(document).on('click','.switch-tmce, .wp-switch-editor.switch-tmce',function(){ setTimeout(bindAllTinyMCE,400); });

            // Editeur code natif
            $('#content').on('paste', function(event){
                // Ne pas activer si visuel actif
                if(typeof tinymce!=='undefined'&&tinymce.activeEditor&&!tinymce.activeEditor.isHidden())return;
                var clipboardItems = (event.originalEvent?event.originalEvent.clipboardData:event.clipboardData).items;
                if(!clipboardItems) return;
                var files=[];
                for(var i=0;i<clipboardItems.length;i++){
                    var item=clipboardItems[i];
                    if(item.kind==='file'&&item.type.indexOf('image')!==-1){
                        var file = item.getAsFile(); if(file) files.push(file);
                    }
                }
                if(files.length===0)return;
                event.preventDefault();
                showLoader('Upload de l’image collée en cours…');
                var uploadPromise = uploadFile(files[0]);
                setTimeout(function(){ showLoader('Génération du texte alternatif de l\'image par IA…'); }, 900);

                uploadPromise.done(function(response){
                    hideLoader();
                    if(response.success){
                        var altHtml = response.data.alt?' alt="'+$('<div>').text(response.data.alt).html()+'" ':'';
                        var imageHtml = '<img src="'+response.data.attachment_url+'"'+altHtml+'style="max-width:100%;height:auto;" />';
                        var textarea = event.target, start = textarea.selectionStart, end = textarea.selectionEnd, text=textarea.value;
                        textarea.value = text.substring(0,start)+imageHtml+text.substring(end);
                        textarea.selectionStart = textarea.selectionEnd = start+imageHtml.length;
                    } else {
                        alert('Erreur upload/génération IA : ' + response.data);
                    }
                }).fail(function(){
                    hideLoader();
                    alert('Erreur lors de l\'upload de l\'image collée ou IA.');
                });
            });

            // Medialibrary (upload.php)
            if(window.location.pathname.match(/\/upload\.php/)){
                $(document).on('paste', function(event){
                    var clipboardItems = (event.originalEvent?event.originalEvent.clipboardData:event.clipboardData).items; if(!clipboardItems) return;
                    var files=[]; for(var i=0;i<clipboardItems.length;i++){ var item=clipboardItems[i]; if(item.kind==='file'&&item.type.indexOf('image')!==-1){ var file=item.getAsFile(); if(file) files.push(file);}}
                    if(files.length===0)return;
                    event.preventDefault();
                    showLoader('Upload de l’image collée en cours…');
                    var uploadPromise=uploadFile(files[0]);
                    setTimeout(function(){ showLoader('Génération du texte alternatif de l\'image par IA…'); }, 900);

                    uploadPromise.done(function(response){
                        hideLoader();
                        if(response.success){ window.location.reload(); } else { alert('Erreur upload/génération IA : '+response.data);}
                    }).fail(function(){ hideLoader(); alert('Erreur lors de l\'upload de l\'image collée ou IA.'); });
                });
            }
        });
    })(jQuery);
    </script>
    <?php
}