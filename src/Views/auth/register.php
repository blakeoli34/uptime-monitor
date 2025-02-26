<?php
use \Core\CSRF;
?>
<div class="columns is-centered">
    <div class="column is-one-third">
        <div class="box">
            <h1 class="title has-text-centered">Register</h1>
            <p class="is-size-7 has-text-centered mb-4">This software is only available to<br>Jointly Studios employees and clients</p>
            
            <?php if (isset($_GET['invite']) && $_GET['invite'] === 'jointly'): ?>
            <form method="POST" action="/register">
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
                    <label class="label">Email</label>
                    <div class="control has-icons-left">
                        <input class="input" type="email" name="email" required>
                        <span class="icon is-small is-left">
                            <i class="fas fa-envelope"></i>
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
                    <label class="label">Confirm Password</label>
                    <div class="control has-icons-left">
                        <input class="input" type="password" name="password_confirmation" required>
                        <span class="icon is-small is-left">
                            <i class="fas fa-lock"></i>
                        </span>
                    </div>
                </div>

                <div class="field">
                    <div class="control">
                        <button class="button is-primary is-fullwidth">
                            Register
                        </button>
                    </div>
                </div>
            </form>
            <p class="has-text-centered mt-4">
                Already have an account? <a href="/login">Login</a>
            </p>
            <?php else: ?>
            <div class="has-text-centered">
                <p class="has-text-grey mb-4">Registration is currently by invite only.</p>
                <a href="/login" class="button is-primary">Login</a>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>