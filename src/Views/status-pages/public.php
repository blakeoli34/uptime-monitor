<?php
use Core\Config;
function getStatusColor($uptime) {
    if ($uptime >= 99) return '#48c774';
    if ($uptime >= 90) return '#ffdd57';
    return '#f14668';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?></title>
    
    <!-- Favicon -->
    <link rel="apple-touch-icon" sizes="180x180" href="/images/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="/images/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/images/favicon-16x16.png">
    <link rel="manifest" href="/images/site.webmanifest">
    <link rel="shortcut icon" href="/images/favicon.ico">
    
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bulma@0.9.4/css/bulma.min.css">
    <meta http-equiv="refresh" content="60">
    <style>
        body {
            background-color: #f5f5f5;
        }
        .status-indicator {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 15px;
            aspect-ratio: 1 / 1;
        }
        .status-indicator.large {
            width: 24px;
            height: 24px;
            margin-right: 24px;
        }
        .pulse-green {
            background-color: #48c774;
            box-shadow: 0 0 0 0 rgba(72, 199, 116, 1);
            animation: pulse-green 2s infinite;
        }
        .pulse-yellow {
            background-color: #ffdd57;
            box-shadow: 0 0 0 0 rgba(255, 221, 87, 1);
            animation: pulse-yellow 2s infinite;
        }
        .pulse-red {
            background-color: #f14668;
            box-shadow: 0 0 0 0 rgba(241, 70, 104, 1);
            animation: pulse-red 2s infinite;
        }
        @keyframes pulse-green {
            0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(72, 199, 116, 0.7); }
            70% { transform: scale(1); box-shadow: 0 0 0 10px rgba(72, 199, 116, 0); }
            100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(72, 199, 116, 0); }
        }
        @keyframes pulse-yellow {
            0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(255, 221, 87, 0.7); }
            70% { transform: scale(1); box-shadow: 0 0 0 10px rgba(255, 221, 87, 0); }
            100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(255, 221, 87, 0); }
        }
        @keyframes pulse-red {
            0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(241, 70, 104, 0.7); }
            70% { transform: scale(1); box-shadow: 0 0 0 10px rgba(241, 70, 104, 0); }
            100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(241, 70, 104, 0); }
        }
        .monitor-card {
            background: white;
            border-bottom: 1px solid #ddd;
            padding: 1.25rem 0;
        }
        .monitor-card:first-child {
            padding-top: 0;
        }
        .monitor-card:last-child {
            padding-bottom: 0;
            border-bottom: none;
        }
        .timeline-container {
            display: flex;
            gap: 2px;
            margin-top: 12px;
            padding: 8px;
            background: #fafafa;
            border-radius: 4px;
            width: 100%;
        }
        .timeline-bar {
            flex: 1;
            height: 24px;
            border-radius: 2px;
        }
        .system-status {
            font-size: 1.6rem;
            font-weight: 700;
        }
        .update-countdown {
            color: #666;
            font-size: 0.9rem;
        }
        .up {
            color: #48c774;
        }
        .partial {
            color: #ffdd57;
        }
        .down {
            color: #f14668;
        }
        .incident-container {
            border-bottom: 1px solid #eee;
        }
        .incident-container:last-child {
            border-bottom: none;
            margin-bottom: 0 !important;
            padding-bottom: 0 !important;
        }
    </style>
    <style>
        @media (prefers-color-scheme: dark) {
            body {
                background-color: #1a1a1a;
                color: #e4e4e4;
            }
            strong {
                color: #fff;
            }
            .box {
                background-color: #262626;
                color: #e4e4e4;
            }
            .monitor-card {
                border-bottom-color: #404040;
            }
            .has-text-grey {
                color: #b0b0b0 !important;
            }
            .timeline-container {
                background: #333;
            }
            .system-status {
                color: #e4e4e4;
            }
            .update-countdown {
                color: #b0b0b0;
            }
            .up {
                color: #50d886;
            }
            .partial {
                color: #ffe066;
            }
            .down {
                color: #f47983;
            }
            .pulse-green {
                background-color: #50d886;
            }
            .pulse-yellow {
                background-color: #ffe066;
            }
            .pulse-red {
                background-color: #f47983;
            }
            .title, .subtitle {
                color: #e4e4e4 !important;
            }
            .monitor-card {
                background-color: #262626;
            }
            .footer {
                background-color: #262626;
                color: #b0b0b0;
            }
        }
    </style>
