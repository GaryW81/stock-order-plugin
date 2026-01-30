(function() {
    if ( typeof tinymce === 'undefined' ) {
        return;
    }

    tinymce.PluginManager.add('sopred', function(editor) {
        editor.formatter.register('sopred', {
            inline: 'span',
            classes: 'sop-note-red'
        });

        editor.ui.registry.addToggleButton('sopred', {
            text: 'Red',
            tooltip: 'Red text',
            onAction: function() {
                editor.formatter.toggle('sopred');
            },
            onSetup: function(api) {
                var handler = function(state) {
                    api.setActive(state);
                };
                editor.formatter.formatChanged('sopred', handler);
                return function() {
                    editor.formatter.formatChanged('sopred', handler);
                };
            }
        });

        editor.addShortcut('ctrl+shift+x', 'Strikethrough', 'Strikethrough');
        editor.addShortcut('meta+shift+x', 'Strikethrough', 'Strikethrough');
    });
})();
