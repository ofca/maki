<?php
/**
 * @type \Maki\Maki $app
 * @type \Maki\File\Markdown $page
 * @type \Maki\File\Markdown $nav
 */
?>
<!DOCTYPE html>
<html>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <link href="<?php echo $app->getResourceUrl($stylesheet) ?>" rel="stylesheet">
        <script src="<?php echo $app->getResourceUrl('resources/jquery.js') ?>"></script>
        <script src="<?php echo $app->getResourceUrl('resources/prism.js') ?>"></script>
        <script src="<?php echo $app->getResourceUrl('resources/toc.min.js') ?>"></script>
        <script src="<?php echo $app->getResourceUrl('resources/mermaid.min.js') ?>"></script>
        <script>
            var __PAGE_PATH__ = '<?php echo $page->getFilePath() ?>';
        </script>
        <?php if ($editing): ?>
            <link href="<?php echo $app->getResourceUrl('resources/simplemde.min.css') ?>" rel="stylesheet">
            <script src="<?php echo $app->getResourceUrl('resources/simplemde.min.js') ?>"></script>
<!--            <link href="--><?php //echo $app->getResourceUrl('resources/codemirror.css') ?><!--" rel='stylesheet'>-->
<!--            <script src="--><?php //echo $app->getResourceUrl('resources/codemirror.js') ?><!--"></script>-->
<!--            <script src="--><?php //echo $app->getResourceUrl('resources/codemirror-continuelist.js') ?><!--"></script>-->
<!--            <script src="--><?php //echo $app->getResourceUrl('resources/codemirror-xml.js') ?><!--"></script>-->
<!--            <script src="--><?php //echo $app->getResourceUrl('resources/codemirror-markdown.js') ?><!--"></script>-->
<!--            <script src="--><?php //echo $app->getResourceUrl('resources/codemirror-rules.js') ?><!--"></script>-->
        <?php endif ?>
    </head>
    <body class="<?php echo $editing ? 'edit-mode' : '' ?>">
        <div class='container'>
            <header class="header">
                <h2><?php echo $app['main_title'] ?></h2>
                <?php if ($app['users']): ?>
                    <div class="user-actions">
                        hello <a><?php echo $app['user']['username'] ?></a> |
                        <a href="?logout=1">logout</a>
                    </div>
                <?php endif ?>
            </header>
            <div class='nav'>
                <div class='nav-inner'>
                    <?php echo $nav->toHTML() ?>
                    <?php if ($editable or $viewable): ?>
                        <div class='page-actions'>
                            <a href='<?php echo $nav->getUrl() ?>?edit=1' class='btn btn-xs btn-info pull-right'><?php echo $editButton ?></a>
                        </div>
                    <?php endif ?>
                </div>
            </div>
            <div class='content'>
                <ol class="breadcrumb">
                    <?php foreach ($page->getBreadcrumb() as $link): ?>
                        <li <?php echo $link['active'] ? 'class="active"' : '' ?>>
                            <?php if ($link['url']): ?>
                                <a href="<?php echo $link['url'] ?>"><?php echo $link['text'] ?></a>
                            <?php else: ?>
                                <?php echo $link['text'] ?>
                            <?php endif ?>
                        </li>
                    <?php endforeach ?>
                </ol>
                <div class='content-inner'>
                    <?php if ($editing): ?>
                        <div class='page-actions'>
                            <a href='<?php echo $page->getUrl() ?>' class='btn btn-xs btn-info'>back</a>
                            <?php if ($editable and $page->isNotLocked()): ?>
                                <a class='btn btn-xs btn-success save-btn'>save</a>
                                <span class='saved-info'>Document saved.</span>
                            <?php endif ?>

                            <?php if ($page->isLocked()): ?>
                                <span class='saved-info' style='display: inline-block'>Someone else is editing this document now.</span>
                            <?php endif ?>
                        </div>

                        <?php if ($editable and $page->isNotLocked()): ?>
                            <textarea id='textarea' class='textarea editor-textarea'><?php echo $page->getContent() ?></textarea>
                        <?php endif ?>
                    <?php else: ?>
                        <?php echo $page->toHTML() ?>

                        <?php if ($editable or $viewable): ?>
                            <div class='page-actions clearfix'>
                                <a href='<?php echo $page->getUrl() ?>?edit=1' class='btn btn-xs btn-info pull-right'><?php echo $editButton ?></a>
                                <?php if ($editable): ?>
                                    <a href='<?php echo $page->getUrl() ?>?delete=1' data-confirm='Are you sure you want delete this page?' class='btn btn-xs btn-danger pull-right'>delete</a>
                                <?php endif ?>
                            </div>
                        <?php endif ?>

                    <?php endif ?>
                </div>
            </div>
            <footer class='footer text-right'>
                <a class="btn btn-info" href="?ctrl=page&action=archive">download archive</a>
                <div class='themes'>
                    <select>
                        <?php foreach ($app->getThemeManager()->getStylesheets() as $name => $url): ?>
                            <option value='<?php echo $name ?>' <?php echo $name == $activeStylesheet ? 'selected="selected"' : '' ?>><?php echo $name ?></option>
                        <?php endforeach ?>
                    </select>
                </div>
                <p class='copyrights'><a href='http://emve.org/maki' target='_blank' class='maki-name'><strong>ma</strong>ki</a> created by <a href='http://emve.org/' target='_blank' class='darkcinnamon-name'>emve</a></p>
            </footer>
        </div>
        <script>
            <?php if ($editing and $editable and $page->isNotLocked()): ?>
            var $saveBtns = $('.save-btn'),
                $saved = $('.saved-info'),
                editor;

            $saved.hide();

            function save() {
                $.ajax({
                    url: '<?php $page->getUrl() ?>?save=1',
                    method: 'post',
                    data: {
                        content:  editor.value()//$('#textarea').val()
                    },
                    success: function() {
                        $saveBtns.attr('disabled', 'disabled');
                        //$saved.show();
                        setTimeout(function() { save(); }, 5000);
                    }
                });
            };

            var editing = <?php echo var_export($editing, true) ?>;

            if (editing) {

                editor = new SimpleMDE({
                    element: document.getElementById('textarea'),
                    spellChecker: false,
                    toolbar: false
                });
                editor.codemirror.on('change', function() {
                    $saveBtns.removeAttr('disabled');
                });
                editor.codemirror.setOption('rulers', [{ color: '#ccc', column: 80, lineStyle: 'dashed' }]);

//                editor = CodeMirror.fromTextArea(document.getElementById("textarea"), {
//                    mode: 'markdown',
//                    tabSize: 4,
//                    lineNumbers: false,
//                    theme: "3024-day",
//                    extraKeys: {"Enter": "newlineAndIndentContinueMarkdownList"},
//                    rulers: [{ color: '#ccc', column: 80, lineStyle: 'dashed' }]
//                });
//
//                // CodeMirror does not put any class which could determine
//                // what language syntax is selected, so we apply such
//                // class manually.
//                editor.getWrapperElement().className += ' lang-markdown';

                $(document).on('click', '.save-btn', save);

                save();
            }
            <?php endif ?>

            $(document).on('click', '[data-confirm]', function(e) {
                if (confirm($(this).attr('data-confirm'))) {
                    return true;
                } else {
                    e.preventDefault();
                    return false;
                }
            });

            var codeActionsTmpl = '' +
                '<div class="code-actions">' +
                '   <a href="#download" class="code-action-download">download</a>'
            '</div>';

            $('.content').find('pre > code').each(function(index) {
                var $this = $(this);

                if (this.className != '') {
                    this.className = 'language-'+this.className;
                    this.parentNode.className = this.className;
                }

                $(codeActionsTmpl)
                    .find('.code-action-download')
                    .attr('href', '?action=downloadCode&index=' + index)
                    .insertAfter($this.parent());
            });

            Prism.highlightAll();

            $('.themes > select').on('change', function() {
                window.location = '<?php $app->getCurrentUrl() ?>?change_css='+this.value;
            });

            $('.nav-inner [href="/'+__PAGE_PATH__+'"]').closest('li').append('<div id="page-toc"></div>');

            var toc = $('#page-toc');
            $('#page-toc').toc({
                container: '.content-inner',
                selectors: 'h2,h3,h4'
            });

            if ($('>ul', toc).is(':empty')) {
                // Remove table of contents if it is empty
                // ----

                toc.remove();
            } else if (toc.length) {
                // Scroll to nav toc
                $('.nav')[0].scrollTop = toc.position().top;
            }

            mermaid.initialize({ startOnLoad: true });

//            // Toggle headers as it would be sections.
//            $('body').on('click', 'h1, h2, h3, h4, h5, h6', function() {
//                var h = $(this),
//                    visible = !h.hasClass('collapsed');
//
//                if (visible) {
//                    h.addClass('collapsed');
//                } else {
//                    h.removeClass('collapsed');
//                }
//
//                h.nextUntil(this.localName).each(function() {
//                    if (visible) {
//                        this.setAttribute('data-display', this.style.display);
//                        this.style.display = 'none';
//                    } else {
//                        this.style.display = this.getAttribute('data-display');
//                    }
//                });<?php //$app->getCurrentUrl() ?>
//            });
        </script>
    </body>
</html>