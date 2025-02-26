<?php
use \Core\CSRF;
?>
<div class="columns is-centered">
    <div class="column is-one-third">
        <div class="box">
            <h1 class="title has-text-centered">Login</h1>
            
            <form method="POST" action="/login">
                <?= \Core\CSRF::getFormField() ?>
                <div class="field">
                    <label class="label">Username</label>
                    <div class="control has-icons-left">
                        <input class="input" type="text" name="username" required autofocus>
                        <span class="icon is-small is-left">
                            <i class="fas fa-user"></i>
                        </span>
                    </div>
                </div>

                <div class="field">
                    <label class="label">Password</label>
                    <div class="control has-icons-left">
                        <input class="input" type="password" name="password" required>
                        <span class="icon is-small is-left">
                            <i class="fas fa-lock"></i>
                        </span>
                    </div>
                </div>

                <div class="field">
                    <div class="control">
                        <label class="checkbox">
                            <input type="checkbox" name="remember">
                            Remember me
                        </label>
                    </div>
                </div>

                <div class="field">
                    <div class="control">
                        <button class="button is-primary is-fullwidth">
                            Login
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>