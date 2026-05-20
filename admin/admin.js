(function ($) {
    'use strict';

    $(function () {

        // =====================================================================
        // Drag-and-drop sortable for script cards between tiers
        // =====================================================================
        $('#sortable-statistics, #sortable-marketing').sortable({
            connectWith: '.dw-sortable-list',
            handle: '.dw-drag-handle',
            placeholder: 'ui-sortable-placeholder',
            tolerance: 'pointer',
            cursor: 'grabbing',
            revert: 100,
            receive: function (event, ui) {
                // Update the tier hidden input when a card is moved to a new column
                var newTier = $(this).closest('.dw-scripts-column').data('tier');
                ui.item.find('.dw-tier-input').val(newTier);
            },
            stop: function () {
                // Reindex all script inputs to preserve order
                reindexScripts();
            }
        });

        function reindexScripts() {
            var index = 0;
            $('.dw-sortable-list .dw-script-card').each(function () {
                $(this).find('input, select').each(function () {
                    var name = $(this).attr('name');
                    if (name) {
                        $(this).attr('name', name.replace(/scripts\[\d+\]/, 'scripts[' + index + ']'));
                    }
                });
                index++;
            });
        }

        // =====================================================================
        // Custom scripts: add / remove
        // =====================================================================
        var customIndex = $('#dw-custom-scripts .dw-custom-script-row').length;

        $('#dw-add-custom-script').on('click', function () {
            var template = $('#dw-custom-script-template').html();
            var html = template.replace(/__INDEX__/g, customIndex);
            $('#dw-custom-scripts').append(html);
            customIndex++;
        });

        $(document).on('click', '.dw-remove-custom-script', function () {
            if (confirm('Remove this custom script?')) {
                $(this).closest('.dw-custom-script-row').remove();
            }
        });

        // =====================================================================
        // Language tabs
        // =====================================================================
        $(document).on('click', '.dw-lang-tab', function () {
            var lang = $(this).data('lang');
            $('.dw-lang-tab').removeClass('dw-lang-tab-active');
            $(this).addClass('dw-lang-tab-active');
            $('.dw-lang-panel').removeClass('dw-lang-panel-active');
            $('.dw-lang-panel[data-lang="' + lang + '"]').addClass('dw-lang-panel-active');
        });

        // Add new language
        $('#dw-add-language').on('click', function () {
            var langCode = prompt('Enter language code (e.g., "de", "fr", "sv"):');
            if (!langCode) return;

            langCode = langCode.toLowerCase().replace(/[^a-z_-]/g, '');
            if (!langCode) {
                alert('Invalid language code.');
                return;
            }

            // Check if it already exists
            if ($('.dw-lang-panel[data-lang="' + langCode + '"]').length > 0) {
                alert('This language already exists.');
                return;
            }

            var template = $('#dw-language-template').html();
            var html = template
                .replace(/__LANG__/g, langCode)
                .replace(/__LANG_UPPER__/g, langCode.toUpperCase());

            // Add tab button
            var tabBtn = $('<button type="button" class="button dw-lang-tab" data-lang="' + langCode + '">' + langCode.toUpperCase() + '</button>');
            $(this).before(tabBtn);

            // Add panel
            $('.dw-language-tabs').append(html);

            // Also add to default language dropdown
            $('#default_language').append('<option value="' + langCode + '">' + langCode.toUpperCase() + '</option>');

            // Switch to the new tab
            tabBtn.trigger('click');
        });

        // Remove language
        $(document).on('click', '.dw-remove-language', function () {
            var lang = $(this).data('lang');
            if (!confirm('Remove the "' + lang.toUpperCase() + '" language? This cannot be undone.')) {
                return;
            }

            // Remove tab
            $('.dw-lang-tab[data-lang="' + lang + '"]').remove();
            // Remove panel
            $('.dw-lang-panel[data-lang="' + lang + '"]').remove();
            // Remove from dropdown
            $('#default_language option[value="' + lang + '"]').remove();

            // Activate first remaining tab
            var firstTab = $('.dw-lang-tab').first();
            if (firstTab.length) {
                firstTab.trigger('click');
            }
        });

    });

})(jQuery);
