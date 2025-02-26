<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $config['app']['name'] ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bulma@0.9.4/css/bulma.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body>
    <?php if ($auth->isLoggedIn()): ?>
    <nav class="navbar is-light" role="navigation" aria-label="main navigation">
        <div class="navbar-brand">
            <a class="navbar-item" href="/">
                <strong><?= $config['app']['name'] ?></strong>
            </a>

            <a role="button" class="navbar-burger" aria-label="menu" aria-expanded="false" data-target="navbarBasic">
                <span aria-hidden="true"></span>
                <span aria-hidden="true"></span>
                <span aria-hidden="true"></span>
            </a>
        </div>

        <div id="navbarBasic" class="navbar-menu">
            <div class="navbar-start">
                <a href="/monitors" class="navbar-item">
                    <span class="icon-text">
                        <span class="icon">
                            <i class="fas fa-desktop"></i>
                        </span>
                        <span>Monitors</span>
                    </span>
                </a>
                
                <a href="/status-pages" class="navbar-item">
                    <span class="icon-text">
                        <span class="icon">
                            <i class="fas fa-signal"></i>
                        </span>
                        <span>Status Pages</span>
                    </span>
                </a>
            </div>

            <div class="navbar-end">
                <div class="navbar-item has-dropdown is-hoverable">
                    <a class="navbar-link">
                        <span class="icon-text">
                            <span class="icon">
                                <i class="fas fa-user"></i>
                            </span>
                            <span><?= htmlspecialchars($currentUser['username']) ?></span>
                        </span>
                    </a>

                    <div class="navbar-dropdown is-right">
                        <a class="navbar-item" href="/profile">
                            Profile
                        </a>
                        <hr class="navbar-divider">
                        <form action="/logout" method="POST" style="display: none;" id="logout-form"></form>
                        <a class="navbar-item" href="#" onclick="event.preventDefault(); document.getElementById('logout-form').submit();">
                            Logout
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </nav>
    <?php endif; ?>

    <main class="section">
        <div class="container">
            <?php if (isset($_SESSION['flash'])): ?>
                <div class="notification is-<?= $_SESSION['flash']['type'] ?>">
                    <?= $_SESSION['flash']['message'] ?>
                </div>
                <?php unset($_SESSION['flash']); ?>
            <?php endif; ?>