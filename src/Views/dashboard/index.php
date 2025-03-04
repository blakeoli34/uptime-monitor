<div class="content">
    <h1 class="title">Dashboard</h1>

    <!-- Overview Cards -->
    <div class="columns is-multiline">
        <div class="column is-one-third">
            <div class="box has-text-centered">
                <p class="heading">Total Monitors</p>
                <p class="title"><?= $stats['total'] ?></p>
            </div>
        </div>
        <div class="column is-one-third">
            <div class="box has-text-centered has-background-success-light">
                <p class="heading">Monitors Up</p>
                <p class="title has-text-success"><?= $stats['up'] ?></p>
            </div>
        </div>
        <div class="column is-one-third">
            <div class="box has-text-centered has-background-danger-light">
                <p class="heading">Monitors Down</p>
                <p class="title has-text-danger"><?= $stats['down'] ?></p>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="buttons mb-5">
        <a href="/monitors/add" class="button">
            <span class="icon">
                <i class="fas fa-plus"></i>
            </span>
            <span>Add Monitor</span>
        </a>
        <a href="/status-pages/add" class="button">
            <span class="icon">
                <i class="fas fa-plus"></i>
            </span>
            <span>Add Status Page</span>
        </a>
    </div>

    <div class="columns">
        <!-- Recent Incidents -->
        <div class="column is-half">
            <div class="box">
                <h2 class="title is-4">Recent Incidents</h2>
                <?php if (empty($recentIncidents)): ?>
                    <p class="has-text-grey">No incidents in the last 24 hours.</p>
                <?php else: ?>
                    <table class="table is-fullwidth">
                    <thead>
                            <tr>
                                <th>Monitor</th>
                                <th>Error</th>
                                <th>Started At</th>
                                <th>Duration</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentIncidents as $incident): ?>
                            <tr>
                                <td><?= html_entity_decode(htmlspecialchars($incident['name'])) ?></td>
                                <td><?= htmlspecialchars($incident['error_message'] ?? 'No error message found') ?></td>
                                <td><?= (new DateTime($incident['started_at']))->format('n/j/Y g:ia') ?></td>
                                <td>
                                    <?php if ($incident['ended_at']): ?>
                                        <?= $this->formatUptime($incident['duration_seconds']) ?>
                                    <?php else: ?>
                                        <span class="has-text-danger">Ongoing</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>

        <!-- Uptime Overview -->
        <div class="column is-half">
            <div class="box">
                <h2 class="title is-4">24 Hour Uptime</h2>
                <?php if (empty($uptimeOverview)): ?>
                    <p class="has-text-grey">No uptime data available.</p>
                <?php else: ?>
                    <?php foreach ($uptimeOverview as $monitor): ?>
                        <div class="mb-4">
                            <div class="level is-mobile">
                                <div class="level-left">
                                    <div class="level-item">
                                        <p class="is-size-6"><?= html_entity_decode(htmlspecialchars($monitor['name'])) ?></p>
                                    </div>
                                </div>
                                <div class="level-right">
                                    <div class="level-item">
                                        <p class="is-size-6 <?= $monitor['uptime_percentage'] >= 99 ? 'has-text-success' : ($monitor['uptime_percentage'] >= 95 ? 'has-text-warning' : 'has-text-danger') ?>">
                                            <?= number_format($monitor['uptime_percentage'], 2) ?>%
                                        </p>
                                    </div>
                                </div>
                            </div>
                            <progress 
                                class="progress <?= $monitor['uptime_percentage'] >= 99 ? 'is-success' : ($monitor['uptime_percentage'] >= 95 ? 'is-warning' : 'is-danger') ?> is-small" 
                                value="<?= $monitor['uptime_percentage'] ?>" 
                                max="100">
                                <?= $monitor['uptime_percentage'] ?>%
                            </progress>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Initialize Charts -->
<script>
$(document).ready(function() {
    // Add any JavaScript for interactivity here
    // We can add charts using Chart.js or other libraries if needed
});
</script>