</head>
<body>
<section class="section">
        <div class="container">
            <div class="is-flex is-align-items-center mb-5">
                <img src="/images/uptime-logo.png" alt="Logo" style="height: 40px; margin-right: 15px;">
                <h1 class="title is-2 mb-0"><?= htmlspecialchars($page['name']) ?></h1>
            </div>
            <p class="is-size-4 mb-6"><?= html_entity_decode(htmlspecialchars($page['description'])) ?></p>

            <!-- System Status Box -->
            <div class="box mb-6">
                <div class="columns is-vcentered">
                    <div class="column">
                        <div class="is-flex is-align-items-center">
                            <div class="status-indicator large <?= $allUp ? 'pulse-green' : ($partialOutage ? 'pulse-yellow' : 'pulse-red') ?>"></div>
                            <div>
                                <div class="system-status"><?= $allUp ? 'All systems <span class="up">operational</span>' : ($partialOutage ? '<span class="partial">Some</span> systems down' : 'All systems <span class="down">down</span>') ?></div>
                            </div>
                        </div>
                    </div>
                    <div class="column is-narrow">
                        <div class="update-countdown">Next update in <span id="countdown">60</span> seconds</div>
                    </div>
                </div>
            </div>

            <!-- Monitors List -->
            <div class="box">
            <?php foreach ($monitors as $monitor): ?>
                <div class="monitor-card">
                    <div class="is-flex is-justify-content-space-between is-align-items-start">
                        <div>
                            <h3 class="is-size-4 is-size-6-mobile has-text-weight-medium mb-2"><?= htmlspecialchars($monitor['name']) ?></h3>
                            <div class="is-flex is-align-items-center">
                                <div class="status-indicator <?= $monitor['current_status'] ? 'pulse-green' : 'pulse-red' ?>"></div>
                                <p class="has-text-grey"><?= number_format($monitor['total_uptime'] ?? 100, 1) ?>%</p>
                            </div>
                        </div>
                        <div class="has-text-right">
                            <p class="has-text-weight-medium mb-1">
                                <?= $monitor['current_status'] ? '<span class="up">Up</span>' : '<span class="down">Down</span>' ?>
                            </p>
                            <p class="has-text-grey is-size-7">
                                <?php if ($monitor['status_since']): ?>
                                    for <?= $formatUptime($monitor['status_since']) ?>
                                <?php else: ?>
                                    just now
                                <?php endif; ?>
                            </p>
                        </div>
                    </div>
                    <div class="timeline-container">
                    <?php foreach ($monitor['daily_status'] as $day): ?>
                        <div class="timeline-bar" 
                            style="background-color: <?= $day['date'] < $monitor['created_at'] ? '#ddd' : getStatusColor($day['uptime'] ?? 100) ?>" 
                            title="<?php 
                                $date = new DateTime($day['date']);
                                $date->setTimezone(new DateTimeZone(Config::get('timezone') ?: 'America/Detroit'));
                                echo $date->format('n/j/Y');
                            ?><?= $day['date'] < $monitor['created_at'] ? ': Monitor not created yet' : ': ' . number_format($day['uptime'] ?? 100, 1) . '% uptime' ?>">
                        </div>
                    <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
            </div>
        </div>

        <!-- Incident History -->
        <div class="container">
            <div class="box mt-6">
                <h2 class="title is-4 mb-4">Incident History</h2>
                <?php if (empty($incidents)): ?>
                    <p class="has-text-grey">No incidents in the last 30 days.</p>
                <?php else: ?>
                    <?php foreach ($incidents as $incident): ?>
                        <div class="incident-container mb-4 pb-4">
                            <div class="is-flex is-justify-content-space-between mb-2">
                                <p class="has-text-weight-medium"><?= htmlspecialchars($incident['monitor_name']) ?></p>
                                <p class="has-text-grey is-size-7">
                                    <?php
                                        $date = new DateTime($incident['started_at']);
                                        echo $date->format('n/j/Y g:ia');
                                    ?>
                                </p>
                            </div>
                            <p class="has-text-grey is-size-7">
                                Duration: <?= isset($incident['ended_at']) && $incident['ended_at'] ? $formatUptime($incident['duration_seconds']) : 'Ongoing' ?>
                                <?php if (isset($incident['error_message']) && $incident['error_message']): ?>
                                    <br>Error: <?= htmlspecialchars($incident['error_message']) ?>
                                <?php endif; ?>
                            </p>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <script>
        function updateCountdown() {
            const countdownEl = document.getElementById('countdown');
            let seconds = 60;
            
            setInterval(() => {
                seconds--;
                if (seconds <= 0) {
                    location.reload();
                    seconds = 60;
                }
                countdownEl.textContent = seconds;
            }, 1000);
        }

        updateCountdown();

        function getStatusColor(uptime) {
            if (uptime >= 99) return '#48c774';
            if (uptime >= 90) return '#ffdd57';
            return '#f14668';
        }
    </script>
</body>
</html>