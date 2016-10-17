<?php
    $tm = $app->getThemeManager();
?>
<!DOCTYPE html>
<html>
    <head>
        <title>maki</title>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <link href='<?php echo $app->getResourceUrl($tm->getStylesheetPath($tm->getActiveStylesheet())) ?>' rel='stylesheet'>
        <script src="<?php echo $app->getResourceUrl('resources/jquery.js') ?>"></script>
    </head>
    <body class='login-page'>
    <div>
        <form>
            <div class="form-group">
                <input type="text" placeholder="Username">
            </div>
            <div class="form-group">
                <input type="password" placeholder="Password">
            </div>
            <div class="form-group checkbox">
                <label for="field-remember_me"><input type="checkbox" id="field-remember_me"> Remember me</label>
            </div>
            <div class="form-group">
                <button type="submit">login</button>
            </div>
        </form>
    </div>
    <script>
        $(function() {
            'use strict';

            var $form = $('form'),
                $name = $('input[type=text]'),
                $password = $('input[type=password]'),
                $remember = $('input[type=checkbox]');

            $form.on('submit', function(e) {
                e.preventDefault();

                $.ajax({
                    url: '?auth=1',
                    type: 'post',
                    data: {
                        username: $name.val(),
                        password: $password.val(),
                        remember: $remember[0].checked ? 1 : 0
                    },
                    success: function() {
                        window.location.reload();
                    },
                    error: function(xhr) {
                        $form.find('.username-form-error').remove();
                        $form.append('<p class="username-form-error">'+xhr.responseJSON.error+'</p>');
                    }
                });

                return false;
            });

        });
    </script>
    </body>
</html>