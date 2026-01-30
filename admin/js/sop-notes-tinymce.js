(function() {
    if ( typeof tinymce === 'undefined' ) {
        return;
    }

    tinymce.PluginManager.add('sopred', function(editor) {
        editor.formatter.register('sop_red', {
            inline: 'span',
            classes: 'sop-note-red'
        });

        if ( editor.ui && editor.ui.registry && editor.ui.registry.addToggleButton ) {
            editor.ui.registry.addToggleButton('sopred', {
                text: 'Red',
                tooltip: 'Red text',
                onAction: function() {
                    editor.formatter.toggle('sop_red');
                },
                onSetup: function(api) {
                    var handler = function(state) {
                        api.setActive(state);
                    };
                    editor.formatter.formatChanged('sop_red', handler);
                    return function() {
                        editor.formatter.formatChanged('sop_red', handler);
                    };
                }
            });
        } else if ( editor.addButton ) {
            editor.addButton('sopred', {
                text: 'Red',
                tooltip: 'Red text',
                onclick: function() {
                    editor.formatter.toggle('sop_red');
                },
                onPostRender: function() {
                    var btn = this;
                    editor.on('NodeChange', function() {
                        var active = editor.formatter.match('sop_red');
                        if ( btn && btn.active ) {
                            btn.active(active);
                        }
                    });
                }
            });
        }

        editor.addShortcut('ctrl+shift+x', 'Strikethrough', 'Strikethrough');
        editor.addShortcut('meta+shift+x', 'Strikethrough', 'Strikethrough');
    });
})();
